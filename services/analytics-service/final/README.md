# Analytics Service - Cuối kỳ (FastAPI)

Analytics Service cung cấp API tổng hợp dòng tiền, phân tích danh mục, xu hướng/dự báo, phát hiện khoản chi bất thường và lưu báo cáo tháng. Phiên bản cuối kỳ triển khai bằng **FastAPI + Uvicorn**, chạy cổng `8084`, dùng **MySQL 8** làm database riêng.

## API

Mọi API nghiệp vụ được gọi qua API Gateway (`http://localhost:8080`) và cần JWT hợp lệ. Gateway chuyển danh tính người dùng qua `X-User-Id`; người dùng thường không thể đổi sang dữ liệu của tài khoản khác bằng query `user_id`. Chỉ tài khoản ADMIN mới được chọn `user_id` khác.

| Method | Endpoint | Chức năng |
|---|---|---|
| `GET` | `/health` | Health check |
| `GET` | `/api/analytics/summary?from_date=2026-01-01&to_date=2026-01-31` | Tổng thu, tổng chi, dòng tiền ròng, số giao dịch |
| `GET` | `/api/analytics/category-breakdown?from_date=2026-01-01&to_date=2026-01-31` | Chi tiêu theo danh mục và tỷ lệ phần trăm |
| `GET` | `/api/analytics/trends?months=6` | Thu/chi theo tháng, dự báo tháng tiếp theo và anomalies |
| `GET` | `/api/analytics/anomalies?from_date=2026-01-01&to_date=2026-01-31` | Khoản chi bất thường độc lập với trends |
| `POST` | `/api/analytics/aggregate` | Tổng hợp, lưu/cập nhật báo cáo tháng vào MySQL |
| `GET` | `/api/analytics/reports?year=2026&month=1` | Đọc báo cáo đã lưu; bỏ lọc để xem tối đa 100 báo cáo gần nhất |

Ngày dùng định dạng `YYYY-MM-DD`; `from_date` phải không muộn hơn `to_date`. Trả lỗi tham số theo dạng `400` với `error=VALIDATION_ERROR`. Khi upstream Transaction Service hoặc MySQL lỗi, API trả `502` và thông tin lỗi.

### Tạo báo cáo

`POST /api/analytics/aggregate` nhận khoảng thời gian và có thể nhận `transactions` trực tiếp. Nếu không có danh sách giao dịch, Analytics gọi Transaction Service.

```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-01-31"
}
```

Body demo độc lập với Transaction Service:

```json
{
  "user_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-01-31",
  "transactions": [
    {"id": 1, "amount": 20000000, "type": "INCOME", "category": "Lương", "transaction_date": "2026-01-05"},
    {"id": 2, "amount": 150000, "type": "EXPENSE", "category": "Ăn uống", "transaction_date": "2026-01-07"}
  ]
}
```

Unique key `(user_id, year, month)` đảm bảo chạy aggregate nhiều lần sẽ cập nhật cùng báo cáo tháng.

## Đồng bộ microservice

- Analytics chỉ lấy giao dịch qua `GET /api/transactions` của Transaction Service; không truy cập trực tiếp `transaction_db`.
- Gửi `user_id`, `from_date`, `to_date`, `limit`/`offset` và header `X-User-Id` khi gọi nội bộ. Phân trang theo từng lô 500 bản ghi.
- Lưu dữ liệu báo cáo trong database riêng `analytics_db`; schema ở `../db/init-analytics-db.sql` (trong image được chép vào `/app/db`).
- Gateway route prefix `/api/analytics` trỏ tới `analytics-service:8084` như các endpoint giữa kỳ.
- Tạo ngân sách và xem tiến độ ngân sách vẫn thuộc Transaction Service.

## Cấu hình

Compose dùng một file `.env` ở thư mục gốc dự án. Các biến của service:

```dotenv
ANALYTICS_SERVICE_PORT=8084
ANALYTICS_DB_HOST=analytics-db
ANALYTICS_DB_PORT=3306
ANALYTICS_DB_NAME=analytics_db
ANALYTICS_DB_USER=root
ANALYTICS_DB_PASSWORD=root_password
```

Container cũng nhận `TRANSACTION_SERVICE_URL=http://transaction-service:8082`. Không dùng SQLite hoặc MongoDB.

## Chạy

Từ thư mục gốc dự án:

```bash
docker compose -f docker-compose.final.yml up --build analytics-db transaction-service analytics-service
```

Hoặc khởi chạy toàn hệ thống:

```bash
docker compose -f docker-compose.final.yml up --build
```

Kiểm tra health: `http://localhost:8084/health`. FastAPI interactive docs: `http://localhost:8084/docs`; OpenAPI JSON: `http://localhost:8084/openapi.json`.

Để chạy riêng ngoài Docker, cài `final/requirements.txt`, đặt biến môi trường MySQL và `TRANSACTION_SERVICE_URL`, sau đó từ `services/analytics-service` chạy:

```bash
uvicorn final.src.main:app --host 0.0.0.0 --port 8084
```
