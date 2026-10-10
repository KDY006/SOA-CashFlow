# Notification Service (Node.js)

- **Phụ trách:** Nhóm trưởng
- **Công nghệ:** Node.js (Bản giữa kỳ: Node.js Native HTTP / Bản cuối kỳ: Express / NestJS + Socket.io)
- **Message Broker / Cache:** Redis (Pub/Sub)
- **Port phục vụ:** `8086`

## Các API & Sự kiện chính
- `POST /api/notifications/send` - Gửi thông báo đến người dùng cụ thể
- `GET /api/notifications/user/{userId}` - Lấy lịch sử thông báo
- `WS /ws/notifications` - Kênh WebSocket realtime gửi cảnh báo vượt ngân sách, biến động số dư tức thì

## Chạy Local
```bash
npm install
npm run dev
```
