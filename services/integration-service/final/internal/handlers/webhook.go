package handlers

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"cashflow/integration-service-final/internal/database"
	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
	"gorm.io/gorm/clause"
)

var (
	errAccountNotFound = errors.New("không tìm thấy tài khoản liên kết")
	errKeyConflict     = errors.New("idempotency key này đã dùng cho một giao dịch khác")
)

const maxAmount models.Money = 1_000_000_000_000 * 100

type WebhookRequest struct {
	AccountID   int64   `json:"account_id"`
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

type WebhookInput struct {
	Provider    string
	ExternalRef string
	BankCode    string
	AccountNo   string
	Direction   string
	Amount      models.Money
	Category    string
	Description string
	OccurredAt  time.Time
	Key         string
	Hash        string
}

func (r WebhookRequest) Normalize(headerKey string) (WebhookInput, error) {
	in := WebhookInput{
		Provider:    strings.ToUpper(strings.TrimSpace(r.Provider)),
		ExternalRef: strings.TrimSpace(r.ExternalRef),
		BankCode:    strings.ToUpper(strings.TrimSpace(r.BankCode)),
		AccountNo:   strings.TrimSpace(r.AccountNo),
		Direction:   strings.ToUpper(strings.TrimSpace(r.Direction)),
		Amount:      models.MoneyFromFloat(r.Amount),
		Category:    httpx.Truncate(strings.TrimSpace(r.Category), 100),
		Description: httpx.Truncate(strings.TrimSpace(r.Description), 255),
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

func ValidSignature(body []byte, signature, secret string) bool {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	expected := hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(expected), []byte(strings.ToLower(strings.TrimSpace(signature))))
}

type webhookResult struct {
	Tx        models.BankTransaction
	UserID    int64
	Duplicate bool
	Rejected  bool
}

func duplicateOf(t models.BankTransaction, in WebhookInput, userID int64) (webhookResult, error) {
	if t.RequestHash != in.Hash {
		return webhookResult{}, errKeyConflict
	}
	return webhookResult{Tx: t, UserID: userID, Duplicate: true, Rejected: t.Status == "REJECTED"}, nil
}

func findExisting(db *gorm.DB, in WebhookInput) (models.BankTransaction, error) {
	var t models.BankTransaction
	err := db.Where("idempotency_key = ? OR (provider = ? AND external_ref = ?)", in.Key, in.Provider, in.ExternalRef).
		First(&t).Error
	return t, err
}

func (s *Server) processWebhook(ctx context.Context, in WebhookInput) (webhookResult, error) {
	var lastErr error
	for attempt := 1; attempt <= 3; attempt++ {
		res, err := s.applyWebhook(ctx, in)
		if !database.IsRetryable(err) {
			return res, err
		}
		lastErr = err
		log.Printf("deadlock khi xử lý %s, thử lại lần %d", in.Key, attempt)
		time.Sleep(time.Duration(attempt*25) * time.Millisecond)
	}
	return webhookResult{}, lastErr
}

func (s *Server) applyWebhook(ctx context.Context, in WebhookInput) (webhookResult, error) {
	unlock := s.locker.Lock(in.BankCode + ":" + in.AccountNo)
	defer unlock()

	var result webhookResult
	err := s.db.WithContext(ctx).Transaction(func(tx *gorm.DB) error {
		var acc models.LinkedAccount
		if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).
			Where("bank_code = ? AND account_no = ?", in.BankCode, in.AccountNo).
			First(&acc).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				return errAccountNotFound
			}
			return err
		}

		existing, err := findExisting(tx, in)
		if err == nil {
			res, derr := duplicateOf(existing, in, acc.UserID)
			if derr != nil {
				return derr
			}
			result = res
			return nil
		}
		if !errors.Is(err, gorm.ErrRecordNotFound) {
			return err
		}

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
			if err := tx.Model(&models.LinkedAccount{}).Where("id = ?", acc.ID).
				Updates(map[string]any{
					"balance": newBalance,
					"version": gorm.Expr("version + 1"),
				}).Error; err != nil {
				return err
			}
		}

		newTx := models.BankTransaction{
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
		if err := tx.Create(&newTx).Error; err != nil {
			return err
		}

		result = webhookResult{Tx: newTx, UserID: acc.UserID, Rejected: status == "REJECTED"}
		return nil
	}, &sql.TxOptions{Isolation: sql.LevelReadCommitted})

	if database.IsDuplicateKey(err) {
		existing, ferr := findExisting(s.db.WithContext(ctx), in)
		if ferr != nil {
			return webhookResult{}, ferr
		}
		owner, _ := s.accountOwner(ctx, existing.AccountID)
		return duplicateOf(existing, in, owner)
	}
	return result, err
}

func (s *Server) HandleBankWebhook(c *gin.Context) {
	raw, err := io.ReadAll(io.LimitReader(c.Request.Body, httpx.MaxBodySize+1))
	if err != nil {
		httpx.Fail(c, http.StatusBadRequest, "BAD_REQUEST", "không đọc được body")
		return
	}
	if len(raw) > httpx.MaxBodySize {
		httpx.Fail(c, http.StatusRequestEntityTooLarge, "PAYLOAD_TOO_LARGE", "body vượt quá 1MB")
		return
	}

	if s.cfg.WebhookSecret != "" && !ValidSignature(raw, c.GetHeader("X-Signature"), s.cfg.WebhookSecret) {
		httpx.Fail(c, http.StatusUnauthorized, "INVALID_SIGNATURE", "chữ ký webhook không hợp lệ")
		return
	}

	var req WebhookRequest
	ct := strings.ToLower(c.GetHeader("Content-Type"))
	trimmedRaw := strings.TrimSpace(string(raw))
	if strings.HasPrefix(ct, "application/x-www-form-urlencoded") || (len(trimmedRaw) > 0 && !strings.HasPrefix(trimmedRaw, "{")) {
		vals, perr := url.ParseQuery(trimmedRaw)
		if perr != nil {
			httpx.Fail(c, http.StatusBadRequest, "BAD_REQUEST", "dữ liệu form không hợp lệ")
			return
		}
		if v, err := strconv.ParseInt(strings.TrimSpace(vals.Get("account_id")), 10, 64); err == nil {
			req.AccountID = v
		}
		req.Provider = vals.Get("provider")
		req.ExternalRef = vals.Get("external_ref")
		req.BankCode = vals.Get("bank_code")
		req.AccountNo = vals.Get("account_no")
		req.Direction = vals.Get("direction")
		if v, err := strconv.ParseFloat(strings.TrimSpace(vals.Get("amount")), 64); err == nil {
			req.Amount = v
		}
		req.Category = vals.Get("category")
		req.Description = vals.Get("description")
		req.OccurredAt = vals.Get("occurred_at")
	} else if len(trimmedRaw) > 0 {
		if err := json.Unmarshal(raw, &req); err != nil {
			httpx.Fail(c, http.StatusBadRequest, "BAD_REQUEST", "json không hợp lệ")
			return
		}
	}

	if req.AccountID <= 0 {
		if v, err := strconv.ParseInt(strings.TrimSpace(c.Query("account_id")), 10, 64); err == nil && v > 0 {
			req.AccountID = v
		}
	}
	if strings.TrimSpace(req.Direction) == "" && c.Query("direction") != "" {
		req.Direction = c.Query("direction")
	}
	if req.Amount == 0 && c.Query("amount") != "" {
		if v, err := strconv.ParseFloat(strings.TrimSpace(c.Query("amount")), 64); err == nil {
			req.Amount = v
		}
	}

	if strings.TrimSpace(req.AccountNo) == "" && s.db != nil {
		if req.AccountID > 0 {
			var acc models.LinkedAccount
			if err := s.db.WithContext(c.Request.Context()).Select("bank_code, account_no").First(&acc, req.AccountID).Error; err == nil {
				if strings.TrimSpace(req.BankCode) == "" {
					req.BankCode = acc.BankCode
				}
				req.AccountNo = acc.AccountNo
			} else if errors.Is(err, gorm.ErrRecordNotFound) {
				httpx.Fail(c, http.StatusNotFound, "ACCOUNT_NOT_FOUND", fmt.Sprintf("không tìm thấy tài khoản liên kết có account_id = %d", req.AccountID))
				return
			}
		} else {
			uid := httpx.AuthUserID(c)
			var acc models.LinkedAccount
			q := s.db.WithContext(c.Request.Context()).Select("bank_code, account_no")
			if uid > 0 {
				q = q.Where("user_id = ?", uid)
			}
			if err := q.Order("id ASC").First(&acc).Error; err == nil {
				if strings.TrimSpace(req.BankCode) == "" {
					req.BankCode = acc.BankCode
				}
				req.AccountNo = acc.AccountNo
			}
		}
	}

	if strings.TrimSpace(req.Provider) == "" && strings.TrimSpace(req.BankCode) != "" {
		req.Provider = req.BankCode
	}
	extRef := strings.TrimSpace(req.ExternalRef)
	if extRef == "" || strings.EqualFold(extRef, "AUTO") {
		req.ExternalRef = fmt.Sprintf("FT%d", time.Now().UnixNano()/1e5)
	}

	idemHeader := strings.TrimSpace(c.GetHeader("Idempotency-Key"))
	if strings.EqualFold(idemHeader, "AUTO") {
		idemHeader = ""
	}

	in, err := req.Normalize(idemHeader)
	if err != nil {
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", err.Error())
		return
	}

	logPayload := raw
	if !json.Valid(logPayload) {
		logPayload, _ = json.Marshal(req)
	}
	s.logWebhook(in.Provider, string(logPayload))

	res, err := s.processWebhook(c.Request.Context(), in)
	switch {
	case errors.Is(err, errAccountNotFound):
		httpx.Fail(c, http.StatusNotFound, "ACCOUNT_NOT_FOUND", err.Error())
		return
	case errors.Is(err, errKeyConflict):
		httpx.Fail(c, http.StatusConflict, "IDEMPOTENCY_CONFLICT", err.Error())
		return
	case err != nil:
		s.internalError(c, err)
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
	c.JSON(status, gin.H{
		"success": !res.Rejected,
		"message": msg,
		"data": gin.H{
			"duplicate":   res.Duplicate,
			"transaction": res.Tx,
		},
	})
}

func (s *Server) afterAccepted(in WebhookInput, res webhookResult) {
	if res.Rejected {
		s.notifier.Send(res.UserID, "Giao dịch bị từ chối",
			fmt.Sprintf("Giao dịch %s %s đ từ %s bị từ chối do số dư không đủ", in.ExternalRef, models.FormatVND(in.Amount), in.BankCode),
			"TRANSACTION_REJECTED", map[string]any{"bank_tx_id": res.Tx.ID, "external_ref": in.ExternalRef})
		return
	}

	s.syncer.Enqueue(res.Tx.ID)

	largeThreshold := models.Money(s.cfg.LargeAmountVND) * 100
	if in.Amount >= largeThreshold {
		sign := "+"
		if in.Direction == "DEBIT" {
			sign = "-"
		}
		s.notifier.Send(res.UserID, "Biến động số dư lớn",
			fmt.Sprintf("Tài khoản %s %s %s đ. Số dư: %s đ", in.BankCode, sign, models.FormatVND(in.Amount), models.FormatVND(res.Tx.BalanceAfter)),
			"LARGE_TRANSACTION", map[string]any{"bank_tx_id": res.Tx.ID, "external_ref": in.ExternalRef})
	}
}

func (s *Server) logWebhook(provider, payload string) {
	entry := models.ExternalWebhook{
		ProviderName: provider,
		EventType:    "BANK_TRANSACTION",
		Payload:      payload,
	}
	if err := s.db.Create(&entry).Error; err != nil {
		log.Printf("không lưu được log webhook: %v", err)
	}
}

func (s *Server) HandleListWebhookLogs(c *gin.Context) {
	q := s.db.WithContext(c.Request.Context()).Model(&models.ExternalWebhook{})
	if provider := strings.ToUpper(strings.TrimSpace(c.Query("provider"))); provider != "" {
		q = q.Where("provider_name = ?", provider)
	}
	limit := 50
	if v, err := strconv.Atoi(c.Query("limit")); err == nil && v > 0 && v <= 200 {
		limit = v
	}
	logs := make([]models.ExternalWebhook, 0)
	if err := q.Order("id DESC").Limit(limit).Find(&logs).Error; err != nil {
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusOK, "lấy nhật ký webhook đối soát thành công", logs)
}

func (s *Server) HandleGetBankTx(c *gin.Context) {
	id, valid := httpx.ParsePathID(c)
	if !valid {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", "id không hợp lệ")
		return
	}
	var t models.BankTransaction
	err := s.db.WithContext(c.Request.Context()).First(&t, id).Error
	if err == nil {
		var owner int64
		owner, err = s.accountOwner(c.Request.Context(), t.AccountID)
		if err == nil && httpx.ForbiddenFor(c, owner) {
			err = gorm.ErrRecordNotFound
		}
	}
	if errors.Is(err, gorm.ErrRecordNotFound) {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy giao dịch")
		return
	}
	if err != nil {
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusOK, "lấy chi tiết giao dịch thành công", t)
}

func (s *Server) HandleDeleteBankTx(c *gin.Context) {
	id, valid := httpx.ParsePathID(c)
	if !valid {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", "id không hợp lệ")
		return
	}
	var t models.BankTransaction
	err := s.db.WithContext(c.Request.Context()).First(&t, id).Error
	if err == nil {
		var owner int64
		owner, err = s.accountOwner(c.Request.Context(), t.AccountID)
		if err == nil && httpx.ForbiddenFor(c, owner) {
			err = gorm.ErrRecordNotFound
		}
	}
	if errors.Is(err, gorm.ErrRecordNotFound) {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy giao dịch biến động")
		return
	}
	if err != nil {
		s.internalError(c, err)
		return
	}

	res := s.db.WithContext(c.Request.Context()).Delete(&models.BankTransaction{}, id)
	if res.Error != nil {
		s.internalError(c, res.Error)
		return
	}
	if res.RowsAffected == 0 {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy giao dịch biến động")
		return
	}
	httpx.OK(c, http.StatusOK, "xoá lịch sử biến động thành công", t)
}

type batchDeleteBankTxRequest struct {
	IDs       []int64 `json:"ids"`
	AccountID int64   `json:"account_id"`
	Status    string  `json:"status"`
}

func (s *Server) HandleBatchDeleteBankTx(c *gin.Context) {
	var req batchDeleteBankTxRequest
	if raw, err := io.ReadAll(io.LimitReader(c.Request.Body, httpx.MaxBodySize)); err == nil && len(strings.TrimSpace(string(raw))) > 0 {
		if err := json.Unmarshal(raw, &req); err != nil {
			httpx.Fail(c, http.StatusBadRequest, "BAD_REQUEST", "json body không hợp lệ")
			return
		}
	}

	seen := map[int64]bool{}
	ids := make([]int64, 0)
	addID := func(v int64) {
		if v > 0 && !seen[v] && len(ids) < 500 {
			seen[v] = true
			ids = append(ids, v)
		}
	}
	for _, id := range req.IDs {
		addID(id)
	}
	for _, rawVal := range c.QueryArray("ids") {
		for _, part := range strings.Split(rawVal, ",") {
			part = strings.TrimSpace(part)
			if part == "" {
				continue
			}
			v, err := strconv.ParseInt(part, 10, 64)
			if err != nil || v <= 0 {
				httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", fmt.Sprintf("id '%s' không hợp lệ", part))
				return
			}
			addID(v)
		}
	}

	accountID := req.AccountID
	if qAcc := strings.TrimSpace(c.Query("account_id")); qAcc != "" {
		v, err := strconv.ParseInt(qAcc, 10, 64)
		if err != nil || v <= 0 {
			httpx.Fail(c, http.StatusBadRequest, "INVALID_ACCOUNT_ID", "account_id không hợp lệ")
			return
		}
		accountID = v
	}

	status := strings.ToUpper(strings.TrimSpace(req.Status))
	if qSt := strings.ToUpper(strings.TrimSpace(c.Query("status"))); qSt != "" {
		status = qSt
	}
	if status != "" && !validStatus[status] {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_STATUS", "status chỉ nhận RECEIVED, SYNCING, SYNCED, FAILED hoặc REJECTED")
		return
	}

	if len(ids) == 0 && accountID <= 0 {
		httpx.Fail(c, http.StatusBadRequest, "MISSING_FILTER", "vui lòng truyền danh sách ids (vd: ?ids=1,2,3) hoặc account_id cần xoá")
		return
	}

	if accountID > 0 {
		owner, err := s.accountOwner(c.Request.Context(), accountID)
		if errors.Is(err, gorm.ErrRecordNotFound) || (err == nil && httpx.ForbiddenFor(c, owner)) {
			httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
			return
		}
		if err != nil {
			s.internalError(c, err)
			return
		}
	}

	q := s.db.WithContext(c.Request.Context()).Table("bank_transactions")
	uid := httpx.AuthUserID(c)
	if uid > 0 && !httpx.IsAdmin(c) {
		q = q.Where("account_id IN (?)", s.db.Model(&models.LinkedAccount{}).Select("id").Where("user_id = ?", uid))
	}
	if accountID > 0 {
		q = q.Where("account_id = ?", accountID)
	}
	if status != "" {
		q = q.Where("status = ?", status)
	}
	if len(ids) > 0 {
		q = q.Where("id IN ?", ids)
	}

	res := q.Delete(&models.BankTransaction{})
	if res.Error != nil {
		s.internalError(c, res.Error)
		return
	}
	httpx.OK(c, http.StatusOK, fmt.Sprintf("đã xoá %d bản ghi lịch sử biến động số dư", res.RowsAffected), gin.H{
		"deleted_count": res.RowsAffected,
		"ids":           ids,
		"account_id":    accountID,
		"status":        status,
	})
}

func (s *Server) HandleRetrySync(c *gin.Context) {
	id, valid := httpx.ParsePathID(c)
	if !valid {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", "id không hợp lệ")
		return
	}
	if uid := httpx.AuthUserID(c); uid > 0 {
		var t models.BankTransaction
		err := s.db.WithContext(c.Request.Context()).Select("account_id").First(&t, id).Error
		if err == nil {
			var owner int64
			owner, err = s.accountOwner(c.Request.Context(), t.AccountID)
			if err == nil && httpx.ForbiddenFor(c, owner) {
				err = gorm.ErrRecordNotFound
			}
		}
		if errors.Is(err, gorm.ErrRecordNotFound) {
			httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy giao dịch")
			return
		}
		if err != nil {
			s.internalError(c, err)
			return
		}
	}

	res := s.db.WithContext(c.Request.Context()).
		Model(&models.BankTransaction{}).
		Where("id = ? AND status = ?", id, "FAILED").
		Updates(map[string]any{"sync_attempts": 0, "last_error": nil})
	if res.Error != nil {
		s.internalError(c, res.Error)
		return
	}
	if res.RowsAffected == 0 {
		httpx.Fail(c, http.StatusConflict, "NOT_RETRYABLE", "chỉ thử lại được giao dịch đang ở trạng thái FAILED")
		return
	}
	s.syncer.Enqueue(id)
	httpx.OK(c, http.StatusAccepted, "đã đưa giao dịch vào hàng đợi đồng bộ", gin.H{"id": id})
}
