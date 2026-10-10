# BÁO CÁO KIẾN TRÚC, GATEWAY, DEVOPS & NOTIFICATION SERVICE
**Đồ án:** Kiến trúc Hướng Dịch vụ (SOA) - Quản trị Dòng tiền và Tài chính (`SOA-CashFlow`)  
**Phụ trách:** Nhóm trưởng (Architecture, Gateway, DevOps & Notification Service)  
**Phiên bản:** Báo cáo Giữa kỳ (Midterm - Native / Code thuần)

---

## 1. Tổng quan Kiến trúc Hệ thống (SOA Architecture)

Hệ thống **SOA-CashFlow** được thiết kế theo kiến trúc Microservices phân tán gồm **6 microservices chuyên biệt** viết bằng các ngôn ngữ khác nhau, được điều phối bởi **API Gateway** tập trung và lưu trữ trên cụm cơ sở dữ liệu đa dạng (Polyglot Persistence):

```
                        +---------------------------------------+
                        |        Client (Web / Mobile / App)     |
                        +---------------------------------------+
                                            |
                                 HTTP Request (Port 8080)
                                            v
                        +---------------------------------------+
                        |       API GATEWAY (Node.js Native)    |
                        |   - Reverse Proxy & Stream Piping     |
                        |   - JWT Auth Guard (HMAC-SHA256)      |
                        |   - CORS & In-memory Rate Limiting    |
                        +---------------------------------------+
                                            |
         +------------------+---------------+------------------+------------------+
         | (8081)           | (8082)        | (8083)           | (8084)           | (8085)           | (8086)
         v                  v               v                  v                  v                  v
+----------------+  +----------------+  +----------------+  +----------------+  +----------------+  +----------------+
|  Auth Service  |  | Transaction Svc|  |  Asset Service |  | Analytics Svc  |  | Integration Svc|  | Notification   |
| (Java Native)  |  |  (PHP Native)  |  |  (C# Native)   |  | (Python Native)|  |  (Go Native)   |  | (Node.js SSE)  |
+----------------+  +----------------+  +----------------+  +----------------+  +----------------+  +----------------+
         |                  |                   |                  |                  |                  ^
         v                  v                   v                  v                  v                  | Pub/Sub
+----------------+  +----------------+  +----------------+  +----------------+  +----------------+  +----------------+
|  auth_db       |  | transaction_db |  |  asset_db      |  | analytics_db   |  | integration_db |  |  Redis 7       |
| (MySQL 8.0)    |  | (MySQL 8.0)    |  | (MySQL 8.0)    |  | (MongoDB 6.0)  |  | (MySQL 8.0)    |  | (Channel Alert)|
+----------------+  +----------------+  +----------------+  +----------------+  +----------------+  +----------------+
```

---

## 2. API Gateway (Native Node.js - Zero-dependency)

- **Vị trí thư mục:** `gateway/midterm/`
- **Cổng phục vụ:** `8080`
- **Công nghệ:** Node.js thuần (Built-in `node:http`, `node:crypto`, `node:url`)

### Các tính năng đã hoàn thiện:
1. **Streaming Reverse Proxy Engine (`src/proxy.js`):**
   - Sử dụng cơ chế Stream Piping (`req.pipe(proxyReq)` và `proxyRes.pipe(res)`), không lưu đệm dữ liệu vào RAM giúp đạt hiệu năng cao và độ trễ thấp.
   - Tự động trích xuất và inject headers danh tính: `X-User-Id`, `X-User-Name`, `X-User-Role` từ token xuống các service con (giải quyết triệt để nguy cơ IDOR).
2. **Native JWT Authentication Guard (`src/jwt.js`):**
   - Tự băm và so khớp chữ ký HMAC-SHA256 bằng thuật toán hằng thời gian (`crypto.timingSafeEqual`) chống Timing Attack.
   - Endpoint công khai (Public): `/api/auth/login`, `/api/auth/register`, `/api/auth/refresh`, `/api/integrations/rates`, `/health`.
   - Endpoint bảo vệ (Protected): Bắt buộc kiểm tra token hợp lệ trước khi proxy.
3. **CORS & Rate Limiting (`src/index.js`):**
   - Tự động phản hồi `204 No Content` cho mọi request `OPTIONS`.
   - Giới hạn 300 requests/phút/IP để bảo vệ các dịch vụ nội bộ khỏi DoS.

---

## 3. Notification Service (Native Node.js - Realtime SSE & Redis)

- **Vị trí thư mục:** `services/notification-service/midterm/`
- **Cổng phục vụ:** `8086`
- **Công nghệ:** Node.js thuần (`node:http`, `node:net`, `node:url`)

### Các tính năng đã hoàn thiện:
1. **Kênh Realtime Server-Sent Events (`src/sse.js`):**
   - Endpoint: `GET /api/notifications/stream?user_id=1`
   - Đẩy cảnh báo tức thời dạng event stream (`text/event-stream`), tự động duy trì kết nối bằng `keep-alive ping` mỗi 25 giây.
2. **Redis Pub/Sub Subscriber (`src/redisClient.js`):**
   - Lắng nghe channel `cashflow:notifications`.
   - Hỗ trợ cả thư viện `ioredis` lẫn kết nối **Native TCP Socket (`node:net`)** qua giao thức RESP (Zero-dependency fallback).
   - Tự động phân phối cảnh báo nhận từ Redis tới các client SSE đang online.
3. **Quản lý Lưu trữ Thông báo (`src/store.js`):**
   - Đầy đủ dữ liệu mẫu (Cảnh báo vượt ngân sách, chi tiêu bất thường từ Analytics, nhắc nợ từ Asset).
   - Hỗ trợ phân trang, lọc chưa đọc (`unreadOnly`) và đánh dấu đã đọc (`markAsRead`, `markAllAsRead`).

---

## 4. DevOps & Docker Orchestration

- **Tệp cấu hình chính:** `docker-compose.midterm.yml`
- **Tổng số Containers điều phối:** **13 containers**
  - **7 Application Containers:** `gateway` (8080), `auth-service` (8081), `transaction-service` (8082), `asset-service` (8083), `analytics-service` (8084), `integration-service` (8085), `notification-service` (8086).
  - **6 Database/Broker Containers:** `auth-db` (3307:3306), `transaction-db` (3306), `asset-db` (3306), `integration-db` (3306), `analytics-mongo` (27017), `redis` (6379).

### Các lỗi hệ thống đã được sửa chữa triệt để:
1. **Sửa Build Contexts:** Chuyển tất cả các context về đúng thư mục `midterm/` của từng service (`./services/<name>/midterm`).
2. **Sửa lỗi `LISTEN_HOST` của C# Asset Service:** Bổ sung `LISTEN_HOST=0.0.0.0` để container chấp nhận kết nối từ Gateway.
3. **Sửa lỗi phụ thuộc của Analytics Service:** Thêm `depends_on: [transaction-service]` và cấu hình biến `TRANSACTION_SERVICE_URL`.
4. **Đồng bộ hóa Database Schemas & Password Hashes:**
   - Cập nhật `services/asset-service/db/init-asset-db.sql` bổ sung đầy đủ các bảng `assets`, `loans`, `investments` khớp 100% với mã C#.
   - Cập nhật `services/auth-service/db/init-auth-db.sql` và `scripts/seed-data.sql` đồng bộ mã băm mật khẩu chuẩn **PBKDF2WithHmacSHA256** cho tài khoản `admin` và `member1` (mật khẩu: `password123`).

---

## 5. Hướng dẫn Khởi chạy & Kiểm thử Tích hợp (Integration Demo)

### Bước 1: Khởi động toàn bộ hệ thống
```bash
docker-compose -f docker-compose.midterm.yml up -d --build
```

### Bước 2: Kiểm tra trạng thái toàn bộ containers
```bash
docker-compose -f docker-compose.midterm.yml ps
```

### Bước 3: Kịch bản Kiểm thử End-to-End qua API Gateway (Port 8080)

#### 1. Kiểm tra Health Gateway
```bash
curl http://localhost:8080/health
```

#### 2. Đăng nhập lấy JWT Access Token
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "password123"}'
```
*Ghi nhận `accessToken` trả về từ JSON response.*

#### 3. Gọi các dịch vụ được bảo vệ bằng Token qua Gateway:

- **Lấy danh sách Giao dịch:**
  ```bash
  curl -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/transactions
  ```
- **Lấy báo cáo Tiến độ Ngân sách:**
  ```bash
  curl -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/budgets/progress
  ```
- **Lấy tổng tài sản ròng (Net Worth):**
  ```bash
  curl -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/assets/net-worth
  ```
- **Lấy tóm tắt Phân tích Dòng tiền:**
  ```bash
  curl -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/analytics/summary
  ```
- **Lấy danh sách Thông báo cảnh báo:**
  ```bash
  curl -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/notifications/user/1
  ```
- **Mở kết nối Realtime SSE:**
  ```bash
  curl -N -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/notifications/stream?user_id=1
  ```
