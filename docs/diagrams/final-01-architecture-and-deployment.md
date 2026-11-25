# Bộ Sơ đồ Kiến trúc & Triển khai Hạ tầng — Phiên bản Cuối kỳ (Full Frameworks)

Tài liệu này mô tả hai sơ đồ kiến trúc và triển khai cấp hệ thống của phiên bản Cuối kỳ **SOA-CashFlow**, bám sát mã nguồn thực tế trong `gateway/final/`, `services/*/final/`, `frontend/` và `docker-compose.final.yml`.

---

## 1. Hình 1-1: Kiến trúc Tổng thể Hệ thống SOA CashFlow Bản Cuối kỳ (Full Frameworks)

```mermaid
flowchart TB
    subgraph ClientLayer["Tầng Giao diện & Công cụ Kiểm thử (Host / Client)"]
        WEB["Frontend Web iBanking<br/>(PHP Built-in :3000 - REST + SSE)"]
        WSCLIENT["Realtime Client / Postman / k6 / Newman<br/>(HTTP REST + Socket.io WebSocket)"]
        SWAGGER["Swagger UI Đa điểm<br/>(:8080/docs, :8083/swagger, :8084/docs)"]
        BANK["External Bank Webhook Simulator<br/>(POST /api/integrations/webhooks/banking + X-Signature)"]
    end

    subgraph GatewayLayer["Tầng Điều phối API Gateway (:8080)"]
        GW["Express 4 API Gateway (cashflow-gateway-final)<br/>CORS -> requestTracer (X-Request-Id) -> rateLimit (600/min)<br/>-> /health/services (AbortController 3s) -> authGuard (HS256)<br/>-> http-proxy-middleware v3 + WS Upgrade (/socket.io)"]
    end

    subgraph ServiceLayer["Tầng 6 Microservices Nghiệp vụ (7 Frameworks - cashflow-final-net)"]
        AUTH["1. Auth & Profile Service<br/>Java 17 + Spring Boot 3.5.16 (:8081)<br/>Spring Security, Nimbus JOSE, JPA/HikariCP"]
        TX["2. Transaction & Budget Service<br/>PHP 8.2 + Laravel 12 (:8082)<br/>Query Builder, BCMath, lockForUpdate()"]
        ASSET["3. Asset & Investment Service<br/>C# .NET 8 + ASP.NET Core 8 (:8083)<br/>JwtBearer, EF Core 8 Pomelo, RepeatableRead"]
        ANA["4. Analytics Service<br/>Python 3.11 + FastAPI 0.110 (:8084)<br/>Pydantic v2, MAD Anomaly, Trailing Avg 3M"]
        INT["5. Integration & Concurrency Service<br/>Go 1.22 + Gin v1.10 + GORM (:8085)<br/>KeyedLocker, FOR UPDATE, Worker Pool + WaitGroup"]
        NOTI["6. Notification & OTP Service<br/>Node.js 20 + Express 4 + Socket.io 4 (:8086)<br/>ioredis Pub/Sub, SSE, 6-Digit OTP Store"]
    end

    subgraph DataLayer["Tầng Lưu trữ Độc lập & Message Broker (5 MySQL 8.0 + 1 Redis 7)"]
        DB_AUTH[("auth_db<br/>MySQL 8.0 :3307<br/>users, refresh_tokens")]
        DB_TX[("transaction_final_db<br/>MySQL 8.0 (Internal)<br/>categories, transactions, budgets + Laravel tables")]
        DB_ASSET[("asset_db<br/>MySQL 8.0 (Internal)<br/>assets, loans, investments, asset_history")]
        DB_ANA[("analytics_db<br/>MySQL 8.0 (Internal)<br/>monthly_reports")]
        DB_INT[("integration_db<br/>MySQL 8.0 :3310<br/>linked_accounts, bank_transactions, exchange_rates, external_webhooks")]
        REDIS[("Redis 7 Alpine (:6379)<br/>Pub/Sub: cashflow:notifications, cashflow:events")]
    end

    WEB -->|"HTTP REST + Bearer JWT"| GW
    WEB -.->|"SSE EventSource (:8086 trực tiếp)"| NOTI
    WSCLIENT -->|"HTTP REST / Bearer JWT"| GW
    WSCLIENT <-->|"WebSocket Upgrade /socket.io"| GW
    SWAGGER -->|"OpenAPI Try-it-out"| GW
    BANK -->|"Webhook POST (Public Endpoint)"| GW

    GW -->|"Proxy + X-Request-Id + X-User-*"| AUTH
    GW -->|"Proxy + X-Request-Id + X-User-*"| TX
    GW -->|"Proxy + Bearer JWT + X-Request-Id"| ASSET
    GW -->|"Proxy + X-Request-Id + X-User-*"| ANA
    GW -->|"Proxy + X-Request-Id + X-User-*"| INT
    GW <-->|"HTTP Proxy + WS Upgrade (/socket.io)"| NOTI

    AUTH -->|"HikariCP Pool / JDBC"| DB_AUTH
    TX -->|"Laravel Migrations + DB::table"| DB_TX
    ASSET -->|"EF Core 8 + Pomelo MySQL"| DB_ASSET
    ANA -->|"PyMySQL"| DB_ANA
    INT -->|"GORM Pool (MaxOpen=30)"| DB_INT
    NOTI <-->|"ioredis redisPub / redisSub"| REDIS

    ANA -->|"HTTP GET phân trang limit=100"| TX
    TX -->|"Http::timeout(2) POST BUDGET_ALERT"| NOTI
    INT -->|"Syncer Worker POST + Idempotency-Key"| TX
    INT -->|"Async Notifier POST (3s timeout)"| NOTI
```

*Hình 1-1: Kiến trúc Tổng thể Hệ thống SOA CashFlow Bản Cuối kỳ (Full Frameworks)*

### Phân tích chi tiết Hình 1-1:
1. **Tầng Giao diện & Khách hàng (Client Tier):**
   - Giao diện Web iBanking viết bằng PHP thuần (`frontend/`, cổng `:3000`) tiếp tục giao tiếp REST với API Gateway (`http://127.0.0.1:8080`) và giữ kết nối SSE trực tiếp tới Notification Service (`http://localhost:8086/api/notifications/stream?user_id=...`), chứng minh khả năng tương thích ngược 100% khi toàn bộ Backend được nâng cấp lên 7 Frameworks.
   - Các client hiện đại, Swagger UI đa điểm (`:8080/docs`, `:8083/swagger`, `:8084/docs`), Newman, Go `tools/loadtest` và `k6` tương tác qua HTTP REST và kênh WebSocket `/socket.io` thông qua Gateway.
2. **Tầng Điều phối Express API Gateway (`gateway/final`, `:8080`):**
   - Thực thi chuỗi 10 bước chuẩn hóa: CORS toàn cục, `requestTracer` gắn `X-Request-Id` và ghi log JSON đo bằng `process.hrtime.bigint()`, `express-rate-limit` (600 req/phút, miễn trừ `/health` và `/api/notifications/stream`), tổng hợp sức khỏe `GET /health/services` (`AbortController` timeout 3.000ms), Swagger UI tổng hợp, `authGuard` kiểm tra JWT `HS256` và xóa các header giả mạo (`X-User-Id`, `X-User-Name`, `X-User-Role`), cùng `http-proxy-middleware` v3 stream nguyên vẹn payload và xử lý sự kiện `server.on('upgrade')` cho `/socket.io`.
3. **Tầng 6 Microservices Nghiệp vụ (Polyglot 6 Ngôn ngữ — 6 Frameworks):**
   - Mỗi service sử dụng framework chính thống tương ứng: **Spring Boot 3.5.16** (`:8081`), **Laravel 12** (`:8082`), **ASP.NET Core 8** (`:8083`), **FastAPI 0.110.0** (`:8084`), **Go Gin + GORM** (`:8085`), và **Express 4 + Socket.io 4 + ioredis** (`:8086`).
   - Áp dụng **Defense-in-Depth**: ngoài Gateway, Auth Service (`JwtAuthenticationFilter`) và Asset Service (`JwtBearer`) tự xác thực lại chữ ký JWT `HS256` độc lập và kiểm tra quyền sở hữu tài nguyên.
4. **Tầng Dữ liệu Độc lập (Database-per-Service) & Giao tiếp Liên dịch vụ:**
   - 5 cơ sở dữ liệu MySQL 8.0 tách biệt (`auth_db`, `transaction_final_db`, `asset_db`, `analytics_db`, `integration_db`), tuyệt đối không có khóa ngoại liên CSDL.
   - **Đồng bộ kéo (Sync Pull):** FastAPI Analytics phân trang `GET /api/transactions?limit=100` từ Laravel Transaction.
   - **Bất đồng bộ đẩy (Async Push):** Go Integration `Syncer` (4 workers) đẩy giao dịch đã chốt sang Laravel Transaction kèm `Idempotency-Key`; cả Laravel Transaction (`Http::timeout(2)`) và Go Integration (`Notifier` goroutine timeout 3s) đều đẩy cảnh báo về Notification Service, nơi phát sóng qua Redis Pub/Sub (`cashflow:notifications`, `cashflow:events`) ra cả Socket.io Room `user:<id>` và SSE.

---

## 2. Hình 3-1: Biểu đồ Triển khai Hạ tầng Docker Compose Production (`docker-compose.final.yml`)

```mermaid
flowchart TB
    subgraph HostMachine["Máy chủ Host (Windows / Linux)"]
        PHPWEB["Host PHP Web Server (:3000)<br/>php -S 0.0.0.0:3000 router.php"]
        TESTERS["Host Tools: Browser / Postman / k6 / go run ./tools/loadtest"]
    end

    subgraph DockerNetwork["Docker Bridge Network: cashflow-final-net (13 Containers)"]
        subgraph AppContainers["7 Application Containers (restart: always)"]
            C_GW["cashflow-gateway-final<br/>node:20-alpine | :8080:8080<br/>depends_on: 6 app services (service_started)"]
            C_AUTH["cashflow-auth-final<br/>eclipse-temurin:17-jre | :8081:8081<br/>USER 10001:10001 | Build: mvn verify<br/>depends_on: auth-db (service_healthy)"]
            C_TX["cashflow-transaction-final<br/>php:8.2-cli-alpine | :8082:8082<br/>Auto: migrate --force && db:seed --force<br/>depends_on: transaction-db (service_healthy)"]
            C_ASSET["cashflow-asset-final<br/>dotnet/aspnet:8.0 | :8083:8083<br/>USER app | Swagger: ENABLE_SWAGGER=true<br/>depends_on: asset-db (service_healthy)"]
            C_ANA["cashflow-analytics-final<br/>python:3.11-slim | :8084:8084<br/>depends_on: analytics-db (service_healthy), tx (started)"]
            C_INT["cashflow-integration-final<br/>alpine:3.19 (TZ=Asia/Ho_Chi_Minh) | :8085:8085<br/>depends_on: int-db (service_healthy), tx/noti (started)"]
            C_NOTI["cashflow-notification-final<br/>node:20-alpine | :8086:8086<br/>depends_on: redis (service_healthy)"]
        end

        subgraph InfraContainers["6 Infrastructure Containers (Docker healthcheck enabled)"]
            C_DB_AUTH[("cashflow-auth-db-final<br/>mysql:8.0 | Host :3307:3306<br/>Healthcheck: SELECT 1 FROM refresh_tokens LIMIT 0")]
            C_DB_TX[("cashflow-transaction-db-final<br/>mysql:8.0 | Internal :3306<br/>Healthcheck: mysqladmin ping")]
            C_DB_ASSET[("cashflow-asset-db-final<br/>mysql:8.0 | Internal :3306<br/>Healthcheck: SELECT 1 FROM asset_history LIMIT 0")]
            C_DB_ANA[("cashflow-analytics-db-final<br/>mysql:8.0 | Internal :3306<br/>Healthcheck: mysqladmin ping")]
            C_DB_INT[("cashflow-integration-db-final<br/>mysql:8.0 | Host :3310:3306<br/>Healthcheck: mysqladmin ping")]
            C_REDIS[("cashflow-redis-final<br/>redis:7-alpine | Host :6379:6379<br/>Healthcheck: redis-cli ping")]
        end

        subgraph NamedVolumes["6 Named Volumes (Persistent Storage)"]
            V1[("auth_final_data")]
            V2[("transaction_laravel_data")]
            V3[("asset_final_data")]
            V4[("analytics_final_data")]
            V5[("integration_final_data")]
            V6[("redis_final_data")]
        end
    end

    PHPWEB -->|"http://127.0.0.1:8080"| C_GW
    PHPWEB -.->|"SSE http://localhost:8086"| C_NOTI
    TESTERS -->|"8080..8086 / 3307, 3310, 6379"| C_GW

    C_GW --> C_AUTH & C_TX & C_ASSET & C_ANA & C_INT & C_NOTI

    C_AUTH -->|"service_healthy"| C_DB_AUTH
    C_TX -->|"service_healthy"| C_DB_TX
    C_ASSET -->|"service_healthy"| C_DB_ASSET
    C_ANA -->|"service_healthy"| C_DB_ANA
    C_INT -->|"service_healthy"| C_DB_INT
    C_NOTI -->|"service_healthy"| C_REDIS

    C_DB_AUTH --- V1
    C_DB_TX --- V2
    C_DB_ASSET --- V3
    C_DB_ANA --- V4
    C_DB_INT --- V5
    C_REDIS --- V6
```

*Hình 3-1: Biểu đồ Triển khai Hạ tầng Docker Compose Production (`cashflow-final-net`)*

### Phân tích chi tiết Hình 3-1:
1. **Cô lập mạng và Volume bền vững (`cashflow-final-net`):** Toàn bộ 13 container vận hành trên mạng bridge riêng `cashflow-final-net` tách biệt hoàn toàn với mạng giữa kỳ (`cashflow-midterm-net`). Dữ liệu của 5 MySQL 8.0 (`utf8mb4_unicode_ci`) và Redis 7 được lưu trên 6 Named Volumes riêng (`auth_final_data`, `transaction_laravel_data`, `asset_final_data`, `analytics_final_data`, `integration_final_data`, `redis_final_data`), tránh ghi đè dữ liệu cũ khi chuyển đổi giữa 2 phiên bản.
2. **Cơ chế Healthcheck thực tế (`condition: service_healthy`):** Trong `docker-compose.final.yml`, cả 5 container MySQL và Redis đều khai báo `healthcheck:` (đặc biệt `auth-db` kiểm tra `SELECT 1 FROM refresh_tokens LIMIT 0` và `asset-db` kiểm tra `SELECT 1 FROM asset_history LIMIT 0` để chắc chắn script `init.sql` đã nạp bảng xong trước khi ứng dụng kết nối). Ngược lại, 7 container ứng dụng không khai báo Docker `healthcheck:` riêng mà được giám sát tập trung qua HTTP tại `GET /health/services` của Gateway (`gateway` và các liên kết `analytics`/`integration -> transaction/notification` dùng `service_started`).
3. **Bảo mật & Tự động hóa trong Dockerfile:**
   - **Kiểm thử lúc Build:** `services/auth-service/final/Dockerfile` chạy `RUN mvn -B -ntp verify`, ngăn chặn đóng gói image nếu unit/integration test thất bại.
   - **Nguyên tắc Đặc quyền Tối thiểu (Least Privilege):** `auth-service` thực thi dưới `USER 10001:10001` và `asset-service` thực thi dưới `USER app` (2 trên 7 Dockerfile cấu hình non-root; 5 Dockerfile còn lại chạy mặc định `root` trên Alpine/Slim).
   - **Tự động hóa Schema:** `transaction-service` thực thi `php artisan migrate --force && php artisan db:seed --force` khi khởi động container vào `transaction_final_db`; `analytics-service` nạp schema `/app/db/init-analytics-db.sql` trong FastAPI `lifespan` với cơ chế retry.
4. **Cổng ánh xạ ra Host:** 7 dịch vụ ứng dụng mở cổng `8080..8086`, Redis mở `6379`, riêng `auth-db` mở `3307:3306` và `integration-db` mở `3310:3306` phục vụ kiểm tra trực tiếp và công cụ test, trong khi `transaction-db`, `asset-db` và `analytics-db` chỉ lắng nghe nội bộ trên cổng `3306` của `cashflow-final-net`.
