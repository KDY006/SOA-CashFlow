# API Gateway

- **Phụ trách:** Nhóm trưởng
- **Công nghệ:** Node.js (Proxy Router / Reverse Proxy / Express / Kong)
- **Port phục vụ:** `8080`

## Vai trò & Nhiệm vụ
1. **Entry Point duy nhất:** Điểm truy cập tập trung duy nhất cho Client (Web / Mobile).
2. **Reverse Proxy & Routing:** Điều hướng các request tới đúng 6 microservices backend:
   - `/api/auth/*` -> `auth-service` (port 8081)
   - `/api/transactions/*` -> `transaction-service` (port 8082)
   - `/api/assets/*` -> `asset-service` (port 8083)
   - `/api/analytics/*` -> `analytics-service` (port 8084)
   - `/api/integrations/*` -> `integration-service` (port 8085)
   - `/api/notifications/*` -> `notification-service` (port 8086)
3. **Authentication & Authorization Guard:** Kiểm tra và xác thực JWT token trước khi chuyển tiếp request vào các internal services.
4. **Rate Limiting & CORS:** Bảo vệ hệ thống khỏi tấn công DoS và kiểm soát nguồn gốc request.
