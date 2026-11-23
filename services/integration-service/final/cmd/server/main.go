package main

import (
	"context"
	"errors"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"cashflow/integration-service-final/internal/config"
	"cashflow/integration-service-final/internal/database"
	"cashflow/integration-service-final/internal/handlers"
	"cashflow/integration-service-final/internal/services"
)

func main() {
	cfg := config.Load()

	db, err := database.Open(cfg)
	if err != nil {
		log.Fatalf("không kết nối được MySQL qua GORM: %v", err)
	}
	if sqlDB, err := db.DB(); err == nil {
		defer sqlDB.Close()
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	httpClient := &http.Client{Timeout: cfg.HTTPTimeout}
	notifier := services.NewNotifier(cfg.NotificationURL, httpClient)
	syncer := services.NewSyncer(db, cfg, httpClient, notifier)
	syncer.Start(ctx)

	srv := handlers.NewServer(cfg, db, syncer, notifier)
	router := srv.SetupRouter()

	httpSrv := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           router,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       15 * time.Second,
		WriteTimeout:      2 * time.Minute,
		IdleTimeout:       60 * time.Second,
	}

	go func() {
		log.Printf("integration-service (Go Gin + GORM) đang chạy tại :%s (db %s:%s/%s)",
			cfg.Port, cfg.DBHost, cfg.DBPort, cfg.DBName)
		if err := httpSrv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("lỗi http server: %v", err)
		}
	}()

	<-ctx.Done()
	log.Println("đang dừng integration-service (final)...")

	shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := httpSrv.Shutdown(shutdownCtx); err != nil {
		log.Printf("dừng server bị lỗi: %v", err)
	}
	syncer.Wait()
	log.Println("đã hoàn tất dọn dẹp worker pool.")
}
