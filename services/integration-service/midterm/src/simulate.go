package main

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"math/rand"
	"net/http"
	"sync"
	"sync/atomic"
	"time"
)

type simulateRequest struct {
	AccountID     int64    `json:"account_id"`
	Events        int      `json:"events"`
	Workers       int      `json:"workers"`
	DuplicateRate *float64 `json:"duplicate_rate"`
	Mode          string   `json:"mode"` // safe | unsafe
	Sync          *bool    `json:"sync"`
}

// POST /api/integrations/simulate
// giả lập ngân hàng bắn dồn dập nhiều biến động vào cùng 1 tài khoản, có cả request bị gửi trùng.
// xong thì so số dư thực tế trong db với số dư tính tay để chứng minh không bị race condition.
func (s *Server) handleSimulate(w http.ResponseWriter, r *http.Request) {
	var req simulateRequest
	if err := decodeJSON(r, &req); err != nil {
		fail(w, http.StatusBadRequest, "BAD_REQUEST", err.Error())
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
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "events tối đa 2000")
		return
	case req.Workers > 200:
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "workers tối đa 200")
		return
	case dupRate < 0 || dupRate > 1:
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "duplicate_rate phải trong khoảng 0 - 1")
		return
	case req.Mode != "safe" && req.Mode != "unsafe":
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "mode chỉ nhận safe hoặc unsafe")
		return
	}
	unsafe := req.Mode == "unsafe"
	doSync := !unsafe && (req.Sync == nil || *req.Sync)

	// không gắn vào request context, client có ngắt kết nối thì đợt giả lập vẫn chạy cho hết
	ctx := context.WithoutCancel(r.Context())

	acc, err := s.getAccount(ctx, req.AccountID)
	if errors.Is(err, sql.ErrNoRows) {
		fail(w, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(w, err)
		return
	}

	prefix := fmt.Sprintf("SIM%d", time.Now().UnixNano())
	events := make([]webhookInput, req.Events)
	for i := range events {
		dir := "CREDIT"
		if rand.Float64() < 0.4 {
			dir = "DEBIT"
		}
		wr := webhookRequest{
			Provider:    acc.BankCode,
			ExternalRef: fmt.Sprintf("%s-%04d", prefix, i+1),
			BankCode:    acc.BankCode,
			AccountNo:   acc.AccountNo,
			Direction:   dir,
			Amount:      float64((rand.Intn(490) + 10) * 1000),
			Description: "Giả lập biến động từ ngân hàng",
		}
		in, err := wr.normalize("")
		if err != nil {
			s.internalError(w, err)
			return
		}
		events[i] = in
	}

	// trộn thêm request trùng để kiểm tra idempotency
	jobs := append([]webhookInput{}, events...)
	for i := 0; i < int(float64(len(events))*dupRate); i++ {
		jobs = append(jobs, events[rand.Intn(len(events))])
	}
	rand.Shuffle(len(jobs), func(i, j int) { jobs[i], jobs[j] = jobs[j], jobs[i] })

	var created, duplicates, rejected, failed atomic.Int64
	var applied atomic.Int64 // tổng tiền thực sự được cộng/trừ vào số dư

	ch := make(chan webhookInput)
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
						s.syncer.enqueue(res.Tx.ID)
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

	// không đồng bộ thì đánh dấu hết lượt thử, nếu không sweeper sẽ nhặt các bản ghi RECEIVED này
	// và vẫn đẩy sang transaction-service, làm bẩn dữ liệu bên php
	if !doSync {
		if _, err := s.db.ExecContext(ctx, `UPDATE bank_transactions SET sync_attempts = ?, last_error = 'dữ liệu giả lập, không đồng bộ'
			WHERE external_ref LIKE ? AND status = 'RECEIVED'`, maxSyncAttempts, prefix+"-%"); err != nil {
			s.internalError(w, err)
			return
		}
	}

	after, err := s.getAccount(ctx, acc.ID)
	if err != nil {
		s.internalError(w, err)
		return
	}
	var stored int64
	if err := s.db.QueryRowContext(ctx, "SELECT COUNT(*) FROM bank_transactions WHERE external_ref LIKE ?", prefix+"-%").Scan(&stored); err != nil {
		s.internalError(w, err)
		return
	}

	expected := acc.Balance + money(applied.Load())
	consistent := after.Balance == expected && stored == int64(len(events)) && failed.Load() == 0

	msg := "dữ liệu nhất quán: số dư khớp, không có giao dịch bị ghi trùng"
	if !consistent {
		msg = "dữ liệu KHÔNG nhất quán: số dư thực tế lệch so với tổng các giao dịch đã ghi nhận"
	}
	ok(w, http.StatusOK, msg, map[string]any{
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

// processUnsafe cố tình bỏ mutex, bỏ transaction và FOR UPDATE.
// chỉ dùng cho /simulate với mode=unsafe, để lúc báo cáo so sánh trước/sau khi có cơ chế khóa.
func (s *Server) processUnsafe(ctx context.Context, in webhookInput) (webhookResult, error) {
	var accID, userID int64
	var balance money
	err := s.db.QueryRowContext(ctx,
		"SELECT id, user_id, balance FROM linked_accounts WHERE bank_code = ? AND account_no = ?",
		in.BankCode, in.AccountNo).Scan(&accID, &userID, &balance)
	if errors.Is(err, sql.ErrNoRows) {
		return webhookResult{}, errAccountNotFound
	}
	if err != nil {
		return webhookResult{}, err
	}

	// giả lập thời gian xử lý nghiệp vụ, khoảng hở này là chỗ race condition xảy ra
	time.Sleep(time.Duration(1+rand.Intn(3)) * time.Millisecond)

	status := "RECEIVED"
	newBalance := balance
	switch {
	case in.Direction == "CREDIT":
		newBalance += in.Amount
	case balance < in.Amount:
		status = "REJECTED"
	default:
		newBalance -= in.Amount
	}
	if status != "REJECTED" {
		if _, err := s.db.ExecContext(ctx, "UPDATE linked_accounts SET balance = ?, version = version + 1 WHERE id = ?", newBalance, accID); err != nil {
			return webhookResult{}, err
		}
	}

	id, err := insertBankTx(ctx, s.db, accID, in, newBalance, status)
	if isDuplicateKey(err) {
		// lúc này số dư đã bị cộng/trừ lần 2 rồi -> đây chính là lỗi khi không có transaction
		return webhookResult{UserID: userID, Duplicate: true}, nil
	}
	if err != nil {
		return webhookResult{}, err
	}
	return webhookResult{Tx: bankTx{ID: id}, UserID: userID, Rejected: status == "REJECTED"}, nil
}
