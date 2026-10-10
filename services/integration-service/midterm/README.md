# Integration Service (Go)

- **Phụ trách:** Trần Hữu Long - 52400138 (Thành viên 5)
- **Giữa kỳ:** Go thuần `net/http` + `database/sql` (driver `go-sql-driver/mysql`), không dùng framework
- **Database:** MySQL `integration_db`
- **Port:** `8085`

Service đóng vai trò cổng nhận biến động số dư từ ngân hàng / ví điện tử (giả lập), ghi nhận vào hệ thống
và đẩy sang `transaction-service`. Đây cũng là nơi xử lý 2 bài toán concurrency của đề:

1. nhiều giao dịch cùng lúc trên 1 tài khoản -> không được ghi sai số dư (race condition)
2. ngân hàng gửi lại cùng 1 giao dịch nhiều lần -> chỉ ghi nhận đúng 1 lần (idempotency)

## Cấu trúc thư mục

```
midterm/
├── Dockerfile
├── go.mod / go.sum
├── src/
│   ├── main.go         khởi động server, tắt êm khi nhận SIGTERM
│   ├── server.go       khai báo route (ServeMux của go 1.22), health check
│   ├── config.go       đọc biến môi trường
│   ├── db.go           kết nối mysql, thử lại khi db chưa lên, nhận diện lỗi 1062/1213
│   ├── http.go         helper trả json, middleware log + recover + cors
│   ├── money.go        kiểu tiền dạng số nguyên (1/100 đồng), tránh sai số float
│   ├── locker.go       mutex theo từng tài khoản
│   ├── webhook.go      nhận webhook ngân hàng: khóa, chống trùng, cập nhật số dư
│   ├── sync.go         worker pool đẩy giao dịch sang transaction-service + quét thử lại
│   ├── notifier.go     gửi cảnh báo sang notification-service
│   ├── accounts.go     tài khoản liên kết và lịch sử biến động
│   ├── rates.go        tỷ giá, đồng bộ song song từ nhiều ngân hàng
│   ├── simulate.go     giả lập ngân hàng bắn dồn dập để demo concurrency
│   └── main_test.go    unit test
└── tools/loadtest/     tool bắn request đồng thời qua http để kiểm chứng
```

## Chạy

```bash
# cùng cả hệ thống
docker compose -f docker-compose.midterm.yml up --build integration-service

# chạy riêng ở máy (cần mysql đã import db/init-integration-db.sql)
cd services/integration-service/midterm
DB_HOST=localhost DB_PASSWORD=xxx go run ./src

# unit test
go test ./src/
```

| Biến môi trường | Mặc định | Ghi chú |
|---|---|---|
| `PORT` | `8085` | |
| `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` | `localhost` / `3306` / `integration_db` / `root` / `root_password` | |
| `TRANSACTION_SERVICE_URL` | `http://localhost:8082` | nơi đẩy giao dịch sang |
| `NOTIFICATION_SERVICE_URL` | `http://localhost:8086` | nơi gửi cảnh báo |
| `WEBHOOK_SECRET` | rỗng | có giá trị thì bắt buộc header `X-Signature` = HMAC-SHA256(body) |
| `SYNC_WORKERS` | `4` | số goroutine đồng bộ sang transaction-service |
| `LARGE_AMOUNT_ALERT` | `10000000` | biến động từ mức này trở lên thì gửi cảnh báo |

## Cơ sở dữ liệu

| Bảng | Nội dung |
|---|---|
| `linked_accounts` | tài khoản ngân hàng/ví người dùng liên kết, giữ số dư. `UNIQUE(bank_code, account_no)`, `CHECK(balance >= 0)` |
| `bank_transactions` | từng biến động nhận được. `UNIQUE(idempotency_key)` và `UNIQUE(provider, external_ref)` để chống ghi trùng |
| `exchange_rates` | tỷ giá ngoại tệ sang VND |
| `external_webhooks` | log thô mọi lần ngân hàng gọi vào (kể cả bị trùng) để đối soát |

Trạng thái của `bank_transactions.status`:

```
RECEIVED --(worker nhận)--> SYNCING --(php trả 2xx)--> SYNCED
                               \--(lỗi)--> FAILED --(sweeper, tối đa 5 lần)--> SYNCING
REJECTED: giao dịch trừ tiền nhưng số dư không đủ (không đồng bộ đi đâu cả)
```

ERD chi tiết: [docs/diagrams/erd-integration.md](../../../docs/diagrams/erd-integration.md)

## Danh sách API

Mọi response đều có dạng `{ "success": bool, "message": "...", "data": ... }`, lỗi thì có thêm `"error": "MA_LOI"`.
Sai method trên đường dẫn có tồn tại thì trả `405` kèm header `Allow`, đường dẫn không tồn tại thì `404`.

**Phân quyền theo user:** request đi qua gateway luôn có header `X-User-Id` (lấy từ JWT). Khi có header này:
- `user_id` trên query / body bị bỏ qua, luôn dùng user đang đăng nhập
- xem tài khoản, lịch sử, giao dịch của người khác -> `404` (không trả 403 để không lộ id có tồn tại)

Gọi thẳng vào cổng 8085 (không có header) thì mới dùng `?user_id=` để tiện test bằng Postman.

| Method | URI | Mô tả |
|---|---|---|
| GET | `/health` | tình trạng service, db, hàng đợi |
| GET | `/api/integrations/rates?currency=USD` | tỷ giá hiện tại |
| POST | `/api/integrations/sync-rates` | lấy tỷ giá song song từ VCB/TCB/BIDV (giả lập), lấy trung bình |
| GET | `/api/integrations/accounts?user_id=1` | danh sách tài khoản liên kết |
| POST | `/api/integrations/accounts` | liên kết tài khoản mới |
| GET | `/api/integrations/accounts/{id}` | chi tiết tài khoản + số dư |
| GET | `/api/integrations/accounts/{id}/transactions?status=&limit=` | lịch sử biến động |
| POST | `/api/integrations/webhooks/banking` | **ngân hàng gọi vào khi có biến động** |
| GET | `/api/integrations/bank-transactions/{id}` | chi tiết 1 biến động |
| POST | `/api/integrations/bank-transactions/{id}/retry` | đẩy lại giao dịch FAILED sang transaction-service |
| POST | `/api/integrations/simulate` | giả lập ngân hàng bắn đồng thời, trả về kết quả đối chiếu |

> Webhook do ngân hàng gọi nên không có JWT. Gateway hiện chỉ mở công khai `/api/integrations/rates`,
> vì vậy khi demo thì gọi webhook thẳng vào cổng `8085` (đã có chữ ký `X-Signature` bảo vệ),
> hoặc nhờ nhóm trưởng thêm `POST /api/integrations/webhooks/banking` vào danh sách public của gateway.

### POST /api/integrations/webhooks/banking

Header tùy chọn: `Idempotency-Key`, `X-Signature`.

```json
{
  "provider": "VCB",
  "external_ref": "FT26283000123",
  "bank_code": "VCB",
  "account_no": "0071000123456",
  "direction": "DEBIT",
  "amount": 250000,
  "category": "Ăn uống & Cà phê",
  "description": "THANH TOAN GRABFOOD",
  "occurred_at": "2026-10-10 12:30:00"
}
```

| Status | Khi nào |
|---|---|
| `201` | ghi nhận mới, số dư đã cập nhật |
| `200` | giao dịch này đã nhận trước đó, trả lại đúng bản ghi cũ, `data.duplicate = true` |
| `422` | sai dữ liệu (`VALIDATION_ERROR`) hoặc số dư không đủ (`transaction.status = REJECTED`) |
| `404` | không có tài khoản liên kết |
| `409` | dùng lại Idempotency-Key cho một giao dịch có nội dung khác |
| `401` | sai chữ ký (khi có bật `WEBHOOK_SECRET`) |

```json
{
  "success": true,
  "message": "ghi nhận biến động số dư thành công",
  "data": {
    "duplicate": false,
    "transaction": {
      "id": 15, "account_id": 1, "provider": "VCB", "external_ref": "FT26283000123",
      "idempotency_key": "VCB:FT26283000123", "direction": "DEBIT",
      "amount": 250000.00, "balance_after": 14750000.00, "status": "RECEIVED",
      "transaction_ref": null, "sync_attempts": 0
    }
  }
}
```

### POST /api/integrations/accounts

```json
{ "user_id": 1, "bank_code": "ACB", "account_no": "123456789", "holder_name": "Nguyen Van A", "initial_balance": 1000000 }
```

`201` tạo thành công, `409` tài khoản đã liên kết, `422` sai dữ liệu.

### POST /api/integrations/simulate

```json
{ "account_id": 1, "events": 100, "workers": 20, "duplicate_rate": 0.5, "mode": "safe" }
```

- `events`: số giao dịch khác nhau, `duplicate_rate`: thêm bao nhiêu % request trùng
- `mode`: `safe` (mặc định) hoặc `unsafe` (tắt hết cơ chế khóa, chỉ để so sánh khi báo cáo)
- `sync`: mặc định `true` ở chế độ safe, tức là các giao dịch giả lập cũng được đẩy sang transaction-service.
  Truyền `"sync": false` nếu không muốn làm bẩn dữ liệu bên php. Chế độ `unsafe` không bao giờ đồng bộ.

Kết quả trả về `balance_before`, `balance_after`, `expected_balance`, `difference`, `stored_records`, `consistent`.

## Xử lý concurrency

### 1. Race condition khi nhiều giao dịch vào cùng 1 tài khoản

Nếu làm kiểu đọc số dư -> tính -> ghi lại, 2 request chạy song song cùng đọc được số dư cũ,
request ghi sau đè mất kết quả của request ghi trước (lost update).

Cách xử lý trong `applyWebhook` (webhook.go), 2 lớp:

1. **Mutex theo tài khoản** (`keyedLocker`): request cùng tài khoản xếp hàng ngay trong process,
   khác tài khoản vẫn chạy song song. Giúp giảm tranh chấp lock dưới db.
2. **`SELECT ... FOR UPDATE` trong transaction**: khóa dòng tài khoản tới khi commit.
   Lớp này mới là đảm bảo thật sự, vì nếu chạy nhiều instance thì mutex trong process không còn tác dụng.

Thêm vào đó:
- transaction chạy mức `READ COMMITTED` để tránh gap lock gây deadlock khi insert song song
- gặp deadlock (1213) hoặc chờ lock quá lâu (1205) thì tự chạy lại tối đa 3 lần
- số tiền tính bằng số nguyên (`money`), không dùng float
- `CHECK (balance >= 0)` dưới db, trừ tiền khi không đủ số dư thì giao dịch bị `REJECTED`

### 2. Idempotency khi ngân hàng gửi lại

- key = header `Idempotency-Key`, nếu không có thì là `provider:external_ref`
- sau khi đã giữ khóa tài khoản mới kiểm tra key -> 2 request trùng không thể cùng lọt qua
- đã có thì trả lại đúng bản ghi cũ (`200`, `duplicate: true`), không cộng tiền lần 2
- cùng key mà nội dung khác (so bằng sha256 của các trường chính) thì trả `409`
- chốt chặn cuối là 2 unique key dưới db, lỡ có trường hợp lọt thì insert lỗi 1062 -> rollback cả phần cập nhật số dư

### 3. Đồng bộ sang transaction-service không bị đẩy trùng

- webhook trả lời ngân hàng ngay, việc gọi sang php do worker pool chạy nền (`sync.go`), nên không bị nghẽn khi tải cao
- worker "giành" bản ghi bằng `UPDATE ... SET status='SYNCING' WHERE id=? AND status IN ('RECEIVED','FAILED')`,
  chỉ 1 worker update thành công nên 1 giao dịch không bị gửi 2 lần
- lỗi thì chuyển `FAILED`, sweeper 15 giây quét 1 lần để thử lại (tối đa 5 lần)
- transaction-service trả `budget_alert` thì chuyển tiếp sang notification-service

Hạn chế còn lại: nếu transaction-service đã lưu xong nhưng chết trước khi trả response,
lần thử lại sẽ tạo trùng bên php. Service đã gửi kèm header `Idempotency-Key`,
bên transaction-service chỉ cần lưu key này với unique là chặn được hoàn toàn (dự kiến làm ở cuối kỳ).

## Kiểm chứng

**Cách 1 - gọi api simulate** (dễ demo bằng postman):

```bash
# có khóa: consistent = true, difference = 0
curl -X POST localhost:8085/api/integrations/simulate -d '{"account_id":1,"events":200,"workers":50,"duplicate_rate":0.5}'

# tắt khóa để so sánh: consistent = false, số dư bị lệch
curl -X POST localhost:8085/api/integrations/simulate -d '{"account_id":3,"events":200,"workers":50,"duplicate_rate":0.5,"mode":"unsafe"}'
```

**Cách 2 - load test qua http** (giống ngân hàng gọi thật):

```bash
go run ./tools/loadtest -url http://localhost:8085 -account 1 -n 300 -dup 3 -c 100
```

Tool gửi mỗi giao dịch 3 lần, trộn lẫn, 100 luồng song song, sau đó so số dư thực tế với số dư tính tay.
Thêm `-secret xxx` nếu service bật `WEBHOOK_SECRET`, thêm `-token <jwt>` nếu bắn qua gateway.

> Lưu ý: trong lúc chạy kiểm chứng không nên có request khác cùng ghi vào tài khoản đó, nếu không số dư mong đợi sẽ không chính xác.
