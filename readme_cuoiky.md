# 🚀 Hướng dẫn Chạy Dự án — Giai đoạn Cuối kỳ (Final: Full Frameworks)

> **Tài liệu này dành riêng cho phiên bản Cuối kỳ (`docker-compose.final.yml` / `docker-compose.yml` & thư mục `*/final/`)** — toàn bộ 6 Microservices và API Gateway được nâng cấp hoàn chỉnh lên **Frameworks chuẩn công nghiệp** cho từng ngôn ngữ, bổ sung **Healthcheck hạ tầng**, **Quy trình xác thực OTP**, **Socket.io + SSE + Redis Pub/Sub**, **Distributed Tracing (`X-Request-Id`)**, **Rate Limiting** và **Concurrency/Idempotency**.
>
> 👉 Bạn muốn chạy phiên bản **Giữa kỳ (100% Native Code)**? Xem **[readme_giuaky.md](readme_giuaky.md)**.  
> 👉 Quay về trang tổng quan dự án: **[README.md](README.md)**.

---

## 📋 Mục lục

1. [Tổng quan Nâng cấp Giữa kỳ → Cuối kỳ](#-1-tổng-quan-nâng-cấp-giữa-kỳ--cuối-kỳ)
2. [Biến Môi trường Quan trọng bản Cuối kỳ](#-2-biến-môi-trường-quan-trọng-bản-cuối-kỳ)
3. [Yêu cầu Môi trường](#-3-yêu-cầu-môi-trường)
4. [Cách 1: Chạy trọn bộ bằng Docker Compose Final (Khuyến nghị số 1)](#-4-cách-1-chạy-trọn-bộ-bằng-docker-compose-final-khuyến-nghị-số-1)
5. [Cách 2: Chạy chế độ Hybrid (DB/Redis trong Docker + Debug Local/IDE)](#-5-cách-2-chạy-chế-độ-hybrid-dbredis-trong-docker--debug-localide)
6. [Cách 3: Chạy thủ công từng Framework trên từng Terminal](#-6-cách-3-chạy-thủ-công-từng-framework-trên-từng-terminal)
7. [Khởi chạy Giao diện Frontend Web iBanking (Port 3000)](#-7-khởi-chạy-giao-diện-frontend-web-ibanking-port-3000)
8. [Hướng dẫn Kiểm thử & Demo Trọng tâm Cuối kỳ](#-8-hướng-dẫn-kiểm-thử--demo-trọng-tâm-cuối-kỳ)
9. [Xem, Giám sát & Reset Dữ liệu trong CSDL Final](#-9-xem-giám-sát--reset-dữ-liệu-trong-csdl-final)
10. [Xử lý Sự cố Thường gặp](#-10-xử-lý-sự-cố-thường-gặp)

---

## 🏗 1. Tổng quan Nâng cấp Giữa kỳ → Cuối kỳ

Bản Cuối kỳ triển khai **13 containers** độc lập trên mạng `cashflow-final-net`, sử dụng **6 Named Volumes** riêng biệt và **Healthcheck** đầy đủ cho toàn bộ 5 MySQL 8.0 + 1 Redis 7:

| STT | Thành phần | Giữa kỳ (Native) | Cuối kỳ (Framework) | Port Host | Container Service | Container CSDL / Broker | Database Name |
|:---:|:---|:---|:---|:---:|:---|:---|:---|
| **0** | **API Gateway** | Node.js `http` thuần | **Express 4** + `http-proxy-middleware` v3 + `express-rate-limit` + `jsonwebtoken` + `X-Request-Id` + Swagger UI | `8080` | `cashflow-gateway-final` | — | — |
| **1** | **Auth Service** | Java `HttpServer` + JDBC | **Spring Boot 3.5** + Spring Security + Spring Data JPA + PBKDF2 + SHA-256 Refresh Token | `8081` | `cashflow-auth-final` | `cashflow-auth-db-final` (Host `:3307`) | `auth_db` |
| **2** | **Transaction Service** | PHP PDO thuần | **Laravel 12** + Migrations + Auto-Seed + Budget Alert Push sang Notification | `8082` | `cashflow-transaction-final` | `cashflow-transaction-db-final` | `transaction_final_db` |
| **3** | **Asset Service** | C# `HttpListener` | **ASP.NET Core 8 Web API** + EF Core 8 (Pomelo) + Swagger + `asset_history` | `8083` | `cashflow-asset-final` | `cashflow-asset-db-final` | `asset_db` |
| **4** | **Analytics Service** | Python `http.server` | **FastAPI** + Uvicorn + Pydantic + Auto OpenAPI `/docs` | `8084` | `cashflow-analytics-final` | `cashflow-analytics-db-final` | `analytics_db` |
| **5** | **Integration Service** | Go `net/http` + `sql` | **Go Gin** + GORM + `KeyedLocker` + `SELECT FOR UPDATE` + `Idempotency-Key` | `8085` | `cashflow-integration-final` | `cashflow-integration-db-final` (Host `:3310`) | `integration_db` |
| **6** | **Notification & OTP** | Node.js SSE + Raw TCP | **Express 4** + **Socket.io 4** + **`ioredis`** + SSE + **OTP Workflow** (6 số, TTL 5p, single-use) | `8086` | `cashflow-notification-final` | `cashflow-redis-final` (Host `:6379`) | Redis Pub/Sub |
| **7** | **Frontend Web** | PHP 8.x Built-in Server | PHP 8.x Built-in Server (tương thích 100% qua Gateway `:8080`) | `3000` | Chạy trên máy Host | — | — |

### Tài khoản Demo có sẵn (từ `services/auth-service/db/init-auth-db.sql`)

| Username | Password | Vai trò (`role`) | Thuật toán Hash Password |
|:---|:---|:---:|:---|
| `admin` | `password123` | `ADMIN` | `PBKDF2WithHmacSHA256` (210.000 iterations + salt ngẫu nhiên) |
| `member1` | `password123` | `USER` | `PBKDF2WithHmacSHA256` (210.000 iterations + salt ngẫu nhiên) |

---

## 🔐 2. Biến Môi trường Quan trọng bản Cuối kỳ

Tất cả biến môi trường đã được gán **giá trị mặc định đồng bộ sẵn** trong `docker-compose.final.yml`, nên bạn có thể chạy ngay mà không cần cấu hình thêm. Nếu muốn tùy biến qua file `.env`, lưu ý các quy tắc bắt buộc sau:

| Biến môi trường | Giá trị mặc định trong Compose | Lưu ý quan trọng |
|:---|:---|:---|
| `JWT_SECRET` | `cashflow-final-production-jwt-secret-key-2026-tdtu` | Phải đồng nhất giữa **Gateway**, **Auth (Spring Boot)** và **Asset (ASP.NET Core)**, độ dài **≥ 32 bytes**. Profile `prod` của Spring Boot **từ chối** chuỗi dev cũ của giữa kỳ. |
| `DB_PASSWORD` | `root_password` | Dùng chung cho các container MySQL và service kết nối. |
| `DB_ALLOW_PUBLIC_KEY_RETRIEVAL` | `true` | Bắt buộc để JDBC Driver của Spring Boot kết nối tới MySQL 8.0 (`caching_sha2_password`). |
| `LARAVEL_APP_KEY` | `base64:Q2FzaEZsb3dGaW5hbExhcmF2ZWxBcHBLZXkyMDI2ISE=` | Khóa mã hóa ứng dụng Laravel 12. |
| `OTP_TTL_SECONDS` | `300` | Thời gian hiệu lực của mã OTP 6 chữ số (mặc định 5 phút). |

---

## ⚙ 3. Yêu cầu Môi trường

- **Cách 1 (Docker Compose — Khuyến nghị):**
  - **Docker Desktop** 4.x (Docker Engine 24+, hỗ trợ lệnh `docker compose`).
  - **PHP 8.1+** (hoặc XAMPP) để chạy giao diện Frontend Web cổng `3000`.
  - Kết nối Internet ở lần build đầu tiên để tải dependency (Maven, Composer, NuGet, pip, Go modules, npm).
- **Cách 3 (Chạy thủ công không cần Docker):**
  - **JDK 17 + Maven**, **PHP 8.2 + Composer**, **.NET 8 SDK**, **Python 3.11+**, **Go 1.22**, **Node.js 20 + npm**, **MySQL 8.0**, **Redis 7**.

---

## 🐳 4. Cách 1: Chạy trọn bộ bằng Docker Compose Final (Khuyến nghị số 1)

> [!IMPORTANT]
> **Lưu ý xung đột cổng:** Nếu bạn vừa chạy bản Giữa kỳ, hãy dừng nó trước khi bật bản Cuối kỳ vì cả hai dùng chung cổng `8080–8086`, `3307`, `3310`, `6379`:
> ```powershell
> docker compose -f docker-compose.midterm.yml down
> ```

### Bước 1: Kiểm tra hợp lệ file Docker Compose
Mở Terminal tại thư mục gốc `SOA-CashFlow`:
```powershell
docker compose -f docker-compose.final.yml config --quiet
```

### Bước 2: Build và khởi chạy toàn bộ 13 containers
Bạn có thể dùng 1 trong 2 lệnh tương đương nhau *(vì `docker-compose.yml` mặc định đã `include: docker-compose.final.yml`)*:
```powershell
# Khuyến nghị ghi rõ file để tránh nhầm lẫn:
docker compose -f docker-compose.final.yml up -d --build

# Hoặc dùng lệnh mặc định ngắn gọn:
docker compose up -d --build
```
> ⏱ *Lưu ý:* Lần build đầu tiên của bản Frameworks sẽ tải các gói thư viện (Spring Boot, Laravel, ASP.NET Core, FastAPI, Gin, Express) nên mất khoảng 3–7 phút tùy mạng. Container `cashflow-transaction-final` (Laravel) sẽ **tự động chạy `php artisan migrate --force && php artisan db:seed --force`** ngay khi MySQL sẵn sàng.

### Bước 3: Kiểm tra trạng thái & Aggregated Health Check
```powershell
# 1. Xem trạng thái 13 containers (5 DB và Redis phải hiển thị "healthy")
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 2. Kiểm tra sức khỏe TỔNG HỢP toàn bộ 6 microservices qua Gateway Final (1 lệnh duy nhất!)
curl.exe http://localhost:8080/health/services

# 3. Kiểm tra chi tiết từng service (nếu cần debug sâu)
curl.exe http://localhost:8080/health                # API Gateway (Express)
curl.exe http://localhost:8081/actuator/health       # Auth Service (Spring Boot Actuator)
curl.exe http://localhost:8082/api/health            # Transaction Service (Laravel)
curl.exe http://localhost:8083/health                # Asset Service (ASP.NET Core)
curl.exe http://localhost:8083/health/ready          # Asset Service DB Readiness
curl.exe http://localhost:8084/health                # Analytics Service (FastAPI)
curl.exe http://localhost:8085/health                # Integration Service (Go Gin)
curl.exe http://localhost:8086/health                # Notification & OTP Service (Express + Socket.io)
```

### Các lệnh vận hành thường dùng
```powershell
# Build lại CHỈ các service bạn vừa chỉnh sửa (tận dụng Docker Layer Cache, chỉ mất vài giây)
docker compose -f docker-compose.final.yml up -d --build gateway notification-service

# Xem log trực tiếp của 1 service cụ thể
docker compose -f docker-compose.final.yml logs -f transaction-service

# Xem 50 dòng log gần nhất của tất cả các container
docker compose -f docker-compose.final.yml logs --tail=50

# Dừng hệ thống (GIỮ NGUYÊN dữ liệu trong 6 named volumes)
docker compose -f docker-compose.final.yml down

# Dừng và XÓA SẠCH toàn bộ volumes để khởi tạo lại dữ liệu mẫu ban đầu
docker compose -f docker-compose.final.yml down -v
```

---

## 🔀 5. Cách 2: Chạy chế độ Hybrid (DB/Redis trong Docker + Debug Local/IDE)

Phù hợp khi lập trình viên muốn chạy toàn bộ **5 MySQL + Redis** sạch trong Docker, đồng thời chạy 1 service (ví dụ Gateway hoặc Notification hoặc Spring Boot) ngay trên IDE cục bộ để đặt breakpoint.

### Bước 1: Chỉ khởi động tầng hạ tầng dữ liệu
```powershell
docker compose -f docker-compose.final.yml up -d auth-db transaction-db asset-db integration-db analytics-db redis
```
Đợi ~15 giây và kiểm tra tất cả 6 container đều ở trạng thái `(healthy)`:
```powershell
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

### Bước 2: Chạy service cần debug trên máy Local
- `auth-db` đã mở sẵn cổng `127.0.0.1:3307` (`auth_db`)
- `integration-db` đã mở sẵn cổng `127.0.0.1:3310` (`integration_db`)
- `redis` đã mở sẵn cổng `127.0.0.1:6379`
- Nếu muốn giữ các service khác chạy trong Docker và chỉ debug 1 service trên máy thật, hãy chạy `up -d` toàn bộ stack rồi `docker stop cashflow-notification-final` và chạy `npm start` ở local (hoặc xem Cách 3 bên dưới).

---

## 🛠 6. Cách 3: Chạy thủ công từng Framework trên từng Terminal

Dành cho môi trường không dùng Docker (yêu cầu đã khởi tạo các database `auth_db`, `transaction_final_db`, `asset_db`, `analytics_db`, `integration_db` trên MySQL cục bộ cổng `3306` với `root` / `root_password` và bật Redis ở cổng `6379`).

> [!TIP]
> Dùng chung chuỗi `JWT_SECRET="cashflow-final-production-jwt-secret-key-2026-tdtu"` cho cả Gateway, Auth Service và Asset Service.

### Terminal 1 — Auth Service (Java 17 + Spring Boot 3.5 — Port `8081`)
```powershell
cd services/auth-service/final
$env:SPRING_PROFILES_ACTIVE="prod"
$env:SERVER_PORT="8081"
$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_NAME="auth_db"; $env:DB_USER="root"; $env:DB_PASSWORD="root_password"
$env:DB_ALLOW_PUBLIC_KEY_RETRIEVAL="true"
$env:JWT_SECRET="cashflow-final-production-jwt-secret-key-2026-tdtu"
mvn spring-boot:run
```

### Terminal 2 — Transaction Service (PHP 8.2 + Laravel 12 — Port `8082`)
```powershell
cd services/transaction-service/final
composer install
# Đảm bảo đã tạo database `transaction_final_db` trên MySQL và cấu hình file .env
Copy-Item .env.example .env -ErrorAction SilentlyContinue
$env:DB_CONNECTION="mysql"; $env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_DATABASE="transaction_final_db"; $env:DB_USERNAME="root"; $env:DB_PASSWORD="root_password"
$env:NOTIFICATION_SERVICE_URL="http://127.0.0.1:8086"
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8082
```

### Terminal 3 — Asset Service (C# .NET 8 + ASP.NET Core Web API — Port `8083`)
*(Nạp trước `services/asset-service/final/db/init-asset-db.sql` vào MySQL).*
```powershell
cd services/asset-service/final
$env:ASPNETCORE_URLS="http://localhost:8083"
$env:ENABLE_SWAGGER="true"
$env:JWT_SECRET="cashflow-final-production-jwt-secret-key-2026-tdtu"
$env:DB_CONNECTION="Server=127.0.0.1;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;"
dotnet restore AssetService.csproj
dotnet run --project AssetService.csproj
```

### Terminal 4 — Analytics Service (Python 3.11 + FastAPI — Port `8084`)
```powershell
cd services/analytics-service/final
pip install -r requirements.txt
$env:TRANSACTION_SERVICE_URL="http://127.0.0.1:8082"
$env:ANALYTICS_DB_HOST="127.0.0.1"; $env:ANALYTICS_DB_PORT="3306"; $env:ANALYTICS_DB_NAME="analytics_db"; $env:ANALYTICS_DB_USER="root"; $env:ANALYTICS_DB_PASSWORD="root_password"
uvicorn src.main:app --host 127.0.0.1 --port 8084 --reload
```

### Terminal 5 — Integration Service (Go 1.22 + Gin + GORM — Port `8085`)
```powershell
cd services/integration-service/final
$env:PORT="8085"
$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_NAME="integration_db"; $env:DB_USER="root"; $env:DB_PASSWORD="root_password"
$env:TRANSACTION_SERVICE_URL="http://127.0.0.1:8082"
$env:NOTIFICATION_SERVICE_URL="http://127.0.0.1:8086"
go run ./cmd/server
```

### Terminal 6 — Notification & OTP Service (Express 4 + Socket.io 4 + ioredis — Port `8086`)
```powershell
cd services/notification-service/final
npm install
$env:HOST="127.0.0.1"; $env:NOTIFICATION_SERVICE_PORT="8086"; $env:PORT="8086"
$env:REDIS_HOST="127.0.0.1"; $env:REDIS_PORT="6379"
$env:OTP_TTL_SECONDS="300"
npm start
```

### Terminal 7 — API Gateway (Express 4 + `http-proxy-middleware` — Port `8080`)
```powershell
cd gateway/final
npm install
$env:GATEWAY_HOST="127.0.0.1"; $env:GATEWAY_PORT="8080"
$env:JWT_SECRET="cashflow-final-production-jwt-secret-key-2026-tdtu"
$env:AUTH_SERVICE_URL="http://127.0.0.1:8081"
$env:TRANSACTION_SERVICE_URL="http://127.0.0.1:8082"
$env:ASSET_SERVICE_URL="http://localhost:8083"
$env:ANALYTICS_SERVICE_URL="http://127.0.0.1:8084"
$env:INTEGRATION_SERVICE_URL="http://127.0.0.1:8085"
$env:NOTIFICATION_SERVICE_URL="http://127.0.0.1:8086"
npm start
```

---

## 🌐 7. Khởi chạy Giao diện Frontend Web iBanking (Port `3000`)

Frontend Web PHP giữ nguyên 100% tương thích với bản Final qua API Gateway cổng `8080`:

- **Cách nhanh:** Nhấp đúp chuột vào file `run-frontend.bat`.
- **Hoặc chạy bằng Terminal:**
  ```powershell
  php -S 127.0.0.1:3000 -t frontend frontend/router.php
  ```
- **Truy cập trình duyệt:** [http://localhost:3000](http://localhost:3000)
- **Tài khoản mẫu:** `admin` / `password123` hoặc `member1` / `password123`.

---

## 🧪 8. Hướng dẫn Kiểm thử & Demo Trọng tâm Cuối kỳ

### 8.1. Kiểm thử qua Swagger UI (Tổng hợp & Từng Framework)
- **Swagger UI tổng hợp tại API Gateway (bao gồm cả OTP Endpoints):** [http://localhost:8080/docs](http://localhost:8080/docs)
  - Từng service: `/docs/auth`, `/docs/transaction`, `/docs/asset`, `/docs/analytics`, `/docs/integration`, `/docs/notification`
- **Swagger riêng do Framework tự sinh:**
  - **Asset Service (ASP.NET Core):** [http://localhost:8083/swagger](http://localhost:8083/swagger)
  - **Analytics Service (FastAPI):** [http://localhost:8084/docs](http://localhost:8084/docs)

### 8.2. Demo Xác thực JWT & Kiểm chứng Hash Password (PowerShell)
```powershell
# 1. Đăng nhập qua Gateway để lấy Access Token JWT
$login = Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/auth/login" `
  -ContentType "application/json" `
  -Body '{"username":"admin","password":"password123"}'
$token = $login.accessToken
$login | ConvertTo-Json -Depth 4

# 2. Gọi API bảo vệ bằng Bearer Token (Gateway xác thực JWT và gắn X-Request-Id)
Invoke-RestMethod -Uri "http://localhost:8080/api/profile" -Headers @{ Authorization = "Bearer $token" } | ConvertTo-Json -Depth 4

# 3. Soi trực tiếp CSDL để chứng minh mật khẩu đã được hash bằng PBKDF2WithHmacSHA256 (không lưu plaintext)
docker exec -it cashflow-auth-db-final mysql -uroot -proot_password auth_db -e "SELECT id, username, role, password_hash FROM users;"
```

### 8.3. Demo Quy trình Xác thực OTP Thanh toán (Single-use, TTL 5 phút, tối đa 3 lần thử)
```powershell
# Bước 1: Yêu cầu sinh mã OTP 6 chữ số (dùng $token đã đăng nhập ở mục 8.2)
$otpRes = Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/notifications/otp/generate" `
  -Headers @{ Authorization = "Bearer $token" } `
  -ContentType "application/json" `
  -Body '{"userId":1,"transactionId":105,"amount":2500000,"action":"PAYMENT"}'
$otpRes | ConvertTo-Json -Depth 4

# Bước 2: Xác thực mã OTP (lấy mã từ $otpRes.data.debugOtp hoặc từ luồng SSE/Socket.io)
$code = $otpRes.data.debugOtp
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/notifications/otp/verify" `
  -Headers @{ Authorization = "Bearer $token" } `
  -ContentType "application/json" `
  -Body "{`"userId`":1,`"transactionId`":105,`"otp`":`"$code`"}" | ConvertTo-Json -Depth 4

# Bước 3 (Chứng minh chống Replay Attack): Gửi lại đúng mã OTP vừa dùng -> Hệ thống từ chối (400 Bad Request)
```

### 8.4. Demo Xử lý Concurrency & Idempotency (Go Loadtest)
Gửi đồng thời **200 giao dịch khác nhau × 3 lần lặp retry = 600 webhook requests** với **50 goroutines chạy song song** vào Integration Service, sau đó tự động so khớp số dư thực tế trong DB với số dư tính tay (lệch sẽ báo lỗi):
```powershell
cd services/integration-service/final
go run ./tools/loadtest -url http://localhost:8085 -account 1 -n 200 -dup 3 -c 50
```

### 8.5. Chạy Bộ Kiểm thử Tự động (Automated Tests)
```powershell
# 1. Newman / Postman CLI (33 requests xuyên suốt qua Gateway :8080)
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on

# 2. Stress test bằng k6
k6 run scripts/concurrency-test-k6.js

# 3. Unit & Integration Tests riêng của từng Framework (khi cài đủ SDK trên máy)
# - Java Spring Boot (JUnit 5 + Mockito + MockMvc):
mvn -f services/auth-service/final/pom.xml test
# - PHP Laravel (PHPUnit):
php services/transaction-service/final/artisan test
# - Go Gin + GORM (race detector):
cd services/integration-service/final; go test -race ./...
```

---

## 🔍 9. Xem, Giám sát & Reset Dữ liệu trong CSDL Final

### 9.1. Xem trực quan bằng phpMyAdmin (Cổng `8088` trên mạng `cashflow-final-net`)
```powershell
docker run -d --name cashflow-phpmyadmin-final --network cashflow-final-net -p 8088:80 -e PMA_ARBITRARY=1 phpmyadmin
```
- Mở trình duyệt: **[http://localhost:8088](http://localhost:8088)**
- Đăng nhập với:
  - **Server:** `auth-db` | `transaction-db` | `asset-db` | `integration-db` | `analytics-db`
  - **Username:** `root`
  - **Password:** `root_password`
- Khi không dùng nữa: `docker rm -f cashflow-phpmyadmin-final`

### 9.2. Truy vấn nhanh qua Terminal (`docker exec`)
*(Lưu ý: tên container bản Final kết thúc bằng `-final` và DB của Laravel Transaction là **`transaction_final_db`**).*

```powershell
# 1. Auth Service (Spring Boot) — Xem users & refresh_tokens
docker exec -it cashflow-auth-db-final mysql -uroot -proot_password auth_db -e "SELECT id, username, email, role, LEFT(password_hash, 40) AS hash_preview FROM users;"

# 2. Transaction Service (Laravel 12) — Xem 10 giao dịch mới nhất kèm tên danh mục
docker exec -it cashflow-transaction-db-final mysql -uroot -proot_password transaction_final_db -e "SELECT t.id, t.user_id, c.name AS category, t.amount, t.type, t.description, t.transaction_date FROM transactions t LEFT JOIN categories c ON t.category_id = c.id ORDER BY t.id DESC LIMIT 10;"

# 3. Transaction Service (Laravel 12) — Xem hạn mức ngân sách
docker exec -it cashflow-transaction-db-final mysql -uroot -proot_password transaction_final_db -e "SELECT b.id, b.user_id, c.name AS category, b.amount_limit, b.period, b.start_date, b.end_date FROM budgets b JOIN categories c ON b.category_id = c.id;"

# 4. Asset Service (ASP.NET Core) — Xem tài sản & lịch sử biến động giá trị
docker exec -it cashflow-asset-db-final mysql -uroot -proot_password asset_db -e "SELECT id, user_id, asset_name, asset_type, purchase_price, current_value, currency FROM assets ORDER BY id DESC;"
docker exec -it cashflow-asset-db-final mysql -uroot -proot_password asset_db -e "SELECT id, asset_id, value, recorded_at FROM asset_history ORDER BY id DESC LIMIT 10;"

# 5. Integration Service (Go Gin) — Xem tài khoản ngân hàng & lịch sử biến động (Idempotency)
docker exec -it cashflow-integration-db-final mysql -uroot -proot_password integration_db -e "SELECT id, user_id, bank_code, account_no, holder_name, balance, version FROM linked_accounts;"
docker exec -it cashflow-integration-db-final mysql -uroot -proot_password integration_db -e "SELECT id, account_id, provider, external_ref, direction, amount, status FROM bank_transactions ORDER BY id DESC LIMIT 10;"

# 6. Analytics Service (FastAPI) — Xem báo cáo phân tích tháng đã lưu
docker exec -it cashflow-analytics-db-final mysql -uroot -proot_password analytics_db -e "SELECT report_id, user_id, year, month, generated_at FROM monthly_reports;"

# 7. Notification & OTP Service (Express + Socket.io) — Xem thông báo & kiểm tra Redis
Invoke-RestMethod -Uri "http://localhost:8086/api/notifications?userId=1" | ConvertTo-Json -Depth 4
docker exec -it cashflow-redis-final redis-cli PUBSUB CHANNELS
```

### 9.3. Reset CSDL Final về trạng thái dữ liệu mẫu ban đầu
```powershell
# Xóa toàn bộ 6 named volumes của bản Final và khởi động lại sạch sẽ
docker compose -f docker-compose.final.yml down -v
docker compose -f docker-compose.final.yml up -d
```

---

## 🔧 10. Xử lý Sự cố Thường gặp

| Triệu chứng | Nguyên nhân | Cách khắc phục |
|:---|:---|:---|
| Trùng cổng `8080–8086`, `3307`, `3310`, `6379` | Bản Giữa kỳ hoặc tiến trình local vẫn đang chạy | Chạy `docker compose -f docker-compose.midterm.yml down` (và nếu từng chạy local: `powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop`). |
| `cashflow-auth-final` thoát ngay với lỗi JWT Secret | Profile `prod` của Spring Boot chặn secret quá ngắn (< 32 bytes) hoặc chuỗi dev cũ | Không ghi đè `JWT_SECRET` bằng chuỗi ngắn trong `.env`; dùng chuỗi mặc định `cashflow-final-production-jwt-secret-key-2026-tdtu` hoặc chuỗi ngẫu nhiên ≥ 32 ký tự. |
| Auth Service báo `Public Key Retrieval is not allowed` | Kết nối JDBC tới MySQL 8.0 thiếu tham số cho phép lấy public key | Đảm bảo biến môi trường `DB_ALLOW_PUBLIC_KEY_RETRIEVAL=true` được giữ nguyên (đã cấu hình sẵn trong `docker-compose.final.yml`). |
| Laravel Transaction Service báo bảng chưa tồn tại hoặc chưa có seed data | Container khởi động khi DB chưa kịp nhận kết nối hoặc volume cũ bị rỗng | Chạy lại migrate & seed nóng: `docker exec -it cashflow-transaction-final php artisan migrate --force && docker exec -it cashflow-transaction-final php artisan db:seed --force`. |
| Asset Service trả `401 Unauthorized` dù đã đăng nhập | `JWT_SECRET` giữa Auth, Gateway và Asset không khớp nhau | Đảm bảo cả 3 service dùng chung cùng một `JWT_SECRET` và rebuild lại bằng `docker compose -f docker-compose.final.yml up -d --build`. |
| Lỗi `open //./pipe/dockerDesktopLinuxEngine` trên Windows | Ứng dụng Docker Desktop chưa được bật | Mở **Docker Desktop**, đợi biểu tượng cá voi xanh báo *Engine running* rồi chạy lại lệnh `docker compose`. |
