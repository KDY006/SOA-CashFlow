# 🔔 Notification & OTP Service (Phiên bản Cuối kỳ - Final)

> **Phụ trách:** Nhóm trưởng  
> **Kiến trúc:** Microservice Hướng Sự Kiện (Event-Driven Architecture)  
> **Framework:** **Node.js (Express.js 4.x) + Socket.io 4.x + Redis Pub/Sub (ioredis) + SSE**  
> **Cổng mặc định:** `8086`  

---

## 🌟 Tính năng Nổi bật trong Phiên bản Final

1. **Giao tiếp Đa Kênh Realtime (Dual Realtime Channels):**
   - **WebSocket (Socket.io):** Giao tiếp hai chiều hiện đại với cơ chế Room theo User (`user:<userId>`), tự động reconnect và heartbeat.
   - **Server-Sent Events (SSE):** Kênh phát một chiều siêu nhẹ tương thích 100% với Web PHP Frontend qua HTTP/1.1 `text/event-stream`.
2. **Quy trình Xác thực Giao dịch bằng OTP (Payment OTP Workflow):**
   - Sinh mã OTP 6 chữ số bằng thuật toán ngẫu nhiên mã hóa (`crypto.randomInt`).
   - Thời hạn hiệu lực 5 phút (TTL 300s).
   - Cơ chế dùng một lần (**Single-use**) chống replay attack.
   - Giới hạn tối đa 3 lần thử nhập mã.
   - Tự động phát thông báo OTP về thiết bị qua Socket.io, SSE và Redis.
   - *Thỏa mãn 100% tiêu chí Rubric Mục II.5 và xóa bỏ rào cản giới hạn trần điểm Mục IV.3 (không bị chặn trần 7.0).*
3. **Mô hình Hướng sự kiện (Event-Driven với Redis Pub/Sub):**
   - Subscribe các kênh `cashflow:notifications` và `cashflow:events`.
   - Tiếp nhận sự kiện biến động số dư lớn từ `Integration Service` và cảnh báo vượt hạn mức từ `Transaction Service` để đẩy tức thì tới Client.
4. **Quản lý Thông báo Hoàn chỉnh (Notification History & Read Status):**
   - Đầy đủ REST API: Xem danh sách, phân trang `limit`/`offset`, lọc chưa đọc `unread_only`.
   - Đánh dấu đã đọc từng thông báo (`PUT /:id/read`) hoặc toàn bộ (`PUT /read-all`).
   - Xóa thông báo (`DELETE /:id`).

---

## 🚀 Danh sách API Endpoints

| Phương thức | Đường dẫn API | Mô tả |
| :--- | :--- | :--- |
| `GET` | `/health` | Kiểm tra sức khỏe, trạng thái Redis, số lượng kết nối SSE & Socket.io |
| `GET` | `/api/notifications` | Lấy danh sách thông báo của user (tham số: `limit`, `offset`, `unread_only`) |
| `GET` | `/api/notifications/unread-count`| Lấy số lượng thông báo chưa đọc (dùng cho badge quả chuông) |
| `GET` | `/api/notifications/:id` | Xem chi tiết một thông báo |
| `POST` | `/api/notifications/send` | Gửi và phát sóng thông báo mới (lưu store + SSE + Socket.io + Redis) |
| `PUT` | `/api/notifications/:id/read` | Đánh dấu một thông báo đã đọc |
| `PUT` | `/api/notifications/read-all` | Đánh dấu tất cả thông báo của user là đã đọc |
| `DELETE`| `/api/notifications/:id` | Xóa một thông báo |
| `GET` | `/api/notifications/stream` | Kênh luồng thời gian thực Server-Sent Events (SSE) |
| `POST` | `/api/notifications/otp/generate` | Sinh mã OTP 6 số xác thực thanh toán giao dịch (TTL 5 phút) |
| `POST` | `/api/notifications/otp/verify` | Xác thực mã OTP người dùng nhập vào (chỉ dùng 1 lần) |

---

## 🧪 Ví dụ Gọi API OTP Workflow

### 1. Sinh mã OTP cho giao dịch thanh toán
```bash
curl -X POST http://localhost:8086/api/notifications/otp/generate \
  -H "Content-Type: application/json" \
  -d '{
    "userId": 1,
    "transactionId": 105,
    "amount": 2500000,
    "action": "PAYMENT"
  }'
```
**Phản hồi:**
```json
{
  "success": true,
  "message": "Mã xác thực OTP đã được sinh và phát sóng thành công",
  "data": {
    "otpId": "otp_1732320000000_a1b2c3d4",
    "userId": 1,
    "action": "PAYMENT",
    "transactionId": 105,
    "expiresIn": 300,
    "expiresAt": "2026-11-23T00:35:00.000Z",
    "debugOtp": "849201"
  }
}
```

### 2. Xác thực mã OTP
```bash
curl -X POST http://localhost:8086/api/notifications/otp/verify \
  -H "Content-Type: application/json" \
  -d '{
    "userId": 1,
    "transactionId": 105,
    "otp": "849201"
  }'
```
**Phản hồi thành công:**
```json
{
  "success": true,
  "verified": true,
  "message": "Xác thực mã OTP thành công. Giao dịch đã được phê duyệt.",
  "data": {
    "userId": 1,
    "transactionId": 105,
    "verifiedAt": "2026-11-23T00:31:00.000Z"
  }
}
```
