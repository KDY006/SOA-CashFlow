package handlers

import (
	"context"
	"errors"
	"fmt"
	"math/rand"
	"net/http"
	"sync"
	"sync/atomic"
	"time"

	"cashflow/integration-service-final/internal/database"
	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"cashflow/integration-service-final/internal/services"
	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
)

type simulateRequest struct {
	AccountID     int64    `json:"account_id"`
	Events        int      `json:"events"`
	Workers       int      `json:"workers"`
	DuplicateRate *float64 `json:"duplicate_rate"`
	Mode          string   `json:"mode"`
	Sync          *bool    `json:"sync"`
}

func (s *Server) HandleSimulate(c *gin.Context) {
	var req simulateRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		httpx.Fail(c, http.StatusBadRequest, "BAD_REQUEST", "json không hợp lệ: "+err.Error())
		return
	}
	if req.Events <= 0 {
		req.Events = 50
	}
	if req.Workers <= 0 {
		req.Workers = 10
	}
	dupRate := 0.3
	if req.DuplicateRate != nil {
		dupRate = *req.DuplicateRate
	}
	if req.Mode == "" {
		req.Mode = "safe"
	}
	switch {
	case req.Events > 2000:
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "events tối đa 2000")
		return
	case req.Workers > 200:
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "workers tối đa 200")
		return
	case dupRate < 0 || dupRate > 1:
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "duplicate_rate phải trong khoảng 0 - 1")
		return
	case req.Mode != "safe" && req.Mode != "unsafe":
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "mode chỉ nhận safe hoặc unsafe")
		return
	}
	unsafe := req.Mode == "unsafe"
	doSync := !unsafe && (req.Sync == nil || *req.Sync)

	ctx := context.WithoutCancel(c.Request.Context())

	acc, err := s.getAccount(ctx, req.AccountID)
	if errors.Is(err, gorm.ErrRecordNotFound) || (err == nil && httpx.ForbiddenFor(c, acc.UserID)) {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(c, err)
		return
	}

	prefix := fmt.Sprintf("SIM%d", time.Now().UnixNano())
	events := make([]WebhookInput, req.Events)
	for i := range events {
		dir := "CREDIT"
		if rand.Float64() < 0.4 {
			dir = "DEBIT"
		}
		wr := WebhookRequest{
			Provider:    acc.BankCode,
			ExternalRef: fmt.Sprintf("%s-%04d", prefix, i+1),
			BankCode:    acc.BankCode,
			AccountNo:   acc.AccountNo,
			Direction:   dir,
			Amount:      float64((rand.Intn(490) + 10) * 1000),
			Description: "Giả lập biến động từ ngân hàng",
		}
		in, err := wr.Normalize("")
		if err != nil {
			s.internalError(c, err)
			return
		}
		events[i] = in
	}

	jobs := append([]WebhookInput{}, events...)
	for i := 0; i < int(float64(len(events))*dupRate); i++ {
		jobs = append(jobs, events[rand.Intn(len(events))])
	}
	rand.Shuffle(len(jobs), func(i, j int) { jobs[i], jobs[j] = jobs[j], jobs[i] })

	var created, duplicates, rejected, failed atomic.Int64
	var applied atomic.Int64

	ch := make(chan WebhookInput)
	var wg sync.WaitGroup
	start := time.Now()
	for i := 0; i < req.Workers; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for in := range ch {
				var res webhookResult
				var err error
				if unsafe {
					res, err = s.processUnsafe(ctx, in)
				} else {
					res, err = s.processWebhook(ctx, in)
				}
				switch {
				case err != nil:
					failed.Add(1)
				case res.Duplicate:
					duplicates.Add(1)
				case res.Rejected:
					rejected.Add(1)
				default:
					created.Add(1)
					if in.Direction == "CREDIT" {
						applied.Add(int64(in.Amount))
					} else {
						applied.Add(-int64(in.Amount))
					}
					if doSync {
						s.syncer.Enqueue(res.Tx.ID)
					}
				}
			}
		}()
	}
	for _, j := range jobs {
		ch <- j
	}
	close(ch)
	wg.Wait()
	elapsed := time.Since(start)

	if !doSync {
		if err := s.db.WithContext(ctx).Model(&models.BankTransaction{}).
			Where("external_ref LIKE ? AND status = ?", prefix+"-%", "RECEIVED").
			Updates(map[string]any{"sync_attempts": services.MaxSyncAttempts, "last_error": "dữ liệu giả lập, không đồng bộ"}).Error; err != nil {
			s.internalError(c, err)
			return
		}
	}

	after, err := s.getAccount(ctx, acc.ID)
	if err != nil {
		s.internalError(c, err)
		return
	}
	var stored int64
	if err := s.db.WithContext(ctx).Model(&models.BankTransaction{}).
		Where("external_ref LIKE ?", prefix+"-%").Count(&stored).Error; err != nil {
		s.internalError(c, err)
		return
	}

	expected := acc.Balance + models.Money(applied.Load())
	consistent := after.Balance == expected && stored == int64(len(events)) && failed.Load() == 0

	msg := "dữ liệu nhất quán: số dư khớp, không có giao dịch bị ghi trùng"
	if !consistent {
		msg = "dữ liệu KHÔNG nhất quán: số dư thực tế lệch so với tổng các giao dịch đã ghi nhận"
	}
	httpx.OK(c, http.StatusOK, msg, gin.H{
		"mode":             req.Mode,
		"account_id":       acc.ID,
		"workers":          req.Workers,
		"unique_events":    len(events),
		"requests_sent":    len(jobs),
		"created":          created.Load(),
		"duplicates":       duplicates.Load(),
		"rejected":         rejected.Load(),
		"errors":           failed.Load(),
		"stored_records":   stored,
		"balance_before":   acc.Balance,
		"balance_after":    after.Balance,
		"expected_balance": expected,
		"difference":       after.Balance - expected,
		"consistent":       consistent,
		"elapsed_ms":       elapsed.Milliseconds(),
		"ref_prefix":       prefix,
	})
}

func (s *Server) processUnsafe(ctx context.Context, in WebhookInput) (webhookResult, error) {
	var acc models.LinkedAccount
	if err := s.db.WithContext(ctx).Select("id, user_id, balance").
		Where("bank_code = ? AND account_no = ?", in.BankCode, in.AccountNo).First(&acc).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return webhookResult{}, errAccountNotFound
		}
		return webhookResult{}, err
	}

	time.Sleep(time.Duration(1+rand.Intn(3)) * time.Millisecond)

	status := "RECEIVED"
	newBalance := acc.Balance
	switch {
	case in.Direction == "CREDIT":
		newBalance += in.Amount
	case acc.Balance < in.Amount:
		status = "REJECTED"
	default:
		newBalance -= in.Amount
	}

	if status != "REJECTED" {
		if err := s.db.WithContext(ctx).Model(&models.LinkedAccount{}).Where("id = ?", acc.ID).
			Updates(map[string]any{"balance": newBalance, "version": gorm.Expr("version + 1")}).Error; err != nil {
			return webhookResult{}, err
		}
	}

	txRow := models.BankTransaction{
		AccountID:      acc.ID,
		Provider:       in.Provider,
		ExternalRef:    in.ExternalRef,
		IdempotencyKey: in.Key,
		RequestHash:    in.Hash,
		Direction:      in.Direction,
		Amount:         in.Amount,
		BalanceAfter:   newBalance,
		Category:       in.Category,
		Description:    in.Description,
		OccurredAt:     in.OccurredAt,
		Status:         status,
	}
	if err := s.db.WithContext(ctx).Create(&txRow).Error; err != nil {
		if database.IsDuplicateKey(err) {
			return webhookResult{UserID: acc.UserID, Duplicate: true}, nil
		}
		return webhookResult{}, err
	}
	return webhookResult{Tx: txRow, UserID: acc.UserID, Rejected: status == "REJECTED"}, nil
}
