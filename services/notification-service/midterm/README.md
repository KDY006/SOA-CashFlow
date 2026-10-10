# Notification Service (Native Node.js - Zero-dependency)

- **Phụ trách:** Nhóm trưởng (Architecture, Gateway, DevOps & Notification Service)
- **Công nghệ:** Node.js thuần (Built-in Standard Modules: `node:http`, `node:net`, `node:url`)
- **Realtime Channel:** Server-Sent Events (SSE) chuẩn W3C
- **Message Broker:** Redis Pub/Sub (Kênh `cashflow:notifications`)
- **Port phục vụ:** `8086`
- **Phiên bản:** Giữa kỳ (Midterm - Native)

---

## 🚀 Các API Endpoints & Kênh Realtime

| Method | Endpoint | Mô tả chức năng |
| :--- | :--- | :--- |
| `GET` | `/health` | Kiểm tra tình trạng hoạt động, trạng thái kết nối Redis và số lượng client SSE đang online |
| `GET` | `/api/notifications/stream?user_id=1` | **Kênh Realtime SSE**: Client kết nối và nhận cảnh báo trực tiếp (không cần polling) |
| `GET` | `/api/notifications/user/:userId` | Lấy danh sách lịch sử thông báo của người dùng (có phân trang & unread count) |
| `POST` | `/api/notifications/send` | Gửi thông báo mới (tự động phát qua SSE và xuất bản lên Redis Pub/Sub) |
| `PUT` | `/api/notifications/:id/read` | Đánh dấu một thông báo cụ thể là đã đọc |
| `PUT` | `/api/notifications/read-all` | Đánh dấu tất cả thông báo của người dùng là đã đọc |

---

## 📡 Ví dụ sử dụng

### 1. Kết nối kênh Realtime SSE từ trình duyệt / Frontend Web
```javascript
const eventSource = new EventSource('http://localhost:8080/api/notifications/stream');

eventSource.addEventListener('connected', (e) => {
  console.log('SSE Connected:', JSON.parse(e.data));
});

eventSource.addEventListener('notification', (e) => {
  const notif = JSON.parse(e.data);
  console.log('Nhận thông báo realtime:', notif.title, notif.message);
  // Hiển thị toast banner cảnh báo trên giao diện
});
```

### 2. Gửi thông báo qua cURL (hoặc từ các Service khác)
```bash
curl -X POST http://localhost:8080/api/notifications/send \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "user_id": 1,
    "type": "BUDGET_ALERT",
    "title": "Cảnh báo vượt ngân sách",
    "message": "Chi tiêu cho danh mục Ăn uống đã vượt hạn mức 15%"
  }'
```

---

## 💻 Chạy Thử nghiệm Local
```bash
node services/notification-service/midterm/src/index.js
```
