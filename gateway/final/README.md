# 🚀 API Gateway (Phiên bản Cuối kỳ - Express.js Framework)

- **Phụ trách:** Nhóm trưởng
- **Công nghệ:** Node.js 20 + **Express.js 4** + **`http-proxy-middleware`** + **`express-rate-limit`** + **`jsonwebtoken`** + **Swagger UI**
- **Port:** `8080`

## Tính năng nổi bật
1. **Dynamic Reverse Proxy** cho 6 Microservices Frameworks (Spring Boot, Laravel, ASP.NET Core, FastAPI, Go Gin, Express+Socket.io) kèm WebSocket upgrade (`/socket.io`).
2. **JWT Authentication Guard tập trung:** Chặn spoofing headers (`X-User-Id`, `X-User-Role`), giải mã JWT HS256 và inject identity headers xuống downstream services.
3. **Rate Limiting & Distributed Tracing:** Chống spam với `express-rate-limit` và gắn `X-Request-Id` xuyên suốt mọi request + structured JSON logging.
4. **Aggregated Health Check:** `GET /health/services` kiểm tra song song sức khỏe toàn bộ 6 microservices.
5. **Swagger UI tổng hợp:** Truy cập `/docs` để test toàn bộ API kèm thanh đăng nhập nhanh.
