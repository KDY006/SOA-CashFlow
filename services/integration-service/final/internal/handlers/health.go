package handlers

import (
	"context"
	"net/http"
	"time"

	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"github.com/gin-gonic/gin"
)

func (s *Server) HandleHealth(c *gin.Context) {
	dbStatus := "UP"
	status := http.StatusOK

	if s.db == nil {
		dbStatus = "DOWN: not initialized"
		status = http.StatusServiceUnavailable
	} else {
		ctx, cancel := context.WithTimeout(c.Request.Context(), 2*time.Second)
		defer cancel()
		sqlDB, err := s.db.DB()
		if err != nil {
			dbStatus = "DOWN: " + err.Error()
			status = http.StatusServiceUnavailable
		} else if err := sqlDB.PingContext(ctx); err != nil {
			dbStatus = "DOWN: " + err.Error()
			status = http.StatusServiceUnavailable
		}
	}

	healthLabel := "UP"
	if status != http.StatusOK {
		healthLabel = "DEGRADED"
	}

	c.JSON(status, gin.H{
		"service":      "integration-service",
		"technology":   "Go Gin + GORM",
		"status":       healthLabel,
		"database":     dbStatus,
		"sync_queue":   s.syncer.Pending(),
		"active_locks": s.locker.Size(),
		"uptime":       time.Since(s.startedAt).Round(time.Second).String(),
		"version":      "2.0.0-final",
		"time":         time.Now().Format(time.RFC3339),
	})
}

func (s *Server) HandleStats(c *gin.Context) {
	ctx := c.Request.Context()
	var totalAccounts, totalWebhooks int64
	if err := s.db.WithContext(ctx).Model(&models.LinkedAccount{}).Count(&totalAccounts).Error; err != nil {
		s.internalError(c, err)
		return
	}
	if err := s.db.WithContext(ctx).Model(&models.ExternalWebhook{}).Count(&totalWebhooks).Error; err != nil {
		s.internalError(c, err)
		return
	}

	type statusRow struct {
		Status string
		Total  int64
	}
	var rows []statusRow
	if err := s.db.WithContext(ctx).Model(&models.BankTransaction{}).
		Select("status, COUNT(*) as total").Group("status").Scan(&rows).Error; err != nil {
		s.internalError(c, err)
		return
	}

	byStatus := map[string]int64{
		"RECEIVED": 0, "SYNCING": 0, "SYNCED": 0, "FAILED": 0, "REJECTED": 0,
	}
	var totalTx int64
	for _, r := range rows {
		byStatus[r.Status] = r.Total
		totalTx += r.Total
	}

	httpx.OK(c, http.StatusOK, "lấy thống kê integration-service thành công", gin.H{
		"linked_accounts":       totalAccounts,
		"external_webhooks":     totalWebhooks,
		"bank_transactions":     totalTx,
		"transactions_by_status": byStatus,
		"sync_queue":            s.syncer.Pending(),
		"active_locks":          s.locker.Size(),
	})
}
