package handlers

import (
	"context"
	"errors"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"cashflow/integration-service-final/internal/database"
	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
)

var (
	bankCodeRe  = regexp.MustCompile(`^[A-Z0-9]{2,20}$`)
	accountNoRe = regexp.MustCompile(`^[0-9]{6,30}$`)
	validStatus = map[string]bool{"RECEIVED": true, "SYNCING": true, "SYNCED": true, "FAILED": true, "REJECTED": true}
)

func (s *Server) getAccount(ctx context.Context, id int64) (models.LinkedAccount, error) {
	var a models.LinkedAccount
	err := s.db.WithContext(ctx).First(&a, id).Error
	return a, err
}

func (s *Server) accountOwner(ctx context.Context, accountID int64) (int64, error) {
	var a models.LinkedAccount
	err := s.db.WithContext(ctx).Select("user_id").First(&a, accountID).Error
	return a.UserID, err
}

func (s *Server) HandleListAccounts(c *gin.Context) {
	q := s.db.WithContext(c.Request.Context()).Model(&models.LinkedAccount{})
	if uid := httpx.UserIDFrom(c); uid > 0 {
		q = q.Where("user_id = ?", uid)
	}
	list := make([]models.LinkedAccount, 0)
	if err := q.Order("id ASC").Find(&list).Error; err != nil {
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusOK, "lấy danh sách tài khoản liên kết thành công", list)
}

func (s *Server) HandleGetAccount(c *gin.Context) {
	id, valid := httpx.ParsePathID(c)
	if !valid {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", "id tài khoản không hợp lệ")
		return
	}
	a, err := s.getAccount(c.Request.Context(), id)
	if errors.Is(err, gorm.ErrRecordNotFound) || (err == nil && httpx.ForbiddenFor(c, a.UserID)) {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusOK, "lấy thông tin tài khoản thành công", a)
}

func (s *Server) HandleDeleteAccount(c *gin.Context) {
	id, valid := httpx.ParsePathID(c)
	if !valid {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", "id tài khoản không hợp lệ")
		return
	}
	a, err := s.getAccount(c.Request.Context(), id)
	if errors.Is(err, gorm.ErrRecordNotFound) || (err == nil && httpx.ForbiddenFor(c, a.UserID)) {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(c, err)
		return
	}

	unlock := s.locker.Lock(a.BankCode + ":" + a.AccountNo)
	defer unlock()

	res := s.db.WithContext(c.Request.Context()).Delete(&models.LinkedAccount{}, id)
	if res.Error != nil {
		s.internalError(c, res.Error)
		return
	}
	if res.RowsAffected == 0 {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	httpx.OK(c, http.StatusOK, "huỷ liên kết tài khoản ngân hàng thành công", a)
}

type createAccountRequest struct {
	UserID         int64   `json:"user_id"`
	BankCode       string  `json:"bank_code"`
	AccountNo      string  `json:"account_no"`
	HolderName     string  `json:"holder_name"`
	InitialBalance float64 `json:"initial_balance"`
}

func (s *Server) HandleCreateAccount(c *gin.Context) {
	var req createAccountRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		httpx.Fail(c, http.StatusBadRequest, "BAD_REQUEST", "json không hợp lệ: "+err.Error())
		return
	}
	if uid := httpx.AuthUserID(c); uid > 0 && !(httpx.IsAdmin(c) && req.UserID > 0) {
		req.UserID = uid
	}
	req.BankCode = strings.ToUpper(strings.TrimSpace(req.BankCode))
	req.AccountNo = strings.TrimSpace(req.AccountNo)
	req.HolderName = strings.ToUpper(strings.TrimSpace(req.HolderName))

	switch {
	case req.UserID <= 0:
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "thiếu user_id")
		return
	case !bankCodeRe.MatchString(req.BankCode):
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "bank_code chỉ gồm chữ in hoa và số, 2-20 ký tự")
		return
	case !accountNoRe.MatchString(req.AccountNo):
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "account_no chỉ gồm số, 6-30 ký tự")
		return
	case req.HolderName == "" || len(req.HolderName) > 100:
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "holder_name không hợp lệ")
		return
	case req.InitialBalance < 0:
		httpx.Fail(c, http.StatusUnprocessableEntity, "VALIDATION_ERROR", "initial_balance không được âm")
		return
	}

	acc := models.LinkedAccount{
		UserID:     req.UserID,
		BankCode:   req.BankCode,
		AccountNo:  req.AccountNo,
		HolderName: req.HolderName,
		Balance:    models.MoneyFromFloat(req.InitialBalance),
	}
	if err := s.db.WithContext(c.Request.Context()).Create(&acc).Error; err != nil {
		if database.IsDuplicateKey(err) {
			httpx.Fail(c, http.StatusConflict, "ACCOUNT_EXISTS", "tài khoản này đã được liên kết")
			return
		}
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusCreated, "liên kết tài khoản thành công", acc)
}

func (s *Server) HandleAccountTransactions(c *gin.Context) {
	id, valid := httpx.ParsePathID(c)
	if !valid {
		httpx.Fail(c, http.StatusBadRequest, "INVALID_ID", "id tài khoản không hợp lệ")
		return
	}
	owner, err := s.accountOwner(c.Request.Context(), id)
	if errors.Is(err, gorm.ErrRecordNotFound) || (err == nil && httpx.ForbiddenFor(c, owner)) {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không tìm thấy tài khoản liên kết")
		return
	}
	if err != nil {
		s.internalError(c, err)
		return
	}

	q := s.db.WithContext(c.Request.Context()).Model(&models.BankTransaction{}).Where("account_id = ?", id)
	if st := strings.ToUpper(c.Query("status")); st != "" {
		if !validStatus[st] {
			httpx.Fail(c, http.StatusBadRequest, "INVALID_STATUS", "status không hợp lệ")
			return
		}
		q = q.Where("status = ?", st)
	}
	limit := 50
	if v, err := strconv.Atoi(c.Query("limit")); err == nil && v > 0 && v <= 200 {
		limit = v
	}

	list := make([]models.BankTransaction, 0)
	if err := q.Order("occurred_at DESC, id DESC").Limit(limit).Find(&list).Error; err != nil {
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusOK, "lấy lịch sử biến động thành công", list)
}
