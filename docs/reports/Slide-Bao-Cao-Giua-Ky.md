# DÀN Ý SLIDE BÁO CÁO GIỮA KỲ: ĐỒ ÁN KIẾN TRÚC HƯỚNG DỊCH VỤ (SOA)
**Đề tài:** Hệ thống Quản trị Dòng tiền & Tài chính Cá nhân/Doanh nghiệp (`SOA-CashFlow`)  
**Nhóm thực hiện:** Nhóm SOA CashFlow  
**Thời lượng trình bày:** 15 – 20 phút (12 Slides chuẩn)

---

## SLIDE 1: TRANG BÌA (TITLE SLIDE)
* **Tiêu đề lớn:** HỆ THỐNG QUẢN TRỊ DÒNG TIỀN VÀ TÀI CHÍNH PHÂN TÁN (SOA-CASHFLOW)
* **Tiêu đề phụ:** Báo cáo Tiến độ Giữa kỳ: Hiện thực hóa Kiến trúc Microservices Đa ngôn ngữ (Native Implementation)
* **Học phần:** Kiến trúc Hướng Dịch vụ (Service-Oriented Architecture - SOA)
* **Giảng viên hướng dẫn:** [Tên Giảng viên]
* **Nhóm sinh viên thực hiện:**
  - **Nhóm trưởng:** Architecture, API Gateway, DevOps & Notification Service (Node.js Native)
  - **Thành viên 1:** Auth Service (Java Native)
  - **Thành viên 2:** Transaction & Budget Service (PHP Native)
  - **Thành viên 3:** Asset & Loan Service (C# Native)
  - **Thành viên 4:** Analytics Service (Python Native)
  - **Thành viên 5:** Integration Service (Go Native)

---

## SLIDE 2: ĐẶT VẤN ĐỀ & MỤC TIÊU DỰ ÁN
* **Bối cảnh thực tế:**
  - Dòng tiền cá nhân/doanh nghiệp thường phân tán ở nhiều tài khoản, danh mục đầu tư và khoản nợ vay.
  - Các hệ thống Monolith cũ khó mở rộng độc lập khi tính năng phân tích tài chính hoặc thông báo đòi hỏi tải cao.
* **Mục tiêu của SOA-CashFlow:**
  - Xây dựng hệ thống tài chính phân tán theo đúng chuẩn Service-Oriented Architecture (SOA).
  - Tách biệt ranh giới nghiệp vụ (Bounded Contexts): Xác thực, Sổ cái giao dịch, Tài sản nợ, Phân tích dữ liệu, Tích hợp tỷ giá, và Kênh thông báo tức thời.
  - **Mục tiêu Giữa kỳ:** Tự xây dựng toàn bộ hệ thống bằng **Code thuần (Native/No-framework)** trên 6 ngôn ngữ để hiểu tường tận cơ chế tầng thấp của giao thức HTTP, luồng socket và mạng phân tán.

---

## SLIDE 3: TỔNG QUAN KIẾN TRÚC HỆ THỐNG (SOA ARCHITECTURE)
* **Mô hình kiến trúc tổng thể:**
  - **Single Entry Point:** API Gateway (Node.js Native - Port 8080).
  - **6 Microservices độc lập (6 Ngôn ngữ):**
    1. `Auth Service` (Java 17 - Port 8081)
    2. `Transaction Service` (PHP 8.2 - Port 8082)
    3. `Asset Service` (C# .NET - Port 8083)
    4. `Analytics Service` (Python 3.11 - Port 8084)
    5. `Integration Service` (Go 1.21 - Port 8085)
    6. `Notification Service` (Node.js 20 - Port 8086)
  - **Cơ sở dữ liệu đa dạng (Polyglot Persistence):** 4 MySQL 8.0 riêng lẻ + 1 MongoDB 6.0 + 1 Redis 7 Pub/Sub.
* *[Hình minh họa: Sơ đồ luồng kết nối từ Client qua Gateway tới 6 Services và cụm Databases]*

---

## SLIDE 4: API GATEWAY - TRÁCH NHIỆM NHÓM TRƯỞNG
* **Công nghệ:** Native Node.js thuần (Zero-dependency - `node:http`, `node:crypto`, `node:url`).
* **Các tính năng cốt lõi đã hoàn thành:**
  - **Streaming Reverse Proxy:** Chuyển tiếp request/response dạng stream (`pipe`) hiệu năng cao, không lưu đệm RAM.
  - **Native JWT Guard:** Tự giải mã và kiểm tra chữ ký HMAC-SHA256 bằng thuật toán hằng thời gian (`timingSafeEqual`) chống Timing Attack.
  - **Khắc phục lỗi IDOR:** Tự động trích xuất `userId` từ token và inject header `X-User-Id` xuống các microservices nội bộ.
  - **Xử lý CORS & Rate Limiting:** Xử lý preflight `OPTIONS` toàn cục và giới hạn 300 req/phút/IP chống DoS.

---

## SLIDE 5: NOTIFICATION SERVICE - TRÁCH NHIỆM NHÓM TRƯỞNG
* **Công nghệ:** Native Node.js thuần (`node:http`, `node:net`, `node:url`).
* **Các tính năng cốt lõi đã hoàn thành:**
  - **Kênh Realtime Server-Sent Events (SSE):** Endpoint `GET /api/notifications/stream` đẩy cảnh báo tức thời dạng event stream tới trình duyệt, có cơ chế heartbeat giữ kết nối TCP.
  - **Redis Pub/Sub Subscriber:** Tiếp nhận sự kiện cảnh báo bất đồng bộ từ Transaction và Analytics Service qua kênh `cashflow:notifications`.
  - **Khả năng tự thích ứng (Resilience):** Hỗ trợ `ioredis` và tự động fallback sang kết nối **Native TCP Socket (`node:net`)** qua giao thức RESP thuần nếu môi trường chưa cài thư viện ngoài.
  - **Quản lý Lưu trữ:** In-memory Notification Store lưu trữ lịch sử, trạng thái đọc/chưa đọc và nạp sẵn dữ liệu mẫu.

---

## SLIDE 6: AUTH SERVICE - THÀNH VIÊN 1 (JAVA NATIVE)
* **Công nghệ:** Java 17 SE, `com.sun.net.httpserver.HttpServer`, JDBC thuần, MySQL `auth_db`.
* **Điểm nổi bật:**
  - Kiến trúc phân tầng mẫu mực: `handler`, `service`, `repository`, `model`, `util`.
  - **Bảo mật mật khẩu cao cấp (OWASP):** Dùng thuật toán **PBKDF2WithHmacSHA256** với **210,000 vòng lặp**, muối ngẫu nhiên 16 byte và so sánh hằng thời gian `MessageDigest.isEqual`.
  - Tự hiện thực hóa chuẩn mã hóa JWT HS256 và cơ chế xoay vòng Refresh Token (7 ngày) lưu trong database.

---

## SLIDE 7: TRANSACTION & BUDGET SERVICE - THÀNH VIÊN 2 (PHP NATIVE)
* **Công nghệ:** PHP 8.2 thuần, PDO Singleton, MySQL `transaction_db`.
* **Điểm nổi bật:**
  - Áp dụng mô hình MVC hướng đối tượng (OOP), tự viết Autoloader chuẩn PSR-4.
  - 100% Prepared Statements chống triệt để SQL Injection.
  - **Cơ chế Cảnh báo Ngân sách Chủ động (Budget Alerts):** Mỗi khi phát sinh giao dịch chi tiêu (`EXPENSE`), hệ thống tự động kiểm tra định mức:
    - Trả về `WARNING` khi chi tiêu $\ge 80\%$ hạn mức.
    - Trả về `EXCEEDED` kèm số tiền bội chi khi vượt ngưỡng.

---

## SLIDE 8: ASSET & LOAN SERVICE - THÀNH VIÊN 3 (C# NATIVE)
* **Công nghệ:** C# Native (`System.Net.HttpListener`), ADO.NET thuần, MySQL `asset_db`.
* **Điểm nổi bật:**
  - Quản lý danh mục tài sản, khoản vay và danh mục đầu tư.
  - **Thuật toán tài chính nghiệp vụ:**
    - Tính khấu hao tài sản tuyến tính: `(PurchasePrice - SalvageValue) / UsefulLifeYears`.
    - Tính lãi vay trả góp định kỳ (Amortization Schedule).
    - Tính toán tổng giá trị tài sản ròng: $\text{Net Worth} = \sum \text{Assets} - \sum \text{Loans}$.

---

## SLIDE 9: ANALYTICS SERVICE - THÀNH VIÊN 4 (PYTHON NATIVE)
* **Công nghệ:** Python 3.11 Standard Library (`http.server.ThreadingHTTPServer`, `urllib`), MongoDB `analytics_db`.
* **Điểm nổi bật:**
  - Giao tiếp liên dịch vụ: Tự động gọi sang Transaction Service để lấy dữ liệu thu chi.
  - Tuân thủ nghiệp vụ dòng tiền: Loại trừ giao dịch `TRANSFER` để tránh tính trùng.
  - **Phát hiện Chi tiêu Bất thường (Anomaly Detection):** Áp dụng kỹ thuật thống kê bền vững **Median Absolute Deviation (MAD)** với ngưỡng $3 \times 1.4826 \times \text{MAD}$ thay vì Mean thông thường.
  - Dự báo dòng tiền tháng kế tiếp theo mô hình trung bình trượt 3 tháng gần nhất (Trailing 3-month Average).

---

## SLIDE 10: INTEGRATION SERVICE - THÀNH VIÊN 5 (GO NATIVE)
* **Công nghệ:** Go 1.21 Native (`net/http`), MySQL `integration_db`.
* **Điểm nổi bật:**
  - Microservice siêu nhẹ phục vụ tỷ giá ngoại tệ (USD, EUR, JPY) quy đổi sang VND.
  - Sẵn sàng endpoint Webhook đón nhận biến động số dư từ ngân hàng đối tác.
  - Tốc độ xử lý mili-giây và tiêu tốn cực ít tài nguyên RAM.

---

## SLIDE 11: DEVOPS & DOCKER ORCHESTRATION - TRÁCH NHIỆM NHÓM TRƯỞNG
* **Điều phối tập trung:** File `docker-compose.midterm.yml` chuẩn hóa **13 containers**:
  - 7 Application Containers + 6 Database/Broker Containers.
  - 1 Mạng nội bộ bảo mật (`cashflow-midterm-net`).
* **Các vấn đề kỹ thuật lớn đã khắc phục:**
  - Sửa toàn bộ đường dẫn Build Context trỏ đúng vào thư mục `midterm/` của từng service.
  - Sửa lỗi Docker Host Binding (`LISTEN_HOST=0.0.0.0`) của C# Asset Service.
  - Đồng bộ hóa cấu trúc Schema CSDL (`init-asset-db.sql`) và mã băm mật khẩu PBKDF2 cho tài khoản khởi tạo ban đầu.
* **Khởi động toàn bộ đồ án chỉ bằng 1 câu lệnh:**
  ```bash
  docker-compose -f docker-compose.midterm.yml up -d --build
  ```

---

## SLIDE 12: ĐÁNH GIÁ GIỮA KỲ & LỘ TRÌNH CUỐI KỲ (FINAL ROADMAP)
* **Bài học rút ra từ giai đoạn Code thuần (Giữa kỳ):**
  - Hiểu sâu bản chất giao thức HTTP, Streams, Sockets, Threading Model và chi phí quản lý kết nối CSDL (Connection Pooling).
  - Thấy rõ rủi ro khi tự viết xác thực hoặc thiếu Transaction trong bài toán tài chính.
* **Lộ trình Chuyển dịch sang Frameworks chuẩn (Giai đoạn Cuối kỳ):**
  - **Gateway:** Kong API Gateway hoặc Express / NestJS Gateway.
  - **Auth:** Spring Boot 3 + Spring Security + JWT Bearer.
  - **Transaction:** Laravel 11 + Eloquent ORM + DB Transactions.
  - **Asset:** ASP.NET Core Web API + Entity Framework Core / Dapper.
  - **Analytics:** FastAPI + Uvicorn + Pydantic AsyncIO.
  - **Integration:** Gin Web Framework (Go).
  - **Notification:** NestJS + Socket.io + BullMQ.
* **Cảm ơn Thầy Cô và các bạn đã lắng nghe! (Q&A)**
