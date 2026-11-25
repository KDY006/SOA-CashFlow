# Sơ đồ Kiến trúc Tổng thể & Triển khai Docker Compose (Midterm — 100% Native Code)

Tài liệu này cung cấp mã nguồn Mermaid chuẩn hóa cho hai sơ đồ nền tảng của hệ thống **SOA CashFlow (Phiên bản Giữa kỳ)**:
- **Hình 1-1:** Kiến trúc Tổng thể Hệ thống SOA CashFlow (4 tầng: Client → Native API Gateway → 6 Microservices đa ngôn ngữ → 5 CSDL MySQL 8.0 + Redis 7).
- **Hình 3-1:** Biểu đồ Triển khai Vật lý Docker Compose (`cashflow-midterm-net` với 13 containers và máy chủ Host chạy Frontend PHP `:3000`).

---

## Hình 1-1: Kiến trúc Tổng thể Hệ thống SOA CashFlow (Phiên bản Giữa kỳ)

```mermaid
flowchart TB
    subgraph T1["Tầng 1: Client & Trình diễn (Presentation / API Consumer)"]
        WEB["PHP Web iBanking :3000<br/>(frontend/ - BFF Session & curl_multi)"]
        SWAGGER["Swagger UI Tập trung :8080/docs<br/>(docs.js hợp nhất 6 OpenAPI 3.0.3)"]
        EXT["Ngân hàng / Webhook Caller<br/>(POST /api/integrations/webhooks/banking)"]
    end

    subgraph T2["Tầng 2: Cổng Giao tiếp Duy nhất (Edge Gateway)"]
        GW["Native API Gateway :8080<br/>(gateway/midterm/ - Node.js 20 Zero-Dep)<br/>Rate Limit 300/phút | JWT HMAC-SHA256 | Anti-IDOR | Pipe Proxy"]
    end

    subgraph T3["Tầng 3: 6 Microservices Nghiệp vụ Đa ngôn ngữ (100% Native Code)"]
        AUTH["Auth & Profile Service :8081<br/>(Java 17 HttpServer + PBKDF2 210k)"]
        TX["Transaction & Budget Service :8082<br/>(PHP 8.2 Built-in + PDO Native)"]
        ASSET["Asset & Investment Service :8083<br/>(C# .NET 10 HttpListener)"]
        ANA["Analytics Service :8084<br/>(Python 3.11 ThreadingHTTPServer)"]
        INT["Integration & Concurrency Service :8085<br/>(Go 1.22 ServeMux + Worker Pool + Sweeper)"]
        NOTIF["Notification Service :8086<br/>(Node.js 20 HTTP + SSE + Raw TCP RESP)"]
    end

    subgraph T4["Tầng 4: Lưu trữ Cô lập (Database-per-Service & Pub/Sub)"]
        DB_AUTH[("auth_db :3307<br/>MySQL 8.0<br/>users, refresh_tokens")]
        DB_TX[("transaction_db<br/>MySQL 8.0<br/>categories, transactions, budgets")]
        DB_ASSET[("asset_db<br/>MySQL 8.0<br/>assets, loans, investments, asset_history")]
        DB_ANA[("analytics_db<br/>MySQL 8.0<br/>monthly_reports")]
        DB_INT[("integration_db :3310<br/>MySQL 8.0<br/>linked_accounts, bank_transactions,<br/>exchange_rates, external_webhooks")]
        REDIS[("Redis 7 Alpine :6379<br/>Pub/Sub Channel:<br/>cashflow:notifications")]
        MEM["In-Memory Store & SSE Map<br/>(store.js + userConnections)"]
    end

    WEB -->|"HTTP REST + Bearer JWT"| GW
    SWAGGER -->|"HTTP REST /docs, /openapi.json"| GW
    EXT -->|"Public Webhook + Idempotency-Key"| GW
    WEB -.->|"Direct EventSource SSE (localhost:8086)"| NOTIF

    GW -->|"HTTP + x-user-id/name/role"| AUTH
    GW -->|"HTTP + x-user-id/name/role"| TX
    GW -->|"HTTP + x-user-id/name/role"| ASSET
    GW -->|"HTTP + x-user-id/name/role"| ANA
    GW -->|"HTTP + x-user-id/name/role"| INT
    GW -->|"HTTP / SSE (timeout: 0)"| NOTIF

    ANA -->|"Đông-Tây Sync Pull: GET /api/transactions?limit=500"| TX
    INT -->|"Đông-Tây Async Worker: POST /api/transactions"| TX
    INT -.->|"Fire-and-forget: POST /api/notifications/send"| NOTIF

    AUTH -->|"JDBC"| DB_AUTH
    TX -->|"PDO Prepared"| DB_TX
    ASSET -->|"MySql.Data"| DB_ASSET
    ANA -->|"PyMySQL"| DB_ANA
    INT -->|"database/sql + FOR UPDATE"| DB_INT
    NOTIF <-->|"Raw TCP net.Socket RESP (PUB/SUB)"| REDIS
    NOTIF --- MEM
```

---

## Hình 3-1: Biểu đồ Triển khai Vật lý Docker Compose (`cashflow-midterm-net`)

```mermaid
flowchart LR
    subgraph HOST["Máy Host (Windows / Linux)"]
        PHP_HOST["PHP Built-in Web Server<br/>127.0.0.1:3000<br/>(frontend/router.php)"]
        BROWSER["Trình duyệt Người dùng<br/>EventSource & Chart.js"]
        SQL_FILES["Tệp khởi tạo SQL cục bộ<br/>services/*/db/init-*-db.sql"]
    end

    subgraph DOCKER["Docker Bridge Network: cashflow-midterm-net (13 Containers)"]
        subgraph EDGE["Edge Container (1)"]
            C_GW["cashflow-gateway-service<br/>node:20-alpine | 8080:8080<br/>depends_on: 6 services"]
        end

        subgraph APPS["Microservice Containers (6)"]
            C_AUTH["cashflow-auth-service<br/>temurin:17-jre-alpine | 8081:8081<br/>depends_on: auth-db"]
            C_TX["cashflow-transaction-service<br/>php:8.2-cli-alpine | 8082:8082<br/>depends_on: transaction-db"]
            C_ASSET["cashflow-asset-service<br/>dotnet/runtime:10.0 | 8083:8083<br/>depends_on: asset-db"]
            C_ANA["cashflow-analytics-service<br/>python:3.11-slim | 8084:8084<br/>restart: always | depends_on: analytics-db, transaction-service"]
            C_INT["cashflow-integration-service<br/>alpine:3.19 | 8085:8085<br/>restart: on-failure | depends_on: integration-db, transaction-service, notification-service"]
            C_NOTIF["cashflow-notification-service<br/>node:20-alpine | 8086:8086<br/>depends_on: redis"]
        end

        subgraph DATA["Database & Cache Containers (6)"]
            C_DB_AUTH[("cashflow-auth-db<br/>mysql:8.0 | 3307:3306")]
            C_DB_TX[("cashflow-transaction-db<br/>mysql:8.0 | internal :3306")]
            C_DB_ASSET[("cashflow-asset-db<br/>mysql:8.0 | internal :3306")]
            C_DB_ANA[("cashflow-analytics-db<br/>mysql:8.0 | internal :3306<br/>restart: always")]
            C_DB_INT[("cashflow-integration-db<br/>mysql:8.0 | 3310:3306")]
            C_REDIS[("cashflow-redis-notification<br/>redis:7-alpine | 6379:6379")]
        end

        VOL[("Named Volume:<br/>analytics_midterm_db_data<br/>-> /var/lib/mysql")]
    end

    BROWSER -->|"HTTP :3000"| PHP_HOST
    PHP_HOST -->|"GATEWAY_URL=127.0.0.1:8080"| C_GW
    BROWSER -.->|"SSE localhost:8086"| C_NOTIF

    C_GW --> C_AUTH
    C_GW --> C_TX
    C_GW --> C_ASSET
    C_GW --> C_ANA
    C_GW --> C_INT
    C_GW --> C_NOTIF

    C_ANA --> C_TX
    C_INT --> C_TX
    C_INT --> C_NOTIF

    C_AUTH --> C_DB_AUTH
    C_TX --> C_DB_TX
    C_ASSET --> C_DB_ASSET
    C_ANA --> C_DB_ANA
    C_INT --> C_DB_INT
    C_NOTIF --> C_REDIS

    C_DB_ANA --- VOL
    SQL_FILES -.->|"Bind Mount :init.sql"| C_DB_AUTH
    SQL_FILES -.->|"Bind Mount :init.sql"| C_DB_TX
    SQL_FILES -.->|"Bind Mount :init.sql"| C_DB_ASSET
    SQL_FILES -.->|"Bind Mount :init.sql"| C_DB_INT
```
