# Transaction & Budget Service (PHP Native)

- **Phụ trách:** Thành viên 2 (Transaction & Budget Service)
- **Công nghệ:** PHP 8.2 (Bản giữa kỳ: PHP Native / Bản cuối kỳ: Laravel)
- **Cơ sở dữ liệu:** MySQL (`transaction_db` trên port 3306)
- **Port phục vụ:** `8082`

---

## 🚀 Các API Endpoints chính

### 1. Quản lý Giao dịch (Transactions)
- `GET /api/transactions` - Lấy danh sách giao dịch (Hỗ trợ query: `user_id`, `type`, `category_id`, `from_date`, `to_date`, `limit`, `offset`)
- `POST /api/transactions` - Thêm mới một giao dịch thu/chi (Tự động kích hoạt kiểm tra cảnh báo hạn mức ngân sách nếu là `EXPENSE`)
- `GET /api/transactions/{id}` - Xem chi tiết một giao dịch
- `PUT /api/transactions/{id}` - Cập nhật thông tin giao dịch
- `DELETE /api/transactions/{id}` - Xóa giao dịch

### 2. Quản lý Ngân sách (Budgets)
- `GET /api/budgets` - Lấy danh sách ngân sách theo người dùng (`?user_id=...`)
- `GET /api/budgets/progress` - Lấy báo cáo tiến độ ngân sách (So sánh hạn mức vs số tiền thực tế đã chi tiêu, trả về trạng thái `SAFE`, `WARNING`, `EXCEEDED`)
- `POST /api/budgets` - Thiết lập ngân sách mới cho danh mục chi tiêu
- `GET /api/budgets/{id}` - Xem chi tiết thiết lập ngân sách
- `PUT /api/budgets/{id}` - Cập nhật ngân sách
- `DELETE /api/budgets/{id}` - Xóa ngân sách

### 3. Quản lý Danh mục (Categories)
- `GET /api/categories` - Lấy danh sách danh mục (Hỗ trợ lọc theo `?type=INCOME|EXPENSE`)
- `POST /api/categories` - Thêm mới danh mục

---

## 💻 Hướng dẫn Chạy Thử nghiệm

### Cách 1: Chạy trực tiếp bằng PHP Built-in Server (Local)
```bash
cd services/transaction-service
php -S 0.0.0.0:8082 -t src src/index.php
```

### Cách 2: Khởi chạy bằng Docker Compose (Giữa kỳ)
Tại thư mục gốc `SOA-CashFlow/`:
```bash
docker-compose -f docker-compose.midterm.yml up -d --build transaction-service transaction-db
```
Kiểm tra Health check:
```bash
curl http://localhost:8082/health
```
