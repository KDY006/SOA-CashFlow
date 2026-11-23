package services

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strconv"
	"sync"
	"time"

	"cashflow/integration-service-final/internal/config"
	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"gorm.io/gorm"
)

const MaxSyncAttempts = 5

type Syncer struct {
	db       *gorm.DB
	cfg      config.Config
	client   *http.Client
	notifier *Notifier
	queue    chan int64
	wg       sync.WaitGroup
}

func NewSyncer(db *gorm.DB, cfg config.Config, client *http.Client, n *Notifier) *Syncer {
	return &Syncer{
		db:       db,
		cfg:      cfg,
		client:   client,
		notifier: n,
		queue:    make(chan int64, 2000),
	}
}

func (s *Syncer) Start(ctx context.Context) {
	for i := 1; i <= s.cfg.SyncWorkers; i++ {
		s.wg.Add(1)
		go func(n int) {
			defer s.wg.Done()
			s.worker(ctx, n)
		}(i)
	}
	s.wg.Add(1)
	go func() {
		defer s.wg.Done()
		s.sweeper(ctx)
	}()
}

func (s *Syncer) Wait() {
	s.wg.Wait()
}

func (s *Syncer) Enqueue(id int64) {
	select {
	case s.queue <- id:
	default:
	}
}

func (s *Syncer) Pending() int {
	return len(s.queue)
}

func (s *Syncer) worker(ctx context.Context, n int) {
	for {
		select {
		case <-ctx.Done():
			return
		case id := <-s.queue:
			if err := s.syncOne(ctx, id); err != nil {
				log.Printf("[sync-%d] giao dịch #%d lỗi: %v", n, id, err)
			}
		}
	}
}

func (s *Syncer) sweeper(ctx context.Context) {
	s.sweep(ctx)
	t := time.NewTicker(15 * time.Second)
	defer t.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			s.sweep(ctx)
		}
	}
}

func (s *Syncer) sweep(ctx context.Context) {
	if err := s.db.WithContext(ctx).
		Model(&models.BankTransaction{}).
		Where("status = ? AND updated_at < NOW() - INTERVAL 2 MINUTE", "SYNCING").
		Updates(map[string]any{"status": "FAILED", "last_error": "quá thời gian chờ đồng bộ"}).Error; err != nil {
		log.Printf("[sweeper] %v", err)
		return
	}

	var ids []int64
	if err := s.db.WithContext(ctx).
		Model(&models.BankTransaction{}).
		Where("status IN ? AND sync_attempts < ? AND updated_at < NOW() - INTERVAL 10 SECOND",
			[]string{"RECEIVED", "FAILED"}, MaxSyncAttempts).
		Order("id ASC").
		Limit(200).
		Pluck("id", &ids).Error; err != nil {
		log.Printf("[sweeper] %v", err)
		return
	}

	for _, id := range ids {
		s.Enqueue(id)
	}
	if len(ids) > 0 {
		log.Printf("[sweeper] đưa lại %d giao dịch vào hàng đợi", len(ids))
	}
}

func (s *Syncer) syncOne(ctx context.Context, id int64) error {
	res := s.db.WithContext(ctx).
		Model(&models.BankTransaction{}).
		Where("id = ? AND status IN ? AND sync_attempts < ?", id, []string{"RECEIVED", "FAILED"}, MaxSyncAttempts).
		Updates(map[string]any{
			"status":        "SYNCING",
			"sync_attempts": gorm.Expr("sync_attempts + 1"),
		})
	if res.Error != nil {
		return res.Error
	}
	if res.RowsAffected == 0 {
		return nil
	}

	var tx models.BankTransaction
	if err := s.db.WithContext(ctx).First(&tx, id).Error; err != nil {
		return err
	}

	var acc models.LinkedAccount
	if err := s.db.WithContext(ctx).Select("user_id").First(&acc, tx.AccountID).Error; err != nil {
		return err
	}

	ref, alert, err := s.forward(ctx, tx, acc.UserID)
	if err != nil {
		if uerr := s.db.WithContext(ctx).Model(&models.BankTransaction{}).Where("id = ?", id).
			Updates(map[string]any{"status": "FAILED", "last_error": httpx.Truncate(err.Error(), 255)}).Error; uerr != nil {
			log.Printf("không cập nhật được trạng thái FAILED cho #%d: %v", id, uerr)
		}
		return err
	}

	if err := s.db.WithContext(ctx).Model(&models.BankTransaction{}).Where("id = ?", id).
		Updates(map[string]any{"status": "SYNCED", "transaction_ref": ref, "last_error": nil}).Error; err != nil {
		return err
	}

	if alert != nil {
		msg, _ := alert["message"].(string)
		if msg == "" {
			msg = "Chi tiêu đã chạm ngưỡng ngân sách"
		}
		s.notifier.Send(acc.UserID, "Cảnh báo ngân sách", msg, "BUDGET_ALERT", alert)
	}
	return nil
}

func (s *Syncer) forward(ctx context.Context, t models.BankTransaction, userID int64) (int64, map[string]any, error) {
	txType, category := "INCOME", t.Category
	if t.Direction == "DEBIT" {
		txType = "EXPENSE"
	}
	if category == "" {
		category = "Thu nhập khác"
		if txType == "EXPENSE" {
			category = "Chi tiêu khác"
		}
	}
	desc := t.Description
	if desc == "" {
		desc = "Biến động số dư"
	}

	body, _ := json.Marshal(map[string]any{
		"user_id":          userID,
		"amount":           t.Amount.Float(),
		"type":             txType,
		"category":         category,
		"description":      fmt.Sprintf("[%s] %s (ref: %s)", t.Provider, desc, t.ExternalRef),
		"transaction_date": t.OccurredAt.Format("2006-01-02 15:04:05"),
	})

	ctx, cancel := context.WithTimeout(ctx, s.cfg.HTTPTimeout)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, s.cfg.TransactionURL+"/api/transactions", bytes.NewReader(body))
	if err != nil {
		return 0, nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-User-Id", strconv.FormatInt(userID, 10))
	req.Header.Set("Idempotency-Key", t.IdempotencyKey)

	resp, err := s.client.Do(req)
	if err != nil {
		return 0, nil, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, httpx.MaxBodySize))

	if resp.StatusCode/100 != 2 {
		return 0, nil, fmt.Errorf("transaction-service trả về %d: %s", resp.StatusCode, httpx.Truncate(string(raw), 150))
	}

	var out struct {
		Data struct {
			Transaction struct {
				ID json.Number `json:"id"`
			} `json:"transaction"`
			BudgetAlert map[string]any `json:"budget_alert"`
		} `json:"data"`
	}
	if err := json.Unmarshal(raw, &out); err != nil {
		return 0, nil, fmt.Errorf("không đọc được phản hồi transaction-service: %v", err)
	}
	ref, _ := out.Data.Transaction.ID.Int64()
	return ref, out.Data.BudgetAlert, nil
}
