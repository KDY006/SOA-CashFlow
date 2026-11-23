package handlers

import (
	"net/http"
	"time"

	"cashflow/integration-service-final/internal/config"
	"cashflow/integration-service-final/internal/middleware"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"cashflow/integration-service-final/internal/pkg/locker"
	"cashflow/integration-service-final/internal/services"
	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
)

type Server struct {
	cfg       config.Config
	db        *gorm.DB
	locker    *locker.KeyedLocker
	syncer    *services.Syncer
	notifier  *services.Notifier
	startedAt time.Time
}

func NewServer(cfg config.Config, db *gorm.DB, syncer *services.Syncer, notifier *services.Notifier) *Server {
	return &Server{
		cfg:       cfg,
		db:        db,
		locker:    locker.New(),
		syncer:    syncer,
		notifier:  notifier,
		startedAt: time.Now(),
	}
}

func (s *Server) SetupRouter() *gin.Engine {
	if s.cfg.GinMode == gin.ReleaseMode {
		gin.SetMode(gin.ReleaseMode)
	}

	r := gin.New()
	r.HandleMethodNotAllowed = true
	r.Use(gin.Logger(), gin.Recovery(), middleware.CORS(), middleware.MaxBodySize(httpx.MaxBodySize))

	r.NoRoute(middleware.HandleNoRoute)
	r.NoMethod(middleware.HandleNoMethod)

	r.GET("/", s.HandleHealth)
	r.GET("/health", s.HandleHealth)

	api := r.Group("/api/integrations")
	{
		api.GET("/stats", s.HandleStats)
		api.GET("/rates", s.HandleGetRates)
		api.POST("/sync-rates", s.HandleSyncRates)

		api.GET("/accounts", s.HandleListAccounts)
		api.POST("/accounts", s.HandleCreateAccount)
		api.GET("/accounts/:id", s.HandleGetAccount)
		api.DELETE("/accounts/:id", s.HandleDeleteAccount)
		api.GET("/accounts/:id/transactions", s.HandleAccountTransactions)

		api.GET("/webhooks", s.HandleListWebhookLogs)
		api.POST("/webhooks/banking", s.HandleBankWebhook)
		api.DELETE("/bank-transactions", s.HandleBatchDeleteBankTx)
		api.GET("/bank-transactions/:id", s.HandleGetBankTx)
		api.DELETE("/bank-transactions/:id", s.HandleDeleteBankTx)
		api.POST("/bank-transactions/:id/retry", s.HandleRetrySync)

		api.POST("/simulate", s.HandleSimulate)
	}

	return r
}

func (s *Server) internalError(c *gin.Context, err error) {
	c.Error(err) //nolint:errcheck
	httpx.Fail(c, http.StatusInternalServerError, "INTERNAL_ERROR", "lỗi hệ thống, vui lòng thử lại")
}
