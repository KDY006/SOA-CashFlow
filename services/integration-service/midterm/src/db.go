package main

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"log"
	"time"

	"github.com/go-sql-driver/mysql"
)

type scanner interface {
	Scan(dest ...any) error
}

// dùng chung được cho cả *sql.DB và *sql.Tx
type querier interface {
	QueryRowContext(ctx context.Context, query string, args ...any) *sql.Row
	ExecContext(ctx context.Context, query string, args ...any) (sql.Result, error)
}

func openDB(cfg Config) (*sql.DB, error) {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4&loc=Local",
		cfg.DBUser, cfg.DBPassword, cfg.DBHost, cfg.DBPort, cfg.DBName)

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(30)
	db.SetMaxIdleConns(10)
	db.SetConnMaxLifetime(5 * time.Minute)

	// mysql trong docker lên chậm hơn service nên phải thử lại vài lần
	var pingErr error
	for i := 1; i <= 20; i++ {
		ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
		pingErr = db.PingContext(ctx)
		cancel()
		if pingErr == nil {
			return db, nil
		}
		log.Printf("chưa kết nối được mysql (lần %d): %v", i, pingErr)
		time.Sleep(2 * time.Second)
	}
	db.Close()
	return nil, pingErr
}

func mysqlErrNo(err error) uint16 {
	var me *mysql.MySQLError
	if errors.As(err, &me) {
		return me.Number
	}
	return 0
}

func isDuplicateKey(err error) bool {
	return mysqlErrNo(err) == 1062
}

// 1213 = deadlock, 1205 = chờ lock quá lâu -> chạy lại transaction là được
func isRetryable(err error) bool {
	n := mysqlErrNo(err)
	return n == 1213 || n == 1205
}
