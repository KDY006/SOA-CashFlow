# Integration Service (Go)

- **Phụ trách:** Thành viên 5
- **Công nghệ:** Go (Bản giữa kỳ: Go net/http Native / Bản cuối kỳ: Gin Web Framework)
- **Cơ sở dữ liệu:** MySQL (`integration_db` trên port 3306)
- **Port phục vụ:** `8085`

## Các API Endpoints chính
- `GET /api/integrations/rates` - Lấy tỷ giá ngoại tệ hiện tại (đồng bộ với ngân hàng)
- `POST /api/integrations/sync-rates` - Cập nhật tự động bảng tỷ giá mới nhất
- `POST /api/integrations/webhooks/banking` - Nhận webhook biến động số dư từ cổng ngân hàng / đối tác

## Chạy Local
```bash
go run ./src/main.go
```
