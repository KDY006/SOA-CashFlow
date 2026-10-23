# Analytics Service - Giữa kỳ

Analytics Service dùng Python Native để nhận yêu cầu qua HTTP, gọi Transaction Service lấy giao dịch và lưu báo cáo tổng hợp vào MySQL 8 (`analytics_db`). **PyMySQL là thư viện Python kết nối tới MySQL**, không phải database riêng.

## API hiện có

- `GET /health` - kiểm tra trạng thái service.
- `GET /api/analytics/summary?from_date=2026-01-01&to_date=2026-01-31` - tổng thu, tổng chi, dòng tiền ròng và số giao dịch.
- `GET /api/analytics/category-breakdown?from_date=2026-01-01&to_date=2026-01-31` - tổng chi theo danh mục và tỷ lệ phần trăm.
- `GET /api/analytics/trends?months=6` - xu hướng thu chi theo tháng và dự báo dòng tiền tháng tiếp theo.
- `GET /api/analytics/anomalies?from_date=2026-01-01&to_date=2026-01-31` - phát hiện khoản chi bất thường.
- `POST /api/analytics/aggregate` - lấy giao dịch, tổng hợp báo cáo tháng và lưu/cập nhật vào MySQL. Có thể gửi `transactions` trực tiếp trong JSON body để demo.
- `GET /api/analytics/reports?year=2026&month=1` - đọc báo cáo đã lưu; bỏ bộ lọc để lấy tối đa 100 báo cáo gần nhất.

Khi request đi qua Gateway, `X-User-Id` được lấy từ JWT. Chỉ ADMIN mới có thể chọn `user_id` khác. Nếu gọi service trực tiếp để demo, truyền `user_id` trên query hoặc body.

## Liên kết với các service khác

Analytics gọi `GET /api/transactions` tại Transaction Service, truyền `user_id`, khoảng ngày, `limit`/`offset` và `X-User-Id`. Phân trang được đọc theo từng đợt để không bỏ sót giao dịch khi người dùng có nhiều dữ liệu. Gateway chuyển tiếp `/api/analytics/*` tới Analytics Service.

Quản lý ngân sách thuộc Transaction Service (`/api/budgets` và `/api/budgets/progress`), nên Analytics không tạo API CRUD ngân sách trùng lặp.

## Database và cấu hình

Bảng `monthly_reports` được tạo bởi `db/init-analytics-db.sql`. Compose chạy service `analytics-db` với image `mysql:8.0` và volume riêng để dữ liệu tồn tại sau khi tạo lại container. Các biến nằm ở `.env` gốc:

- `ANALYTICS_DB_HOST=analytics-db`
- `ANALYTICS_DB_PORT=3306`
- `ANALYTICS_DB_NAME=analytics_db`
- `ANALYTICS_DB_USER=root`
- `ANALYTICS_DB_PASSWORD=root_password`

Không dùng SQLite hoặc MongoDB. Mỗi microservice giữ database riêng; Analytics chỉ lấy giao dịch qua HTTP API, không đọc trực tiếp `transaction_db`.

## Chạy toàn bộ backend giữa kỳ

Từ thư mục gốc project:

```bash
docker compose -f docker-compose.midterm.yml up --build
```

Sau khi các container khởi động, kiểm tra `http://localhost:8084/health`. Khi chạy trong Compose, hostname `analytics-db` và `transaction-service` được phân giải nội bộ.

## Chạy riêng Analytics và dependencies

```bash
docker compose -f docker-compose.midterm.yml up --build analytics-db transaction-service analytics-service
```

Docker Compose chỉ dùng một `.env` ở thư mục gốc; file này chứa các biến theo prefix của từng service, ví dụ `ANALYTICS_DB_*`.
