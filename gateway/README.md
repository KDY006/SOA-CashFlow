# API Gateway (Native Node.js - Zero-dependency)

- **Phụ trách:** Nhóm trưởng (Architecture, Gateway, DevOps & Notification Service)
- **Công nghệ:** Node.js thuần (Built-in Standard Modules: `node:http`, `node:crypto`, `node:url`)
- **Port phục vụ:** `8080`
- **Phiên bản:** Giữa kỳ (Midterm - Native)

---

## 🏗️ Kiến trúc & Tính năng cốt lõi

1. **Single Entry Point:** Điểm kết nối tập trung duy nhất của toàn bộ hệ thống CashFlow trên cổng `8080`.
2. **Streaming Reverse Proxy:** Chuyển tiếp request và response dạng stream hiệu năng cao bằng `req.pipe(proxyReq)` và `proxyRes.pipe(res)` mà không cần lưu đệm toàn bộ payload vào RAM.
3. **Native JWT Authentication Guard:**
   - Tự giải mã và kiểm tra chữ ký HMAC-SHA256 (`node:crypto`) với thuật toán so sánh hằng thời gian chống Timing Attack.
   - Các endpoint công khai: `/api/auth/login`, `/api/auth/register`, `/api/auth/refresh`, `/api/integrations/rates`, `/health`.
   - Các endpoint được bảo vệ: Bắt buộc có header `Authorization: Bearer <token>`.
   - Tự động trích xuất `userId` từ token và inject vào header `X-User-Id` chuyển tiếp xuống các service nội bộ (giải quyết triệt để nguy cơ IDOR).
4. **CORS & Preflight:** Xử lý tập trung phương thức `OPTIONS`, tự động cấp phát các header `Access-Control-Allow-*`.
5. **Rate Limiting:** Giới hạn tần suất 300 requests/phút/IP để bảo vệ các microservices nội bộ khỏi spam/DoS.

---

## 🧭 Bảng định tuyến (Routing Table)

| Đường dẫn (URL Prefix) | Dịch vụ đích (Target Service) | Ngôn ngữ backend | Cổng nội bộ |
| :--- | :--- | :--- | :--- |
| `/api/auth/*` | Auth Service | Java Native | `8081` |
| `/api/profile` | Auth Service | Java Native | `8081` |
| `/api/transactions/*` | Transaction Service | PHP Native | `8082` |
| `/api/budgets/*` | Transaction Service | PHP Native | `8082` |
| `/api/categories/*` | Transaction Service | PHP Native | `8082` |
| `/api/assets/*` | Asset Service | C# Native | `8083` |
| `/api/loans/*` | Asset Service | C# Native | `8083` |
| `/api/investments/*` | Asset Service | C# Native | `8083` |
| `/api/analytics/*` | Analytics Service | Python Native | `8084` |
| `/api/integrations/*` | Integration Service | Go Native | `8085` |
| `/api/notifications/*` | Notification Service | Node.js Native | `8086` |
| `/health`, `/` | API Gateway Health & Directory | Node.js Native | `8080` |

---

## 💻 Hướng dẫn chạy thử nghiệm

### 1. Chạy trực tiếp (Local)
```bash
cd gateway/midterm
node src/index.js
```
Hoặc từ thư mục gốc:
```bash
node gateway/midterm/src/index.js
```

### 2. Kiểm tra Health Check
```bash
curl http://localhost:8080/health
```
