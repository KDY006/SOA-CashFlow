# Payment Service (Hoan)

Service backend cho quy trình đóng học phí. Luồng dễ nhớ: **tạo giao dịch → nhập OTP → khóa tài khoản và khoản nợ → trừ tiền → gạch nợ → lưu kết quả**.

## Chạy local

Yêu cầu Python 3.11+, PostgreSQL (hoặc SQLite mặc định để học/thử), Redis và ba service phụ trợ User, Tuition, OTP.

```powershell
cd payment-service
py -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
$env:DATABASE_URL='postgresql+psycopg://user:password@localhost:5432/payment_db'
$env:REDIS_URL='redis://localhost:6379/0'
$env:USER_SERVICE_URL='http://localhost:8001'
$env:TUITION_SERVICE_URL='http://localhost:8002'
$env:OTP_SERVICE_URL='http://localhost:8003'
uvicorn main:app --reload --port 8004
```

Mở `http://localhost:8004/docs` để xem và gọi API. SQLite mặc định sẽ tạo file `payment.db` trong thư mục chạy lệnh. Driver PostgreSQL `psycopg[binary]` đã nằm trong `requirements.txt`.

## API của Payment

- `POST /api/v1/payments`: tạo giao dịch và yêu cầu OTP. Body: `user_id`, `tuition_record_id`, `idempotency_key`.
- `POST /api/v1/payments/{transaction_id}/confirm`: xác nhận OTP và xử lý thu tiền.
- `GET /api/v1/payments/history?user_id=...`: lịch sử của một người dùng; bỏ `user_id` để lấy toàn hệ thống (dành cho Admin).
- `GET /health`: kiểm tra service.

Ví dụ tạo giao dịch:

```json
{
  "user_id": "user-001",
  "tuition_record_id": "tuition-2026-001",
  "idempotency_key": "web-checkout-unique-001"
}
```

## Giao kèo với các service nhóm

Các đường dẫn nội bộ và trường dữ liệu được mô tả trong [API contract](docs/api-contract.md). User Service phải trừ/cộng tiền nguyên tử và idempotent theo `reference_id`; Tuition Service phải chỉ gạch nợ một lần theo `transaction_id`. Redis lock giúp các Payment instance phối hợp, nhưng atomic update tại User/Tuition vẫn là lớp bảo vệ cuối cùng.

> Ghi chú tích hợp: hợp đồng URL hiện là giả định để Payment chạy độc lập. Nhóm cần khớp tên đường dẫn và JSON với API thật của Đạt, Quý và Phúc trước khi chạy toàn hệ thống.

> Bảo mật: API Admin xem toàn hệ thống cần được giới hạn ở API Gateway hoặc bổ sung JWT role check trước khi demo với người dùng khác. Bộ khung hiện tập trung vào nghiệp vụ thanh toán và chưa tự xác thực token.
