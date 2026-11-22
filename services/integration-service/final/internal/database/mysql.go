package database

import (
	"context"
	"errors"
	"fmt"
	"log"
	"time"

	"cashflow/integration-service-final/internal/config"
	"github.com/go-sql-driver/mysql"
	gormmysql "gorm.io/driver/mysql"
	"gorm.io/gorm"
	"gorm.io/gorm/logger"
)

func Open(cfg config.Config) (*gorm.DB, error) {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4&loc=Local",
		cfg.DBUser, cfg.DBPassword, cfg.DBHost, cfg.DBPort, cfg.DBName)

	gormCfg := &gorm.Config{
		Logger: logger.Default.LogMode(logger.Warn),
	}

	var db *gorm.DB
	var err error
	for i := 1; i <= 20; i++ {
		db, err = gorm.Open(gormmysql.Open(dsn), gormCfg)
		if err == nil {
			sqlDB, serr := db.DB()
			if serr == nil {
				sqlDB.SetMaxOpenConns(30)
				sqlDB.SetMaxIdleConns(10)
				sqlDB.SetConnMaxLifetime(5 * time.Minute)
				ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
				perr := sqlDB.PingContext(ctx)
				cancel()
				if perr == nil {
					return db, nil
				}
				err = perr
			} else {
				err = serr
			}
		}
		log.Printf("chưa kết nối được MySQL qua GORM (lần %d): %v", i, err)
		time.Sleep(2 * time.Second)
	}
	return nil, err
}

func MySQLErrNo(err error) uint16 {
	var me *mysql.MySQLError
	if errors.As(err, &me) {
		return me.Number
	}
	return 0
}

func IsDuplicateKey(err error) bool {
	return MySQLErrNo(err) == 1062
}

// 1213 = deadlock, 1205 = lock wait timeout -> retry transaction
func IsRetryable(err error) bool {
	n := MySQLErrNo(err)
	return n == 1213 || n == 1205
}
