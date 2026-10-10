package main

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net/http"
	"strings"
	"time"
)

var (
	errAccountNotFound = errors.New("không tìm thấy tài khoản liên kết")
	errKeyConflict     = errors.New("idempotency key này đã dùng cho một giao dịch khác")
)

const maxAmount money = 1_000_000_000_000 * 100 // 1000 tỷ

// payload ngân hàng gửi sang
type webhookRequest struct {
	Provider    string  `json:"provider"`
	ExternalRef string  `json:"external_ref"`
	BankCode    string  `json:"bank_code"`
	AccountNo   string  `json:"account_no"`
	Direction   string  `json:"direction"`
	Amount      float64 `json:"amount"`
	Category    string  `json:"category"`
	Description string  `json:"description"`
	OccurredAt  string  `json:"occurred_at"`
}

// dữ liệu đã chuẩn hóa để xử lý
type webhookInput struct {
	Provider    string
	ExternalRef string
	BankCode    string
	AccountNo   string
	Direction   string
	Amount      money
	Category    string
	Description string
	OccurredAt  time.Time
	Key         string // idempotency key
	Hash        string // hash nội dung, để phát hiện dùng lại key cho giao dịch khác
}

func (r webhookRequest) normalize(headerKey string) (webhookInput, error) {
	in := webhookInput{
		Provider:    strings.ToUpper(strings.TrimSpace(r.Provider)),
		ExternalRef: strings.TrimSpace(r.ExternalRef),
		BankCode:    strings.ToUpper(strings.TrimSpace(r.BankCode)),
		AccountNo:   strings.TrimSpace(r.AccountNo),
		Direction:   strings.ToUpper(strings.TrimSpace(r.Direction)),
		Amount:      moneyFromFloat(r.Amount),
		Category:    truncate(strings.TrimSpace(r.Category), 100),
		Description: truncate(strings.TrimSpace(r.Description), 255),
	}

	if in.Provider == "" || len(in.Provider) > 20 {
		return in, errors.New("provider không hợp lệ")
	}
	if in.ExternalRef == "" || len(in.ExternalRef) > 100 {
		return in, errors.New("external_ref không hợp lệ")
	}
	if in.BankCode == "" {
		in.BankCode = in.Provider
	}
	if in.AccountNo == "" {
		return in, errors.New("thiếu account_no")
	}
	if in.Direction != "CREDIT" && in.Direction != "DEBIT" {
		return in, errors.New("direction chỉ nhận CREDIT hoặc DEBIT")
	}
	if in.Amount <= 0 || in.Amount > maxAmount {
		return in, errors.New("amount phải lớn hơn 0 và không vượt quá 1000 tỷ")
	}

	in.OccurredAt = time.Now()
	if r.OccurredAt != "" {
		t, err := parseTime(r.OccurredAt)
		if err != nil {
			return in, errors.New("occurred_at sai định dạng (vd: 2026-10-10 14:30:00)")
		}
		in.OccurredAt = t
	}

	// ngân hàng không gửi Idempotency-Key thì lấy provider + mã giao dịch của họ làm key
	in.Key = strings.TrimSpace(headerKey)
	if in.Key == "" {
		in.Key = in.Provider + ":" + in.ExternalRef
	}
	if len(in.Key) > 150 {
		return in, errors.New("Idempotency-Key quá dài")
	}

	raw := fmt.Sprintf("%s|%s|%s|%s|%s|%d", in.Provider, in.ExternalRef, in.BankCode, in.AccountNo, in.Direction, int64(in.Amount))
	sum := sha256.Sum256([]byte(raw))
	in.Hash = hex.EncodeToString(sum[:])
	return in, nil
}

func parseTime(s string) (time.Time, error) {
	if t, err := time.Parse(time.RFC3339, s); err == nil {
		return t.Local(), nil
	}
	for _, layout := range []string{"2006-01-02 15:04:05", "2006-01-02T15:04:05"} {
		if t, err := time.ParseInLocation(layout, s, time.Local); err == nil {
			return t, nil
		}
	}
	return time.Time{}, errors.New("sai định dạng thời gian")
}

type bankTx struct {
	ID             int64     `json:"id"`
	AccountID      int64     `json:"account_id"`
	Provider       string    `json:"provider"`
	ExternalRef    string    `json:"external_ref"`
	IdempotencyKey string    `json:"idempotency_key"`
	Direction      string    `json:"direction"`
	Amount         money     `json:"amount"`
	BalanceAfter   money     `json:"balance_after"`
	Category       string    `json:"category"`
	Description    string    `json:"description"`
	OccurredAt     time.Time `json:"occurred_at"`
	Status         string    `json:"status"`
	TransactionRef *int64    `json:"transaction_ref"`
	SyncAttempts   int       `json:"sync_attempts"`
	LastError      *string   `json:"last_error,omitempty"`
	CreatedAt      time.Time `json:"created_at"`
	requestHash    string
}

const bankTxColumns = "id, account_id, provider, external_ref, idempotency_key, direction, amount, balance_after, " +
	"category, description, occurred_at, status, transaction_ref, sync_attempts, last_error, created_at, request_hash"

func scanBankTx(sc scanner) (bankTx, error) {
	var t bankTx
	err := sc.Scan(&t.ID, &t.AccountID, &t.Provider, &t.ExternalRef, &t.IdempotencyKey, &t.Direction, &t.Amount,
		&t.BalanceAfter, &t.Category, &t.Description, &t.OccurredAt, &t.Status, &t.TransactionRef,
		&t.SyncAttempts, &t.LastError, &t.CreatedAt, &t.requestHash)
	return t, err
}

func getBankTx(ctx context.Context, q querier, id int64) (bankTx, error) {
	return scanBankTx(q.QueryRowContext(ctx, "SELECT "+bankTxColumns+" FROM bank_transactions WHERE id = ?", id))
}

// tìm giao dịch đã ghi nhận theo key hoặc theo mã giao dịch của ngân hàng
func findExisting(ctx context.Context, q querier, in webhookInput) (bankTx, error) {
	return scanBankTx(q.QueryRowContext(ctx,
		"SELECT "+bankTxColumns+" FROM bank_transactions WHERE idempotency_key = ? OR (provider = ? AND external_ref = ?) LIMIT 1",
		in.Key, in.Provider, in.ExternalRef))
}

func insertBankTx(ctx context.Context, q querier, accountID int64, in webhookInput, balanceAfter money, status string) (int64, error) {
	res, err := q.ExecContext(ctx, `INSERT INTO bank_transactions
		(account_id, provider, external_ref, idempotency_key, request_hash, direction, amount, balance_after, category, description, occurred_at, status)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		accountID, in.Provider, in.ExternalRef, in.Key, in.Hash, in.Direction, in.Amount, balanceAfter,
		in.Category, in.Description, in.OccurredAt, status)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

type webhookResult struct {
	Tx        bankTx
	UserID    int64
	Duplicate bool
	Rejected  bool
}

func duplicateOf(t bankTx, in webhookInput, userID int64) (webhookResult, error) {
	if t.requestHash != in.Hash {
		return webhookResult{}, errKeyConflict
	}
	return webhookResult{Tx: t, UserID: userID, Duplicate: true, Rejected: t.Status == "REJECTED"}, nil
}

// processWebhook ghi nhận 1 biến động số dư. gặp deadlock thì thử lại tối đa 3 lần.
func (s *Server) processWebhook(ctx context.Context, in webhookInput) (webhookResult, error) {
	var lastErr error
	for attempt := 1; attempt <= 3; attempt++ {
		res, err := s.applyWebhook(ctx, in)
		if !isRetryable(err) {
			return res, err
		}
		lastErr = err
		log.Printf("deadlock khi xử lý %s, thử lại lần %d", in.Key, attempt)
		time.Sleep(time.Duration(attempt*25) * time.Millisecond)
	}
	return webhookResult{}, lastErr
}

// applyWebhook là phần xử lý chính, chống race condition bằng 2 lớp:
//  1. mutex theo tài khoản trong process -> các request cùng tài khoản xếp hàng, đỡ tranh lock dưới db
//  2. SELECT ... FOR UPDATE trong transaction -> khóa dòng tài khoản, đúng cả khi chạy nhiều instance
//
// idempotency: kiểm tra key sau khi đã giữ khóa, thêm unique key dưới db làm chốt chặn cuối.
func (s *Server) applyWebhook(ctx context.Context, in webhookInput) (webhookResult, error) {
	unlock := s.locker.Lock(in.BankCode + ":" + in.AccountNo)
	defer unlock()

	// read committed để mỗi câu select luôn thấy dữ liệu mới nhất đã commit,
	// và tránh gap lock của repeatable read (dễ deadlock khi insert song song)
	tx, err := s.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return webhookResult{}, err
	}
	defer tx.Rollback()

	var accID, userID int64
	var balance money
	err = tx.QueryRowContext(ctx,
		"SELECT id, user_id, balance FROM linked_accounts WHERE bank_code = ? AND account_no = ? FOR UPDATE",
		in.BankCode, in.AccountNo).Scan(&accID, &userID, &balance)
	if errors.Is(err, sql.ErrNoRows) {
		return webhookResult{}, errAccountNotFound
	}
	if err != nil {
		return webhookResult{}, err
	}

	// đã giữ khóa tài khoản rồi mới kiểm tra trùng, nên 2 request trùng nhau không thể cùng lọt qua
	existing, err := findExisting(ctx, tx, in)
	if err == nil {
		return duplicateOf(existing, in, userID)
	}
	if !errors.Is(err, sql.ErrNoRows) {
		return webhookResult{}, err
	}

	status := "RECEIVED"
	newBalance := balance
	switch {
	case in.Direction == "CREDIT":
		newBalance += in.Amount
	case balance < in.Amount:
		// vẫn lưu lại giao dịch bị từ chối, để ngân hàng gửi lại thì trả đúng kết quả cũ
		status = "REJECTED"
	default:
		newBalance -= in.Amount
	}

	if status != "REJECTED" {
		if _, err := tx.ExecContext(ctx,
			"UPDATE linked_accounts SET balance = ?, version = version + 1 WHERE id = ?", newBalance, accID); err != nil {
			return webhookResult{}, err
		}
	}

	id, err := insertBankTx(ctx, tx, accID, in, newBalance, status)
	if isDuplicateKey(err) {
		// trường hợp hiếm: cùng mã giao dịch nhưng gửi vào tài khoản khác, hoặc key khác.
		// rollback để số dư không bị cộng, rồi trả về bản ghi đã có
		tx.Rollback()
		existing, ferr := findExisting(ctx, s.db, in)
		if ferr != nil {
			return webhookResult{}, ferr
		}
		return duplicateOf(existing, in, userID)
	}
	if err != nil {
		return webhookResult{}, err
	}

	if err := tx.Commit(); err != nil {
		return webhookResult{}, err
	}

	t, err := getBankTx(ctx, s.db, id)
	if err != nil {
		return webhookResult{}, err
	}
	return webhookResult{Tx: t, UserID: userID, Rejected: status == "REJECTED"}, nil
}

func validSignature(body []byte, signature, secret string) bool {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	expected := hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(expected), []byte(strings.ToLower(strings.TrimSpace(signature))))
}

// POST /api/integrations/webhooks/banking
func (s *Server) handleBankWebhook(w http.ResponseWriter, r *http.Request) {
	raw, err := readBody(r)
	if errors.Is(err, errBodyTooLarge) {
		fail(w, http.StatusRequestEntityTooLarge, "PAYLOAD_TOO_LARGE", err.Error())
		return
	}
	if err != nil {
		fail(w, http.StatusBadRequest, "BAD_REQUEST", "không đọc được body")
		return
	}

	if s.cfg.WebhookSecret != "" && !validSignature(raw, r.Header.Get("X-Signature"), s.cfg.WebhookSecret) {
		fail(w, http.StatusUnauthorized, "INVALID_SIGNATURE", "chữ ký webhook không hợp lệ")
		return
	}

	var req webhookRequest
	if err := json.Unmarshal(raw, &req); err != nil {
		fail(w, http.StatusBadRequest, "BAD_REQUEST", "json không hợp lệ")
		return
	}
	in, err := req.normalize(r.Header.Get("Idempotency-Key"))
	if err != nil {
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", err.Error())
		return
	}

	s.logWebhook(in.Provider, raw)

	res, err := s.processWebhook(r.Context(), in)
	switch {
	case errors.Is(err, errAccountNotFound):
		fail(w, http.StatusNotFound, "ACCOUNT_NOT_FOUND", err.Error())
		return
	case errors.Is(err, errKeyConflict):
		fail(w, http.StatusConflict, "IDEMPOTENCY_CONFLICT", err.Error())
		return
	case err != nil:
		s.internalError(w, err)
		return
	}

	if !res.Duplicate {
		s.afterAccepted(in, res)
	}

	status, msg := http.StatusCreated, "ghi nhận biến động số dư thành công"
	switch {
	case res.Rejected:
		status, msg = http.StatusUnprocessableEntity, "số dư không đủ, giao dịch bị từ chối"
	case res.Duplicate:
		status, msg = http.StatusOK, "giao dịch đã được ghi nhận trước đó, bỏ qua"
	}
	writeJSON(w, status, map[string]any{
		"success": !res.Rejected,
		"message": msg,
		"data": map[string]any{
			"duplicate":   res.Duplicate,
			"transaction": res.Tx,
		},
	})
}

// việc phụ sau khi ghi nhận: đẩy sang transaction-service và gửi cảnh báo
func (s *Server) afterAccepted(in webhookInput, res webhookResult) {
	if res.Rejected {
		s.notifier.send(res.UserID, "Giao dịch bị từ chối",
			fmt.Sprintf("Giao dịch %s %s đ từ %s bị từ chối do số dư không đủ", in.ExternalRef, formatVND(in.Amount), in.BankCode),
			"TRANSACTION_REJECTED", map[string]any{"bank_tx_id": res.Tx.ID, "external_ref": in.ExternalRef})
		return
	}

	s.syncer.enqueue(res.Tx.ID)

	if in.Amount >= s.cfg.LargeAmount {
		sign := "+"
		if in.Direction == "DEBIT" {
			sign = "-"
		}
		s.notifier.send(res.UserID, "Biến động số dư lớn",
			fmt.Sprintf("Tài khoản %s %s %s đ. Số dư: %s đ", in.BankCode, sign, formatVND(in.Amount), formatVND(res.Tx.BalanceAfter)),
			"LARGE_TRANSACTION", map[string]any{"bank_tx_id": res.Tx.ID, "external_ref": in.ExternalRef})
	}
}

func (s *Server) logWebhook(provider string, raw []byte) {
	_, err := s.db.Exec("INSERT INTO external_webhooks (provider_name, event_type, payload) VALUES (?, 'BANK_TRANSACTION', ?)",
		provider, string(raw))
	if err != nil {
		log.Printf("không lưu được log webhook: %v", err)
	}
}

// GET /api/integrations/bank-transactions/{id}
func (s *Server) handleGetBankTx(w http.ResponseWriter, r *http.Request) {
	id, valid := pathID(r)
	if !valid {
		fail(w, http.StatusBadRequest, "INVALID_ID", "id không hợp lệ")
		return
	}
	t, err := getBankTx(r.Context(), s.db, id)
	if err == nil {
		var owner int64
		owner, err = s.accountOwner(r.Context(), t.AccountID)
		if err == nil && forbiddenFor(r, owner) {
			err = sql.ErrNoRows
		}
	}
	if errors.Is(err, sql.ErrNoRows) {
		fail(w, http.StatusNotFound, "NOT_FOUND", "không tìm thấy giao dịch")
		return
	}
	if err != nil {
		s.internalError(w, err)
		return
	}
	ok(w, http.StatusOK, "lấy chi tiết giao dịch thành công", t)
}

// POST /api/integrations/bank-transactions/{id}/retry
// đẩy lại thủ công những giao dịch đã hết lượt tự thử lại
func (s *Server) handleRetrySync(w http.ResponseWriter, r *http.Request) {
	id, valid := pathID(r)
	if !valid {
		fail(w, http.StatusBadRequest, "INVALID_ID", "id không hợp lệ")
		return
	}
	if uid := authUserID(r); uid > 0 {
		var owner int64
		err := s.db.QueryRowContext(r.Context(), `SELECT a.user_id FROM bank_transactions t
			JOIN linked_accounts a ON a.id = t.account_id WHERE t.id = ?`, id).Scan(&owner)
		if errors.Is(err, sql.ErrNoRows) || (err == nil && owner != uid) {
			fail(w, http.StatusNotFound, "NOT_FOUND", "không tìm thấy giao dịch")
			return
		}
		if err != nil {
			s.internalError(w, err)
			return
		}
	}
	res, err := s.db.ExecContext(r.Context(),
		"UPDATE bank_transactions SET sync_attempts = 0, last_error = NULL WHERE id = ? AND status = 'FAILED'", id)
	if err != nil {
		s.internalError(w, err)
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		fail(w, http.StatusConflict, "NOT_RETRYABLE", "chỉ thử lại được giao dịch đang ở trạng thái FAILED")
		return
	}
	s.syncer.enqueue(id)
	ok(w, http.StatusAccepted, "đã đưa giao dịch vào hàng đợi đồng bộ", map[string]any{"id": id})
}

// 1500000.00 -> 1.500.000
func formatVND(m money) string {
	s := fmt.Sprintf("%d", int64(m)/100)
	neg := strings.HasPrefix(s, "-")
	s = strings.TrimPrefix(s, "-")
	var b strings.Builder
	for i, c := range s {
		if i > 0 && (len(s)-i)%3 == 0 {
			b.WriteByte('.')
		}
		b.WriteRune(c)
	}
	if neg {
		return "-" + b.String()
	}
	return b.String()
}
