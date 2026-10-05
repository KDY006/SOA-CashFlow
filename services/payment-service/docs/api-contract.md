# API contract giữa Payment và các service

Payment dùng HTTP nội bộ. Các route này là phần cần thống nhất với thành viên sở hữu service tương ứng.

Mỗi OTP phải gắn với đúng `transaction_id`; OTP service cần xử lý xác nhận lặp một cách an toàn để request gửi lại sau lỗi mạng không tạo thanh toán lần hai.

| Service | Method + path | JSON tối thiểu | Ý nghĩa |
|---|---|---|---|
| User | `GET /internal/accounts/{user_id}` | `{ "balance": "5000000" }` | Đọc số dư mới nhất |
| User | `POST /internal/accounts/{user_id}/debit` | `{ "amount": "1500000", "reference_id": "<transaction_id>" }` | Trừ tiền nguyên tử; lặp `reference_id` không trừ lần hai |
| User | `POST /internal/accounts/{user_id}/credit` | `{ "amount": "1500000", "reference_id": "refund:<transaction_id>" }` | Hoàn tiền bù trừ, cũng idempotent |
| Tuition | `GET /internal/tuition-records/{record_id}` | `{ "amount": "1500000", "status": "UNPAID" }` | Đọc khoản nợ |
| Tuition | `POST /internal/tuition-records/{record_id}/pay` | `{ "transaction_id": "<transaction_id>" }` | Đổi trạng thái sang PAID đúng một lần |
| OTP | `POST /internal/otps` | `{ "transaction_id": "...", "user_id": "..." }` | Tạo và gửi OTP gắn với giao dịch |
| OTP | `POST /internal/otps/verify` | `{ "transaction_id": "...", "otp_code": "123456" }` | Trả `{ "valid": true }`; OTP phải hết hạn sau 5 phút, dùng một lần |

## Trạng thái giao dịch

- `PENDING`: mới tạo, chưa gửi OTP.
- `OTP_PENDING`: đang đợi OTP.
- `SUCCEEDED`: trừ tiền và gạch nợ thành công.
- `FAILED`: không thể hoàn tất hoặc đã bù trừ.

## Mã HTTP chính

- `201`: đã khởi tạo giao dịch.
- `200`: xác nhận thành công / lấy lịch sử thành công.
- `400`: OTP sai, hết hạn hoặc đã dùng.
- `404`: không tìm thấy giao dịch.
- `409`: đã thanh toán, thiếu tiền, trạng thái không hợp lệ, hoặc lock đang bận.
- `502`: service phụ trợ lỗi.

## Nhất quán giữa các service

Không có một transaction SQL chung cho nhiều database. Payment điều phối kiểu saga: trừ tiền trước, nếu Tuition từ chối gạch nợ thì gọi hoàn tiền. Hai thao tác debit/credit phải idempotent. Nếu hoàn tiền cũng lỗi do service mất kết nối thì cần hàng đợi/retry và bảng ghi nhận bù trừ trong bản triển khai thật; không được âm thầm bỏ qua lỗi đó.
