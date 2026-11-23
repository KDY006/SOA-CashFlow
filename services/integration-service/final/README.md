# Integration Service — Phiên bản Cuối kỳ (Go Gin + GORM)

- **Phụ trách:** Trần Hữu Long - 52400138 (Thành viên 5)
- **Framework:** [Gin Web Framework](https://github.com/gin-gonic/gin) (`v1.10.0`) + [GORM](https://gorm.io/) (`v1.25.12`) + MySQL Driver
- **Database:** MySQL 8.0 (`integration_db`)
- **Port:** `8085`

## Nâng cấp so với bản Giữa kỳ (Native -> Framework)

| Thành phần | Giữa kỳ (Native) | Cuối kỳ (Framework) |
|---|---|---|
| HTTP Router & Middleware | `net/http.ServeMux` + wrapper thủ công | `gin.Engine` (`gin.Logger`, `gin.Recovery`, `HandleMethodNotAllowed`, CORS, MaxBodySize) |
| Database & ORM | `database/sql` + SQL thuần | `GORM` (`gorm.io/gorm` + `gorm.io/driver/mysql`) |
| Concurrency & Locking | `keyedLocker` + `SELECT ... FOR UPDATE` SQL | `locker.KeyedLocker` + GORM `clause.Locking{Strength: "UPDATE"}` trong transaction `READ COMMITTED` |
| Upsert tỷ giá | `ON DUPLICATE KEY UPDATE` thủ công | GORM `clause.OnConflict` batch upsert |
| Kiến trúc thư mục | Phẳng trong `midterm/src/` | Chuẩn Go layout: `cmd/server/`, `internal/{config,database,models,middleware,handlers,services,pkg}` |

## Cấu trúc thư mục

```text
final/
├── Dockerfile
├── go.mod
├── cmd/server/main.go               # Khởi động Gin server + Graceful Shutdown
└── internal/
    ├── config/config.go             # Đọc biến môi trường (GIN_MODE, DB_*, ...)
    ├── database/mysql.go            # Kết nối GORM MySQL + retry + bắt lỗi 1062/1213/1205
    ├── models/models.go             # Struct GORM + kiểu Money (1/100 đồng)
    ├── middleware/cors.go           # CORS, giới hạn body 1MB, 404/405 JSON
    ├── pkg/
    │   ├── httpx/response.go        # Helper JSON, JWT header X-User-Id / X-User-Role
    │   └── locker/keyed_locker.go   # Mutex theo tài khoản ngân hàng
    ├── services/
    │   ├── notifier.go              # Gửi cảnh báo sang Notification Service
    │   └── syncer.go                # Worker pool + Sweeper đồng bộ sang Transaction Service
    └── handlers/
        ├── server.go                # Đăng ký route group /api/integrations
        ├── health.go                # GET /health, GET /api/integrations/stats
        ├── rates.go                 # GET /rates, POST /sync-rates (goroutine)
        ├── accounts.go              # CRUD linked_accounts + lịch sử biến động
        ├── webhook.go               # POST /webhooks/banking, GET /webhooks (đối soát), xóa lẻ/hàng loạt, retry
        ├── simulate.go              # POST /simulate (Safe / Unsafe concurrency benchmark)
        └── integration_test.go      # Unit test (go test ./...)
```

## Chạy và kiểm thử

```bash
# Chạy unit test & build kiểm tra
cd services/integration-service/final
go test ./...

# Chạy cùng hệ thống Final Term (từ thư mục gốc SOA)
docker compose -f docker-compose.final.yml up -d --build integration-service
```
