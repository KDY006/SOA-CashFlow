# Analytics Service - Giữa kỳ (Python thuần)

Thành viên phụ trách: **Lê Khải Hoàn (khonahihi)**. API dùng `http.server`, `urllib` và `sqlite3` có sẵn trong Python, không dùng framework hoặc ORM. Port mặc định `8084`.

## API

- `GET /health`: health check.
- `GET /api/analytics/summary?user_id=1&from_date=2026-01-01&to_date=2026-01-31`: tổng thu, tổng chi, dòng tiền ròng.
- `GET /api/analytics/category-breakdown?user_id=1&from_date=2026-01-01&to_date=2026-01-31`: cơ cấu chi tiêu theo danh mục.
- `GET /api/analytics/trends?user_id=1&months=6`: lịch sử dòng tiền và dự báo tháng tiếp theo.
- `GET /api/analytics/anomalies?user_id=1&from_date=2026-01-01&to_date=2026-01-31`: các khoản chi bất thường độc lập với API xu hướng.
- `POST /api/analytics/aggregate`: tổng hợp báo cáo và upsert vào SQLite theo `user_id`, `year`, `month`.
- `GET /api/analytics/reports?user_id=1&year=2026&month=1`: đọc báo cáo đã lưu. Có thể bỏ `year` và/hoặc `month` để lấy tối đa 100 báo cáo gần nhất.

`POST /aggregate` nhận `user_id`, `from_date`, `to_date`; có thể truyền `transactions` để chạy độc lập với Transaction Service. Khi không truyền, service gọi `GET /api/transactions` tại Transaction Service.

## Database

Schema nằm ở `services/analytics-service/db/init-analytics-db.sql`. Service tạo file SQLite theo biến `ANALYTICS_DB_PATH`; mặc định là `db/analytics.sqlite3` trong thư mục Analytics Service. Khóa duy nhất `(user_id, year, month)` giúp một kỳ báo cáo được cập nhật thay vì tạo bản trùng.

Database khởi tạo từ SQL schema khi service khởi động. Docker Compose gắn volume `analytics_data` để dữ liệu vẫn còn sau khi container được tạo lại.

## Chạy local

```powershell
$env:TRANSACTION_SERVICE_URL = "http://localhost:8082"
python .\src\main.py
```

Nếu không truyền `transactions` khi gọi `/aggregate`, Transaction Service cần chạy. Dữ liệu SQLite được lưu tại `db/analytics.sqlite3`.

## Chạy Docker

Từ thư mục gốc dự án:

```bash
docker compose -f docker-compose.midterm.yml up --build analytics-service
```


