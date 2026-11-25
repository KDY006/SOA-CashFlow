# Kế hoạch Biên soạn Báo cáo Giữa kỳ & Chuẩn hóa Hệ thống Sơ đồ (Midterm — 100% Native Code)

## 1. Mục tiêu & Định hướng Thực hiện (Goal Description)

Dựa trên phản hồi của bạn và quyển báo cáo mẫu `BaoCao.pdf` của nhóm tại TDTU, kế hoạch được cập nhật toàn diện theo **3 nguyên tắc**:

1. **Văn phong & Kết cấu chuẩn học thuật TDTU (tham chiếu từ `BaoCao.pdf`):**
   - Không tạo trang bìa/lời cảm ơn hành chính (để bạn tự ghép vào mẫu Word/PDF), đi thẳng vào nội dung chuyên môn gồm: **Tóm tắt báo cáo**, **Danh mục Hình ảnh (`Hình X-Y`)**, **Danh mục Bảng biểu (`Bảng X.Y`)**, **4 Chương nội dung chi tiết**, **Phụ lục phân công đóng góp của 6 thành viên** và **Tài liệu tham khảo**.
   - Sau mỗi sơ đồ kỹ thuật (`Hình X-Y`) đều có mục **"Phân tích chi tiết Hình X-Y"** giải thích cặn kẽ luồng dữ liệu, ranh giới dịch vụ, quan hệ lớp, bản số ERD và cơ chế khóa tương tự phong cách `BaoCao.pdf`.
   - Đặc tả đầy đủ **Bảng từ điển dữ liệu (Data Dictionary)** và ràng buộc SQL (`PK`, `FK`, `UNIQUE`, `CHECK`, `INDEX`) cho toàn bộ **14 bảng trên 5 CSDL MySQL 8.0** của phiên bản Giữa kỳ.

2. **Chuẩn hóa & Bổ sung trọn bộ Sơ đồ Kỹ thuật tại `docs/diagrams/`:**
   - Qua kiểm tra thực tế, 2 tệp [architecture-diagram.png](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/architecture-diagram.png) và [usecase-diagram.png](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/usecase-diagram.png) hiện chỉ là **ảnh ô vuông màu xanh rỗng**, còn [erd_overall.png](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/erd_overall.png) đang vẽ Notification thành bảng SQL và nối khóa trực tiếp xuyên DB (chưa phản ánh đúng nguyên lý *Database-per-Service*).
   - Tôi sẽ xây dựng đầy đủ bộ mã nguồn sơ đồ chuẩn (**Mermaid + PlantUML `.puml`**) trong `docs/diagrams/` gồm: **Architecture Overview**, **Deployment Diagram (Docker 13 containers)**, **Use Case Tổng quát**, **Class / Module Diagrams (cả 7 dịch vụ Native)**, **ERD Toàn hệ thống & từng CSDL**, **Multi-tier Sequence Diagrams (3 luồng cốt lõi)** và **State Machine Diagram** (vòng đời trạng thái giao dịch ngân hàng).

3. **Chèn sẵn sơ đồ Mermaid & Note rõ ràng mọi vị trí chèn ảnh thực nghiệm:**
   - Đối với **Sơ đồ kỹ thuật**: Chèn trực tiếp khối sơ đồ `mermaid` ngay trong báo cáo, kèm khung ghi chú trỏ tới file nguồn trong `docs/diagrams/` để bạn có thể render ảnh độ phân giải cao khi đưa sang Word.
   - Đối với **Ảnh chụp màn hình thực nghiệm (Docker, Swagger UI, Giao diện Web iBanking, CSDL)**: Không tạo ảnh giả trước; đặt các khối `> 📸 **[VỊ TRÍ CHÈN HÌNH X-Y: ...]**` ghi rõ từng bước thao tác (URL/lệnh cần mở, dữ liệu nhập mẫu, điểm nhấn cần thấy trên màn hình) để bạn tự chụp và dán vào Word/PDF nhanh chóng.

---

## 2. Danh mục Tệp Tạo mới & Chỉnh sửa (Proposed Changes)

### Thành phần 1: Bộ Sơ đồ Kỹ thuật Hệ thống (`docs/diagrams/`)

> [!NOTE]
> Xóa 2 tệp ảnh xanh rỗng (`architecture-diagram.png`, `usecase-diagram.png`) để tránh nhầm lẫn, giữ nguyên các sơ đồ hợp lệ sẵn có của thành viên (`erd-integration.*`, `usecase-integration.*`, `erd-transaction-budget.md`, `useccase-erd-analyticsservice.png`) và tạo bộ 6 tệp sơ đồ chuẩn hóa bao phủ toàn dự án:

#### [DELETE] [architecture-diagram.png](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/architecture-diagram.png) & [usecase-diagram.png](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/usecase-diagram.png)
*(Hai tệp ảnh placeholder màu xanh lá trơn không có nội dung).*

#### [NEW] [01-architecture-and-deployment.md](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/01-architecture-and-deployment.md)
- **Sơ đồ 1 (`Hình 1-1`):** Kiến trúc Tổng thể Hệ thống SOA CashFlow bản Giữa kỳ (4 tầng: Client Web `:3000` & Swagger `:8080/docs` → Native API Gateway `:8080` → 6 Microservices đa ngôn ngữ `:8081..8086` → 5 MySQL 8.0 + 1 Redis 7 Pub/Sub, phân biệt luồng HTTP REST đồng bộ, Worker bất đồng bộ, Raw TCP RESP và SSE).
- **Sơ đồ 2 (`Hình 3-1`):** Biểu đồ Triển khai Vật lý Docker Compose (`Deployment Diagram` — Máy Host chạy PHP Web `:3000` giao tiếp với 13 Containers bên trong mạng bridge cô lập `cashflow-midterm-net`, ánh xạ cổng và volume khởi tạo `init.sql`).

#### [NEW] [02-usecase-overall.md](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/02-usecase-overall.md)
- **Sơ đồ (`Hình 1-2` — kèm mã nguồn PlantUML để vẽ ngang/dọc đẹp như `BaoCao.pdf`):** Biểu đồ Use Case Tổng quát toàn hệ thống với 3 tác nhân bên trái (*Người dùng cá nhân `USER`*, *Quản trị viên `ADMIN`*, *Đối tác Ngân hàng / Webhook*) và các tác nhân hệ thống phụ trợ, bao phủ 6 nhóm phân hệ chức năng cùng quan hệ `<<include>>` / `<<extend>>` (ví dụ: Tạo giao dịch chi tiêu `<<extend>>` Cảnh báo vượt hạn mức ngân sách; Nhận Webhook `<<include>>` Kiểm tra Idempotency & Đồng bộ nền).

#### [NEW] [03-class-diagrams-midterm.md](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/03-class-diagrams-midterm.md)
- **Bộ Biểu đồ Lớp & Cấu trúc Mô-đun Thiết kế (`Hình 2-2` → `Hình 2-8`)** bám sát đúng tên class/struct/method trong code thuần:
  1. **API Gateway (`Node.js Native`):** Tổ chức mô-đun `index.js` (`isRateLimited`, `sendJson`), `config.js` (`routeMap`, `publicEndpoints`), `jwt.js` (`verifyJwt`, `extractBearerToken`), `proxy.js` (`forwardRequest`), `docs.js` (`handleDocs`, `buildMergedSpec`).
  2. **Auth Service (`Java 17 OOP`):** Phân lớp `Main` → `RegisterHandler`, `LoginHandler`, `RefreshHandler`, `ValidateHandler`, `ProfileHandler`, `AdminUserHandler` → `AuthService` → `UserRepository`, `RefreshTokenRepository` → `DatabaseConfig`, `User`, `PasswordUtil` (`PBKDF2WithHmacSHA256`), `JwtUtil` (`HmacSHA256`), `MessageDigestUtil`.
  3. **Transaction Service (`PHP 8.2 MVC OOP`):** PSR-4 `index.php` → `TransactionController`, `BudgetController`, `CategoryController` → `Transaction`, `Budget`, `Category` → `Database` (PDO Singleton), `Auth`, `Response`.
  4. **Asset Service (`C# .NET 10 Native`):** `Program` (`HttpListener`) → `Router` (`DispatchAsync`) → `AssetHandler`, `LoanHandler`, `InvestmentHandler`, `AnalyticsHandler` → `Database`, `HttpHelper` + các Record DTO (`AssetInput`, `DepreciationInput`, `LoanInput`, `LoanCalculationInput`, `InvestmentInput`).
  5. **Analytics Service (`Python 3.11 Native`):** `ThreadingHTTPServer` → `AnalyticsHandler` (`do_GET`, `do_POST`, `_user_id`) → `_DatabaseConnection` (`PyMySQL`) + bộ hàm thống kê `summary()`, `category_breakdown()`, `trends()`, `anomalies()` (MAD).
  6. **Integration Service (`Go 1.22 Structs`):** `Server` → `keyedLocker` & `keyLock`, `syncer` (Worker Pool + `sweeper`), `notifier`, kiểu tiền tệ `money` (`int64`), `webhookRequest` / `webhookInput` / `bankTx`.
  7. **Notification Service (`Node.js 20 Native`):** `index.js` → `sse.js` (`userConnections: Map<uid, Set<res>>`, `registerClient`, `broadcastToUser`), `redisClient.js` (Raw TCP Socket `RESP`), `store.js` (In-Memory Notification Store).

#### [NEW] [04-erd-database-per-service.md](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/04-erd-database-per-service.md)
- **Mô hình ERD Chuẩn hóa theo Kiến trúc Database-per-Service (`Hình 2-9`):**
  - Vẽ đầy đủ **14 bảng vật lý trên 5 MySQL 8.0 độc lập**:
    - `auth_db` (2 bảng): `users`, `refresh_tokens` (`FK user_id`).
    - `transaction_db` (3 bảng): `categories`, `transactions` (`FK category_id`), `budgets` (`FK category_id`).
    - `asset_db` (4 bảng): `assets`, `asset_history` (`FK asset_id`), `loans`, `investments`.
    - `analytics_db` (1 bảng): `monthly_reports` (cột `report_json JSON`, `UNIQUE (user_id, year, month)`).
    - `integration_db` (4 bảng): `linked_accounts`, `bank_transactions` (`FK account_id`, 2 `UNIQUE KEY`), `exchange_rates`, `external_webhooks`.
  - Thể hiện rõ ranh giới cô lập giữa các CSDL: tuyệt đối không có `FOREIGN KEY` cứng xuyên CSDL; chỉ liên kết logic qua `user_id` và `transaction_ref`.

#### [NEW] [05-sequence-and-state-diagrams.md](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/diagrams/05-sequence-and-state-diagrams.md)
- **3 Biểu đồ Tuần tự Đa tầng (`Sequence Diagrams`):**
  - *`Hình 2-1` (Gateway Auth & Anti-IDOR):* Đăng nhập nhận JWT → Gửi request kèm Bearer Token → Gateway kiểm tra `timingSafeEqual` → Xóa header giả mạo & gắn `X-User-Id`, `X-User-Role` → Stream Piping xuống Microservice.
  - *`Hình 2-10` (Đồng bộ Analytics liên dịch vụ):* Client gọi `/api/analytics/aggregate` → Gateway → `Analytics Service` phân trang gọi `Transaction Service` → Tính toán MAD & dự báo xu hướng → Upsert vào bảng `monthly_reports` (`analytics_db`).
  - *`Hình 2-11` (Luồng Webhook Đồng thời, Đồng bộ Nền & Cảnh báo Realtime):* Ngân hàng gửi `POST /webhooks/banking` → `Integration Service` khóa `keyedLocker` + `SELECT ... FOR UPDATE`, đối chiếu `Idempotency-Key` & `SHA-256 request_hash` → Trả `201 Created` tức thì → Background Worker giành bản ghi `SYNCING` đẩy sang `Transaction Service` → Nhận `budget_alert` (`EXCEEDED`) → Gọi `Notification Service` → Phát qua Redis `RESP` & đẩy tức thời qua kênh `SSE` tới trình duyệt.
- **1 Biểu đồ Máy trạng thái (`State Diagram` — `Hình 2-12`):** Vòng đời trạng thái của thực thể `bank_transactions` (`RECEIVED` → `SYNCING` → `SYNCED` / `FAILED` → cơ chế `sweeper` giải cứu bản ghi kẹt quá 2 phút và thử lại tối đa 5 lần, hoặc `REJECTED` khi không đủ số dư).

---

### Thành phần 2: Tài liệu Báo cáo Chi tiết Giữa kỳ (`docs/reports/`)

#### [NEW] [Bao-Cao-Giua-Ky.md](file:///d:/3.1.%20SOA/SOA-CashFlow/docs/reports/Bao-Cao-Giua-Ky.md)

Tài liệu Markdown hoàn chỉnh sẵn sàng để bạn chuyển sang Word/PDF, được tổ chức chặt chẽ gồm các phần sau:

1. **Phần Mở đầu Chuyên môn:**
   - **Tóm tắt báo cáo** (tóm lược nội dung 4 chương theo văn phong trang `iii–iv` của `BaoCao.pdf`).
   - **Danh mục Hình ảnh** (`Hình 1-1` → `Hình 3-11`, phân biệt rõ sơ đồ kiến trúc/UML và ảnh chụp màn hình thực nghiệm).
   - **Danh mục Bảng biểu** (`Bảng 1.1` → `Bảng 4.1`).

2. **Chương 1: Tổng quan Bài toán & Kiến trúc Hệ thống**
   - `1.1. Đặt vấn đề & Mục tiêu dự án`: Phân tích 4 bất cập thực tế của quản lý tài chính cá nhân rời rạc, bảng đối chiếu *As-Is vs To-Be*, phạm vi thực hiện và mục tiêu cốt lõi của phiên bản Giữa kỳ (**100% Native Code / Zero-Framework**).
   - `1.2. Kiến trúc tổng thể (Architecture Overview)`: Sơ đồ khối tổng thể (`Hình 1-1`), Biểu đồ Use Case tổng quát (`Hình 1-2`), nguyên tắc *Database-per-Service* (5 MySQL 8.0 + 1 Redis 7) và phân tích chi tiết các luồng giao tiếp Bắc–Nam / Đông–Tây.
   - `1.3. Công nghệ sử dụng & Phân công nhiệm vụ`: Bảng đặc tả Stack công nghệ thuần của 7 dịch vụ + Frontend PHP, cơ chế cấu hình biến môi trường linh hoạt Docker/Local và bảng phân công nhiệm vụ 6 thành viên.

3. **Chương 2: Thiết kế & Hiện thực các Dịch vụ**
   - `2.1. API Gateway & Cơ chế định tuyến / xác thực`: Pipeline 6 bước xử lý request tại `gateway/midterm/`, thuật toán Sliding Window Rate Limiting, kiểm chứng chữ ký JWT `HMAC-SHA256` hằng thời gian (`crypto.timingSafeEqual`), cơ chế loại bỏ Header giả mạo chống IDOR, Streaming Reverse Proxy không đệm RAM (`pipe`) và xử lý riêng `timeout: 0` cho luồng SSE (kèm `Hình 2-1` Sequence & `Hình 2-2` Module Diagram).
   - `2.2. Chi tiết 6 Microservices nghiệp vụ` (`2.2.1` Auth Java 17, `2.2.2` Transaction & Budget PHP 8.2, `2.2.3` Asset & Loan C# .NET 10, `2.2.4` Analytics Python 3.11, `2.2.5` Integration & Concurrency Go 1.22, `2.2.6` Notification Node.js 20):
     - Mỗi dịch vụ trình bày thống nhất theo cấu trúc chuẩn: **(a) Kiến trúc lớp/mô-đun & Biểu đồ Class (`Hình 2-3` → `Hình 2-8`)**, **(b) Endpoints tiêu biểu**, **(c) Thiết kế CSDL riêng & Bảng từ điển dữ liệu chi tiết (`Hình 2-9`)**, **(d) Giải thuật nghiệp vụ & xử lý kỹ thuật tầng thấp kèm trích đoạn code thực tế** (PBKDF2 210.000 vòng lặp, PDO Native Prepared Statements + Cảnh báo ngân sách 80%/100%, Khấu hao tuyến tính + Công thức trả góp EMI, Phát hiện bất thường MAD + Dự báo trượt 3 tháng, Khóa 2 tầng `keyedLocker` + `SELECT FOR UPDATE` + Idempotency 3 lớp, SSE Heartbeat + Raw TCP Redis `RESP`).
   - `2.3. Giao tiếp liên dịch vụ & Xử lý ràng buộc`: Phân tích mô hình kéo đồng bộ (Analytics → Transaction) và mô hình đẩy bất đồng bộ qua Worker Pool + Sweeper tự phục hồi lỗi (Integration → Transaction → Notification → Redis/SSE), kèm `Hình 2-10`, `Hình 2-11` (Sequence) và `Hình 2-12` (State Machine), cùng chiến lược chịu lỗi khởi động CSDL và đảm bảo nhất quán dữ liệu phân tán (*Eventual Consistency*).

4. **Chương 3: Đóng gói, Triển khai (Docker) & Kiểm thử Hệ thống**
   - `3.1. Đóng gói và điều phối bằng Docker Compose`: Phân tích kỹ thuật Multi-stage Build cho Java/C#/Go và Alpine/Slim cho Node.js/PHP/Python; điều phối 13 containers trên mạng `cashflow-midterm-net`, cơ chế nạp `init.sql`, ánh xạ cổng và thứ tự `depends_on` (kèm `Hình 3-1` Deployment Diagram & ghi chú chụp `docker ps`).
   - `3.2. Các vấn đề thực tế khi triển khai & Cách khắc phục`: Phân tích sâu 6 sự cố kỹ thuật thực tế (C# `HttpListener` binding `+`, độ trễ IPv6 `localhost` ~200ms trên Windows, trễ khởi tạo MySQL 8.0 & đọng volume cũ, `allowPublicKeyRetrieval` & `SslMode` & `utf8mb4`, ngắt kết nối SSE qua Proxy, đồng bộ hash PBKDF2 trong `init-auth-db.sql`) và giải pháp triệt để đã áp dụng.
   - `3.3. Kiểm thử tích hợp API qua Swagger UI tập trung & Tự động hóa`:
     - Phân tích kiến trúc Swagger UI tự hiện thực trên Native Gateway (`src/docs.js` gộp 6 đặc tả OpenAPI 3.0.3 + thanh đăng nhập nhanh tự nạp Bearer Token).
     - Kịch bản kiểm thử 5 luồng nghiệp vụ trọng tâm trực tiếp trên Swagger UI (kèm các khung ghi chú chụp màn hình cụ thể: Đăng nhập & kiểm tra `password_hash` trong DB, tạo giao dịch nhận `budget_alert`, tính EMI/Net Worth, chạy `/api/integrations/simulate` đối chiếu `safe` lệch 0 đồng vs `unsafe` lệch số dư), kết hợp bổ trợ chạy tự động Newman CLI / Go `loadtest`.
   - `3.4. Kiểm thử trên Giao diện Web iBanking`: Phân tích kiến trúc Frontend PHP không chạm DB (`api.php` ẩn JWT trong Server Session + chống CSRF, tự động làm mới token ngầm, gọi song song đa dịch vụ bằng `curl_multi` `apiAll()`); trình bày luồng điều hướng và đặt đầy đủ **khung ghi chú vị trí chụp màn hình thực nghiệm** cho các giao diện chính (Đăng nhập, Dashboard tổng hợp, Giao dịch, Ngân sách, Tài sản & Tính lãi vay, Phân tích dòng tiền, Liên kết ngân hàng, Thông báo realtime SSE và Quản trị người dùng).

5. **Chương 4: Đánh giá Kết quả & Kế hoạch Giai đoạn Tiếp theo**
   - `4.1. Tổng kết những gì đã đạt được`: Bảng đối chiếu mức độ hoàn thành 100% các mục tiêu kiến trúc, bảo mật, nghiệp vụ và triển khai giữa kỳ.
   - `4.2. Hạn chế còn tồn tại`: Nhìn nhận khách quan các giới hạn của code thuần (thiếu Connection Pool chuyên dụng, PHP single-thread built-in server, xác thực JWT tập trung ở biên Gateway, lưu trữ Notification In-Memory, chưa có quy trình OTP và `healthcheck` cứng ở tầng Compose).
   - `4.3. Hướng phát triển cho báo cáo cuối kỳ`: Lộ trình nâng cấp lên 7 Frameworks chuẩn công nghiệp (Express, Spring Boot 3.5, Laravel 12, ASP.NET Core 8, FastAPI, Go Gin + GORM, Socket.io + `ioredis`), tích hợp quy trình xác thực giao dịch bằng OTP 6 số, bảo mật JWT đa lớp và chuẩn hóa CI/CD + Docker Healthcheck.

6. **Phụ lục Phân chia Công việc Chi tiết (6 thành viên) & Tài liệu Tham khảo:** Trình bày rõ khối lượng đóng góp của từng thành viên theo chuẩn Chương 9 của `BaoCao.pdf` và bảng tài liệu tham khảo chính thống (RFC 7519 JWT, NIST SP 800-132 PBKDF2, Redis RESP Protocol, MySQL 8.0 InnoDB Locking, OpenAPI 3.0.3...).

---

## 3. Kế hoạch Kiểm chứng Chất lượng (Verification Plan)

1. **Kiểm tra chéo tự động với mã nguồn:** Chạy script kiểm tra toàn bộ tên file, class, hàm, biến môi trường, cổng dịch vụ, tên bảng/cột SQL và đường dẫn API xuất hiện trong `docs/reports/Bao-Cao-Giua-Ky.md` và `docs/diagrams/*.md` khớp 100% với code thực tế trong `midterm/`.
2. **Kiểm tra cú pháp sơ đồ Mermaid & Liên kết:** Quét toàn bộ khối `mermaid` (đảm bảo trích dẫn nhãn có ký tự đặc biệt đúng chuẩn, không lỗi cú pháp) và kiểm tra mọi liên kết tệp đều hợp lệ.
