package main

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strconv"
	"time"
)

const maxSyncAttempts = 5

// syncer đẩy các biến động đã ghi nhận sang transaction-service (php) ở background.
// webhook trả lời ngân hàng ngay, không phải chờ service khác -> không nghẽn khi tải cao.
type syncer struct {
	db       *sql.DB
	cfg      Config
	client   *http.Client
	notifier *notifier
	queue    chan int64
}

func newSyncer(db *sql.DB, cfg Config, client *http.Client, n *notifier) *syncer {
	return &syncer{db: db, cfg: cfg, client: client, notifier: n, queue: make(chan int64, 2000)}
}

func (s *syncer) start(ctx context.Context) {
	for i := 1; i <= s.cfg.SyncWorkers; i++ {
		go s.worker(ctx, i)
	}
	go s.sweeper(ctx)
}

func (s *syncer) enqueue(id int64) {
	select {
	case s.queue <- id:
	default:
		// hàng đợi đầy thì bỏ qua, sweeper sẽ quét lại từ db sau
	}
}

func (s *syncer) pending() int {
	return len(s.queue)
}

func (s *syncer) worker(ctx context.Context, n int) {
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

// sweeper chạy định kỳ: nhặt lại giao dịch lỗi hoặc bị rớt khỏi hàng đợi
func (s *syncer) sweeper(ctx context.Context) {
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

func (s *syncer) sweep(ctx context.Context) {
	// service bị tắt giữa lúc đang đồng bộ thì bản ghi kẹt ở SYNCING, trả về FAILED để thử lại
	if _, err := s.db.ExecContext(ctx, `UPDATE bank_transactions SET status = 'FAILED', last_error = 'quá thời gian chờ đồng bộ'
		WHERE status = 'SYNCING' AND updated_at < NOW() - INTERVAL 2 MINUTE`); err != nil {
		log.Printf("[sweeper] %v", err)
		return
	}

	rows, err := s.db.QueryContext(ctx, `SELECT id FROM bank_transactions
		WHERE status IN ('RECEIVED', 'FAILED') AND sync_attempts < ? AND updated_at < NOW() - INTERVAL 10 SECOND
		ORDER BY id LIMIT 200`, maxSyncAttempts)
	if err != nil {
		log.Printf("[sweeper] %v", err)
		return
	}
	var ids []int64
	for rows.Next() {
		var id int64
		if rows.Scan(&id) == nil {
			ids = append(ids, id)
		}
	}
	rows.Close()

	for _, id := range ids {
		s.enqueue(id)
	}
	if len(ids) > 0 {
		log.Printf("[sweeper] đưa lại %d giao dịch vào hàng đợi", len(ids))
	}
}

func (s *syncer) syncOne(ctx context.Context, id int64) error {
	// "giành" bản ghi bằng 1 câu update có điều kiện. chỉ 1 worker (hoặc 1 instance) update được,
	// các worker khác nhận rows affected = 0 thì bỏ qua -> không bao giờ đẩy trùng 1 giao dịch
	res, err := s.db.ExecContext(ctx, `UPDATE bank_transactions SET status = 'SYNCING', sync_attempts = sync_attempts + 1
		WHERE id = ? AND status IN ('RECEIVED', 'FAILED') AND sync_attempts < ?`, id, maxSyncAttempts)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return nil
	}

	t, err := getBankTx(ctx, s.db, id)
	if err != nil {
		return err
	}
	var userID int64
	if err := s.db.QueryRowContext(ctx, "SELECT user_id FROM linked_accounts WHERE id = ?", t.AccountID).Scan(&userID); err != nil {
		return err
	}

	ref, alert, err := s.forward(ctx, t, userID)
	if err != nil {
		_, uerr := s.db.ExecContext(ctx, "UPDATE bank_transactions SET status = 'FAILED', last_error = ? WHERE id = ?",
			truncate(err.Error(), 255), id)
		if uerr != nil {
			log.Printf("không cập nhật được trạng thái FAILED cho #%d: %v", id, uerr)
		}
		return err
	}

	if _, err := s.db.ExecContext(ctx, "UPDATE bank_transactions SET status = 'SYNCED', transaction_ref = ?, last_error = NULL WHERE id = ?",
		ref, id); err != nil {
		return err
	}

	// transaction-service báo vượt / sắp vượt ngân sách thì chuyển tiếp sang notification-service
	if alert != nil {
		msg, _ := alert["message"].(string)
		if msg == "" {
			msg = "Chi tiêu đã chạm ngưỡng ngân sách"
		}
		s.notifier.send(userID, "Cảnh báo ngân sách", msg, "BUDGET_ALERT", alert)
	}
	return nil
}

func (s *syncer) forward(ctx context.Context, t bankTx, userID int64) (int64, map[string]any, error) {
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
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, maxBodySize))

	if resp.StatusCode/100 != 2 {
		return 0, nil, fmt.Errorf("transaction-service trả về %d: %s", resp.StatusCode, truncate(string(raw), 150))
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
