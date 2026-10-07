# Transaction Service (PHP)

- **Phụ trách:** Thành viên 2
- **Công nghệ:** PHP (Bản giữa kỳ: PHP Native / Bản cuối kỳ: Laravel)
- **Cơ sở dữ liệu:** MySQL (`transaction_db` trên port 3306)
- **Port phục vụ:** `8082`

## Các API Endpoints chính
- `GET /api/transactions` - Lấy danh sách giao dịch thu/chi của người dùng
- `POST /api/transactions` - Thêm mới một giao dịch thu/chi
- `GET /api/transactions/{id}` - Chi tiết giao dịch
- `PUT /api/transactions/{id}` - Cập nhật giao dịch
- `DELETE /api/transactions/{id}` - Xóa giao dịch

## Chạy Local
```bash
php -S 0.0.0.0:8082 -t src
```
