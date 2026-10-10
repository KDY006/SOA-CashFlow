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
)

func main() {
	cfg := loadConfig()

	db, err := openDB(cfg)
	if err != nil {
		log.Fatalf("không kết nối được database: %v", err)
	}
	defer db.Close()

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	srv := newServer(cfg, db)
	srv.syncer.start(ctx)

	httpSrv := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           withMiddleware(srv.routes()),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       15 * time.Second,
		WriteTimeout:      2 * time.Minute, // /simulate với nhiều event có thể chạy lâu
		IdleTimeout:       60 * time.Second,
	}

	go func() {
		log.Printf("integration-service đang chạy tại :%s (db %s:%s/%s)", cfg.Port, cfg.DBHost, cfg.DBPort, cfg.DBName)
		if err := httpSrv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("lỗi http server: %v", err)
		}
	}()

	<-ctx.Done()
	log.Println("đang dừng integration-service...")

	shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := httpSrv.Shutdown(shutdownCtx); err != nil {
		log.Printf("dừng server bị lỗi: %v", err)
	}
}
