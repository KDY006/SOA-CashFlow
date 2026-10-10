package main

import (
	"context"
	"database/sql"
	"errors"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"
)

type linkedAccount struct {
	ID         int64     `json:"id"`
	UserID     int64     `json:"user_id"`
	BankCode   string    `json:"bank_code"`
	AccountNo  string    `json:"account_no"`
	HolderName string    `json:"holder_name"`
	Balance    money     `json:"balance"`
	Version    int64     `json:"version"`
	CreatedAt  time.Time `json:"created_at"`
	UpdatedAt  time.Time `json:"updated_at"`
}

const accountColumns = "id, user_id, bank_code, account_no, holder_name, balance, version, created_at, updated_at"

func scanAccount(sc scanner) (linkedAccount, error) {
	var a linkedAccount
	err := sc.Scan(&a.ID, &a.UserID, &a.BankCode, &a.AccountNo, &a.HolderName, &a.Balance, &a.Version, &a.CreatedAt, &a.UpdatedAt)
	return a, err
}

func (s *Server) getAccount(ctx context.Context, id int64) (linkedAccount, error) {
	return scanAccount(s.db.QueryRowContext(ctx, "SELECT "+accountColumns+" FROM linked_accounts WHERE id = ?", id))
}

func (s *Server) accountOwner(ctx context.Context, accountID int64) (int64, error) {
	var uid int64
	err := s.db.QueryRowContext(ctx, "SELECT user_id FROM linked_accounts WHERE id = ?", accountID).Scan(&uid)
	return uid, err
}

// GET /api/integrations/accounts?user_id=1
func (s *Server) handleListAccounts(w http.ResponseWriter, r *http.Request) {
	query := "SELECT " + accountColumns + " FROM linked_accounts"
	var args []any
	if uid := userIDFrom(r); uid > 0 {
		query += " WHERE user_id = ?"
		args = append(args, uid)
	}
	query += " ORDER BY id"

	rows, err := s.db.QueryContext(r.Context(), query, args...)
	if err != nil {
		s.internalError(w, err)
		return
	}
	defer rows.Close()

	list := []linkedAccount{}
	for rows.Next() {
		a, err := scanAccount(rows)
		if err != nil {
			s.internalError(w, err)
			return
		}
		list = append(list, a)
	}
	if err := rows.Err(); err != nil {
		s.internalError(w, err)
		return
	}
	ok(w, http.StatusOK, "lấy danh sách tài khoản liên kết thành công", list)
}

// GET /api/integrations/accounts/{id}
func (s *Server) handleGetAccount(w http.ResponseWriter, r *http.Request) {
	id, valid := pathID(r)
	if !valid {
		fail(w, http.StatusBadRequest, "INVALID_ID", "id tài khoản không hợp lệ")
		return
	}
	a, err := s.getAccount(r.Context(), id)
	if errors.Is(err, sql.ErrNoRows) || (err == nil && forbiddenFor(r, a.UserID)) {
		fail(w, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(w, err)
		return
	}
	ok(w, http.StatusOK, "lấy thông tin tài khoản thành công", a)
}

var (
	bankCodeRe  = regexp.MustCompile(`^[A-Z0-9]{2,20}$`)
	accountNoRe = regexp.MustCompile(`^[0-9]{6,30}$`)
)

type createAccountRequest struct {
	UserID         int64   `json:"user_id"`
	BankCode       string  `json:"bank_code"`
	AccountNo      string  `json:"account_no"`
	HolderName     string  `json:"holder_name"`
	InitialBalance float64 `json:"initial_balance"`
}

// POST /api/integrations/accounts
func (s *Server) handleCreateAccount(w http.ResponseWriter, r *http.Request) {
	var req createAccountRequest
	if err := decodeJSON(r, &req); err != nil {
		fail(w, http.StatusBadRequest, "BAD_REQUEST", err.Error())
		return
	}
	// đi qua gateway thì luôn liên kết cho chính user đang đăng nhập, bỏ qua user_id trong body
	if uid := authUserID(r); uid > 0 {
		req.UserID = uid
	}
	req.BankCode = strings.ToUpper(strings.TrimSpace(req.BankCode))
	req.AccountNo = strings.TrimSpace(req.AccountNo)
	req.HolderName = strings.ToUpper(strings.TrimSpace(req.HolderName))

	switch {
	case req.UserID <= 0:
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "thiếu user_id")
		return
	case !bankCodeRe.MatchString(req.BankCode):
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "bank_code chỉ gồm chữ in hoa và số, 2-20 ký tự")
		return
	case !accountNoRe.MatchString(req.AccountNo):
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "account_no chỉ gồm số, 6-30 ký tự")
		return
	case req.HolderName == "" || len(req.HolderName) > 100:
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "holder_name không hợp lệ")
		return
	case req.InitialBalance < 0:
		fail(w, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "initial_balance không được âm")
		return
	}

	res, err := s.db.ExecContext(r.Context(),
		`INSERT INTO linked_accounts (user_id, bank_code, account_no, holder_name, balance) VALUES (?, ?, ?, ?, ?)`,
		req.UserID, req.BankCode, req.AccountNo, req.HolderName, moneyFromFloat(req.InitialBalance))
	if isDuplicateKey(err) {
		fail(w, http.StatusConflict, "ACCOUNT_EXISTS", "tài khoản này đã được liên kết")
		return
	}
	if err != nil {
		s.internalError(w, err)
		return
	}
	id, _ := res.LastInsertId()
	a, err := s.getAccount(r.Context(), id)
	if err != nil {
		s.internalError(w, err)
		return
	}
	ok(w, http.StatusCreated, "liên kết tài khoản thành công", a)
}

var validTxStatus = map[string]bool{"RECEIVED": true, "SYNCING": true, "SYNCED": true, "FAILED": true, "REJECTED": true}

// GET /api/integrations/accounts/{id}/transactions?status=SYNCED&limit=20
func (s *Server) handleAccountTransactions(w http.ResponseWriter, r *http.Request) {
	id, valid := pathID(r)
	if !valid {
		fail(w, http.StatusBadRequest, "INVALID_ID", "id tài khoản không hợp lệ")
		return
	}
	owner, err := s.accountOwner(r.Context(), id)
	if errors.Is(err, sql.ErrNoRows) || (err == nil && forbiddenFor(r, owner)) {
		fail(w, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(w, err)
		return
	}

	query := "SELECT " + bankTxColumns + " FROM bank_transactions WHERE account_id = ?"
	args := []any{id}
	if st := strings.ToUpper(r.URL.Query().Get("status")); st != "" {
		if !validTxStatus[st] {
			fail(w, http.StatusBadRequest, "INVALID_STATUS", "status không hợp lệ")
			return
		}
		query += " AND status = ?"
		args = append(args, st)
	}
	limit := 50
	if v, err := strconv.Atoi(r.URL.Query().Get("limit")); err == nil && v > 0 && v <= 200 {
		limit = v
	}
	query += " ORDER BY occurred_at DESC, id DESC LIMIT " + strconv.Itoa(limit)

	rows, err := s.db.QueryContext(r.Context(), query, args...)
	if err != nil {
		s.internalError(w, err)
		return
	}
	defer rows.Close()

	list := []bankTx{}
	for rows.Next() {
		t, err := scanBankTx(rows)
		if err != nil {
			s.internalError(w, err)
			return
		}
		list = append(list, t)
	}
	if err := rows.Err(); err != nil {
		s.internalError(w, err)
		return
	}
	ok(w, http.StatusOK, "lấy lịch sử biến động thành công", list)
}
