# Analytics Service - Giữa kỳ (Python thuần)

Thành viên phụ trách: **Lê Khải Hoàn **. API dùng `http.server` và `urllib` của Python, không dùng FastAPI/Flask. Port mặc định `8084`.

## Chức năng

- `GET /health`: health check.
- `GET /api/analytics/summary?user_id=1&from_date=2026-01-01&to_date=2026-01-31`: tổng thu, tổng chi, dòng tiền ròng.
- `GET /api/analytics/category-breakdown?user_id=1&from_date=...&to_date=...`: cơ cấu chi phí và tỷ lệ theo danh mục.
- `GET /api/analytics/trends?user_id=1&months=6`: tổng hợp tháng, dự báo dòng tiền tháng tiếp theo bằng trung bình tối đa 3 tháng gần nhất và danh sách khoản chi bất thường.
- `POST /api/analytics/aggregate`: tổng hợp báo cáo, upsert vào MongoDB nếu `MONGO_URI` được cấu hình. Body nhận `user_id`, `from_date`, `to_date`; có thể truyền `transactions` để chạy báo cáo từ dữ liệu gửi trực tiếp.

Ví dụ body:

```json
{
  "user_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-01-31",
  "transactions": [
    {"id": 10, "amount": 120000, "type": "EXPENSE", "category": "Ăn uống", "transaction_date": "2026-01-12"},
    {"id": 11, "amount": 15000000, "type": "INCOME", "category": "Lương", "transaction_date": "2026-01-25"}
  ]
}
```

Response dùng JSON UTF-8, lỗi có mã HTTP phù hợp (`400`, `404`, `502`). Bộ phân tích chỉ tính `INCOME` và `EXPENSE`, bỏ qua `TRANSFER` khỏi dòng tiền. Phát hiện bất thường dùng median/MAD theo danh mục, cần tối thiểu 4 khoản chi để đánh dấu.

## Nguồn dữ liệu và MongoDB

Mặc định API truy vấn `GET {TRANSACTION_SERVICE_URL}/api/transactions?user_id=...&limit=1000` bằng `urllib`; mặc định URL là `http://transaction-service:8082`. Có thể gửi `X-User-Id` thay query parameter. `POST /aggregate` cũng nhận danh sách giao dịch trực tiếp để demo độc lập.

`MONGO_URI` là tùy chọn. Khi có URI, báo cáo được upsert vào `analytics_db.monthly_reports` theo khóa `(user_id, year, month)`; khi không cấu hình, API vẫn tính và trả kết quả nhưng không lưu. MongoDB URI chạy compose được khai báo trong `docker-compose.midterm.yml`.

## Chạy local

```powershell
$env:TRANSACTION_SERVICE_URL = "http://localhost:8082"
$env:MONGO_URI = "mongodb://mongo_admin:mongo_password@localhost:27017/analytics_db?authSource=admin"
python .\src\main.py
```

Hoặc chạy không Mongo bằng cách bỏ `MONGO_URI`. Python 3.11+; MongoDB driver được cài từ `requirements.txt`.

## Chạy Docker

Compose giữa kỳ cần build từ thư mục `services/analytics-service/midterm`. Container lắng nghe `8084` và kết nối Transaction/Mongo qua network nội bộ.
