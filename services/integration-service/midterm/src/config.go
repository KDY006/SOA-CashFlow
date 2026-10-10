package main

import (
	"os"
	"strconv"
	"time"
)

type Config struct {
	Port            string
	DBHost          string
	DBPort          string
	DBName          string
	DBUser          string
	DBPassword      string
	TransactionURL  string
	NotificationURL string
	WebhookSecret   string
	SyncWorkers     int
	LargeAmount     money
	HTTPTimeout     time.Duration
}

func loadConfig() Config {
	return Config{
		Port:            env("PORT", "8085"),
		DBHost:          env("DB_HOST", "localhost"),
		DBPort:          env("DB_PORT", "3306"),
		DBName:          env("DB_NAME", "integration_db"),
		DBUser:          env("DB_USER", "root"),
		DBPassword:      env("DB_PASSWORD", "root_password"),
		TransactionURL:  env("TRANSACTION_SERVICE_URL", "http://localhost:8082"),
		NotificationURL: env("NOTIFICATION_SERVICE_URL", "http://localhost:8086"),
		// để trống thì không kiểm tra chữ ký webhook (tiện khi demo bằng postman)
		WebhookSecret: os.Getenv("WEBHOOK_SECRET"),
		SyncWorkers:   envInt("SYNC_WORKERS", 4),
		LargeAmount:   money(envInt("LARGE_AMOUNT_ALERT", 10000000)) * 100,
		HTTPTimeout:   5 * time.Second,
	}
}

func env(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func envInt(key string, def int) int {
	v, err := strconv.Atoi(os.Getenv(key))
	if err != nil || v <= 0 {
		return def
	}
	return v
}
