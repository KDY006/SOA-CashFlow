# Analytics Service - Giữa kỳ (Python thuần)

Analytics Service đọc các giao dịch từ Transaction Service, tính toán thống kê tài chính và lưu báo cáo tổng hợp vào MySQL.

- **Thành viên phụ trách:** Lê Khải Hoàn (`khonahihi`)
- **Ngôn ngữ:** Python
- **HTTP server:** `http.server` (thư viện chuẩn Python)
- **Gọi service khác:** `urllib` (thư viện chuẩn Python)
- **Database:** MySQL 8, kết nối qua PyMySQL
- **Port mặc định:** `8084`

> Service không dùng SQLite hay MongoDB. MySQL của Analytics là database riêng tên `analytics_db`.

## Các API

Mọi API trả dữ liệu JSON. Các endpoint phân tích yêu cầu biết người dùng cần xem bằng `user_id` trên query string hoặc header `X-User-Id`.

### Kiểm tra service

```http
GET /health
```

Trả trạng thái hoạt động của Analytics Service. Endpoint này không cần MySQL hay Transaction Service để trả lời.

### Tổng quan thu chi

```http
GET /api/analytics/summary?user_id=1&from_date=2026-01-01&to_date=2026-01-31
```

Trả số giao dịch, tổng thu, tổng chi và dòng tiền ròng trong khoảng ngày được chọn. Có thể bỏ ngày bắt đầu hoặc kết thúc để lấy dữ liệu không giới hạn ở phía đó.

### Xu hướng và dự báo

```http
GET /api/analytics/trends?user_id=1&months=6
```

Trả tổng thu, tổng chi và dòng tiền ròng theo từng tháng trong các tháng gần nhất. `months` nhận giá trị từ 1 đến 36, mặc định là 6. Phần dự báo dùng trung bình dòng tiền ròng tối đa ba tháng gần nhất.

### Chi tiêu theo danh mục

```http
GET /api/analytics/category-breakdown?user_id=1&from_date=2026-01-01&to_date=2026-01-31
```

Trả tổng chi và tỷ lệ phần trăm theo từng danh mục trong khoảng thời gian đã chọn.

### Phát hiện khoản chi bất thường

```http
GET /api/analytics/anomalies?user_id=1&from_date=2026-01-01&to_date=2026-01-31
```

Tìm các khoản chi cao bất thường so với những giao dịch cùng danh mục. Danh mục cần có ít nhất bốn khoản chi để phân tích.

### Tạo và lưu báo cáo tổng hợp

```http
POST /api/analytics/aggregate
Content-Type: application/json

{
  "user_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-01-31"
}
```

Nếu không gửi trường `transactions`, service gọi Transaction Service để lấy giao dịch. Có thể gửi trực tiếp dữ liệu để demo mà không cần Transaction Service:

```json
{
  "user_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-01-31",
  "transactions": [
    {"id": 101, "user_id": 1, "amount": 5000000, "type": "INCOME", "category": "Lương", "transaction_date": "2026-01-05"},
    {"id": 102, "user_id": 1, "amount": 250000, "type": "EXPENSE", "category": "Ăn uống", "transaction_date": "2026-01-08"}
  ]
}
```

Báo cáo được lưu trong bảng `monthly_reports`. Nếu đã có báo cáo cùng `user_id`, `year` và `month`, dữ liệu cũ được cập nhật thay vì tạo bản ghi trùng. API trả mã HTTP `201` khi lưu thành công.

### Đọc báo cáo đã lưu

```http
GET /api/analytics/reports?user_id=1&year=2026&month=1
```

Có thể bỏ `year` và/hoặc `month` để lấy tối đa 100 báo cáo gần nhất của người dùng.

## Luồng hoạt động

1. Client gọi API Analytics, thường thông qua API Gateway.
2. Analytics gọi `GET /api/transactions` của Transaction Service với `user_id` và khoảng ngày.
3. Analytics tính toán kết quả; với endpoint `aggregate`, báo cáo tháng được ghi vào MySQL `analytics_db`.
4. Client nhận kết quả JSON từ Analytics.

Các endpoint phân tích ngoài `/health` cần Transaction Service hoạt động, trừ khi gửi `transactions` trực tiếp tới `POST /aggregate`.

## MySQL và cấu hình

Schema tạo bảng nằm tại `db/init-analytics-db.sql`. Trong Docker Compose, service `analytics-db` tạo database `analytics_db`, chạy schema khi khởi tạo volume lần đầu và lưu dữ liệu vào volume riêng.

Analytics Service đọc các biến môi trường sau:

| Biến | Giá trị Docker mặc định | Ý nghĩa |
|---|---|---|
| `ANALYTICS_DB_HOST` | `analytics-db` | Tên host/service MySQL trên mạng Docker |
| `ANALYTICS_DB_PORT` | `3306` | Cổng MySQL |
| `ANALYTICS_DB_NAME` | `analytics_db` | Tên database |
| `ANALYTICS_DB_USER` | `root` | Tài khoản MySQL |
| `ANALYTICS_DB_PASSWORD` | `root_password` | Mật khẩu MySQL trong cấu hình Compose hiện tại |
| `TRANSACTION_SERVICE_URL` | `http://transaction-service:8082` | Địa chỉ Transaction Service |
| `PORT` | `8084` | Cổng HTTP của Analytics Service |

Nếu chạy Python trực tiếp trên máy, đặt `ANALYTICS_DB_HOST=localhost` và đảm bảo MySQL đang chạy, database đã được tạo và schema đã được nạp. Giá trị `analytics-db` chỉ phân giải được bên trong mạng Docker Compose.

## Chạy bằng Docker Compose

Chạy từ thư mục gốc của project:

```bash
docker compose -f docker-compose.midterm.yml up --build analytics-db transaction-service analytics-service
```

API sẽ nghe tại `http://localhost:8084` (hoặc cổng host đặt bởi `ANALYTICS_SERVICE_PORT`). Kiểm tra nhanh:

```bash
curl http://localhost:8084/health
```

Dữ liệu MySQL được giữ trong volume riêng kể cả khi container bị tạo lại. Xóa volume sẽ xóa dữ liệu database.

## Chạy trực tiếp bằng Python

Cần Python 3.10 trở lên và MySQL có database/schema sẵn sàng. Từ thư mục `services/analytics-service/midterm`:

```powershell
python -m pip install -r requirements.txt
$env:ANALYTICS_DB_HOST = "localhost"
$env:ANALYTICS_DB_PORT = "3306"
$env:ANALYTICS_DB_NAME = "analytics_db"
$env:ANALYTICS_DB_USER = "root"
$env:ANALYTICS_DB_PASSWORD = "root_password"
$env:TRANSACTION_SERVICE_URL = "http://localhost:8082"
python .\src\main.py
```

Để xem các API phân tích, Transaction Service phải chạy tại URL đã cấu hình. Riêng `POST /aggregate` có thể demo độc lập nếu body có trường `transactions`.

## Một số lỗi thường gặp

- **Không kết nối được MySQL:** kiểm tra MySQL đã chạy chưa, host/port/user/password có đúng không. Trong Docker, host phải là `analytics-db`; chạy local thường dùng `localhost`.
- **Không kết nối được Transaction Service:** kiểm tra `TRANSACTION_SERVICE_URL`, trạng thái service và định dạng endpoint `/api/transactions`.
- **`user_id` không hợp lệ:** truyền số nguyên dương qua query parameter `user_id` hoặc header `X-User-Id`.
- **Không thấy dữ liệu:** kiểm tra Transaction Service có giao dịch của người dùng và khoảng ngày yêu cầu có chứa giao dịch đó không.
