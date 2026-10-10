package main

import (
	"context"
	"database/sql"
	"fmt"
	"log"
	"net/http"
	"time"
)

type Server struct {
	cfg       Config
	db        *sql.DB
	locker    *keyedLocker
	syncer    *syncer
	notifier  *notifier
	startedAt time.Time
}

func newServer(cfg Config, db *sql.DB) *Server {
	client := &http.Client{Timeout: cfg.HTTPTimeout}
	n := &notifier{url: cfg.NotificationURL, client: client}
	return &Server{
		cfg:       cfg,
		db:        db,
		locker:    newKeyedLocker(),
		syncer:    newSyncer(db, cfg, client, n),
		notifier:  n,
		startedAt: time.Now(),
	}
}

func (s *Server) routes() http.Handler {
	mux := http.NewServeMux()

	mux.HandleFunc("GET /{$}", s.handleHealth)
	mux.HandleFunc("GET /health", s.handleHealth)

	mux.HandleFunc("GET /api/integrations/rates", s.handleGetRates)
	mux.HandleFunc("POST /api/integrations/sync-rates", s.handleSyncRates)

	mux.HandleFunc("GET /api/integrations/accounts", s.handleListAccounts)
	mux.HandleFunc("POST /api/integrations/accounts", s.handleCreateAccount)
	mux.HandleFunc("GET /api/integrations/accounts/{id}", s.handleGetAccount)
	mux.HandleFunc("GET /api/integrations/accounts/{id}/transactions", s.handleAccountTransactions)

	mux.HandleFunc("POST /api/integrations/webhooks/banking", s.handleBankWebhook)
	mux.HandleFunc("GET /api/integrations/bank-transactions/{id}", s.handleGetBankTx)
	mux.HandleFunc("POST /api/integrations/bank-transactions/{id}/retry", s.handleRetrySync)

	mux.HandleFunc("POST /api/integrations/simulate", s.handleSimulate)

	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		// route "/" này hứng hết mọi request không khớp, kể cả trường hợp đúng đường dẫn nhưng sai method.
		// thử lại với method khác, nếu khớp thì trả 405 cho đúng thay vì 404
		for _, m := range []string{http.MethodGet, http.MethodPost} {
			if m == r.Method {
				continue
			}
			r2 := r.Clone(r.Context())
			r2.Method = m
			if _, pattern := mux.Handler(r2); pattern != "/" {
				w.Header().Set("Allow", m)
				fail(w, http.StatusMethodNotAllowed, "METHOD_NOT_ALLOWED", fmt.Sprintf("%s không hỗ trợ method %s", r.URL.Path, r.Method))
				return
			}
		}
		fail(w, http.StatusNotFound, "NOT_FOUND", fmt.Sprintf("không có đường dẫn %s %s trên integration-service", r.Method, r.URL.Path))
	})
	return mux
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()

	dbStatus := "UP"
	status := http.StatusOK
	if err := s.db.PingContext(ctx); err != nil {
		dbStatus = "DOWN: " + err.Error()
		status = http.StatusServiceUnavailable
	}
	writeJSON(w, status, map[string]any{
		"service":      "integration-service",
		"technology":   "Go net/http (native)",
		"status":       map[bool]string{true: "UP", false: "DEGRADED"}[status == http.StatusOK],
		"database":     dbStatus,
		"sync_queue":   s.syncer.pending(),
		"active_locks": s.locker.size(),
		"uptime":       time.Since(s.startedAt).Round(time.Second).String(),
		"version":      "1.0.0-midterm",
		"time":         time.Now().Format(time.RFC3339),
	})
}

func (s *Server) internalError(w http.ResponseWriter, err error) {
	log.Printf("lỗi: %v", err)
	fail(w, http.StatusInternalServerError, "INTERNAL_ERROR", "lỗi hệ thống, vui lòng thử lại")
}
