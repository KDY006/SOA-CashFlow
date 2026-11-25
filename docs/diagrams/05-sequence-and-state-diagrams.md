# Bộ Biểu đồ Tuần tự Đa tầng & Máy trạng thái (Midterm — 100% Native Code)

Tài liệu này chứa 4 sơ đồ hành vi trọng tâm của phiên bản Giữa kỳ:
- **Hình 2-1:** Luồng Xác thực JWT & Chống Giả mạo Header (Anti-IDOR) tại API Gateway.
- **Hình 2-10:** Luồng Kéo Dữ liệu Phân tích Liên dịch vụ (Analytics → Transaction → `analytics_db`).
- **Hình 2-11:** Luồng Webhook Đồng thời, Đồng bộ Nền & Cảnh báo Ngân sách Realtime SSE.
- **Hình 2-12:** Biểu đồ Máy trạng thái (`State Diagram`) của thực thể `bank_transactions.status`.

---

## Hình 2-1: Biểu đồ Tuần tự Luồng Xác thực JWT & Chống Giả mạo Header (Anti-IDOR)

```mermaid
sequenceDiagram
    autonumber
    actor Client as "Client / PHP Web (:3000)"
    participant GW as "Native API Gateway (:8080)"
    participant Auth as "Auth Service (:8081)"
    participant AuthDB as "auth_db (MySQL 8.0)"
    participant Target as "Downstream Service (:8082..:8086)"

    Client->>GW: POST /api/auth/login {username, password}
    GW->>GW: isRateLimited(ip) & match publicEndpoints
    GW->>Auth: Stream pipe POST /api/auth/login
    Auth->>AuthDB: SELECT user WHERE username/email
    AuthDB-->>Auth: User (password_hash PBKDF2 210k, status)
    Auth->>Auth: PasswordUtil.verifyPassword() + MessageDigest.isEqual()
    Auth->>AuthDB: DELETE old refresh_tokens & INSERT 7-day token
    Auth-->>GW: 200 OK {accessToken (HS256, 3600s), refreshToken, user}
    GW-->>Client: 200 OK (lưu vào $_SESSION phía PHP Web)

    Note over Client,Target: Truy cập Endpoint Bảo vệ (kèm header giả mạo X-User-Id từ kẻ tấn công nếu có)
    Client->>GW: GET /api/transactions (Authorization: Bearer JWT, X-User-Id: 999)
    GW->>GW: isRateLimited(ip) -> OK (<= 300 req/phút)
    GW->>GW: extractBearerToken() & verifyJwt() bằng crypto.timingSafeEqual()
    alt Token không hợp lệ hoặc hết hạn
        GW-->>Client: 401 Unauthorized {error: "UNAUTHORIZED"}
    else Token hợp lệ (userId=2, username="member1", role="USER")
        GW->>GW: Xóa sạch client-supplied x-user-id, x-user-name, x-user-role
        GW->>GW: Gắn header tin cậy x-user-id: 2, x-user-name: member1, x-user-role: USER
        GW->>Target: req.pipe(proxyReq) (timeout 15s, SSE = 0)
        Target->>Target: Auth::forbiddenFor() / ResolveUserId() đối chiếu x-user-id
        Target-->>GW: 200 OK JSON Response
        GW-->>Client: proxyRes.pipe(res) (+ x-proxied-by: CashFlow-Native-Gateway)
    end
```

---

## Hình 2-10: Biểu đồ Tuần tự Luồng Kéo Dữ liệu Phân tích Liên dịch vụ

```mermaid
sequenceDiagram
    autonumber
    actor Client as "Client / PHP Web"
    participant GW as "API Gateway (:8080)"
    participant Ana as "Analytics Service (:8084)"
    participant Tx as "Transaction Service (:8082)"
    participant TxDB as "transaction_db (MySQL 8.0)"
    participant AnaDB as "analytics_db (MySQL 8.0)"

    Client->>GW: POST /api/analytics/aggregate {from_date, to_date}
    GW->>GW: verifyJwt() -> gắn x-user-id, x-user-name, x-user-role
    GW->>Ana: Forward POST /api/analytics/aggregate
    Ana->>Ana: _user_id() xác định user_id từ X-User-Id

    loop Phân trang kéo dữ liệu (page size = 500, cap = 100,000, timeout = 8s)
        Ana->>Tx: GET /api/transactions?user_id=...&limit=500&offset=... (Header: X-User-Id)
        Tx->>TxDB: PreparedStatement SELECT transactions WHERE user_id = ?
        TxDB-->>Tx: Danh sách giao dịch trang hiện tại
        Tx-->>Ana: 200 OK {success: true, data: [...]}
    end

    Ana->>Ana: _normalise() & _period() chuẩn hóa số tiền, ngày tháng
    Ana->>Ana: Tính summary(), category_breakdown(), anomalies() (MAD: median + 3*1.4826*MAD)
    Ana->>AnaDB: INSERT INTO monthly_reports ... ON DUPLICATE KEY UPDATE
    AnaDB-->>Ana: report_id
    Ana-->>GW: 201 Created {data: {summary, category_breakdown, anomalies, report_id}, meta: {stored: true}}
    GW-->>Client: 201 Created JSON Report
```

---

## Hình 2-11: Biểu đồ Tuần tự Luồng Webhook Đồng thời, Đồng bộ Nền & Cảnh báo Realtime SSE

```mermaid
sequenceDiagram
    autonumber
    actor Bank as "Ngân hàng / Webhook Caller"
    participant GW as "API Gateway (:8080)"
    participant Int as "Integration Service (:8085)"
    participant IntDB as "integration_db (MySQL 8.0)"
    participant Sync as "Background Syncer (4 Workers)"
    participant Tx as "Transaction Service (:8082)"
    participant Notif as "Notification Service (:8086)"
    participant Redis as "Redis 7 Pub/Sub (:6379)"
    actor Browser as "Trình duyệt (EventSource SSE)"

    Bank->>GW: POST /api/integrations/webhooks/banking (Idempotency-Key, X-Signature)
    GW->>Int: Public Endpoint -> Forward Webhook
    Int->>Int: Kiểm tra HMAC-SHA256 X-Signature & tính SHA-256 request_hash
    Int->>IntDB: INSERT INTO external_webhooks (audit log thô)
    Int->>Int: keyedLocker.Lock(bank_code + ":" + account_no)
    Int->>IntDB: BEGIN TX (READ COMMITTED) + SELECT ... FROM linked_accounts FOR UPDATE

    alt Trùng Idempotency-Key hoặc (provider, external_ref)
        Int->>Int: duplicateOf() so sánh request_hash
        Int-->>Bank: 200 OK (khớp hash) hoặc 409 IDEMPOTENCY_CONFLICT (lệch hash)
    else Giao dịch DEBIT vượt số dư
        Int->>IntDB: INSERT bank_transactions (status='REJECTED') & COMMIT
        Int-->>Notif: Async notifier.send(TRANSACTION_REJECTED)
        Int-->>Bank: 422 Unprocessable Entity {status: "REJECTED"}
    else Hợp lệ
        Int->>IntDB: UPDATE linked_accounts SET balance=?, version=version+1
        Int->>IntDB: INSERT bank_transactions (status='RECEIVED') & COMMIT
        Int->>Sync: syncer.enqueue(txID) (buffered channel 2000)
        Int-->>Bank: 201 Created (trả lời tức thì không chờ Transaction Service)
    end

    Note over Sync,Browser: Giai đoạn 2 — Đồng bộ nền & Phát cảnh báo Realtime
    Sync->>IntDB: Atomic Claim: UPDATE bank_transactions SET status='SYNCING', sync_attempts=sync_attempts+1
    Sync->>Tx: POST /api/transactions (X-User-Id, Idempotency-Key)
    Tx->>Tx: Lưu transaction & kiểm tra Budget (WARNING >=80%, EXCEEDED >100%)
    Tx-->>Sync: 201 Created {data: {transaction, budget_alert}}
    Sync->>IntDB: UPDATE bank_transactions SET status='SYNCED', transaction_ref=id

    opt Có budget_alert (WARNING hoặc EXCEEDED)
        Sync->>Notif: POST /api/notifications/send (type="BUDGET_ALERT")
        Notif->>Notif: Lưu vào In-Memory store.js
        Notif->>Browser: sse.broadcastToUser() -> event: notification
        Notif->>Redis: Raw TCP RESP PUBLISH cashflow:notifications
        Redis-->>Notif: RESP message -> đồng bộ qua kênh Pub/Sub
        Browser->>Browser: Hiển thị Toast & tăng #notifBadge
    end
```

---

## Hình 2-12: Biểu đồ Máy trạng thái Vòng đời Giao dịch Ngân hàng (`bank_transactions.status`)

```mermaid
stateDiagram-v2
    [*] --> REJECTED : "DEBIT & balance < amount (Lưu audit, gửi TRANSACTION_REJECTED)"
    [*] --> RECEIVED : "Webhook hợp lệ (Cập nhật balance + version, enqueue vào syncer)"

    RECEIVED --> SYNCING : "Worker giành bản ghi (UPDATE WHERE status IN ('RECEIVED','FAILED') AND sync_attempts < 5)"
    FAILED --> SYNCING : "Sweeper (15s) nhặt lại sau 10s hoặc POST /bank-transactions/{id}/retry (reset sync_attempts=0)"

    SYNCING --> SYNCED : "Transaction Service trả 2xx (Gán transaction_ref, last_error=NULL)"
    SYNCING --> FAILED : "Lỗi HTTP/Timeout hoặc Sweeper giải cứu bản ghi kẹt SYNCING > 2 phút"

    SYNCED --> [*] : "Trạng thái hoàn tất"
    REJECTED --> [*] : "Trạng thái kết thúc (từ chối do thiếu số dư)"
```
