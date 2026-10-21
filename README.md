# 💰 SOA CashFlow — Hệ thống Quản lý Tài chính & Phân tích Dòng tiền

> **Môn học:** Kiến trúc Hướng Dịch vụ (SOA) — Trường Đại học Tôn Đức Thắng (TDTU)  
> **Kiến trúc:** 6 Microservices (6 ngôn ngữ độc lập) + API Gateway + Frontend Web PHP  
> **Hạ tầng CSDL & Broker:** 4 MySQL 8.0 + 1 SQLite (nhúng) + 1 Redis 7  
> **Mô hình triển khai:** Docker Compose (12 containers) hoặc Local Native Windows  

---

## 📋 Mục lục

1. [Tổng quan Kiến trúc Hệ thống](#-tổng-quan-kiến-trúc-hệ-thống)
2. [Cấu trúc Thư mục Dự án](#-cấu-trúc-thư-mục-dự-án)
3. [Yêu cầu Môi trường](#-yêu-cầu-môi-trường)
4. [Hướng dẫn Chạy Dự án — Cách 1: Docker Compose (Khuyến nghị)](#-cách-1-chạy-bằng-docker-compose-khuyến-nghị)
   - [4.1. Khởi động toàn bộ hệ thống](#41-khởi-động-toàn-bộ-hệ-thống)
   - [4.2. Kiểm tra trạng thái & Log](#42-kiểm-tra-trạng-thái--log)
   - [4.3. Build lại từng service khi sửa code (Tối ưu thời gian)](#43-build-lại-từng-service-khi-sửa-code)
   - [4.4. Dừng hệ thống & Reset CSDL](#44-dừng-hệ-thống--reset-csdl)
5. [Hướng dẫn Chạy Dự án — Cách 2: Chạy Local trực tiếp](#-cách-2-chạy-local-trực-tiếp-không-cần-docker)
6. [Khởi chạy Giao diện Frontend Web (Port 3000)](#-khởi-chạy-giao-diện-frontend-web)
   - [6.3. Kiểm thử luồng nghiệp vụ trên Web & Lệnh kiểm tra dữ liệu trong Docker](#63-kiểm-thử-luồng-nghiệp-vụ-trên-web--lệnh-kiểm-tra-dữ-liệu-trong-docker)
   - [6.4. Cách hoàn lại Cơ sở Dữ liệu về Trạng thái Gốc (Reset sau khi Test)](#64-cách-hoàn-lại-cơ-sở-dữ-liệu-về-trạng-thái-gốc-reset-sau-khi-test)
7. [Kiểm thử API trực quan bằng Swagger UI (Tích hợp sẵn)](#-kiểm-thử-api-trực-quan-bằng-swagger-ui-tích-hợp-sẵn)
8. [Kiểm thử Tự động API bằng Newman / Postman](#-kiểm-thử-tự-động-api-bằng-newman--postman)
9. [Kiểm thử Hiệu năng & Tải (k6)](#-kiểm-thử-hiệu-năng--tải-bằng-k6)
10. [Bảng Tra cứu API Endpoints & Định tuyến Gateway](#-bảng-tra-cứu-api-endpoints)
11. [Xử lý Sự cố Thường gặp (Troubleshooting)](#-xử-lý-sự-cố-thường-gặp)
12. [Phân công Thành viên Thực hiện](#-phân-công-thành-viên-thực-hiện)

---

## 🏗 Tổng quan Kiến trúc Hệ thống

Hệ thống được thiết kế theo đúng chuẩn **Kiến trúc Hướng Dịch vụ (Service-Oriented Architecture - SOA)**, kết hợp đa ngôn ngữ (Polyglot Microservices), giao tiếp phi tập trung qua HTTP RESTful API và sự kiện bất đồng bộ qua Redis Pub/Sub / SSE:

```
                              ┌────────────────────────┐
                              │  Frontend Web (PHP)    │
                              │   http://localhost:3000│
                              └───────────┬────────────┘
                                          │ HTTP Requests
                                          ▼
                              ┌────────────────────────┐
                              │    API Gateway (Node)  │
                              │   http://localhost:8080│
                              └──┬───┬───┬───┬───┬───┬─┘
                                 │   │   │   │   │   │
        ┌────────────────────────┘   │   │   │   │   └────────────────────────┐
        ▼                            ▼   │   │   ▼                            ▼
┌───────────────┐  ┌──────────────────┐  │ ┌──────────────────┐  ┌────────────────────┐
│ Auth Service  │  │Transaction Serv. │  │ │Analytics Service │  │Notification Service│
│ Java 17 :8081 │  │  PHP 8.2  :8082  │  │ │Python 3.11 :8084 │  │  Node.js SSE :8086 │
│ (auth-db:3307)│  │ (transaction-db) │  │ │  (SQLite nhúng)  │  │  (cashflow-redis)  │
└───────────────┘  └──────────────────┘  │ └──────────────────┘  └────────────────────┘
                                         │
                         ┌───────────────┴───────────────┐
                         ▼                               ▼
               ┌──────────────────┐            ┌────────────────────┐
               │  Asset Service   │            │Integration Service │
               │  C# .NET 10 :8083│            │   Go 1.22   :8085  │
               │    (asset-db)    │            │(integration-db:3310│
               └──────────────────┘            └────────────────────┘
```

### Chi tiết các Microservices

| Service | Công nghệ / Runtime | Port Host | Cơ sở Dữ liệu & Broker | Chức năng chính |
|:---|:---|:---:|:---|:---|
| **API Gateway** | Node.js 20 (Native HTTP Proxy) | `8080` | — | Reverse proxy, xác thực JWT, điều hướng request, CORS, Health Check tập trung |
| **Auth Service** | Java 17 (`HttpServer` thuần) | `8081` | MySQL 8.0 (`auth-db`, port `3307`) | Quản lý người dùng, đăng ký, đăng nhập, cấp phát và xác minh JWT token |
| **Transaction Service** | PHP 8.2 (PDO Native) | `8082` | MySQL 8.0 (`transaction-db`) | Ghi nhận thu/chi, phân loại giao dịch, thiết lập và theo dõi ngân sách (Budgets) |
| **Asset Service** | C# .NET 10 (`HttpListener` thuần) | `8083` | MySQL 8.0 (`asset-db`) | Quản lý danh mục tài sản, khoản vay, danh mục đầu tư, tính Net Worth |
| **Analytics Service** | Python 3.11 (`http.server` thuần) | `8084` | SQLite nhúng (`analytics.sqlite3`) | Tổng hợp báo cáo thu chi, phân tích xu hướng dòng tiền, dự báo tài chính |
| **Integration Service** | Go 1.22 (`net/http` thuần) | `8085` | MySQL 8.0 (`integration-db`, port `3310`) | Giả lập Open Banking, liên kết tài khoản ngân hàng, đồng bộ sao kê |
| **Notification Service**| Node.js 20 (SSE Native) | `8086` | Redis 7 (`cashflow-redis`, port `6379`) | Phát thông báo thời gian thực Server-Sent Events, Redis Pub/Sub |
| **Frontend Web** | PHP 8.x (Built-in Server) | `3000` | — | Giao diện người dùng iBanking trực quan, kết nối toàn bộ luồng nghiệp vụ |

---

## 📁 Cấu trúc Thư mục Dự án

```
SOA-CashFlow/
├── gateway/
│   └── midterm/                   # API Gateway viết bằng Node.js thuần (Zero-dependency)
│       ├── Dockerfile
│       └── src/                   # index.js, proxy.js, jwt.js, config.js, docs.js
│
├── services/
│   ├── auth-service/
│   │   ├── db/init-auth-db.sql    # DDL & Seed bảng users
│   │   └── midterm/               # Java 17 HttpServer (pom.xml, src/)
│   ├── transaction-service/
│   │   ├── db/init-transaction-db.sql # Bảng transactions, budgets, categories
│   │   └── midterm/               # PHP 8.2 PDO (src/index.php)
│   ├── asset-service/
│   │   ├── db/init-asset-db.sql   # Bảng assets, loans, investments
│   │   └── midterm/               # C# .NET 10 HttpListener (AssetService.csproj, src/)
│   ├── analytics-service/
│   │   ├── db/init-analytics-db.sql # SQLite schema cho báo cáo monthly_reports
│   │   └── midterm/               # Python 3.11 http.server & sqlite3 (src/main.py)
│   ├── integration-service/
│   │   ├── db/init-integration-db.sql # Bảng bank_accounts, sync_logs
│   │   └── midterm/               # Go 1.22 net/http (go.mod, src/)
│   └── notification-service/
│       └── midterm/               # Node.js SSE + Redis Pub/Sub (src/index.js)
│
├── frontend/                      # Web Client PHP giao diện iBanking (chạy trên port 3000)
│   ├── router.php                 # Router định tuyến cho PHP built-in server
│   ├── index.php                  # Dashboard chính
│   ├── login.php / register.php   # Xác thực người dùng
│   ├── transactions.php           # Quản lý giao dịch thu chi
│   ├── budgets.php                # Quản lý hạn mức ngân sách
│   ├── assets.php                 # Quản lý tài sản & đầu tư
│   ├── analytics.php              # Xem biểu đồ phân tích
│   ├── banking.php                # Liên kết & đồng bộ ngân hàng
│   └── notifications.php          # Xem thông báo SSE
│
├── docs/
│   ├── api-docs/                  # Postman Collection & Environment JSON
│   ├── diagrams/                  # Kiến trúc hệ thống, ERD, Use Case
│   └── reports/                   # Slide thuyết trình & Báo cáo đồ án
│
├── scripts/
│   ├── concurrency-test-k6.js     # Script kiểm thử tải k6
│   └── seed-data.sql              # Dữ liệu kiểm thử tổng hợp
│
├── docker-compose.midterm.yml     # File Docker Compose chính cho Giữa kỳ (12 containers)
├── docker-compose.final.yml       # File Docker Compose cho phiên bản Framework (Cuối kỳ)
├── docker-compose.yml             # Cấu hình compose tổng quát
├── start-services.ps1             # PowerShell script tự động chạy toàn bộ local trên Windows
├── run-frontend.bat               # File batch khởi chạy nhanh Frontend Web trên port 3000
└── README.md                      # Hướng dẫn chi tiết chạy dự án
```

---

## ⚙ Yêu cầu Môi trường

| Công cụ | Phiên bản khuyến nghị | Mục đích sử dụng |
|:---|:---:|:---|
| **Docker Desktop** | 4.x (Docker Engine 24+) | Chạy toàn bộ 12 containers hệ thống |
| **PHP** | 8.1 hoặc 8.2 (hoặc XAMPP) | Khởi chạy giao diện Frontend Web |
| **Node.js & npm** | 18.x hoặc 20.x | Cài đặt và thực thi công cụ Newman chạy test API |
| **Git** | 2.x | Quản lý mã nguồn |

---

## 🐳 Cách 1: Chạy bằng Docker Compose (Khuyến nghị)

Đây là cách chạy chuẩn hóa, ổn định nhất và **không yêu cầu cài đặt riêng từng môi trường Java, Go, .NET, Python, Redis trên máy host**.

### 4.1. Khởi động toàn bộ hệ thống

Mở terminal (PowerShell, Command Prompt hoặc Git Bash) tại thư mục gốc của dự án `SOA-CashFlow`:

```powershell
# Khởi chạy toàn bộ 12 containers cho phiên bản Giữa kỳ (Native Services):
docker-compose -f docker-compose.midterm.yml up -d --build
```

> ⏱ **Lưu ý ở lần chạy đầu tiên:** Docker sẽ kéo các image gốc (`mysql:8.0`, `redis:7-alpine`) và build 7 image nội bộ (mất từ 3–8 phút tùy tốc độ mạng). Các lần sau Docker đã lưu cache nên chỉ mất vài giây.

Sau khi lệnh hoàn thành, **12 containers** sẽ chạy ngầm:
- `cashflow-gateway-midterm` (Cổng `8080`)
- `cashflow-auth-midterm` (Cổng `8081`)
- `cashflow-transaction-midterm` (Cổng `8082`)
- `cashflow-asset-midterm` (Cổng `8083`)
- `cashflow-analytics-midterm` (Cổng `8084`)
- `cashflow-integration-midterm` (Cổng `8085`)
- `cashflow-notification-midterm` (Cổng `8086`)
- `cashflow-auth-db` (MySQL cổng host `3307`)
- `cashflow-transaction-db` (MySQL)
- `cashflow-asset-db` (MySQL)
- `cashflow-integration-db` (MySQL cổng host `3310`)
- `cashflow-redis` (Redis cổng host `6379`)

### 4.2. Kiểm tra trạng thái & Log

1. **Kiểm tra trạng thái các container:**
   ```powershell
   docker-compose -f docker-compose.midterm.yml ps
   ```
   Tất cả các container cần ở trạng thái `Up` (hoặc `running`).

2. **Kiểm tra sức khỏe Gateway:**
   ```powershell
   curl.exe http://localhost:8080/health
   ```
   Kết quả trả về JSON có `"status": "UP"` cùng danh sách đầy đủ 12 route điều hướng.

3. **Xem log trực tiếp của một service:**
   ```powershell
   # Xem log Gateway:
   docker logs -f cashflow-gateway-midterm

   # Xem log Auth Service:
   docker logs -f cashflow-auth-midterm

   # Xem log Asset Service (.NET):
   docker logs -f cashflow-asset-midterm

   # Xem log Integration Service (Go):
   docker logs -f cashflow-integration-midterm
   ```

### 4.3. Build lại từng service khi sửa code

Khi bạn chỉnh sửa mã nguồn của một hoặc vài service, **KHÔNG CẦN** build lại toàn bộ cả 12 container mà chỉ cần chỉ định tên service cần build lại:

```powershell
# Ví dụ 1: Chỉ build lại Auth Service:
docker-compose -f docker-compose.midterm.yml up -d --build auth-service

# Ví dụ 2: Chỉ build lại 3 service có chỉnh sửa:
docker-compose -f docker-compose.midterm.yml up -d --build auth-service asset-service integration-service

# Ví dụ 3: Chỉ khởi động lại Gateway khi thay đổi cấu hình định tuyến:
docker-compose -f docker-compose.midterm.yml up -d --build gateway
```

### 4.4. Dừng hệ thống & Reset CSDL

```powershell
# 1. Dừng các container (dữ liệu trong database vẫn được giữ nguyên):
docker-compose -f docker-compose.midterm.yml down

# 2. Dừng VÀ XÓA HOÀN TOÀN volume CSDL (Reset database về trạng thái mới tinh từ file init.sql):
docker-compose -f docker-compose.midterm.yml down -v

# 3. Dọn dẹp sạch toàn bộ cache build cũ nếu muốn build lại hoàn toàn từ con số 0:
docker-compose -f docker-compose.midterm.yml down -v --rmi local
```

---

## 💻 Cách 2: Chạy Local trực tiếp (Không cần Docker)

Nếu máy bạn đã cài sẵn XAMPP (hoặc MySQL) và muốn debug trực tiếp mã nguồn trên máy Windows mà không thông qua Docker:

### Yêu cầu:
- XAMPP đang bật dịch vụ **MySQL** (cổng `3306`, tài khoản `root`, mật khẩu `root_password`).
- Node.js, Python 3.11+, PHP 8.x đã cài trong máy.
- Các bộ compiler (JDK 17, Maven, Go 1.22, .NET 10 SDK) đã được đặt sẵn trong thư mục `.tools/`.

### Thực hiện qua PowerShell Script:

```powershell
# Khởi động toàn bộ 6 services + Gateway + Frontend:
powershell -ExecutionPolicy Bypass -File start-services.ps1

# Chạy nhanh (bỏ qua bước build nếu code không đổi):
powershell -ExecutionPolicy Bypass -File start-services.ps1 -NoBuild

# Dừng tất cả các tiến trình services đang chạy local:
powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop
```

Script sẽ tự động:
1. Đọc compiler từ thư mục `.tools/` và thiết lập các biến môi trường tạm thời.
2. Build `auth-service` (Maven), `asset-service` (.NET 10), `integration-service` (Go).
3. Lần lượt khởi động 6 Microservices, API Gateway và Frontend Web.
4. Ghi toàn bộ log của từng service vào thư mục `.tools/logs/`.
5. Tự động kiểm tra HTTP Health trên từng cổng từ `8081` đến `8086`.

---

## 🌐 Khởi chạy Giao diện Frontend Web

Giao diện Web iBanking được xây dựng bằng PHP, hoạt động trực tiếp trên máy host và gửi toàn bộ yêu cầu qua **API Gateway (cổng 8080)**:

### Cách khởi động:

- **Cách 1 (Nhanh nhất):** Nhấp đúp chuột vào file [run-frontend.bat](file:///d:/3.1.%20SOA/SOA-CashFlow/run-frontend.bat) tại thư mục gốc của dự án.
- **Cách 2 (Bằng Terminal):**
  ```powershell
  # Chạy bằng PHP có sẵn trên máy:
  php -S 127.0.0.1:3000 -t frontend frontend/router.php

  # Hoặc dùng trực tiếp PHP của XAMPP:
  C:\xampp\php\php.exe -S 127.0.0.1:3000 -t frontend frontend/router.php
  ```

### Truy cập ứng dụng:
- Mở trình duyệt và truy cập: **[http://localhost:3000](http://localhost:3000)** (hoặc `http://127.0.0.1:3000`)
- **Tài khoản đăng nhập kiểm thử mặc định:**
  - **Tên đăng nhập:** `admin` (hoặc `user1`)
  - **Mật khẩu:** `password123`

### 6.3. Kiểm thử luồng nghiệp vụ trên Web & Lệnh kiểm tra dữ liệu trong Docker

Khi bạn thao tác trên giao diện Web (thêm thu/chi, tạo tài sản, liên kết ngân hàng), Frontend sẽ gọi API qua Gateway vào các Microservices và ghi thẳng vào CSDL Docker. Bạn có thể mở một Terminal để chạy các lệnh sau để **xuất dữ liệu dạng bảng đẹp mắt** và đối chiếu ngay lập tức:

#### 1. Kiểm tra tài khoản người dùng vừa đăng ký/đổi thông tin:
```powershell
docker exec -it cashflow-auth-db mysql -uroot -proot_password auth_db -e "SELECT id, username, email, full_name, role, created_at FROM users;"
```

#### 2. Kiểm tra giao dịch vừa thêm trên trang Transactions:
```powershell
docker exec -it cashflow-transaction-db mysql -uroot -proot_password transaction_db -e "SELECT id, user_id, amount, type, category, description, transaction_date FROM transactions ORDER BY id DESC LIMIT 10;"
```

#### 3. Kiểm tra danh mục Ngân sách (Budgets) vừa thiết lập:
```powershell
docker exec -it cashflow-transaction-db mysql -uroot -proot_password transaction_db -e "SELECT id, user_id, category, amount_limit, spent_amount, period FROM budgets;"
```

#### 4. Kiểm tra Tài sản & Đầu tư vừa thêm trên trang Assets:
```powershell
docker exec -it cashflow-asset-db mysql -uroot -proot_password asset_db -e "SELECT id, user_id, name, type, value, status, created_at FROM assets ORDER BY id DESC;"
```

#### 5. Kiểm tra Tài khoản Ngân hàng vừa liên kết trên trang Banking:
```powershell
docker exec -it cashflow-integration-db mysql -uroot -proot_password integration_db -e "SELECT id, user_id, bank_code, account_number, account_holder, balance FROM bank_accounts;"
```

#### 6. Kiểm tra Báo cáo Dòng tiền lưu trong SQLite của Analytics Service:
```powershell
docker exec -it cashflow-analytics-midterm python -c "import sqlite3; conn = sqlite3.connect('db/analytics.sqlite3'); cur = conn.cursor(); cur.execute('SELECT report_id, user_id, year, month, generated_at FROM monthly_reports'); [print(row) for row in cur.fetchall()]"
```

### 6.4. Cách hoàn lại Cơ sở Dữ liệu về Trạng thái Gốc (Reset sau khi Test)

Sau khi bạn test xong (tạo thử nhiều tài khoản rác, giao dịch test...), nếu muốn **xóa toàn bộ dữ liệu vừa tạo** và đưa hệ thống quay lại trạng thái dữ liệu mẫu ban đầu:

#### Cách 1: Reset nhanh bằng Docker Compose (Khuyên dùng - Triệt để nhất)
Chỉ cần chạy lệnh sau, Docker sẽ xóa toàn bộ dữ liệu phát sinh trong các container và tự động chạy lại các file `init-*-db.sql` ban đầu:

```powershell
# Dừng và xóa toàn bộ dữ liệu test:
docker-compose -f docker-compose.midterm.yml down -v

# Khởi động lại hệ thống (chỉ mất ~5-10 giây vì images đã build sẵn):
docker-compose -f docker-compose.midterm.yml up -d
```
> ⏱ *Sau khi chạy lệnh trên, đợi khoảng 10 giây để MySQL khởi tạo xong là hệ thống sẽ trở về đúng trạng thái ban đầu.*

#### Cách 2: Reset nóng Database mà không cần tắt các Service khác
Nếu bạn đang mở Swagger hoặc Frontend và **không muốn tắt Gateway/Services**, chỉ cần recreate riêng 4 container Database và Analytics:

```powershell
# Xóa và tạo lại riêng 4 CSDL:
docker rm -f cashflow-auth-db cashflow-transaction-db cashflow-asset-db cashflow-integration-db
docker-compose -f docker-compose.midterm.yml up -d auth-db transaction-db asset-db integration-db

# Reset lại file SQLite của Analytics:
docker exec -it cashflow-analytics-midterm rm -f db/analytics.sqlite3
```

---

## 📑 Kiểm thử API trực quan bằng Swagger UI (Tích hợp sẵn)

Dự án đã tích hợp sẵn **Swagger UI** tại API Gateway (cổng `8080`), cho phép kiểm thử trực tiếp mọi endpoint của cả 6 Microservices thông qua giao diện web trực quan mà không cần cài đặt thêm phần mềm (như Postman hay Newman).

### 7.1. Truy cập Swagger UI

Đảm bảo Gateway đang chạy, mở trình duyệt vào một trong các địa chỉ sau:

- **Giao diện tổng hợp toàn bộ 6 Services:**  
  👉 **[http://localhost:8080/docs](http://localhost:8080/docs)** (hoặc `http://localhost:8080/swagger`)
- **Hoặc mở trực tiếp trang tài liệu của từng service:**
  - 👤 **Auth Service (Java):** `http://localhost:8080/docs/auth`
  - 💳 **Transaction Service (PHP):** `http://localhost:8080/docs/transaction`
  - 🏠 **Asset Service (C# .NET):** `http://localhost:8080/docs/asset`
  - 📊 **Analytics Service (Python):** `http://localhost:8080/docs/analytics`
  - 🏦 **Integration Service (Go):** `http://localhost:8080/docs/integration`
  - 🔔 **Notification Service (Node.js):** `http://localhost:8080/docs/notification`

### 7.2. Các bước thực hiện Test API trên Swagger UI

#### Bước 1: Lấy Token xác thực (Đăng nhập)
1. Trên thanh chọn dịch vụ ở góc trên bên phải trang Swagger, chọn spec **Auth Service**.
2. Tìm đến endpoint **`POST /api/auth/login`**.
3. Bấm **Try it out**, nhập tài khoản kiểm thử:
   ```json
   {
     "username": "user1",
     "password": "password123"
   }
   ```
4. Bấm nút **Execute**.
5. Trong phần **Server response (200)**, sao chép chuỗi mã token trong trường `"token"`:
   ```json
   {
     "success": true,
     "data": {
       "token": "eyJhbGciOiJIUzI1NiIsIn..."
     }
   }
   ```

#### Bước 2: Xác thực toàn cục (Authorize)
1. Cuộn lên đầu trang Swagger UI, bấm vào nút **Authorize 🔓** (có biểu tượng ổ khóa màu xanh lá cây).
2. Dán chuỗi token vừa copy vào ô **Value** (bạn có thể dán trực tiếp token hoặc nhập `Bearer <token>`).
3. Bấm **Authorize** -> sau đó bấm **Close**.  
   *(Lúc này biểu tượng ổ khóa sẽ chuyển sang trạng thái đã khóa 🔒, tất cả các request gửi đi từ Swagger sẽ tự động kèm header Authorization).*

#### Bước 3: Kiểm thử các API nghiệp vụ
Bây giờ bạn có thể chuyển sang bất kỳ service nào để gọi API:
- **Tạo giao dịch thu/chi:** Chọn `Transaction Service` -> tìm `POST /api/transactions` -> bấm *Try it out* -> nhập JSON giao dịch -> bấm *Execute*.
- **Xem báo cáo phân tích:** Chọn `Analytics Service` -> tìm `GET /api/analytics/summary` -> bấm *Execute*.
- **Thêm danh mục tài sản:** Chọn `Asset Service` -> tìm `POST /api/assets` -> bấm *Execute*.
- Dữ liệu bạn tạo trên Swagger sẽ được lưu trực tiếp vào CSDL trong Docker và hiển thị ngay trên Frontend Web (`http://localhost:3000`).

---

## 🧪 Kiểm thử Tự động API bằng Newman / Postman

Dự án đã tích hợp sẵn bộ kiểm thử hồi quy tự động gồm **33 API requests & hàng chục assertions** bao phủ toàn diện 6 microservices và gateway.

### 8.1. Cài đặt Newman (Chỉ cần chạy 1 lần)
Mở terminal và cài đặt công cụ Newman từ npm:
```powershell
npm install -g newman
```
*(Tùy chọn) Cài thêm reporter để xuất báo cáo HTML giao diện đẹp:*
```powershell
npm install -g newman-reporter-htmlextra
```

### 8.2. Chạy kiểm thử toàn bộ API

Đảm bảo hệ thống backend đang chạy trên Docker (Gateway cổng `8080`), thực thi lệnh sau:

```powershell
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on
```

### 8.3. Xuất file báo cáo kiểm thử HTML

```powershell
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json -r cli,htmlextra --reporter-htmlextra-export newman-report.html
```
Sau khi chạy xong, mở file `newman-report.html` trên trình duyệt để xem biểu đồ và kết quả chi tiết từng request.

### 8.4. Lưu ý về mã `409 Conflict` khi chạy lại nhiều lần
- Khi chạy Newman **từ lần thứ 2 trở đi**, một số request như `Register User` hoặc `Link Bank Account` có thể trả về HTTP `409 Conflict` (báo rằng username hoặc tài khoản ngân hàng đó đã tồn tại trong CSDL từ lần chạy trước).
- **Đây là hành vi hoàn toàn bình thường và chính xác.** Bộ test trong collection đã được viết script chấp nhận cả mã `201 Created` lẫn `409 Conflict`.
- Nếu bạn muốn đưa kết quả 100% về `201 Created`, chỉ cần xóa volume và tạo lại database:
  ```powershell
  docker-compose -f docker-compose.midterm.yml down -v
  docker-compose -f docker-compose.midterm.yml up -d
  # Đợi 10-15 giây cho MySQL khởi tạo xong, sau đó chạy lại lệnh newman run
  ```

---

## ⚡ Kiểm thử Hiệu năng & Tải bằng k6

Script kiểm thử tải đồng thời (Concurrency & Stress testing) được đặt tại `scripts/concurrency-test-k6.js`.

1. Cài đặt [k6](https://k6.io/docs/get-started/installation/):
   ```powershell
   winget install k6 --source winget
   # hoặc tải file exe từ trang chủ k6
   ```
2. Chạy bài kiểm thử tải:
   ```powershell
   k6 run scripts/concurrency-test-k6.js
   ```

---

## 📡 Bảng Tra cứu API Endpoints

Mọi request từ phía người dùng và Frontend đều đi qua **API Gateway cổng `8080`**. Gateway sẽ kiểm tra tính hợp lệ của Header `Authorization: Bearer <JWT>` (ngoại trừ các endpoint công khai) và chuyển tiếp tới service đích tương ứng.

### Các Endpoints chính qua API Gateway (`http://localhost:8080`)

| Method | Endpoint | Service đích | Yêu cầu JWT? | Mô tả chi tiết |
|:---:|:---|:---|:---:|:---|
| `GET` | `/health` | API Gateway | ❌ Không | Kiểm tra trạng thái hoạt động của Gateway và danh sách route |
| `POST` | `/api/auth/register` | Auth Service | ❌ Không | Đăng ký tài khoản người dùng mới |
| `POST` | `/api/auth/login` | Auth Service | ❌ Không | Đăng nhập hệ thống, nhận JWT Access Token |
| `GET` | `/api/auth/profile` hoặc `/api/profile` | Auth Service | ✅ Có | Xem thông tin chi tiết của người dùng đang đăng nhập |
| `GET` | `/api/transactions` | Transaction Service | ✅ Có | Lấy danh sách giao dịch thu/chi tài chính |
| `POST` | `/api/transactions` | Transaction Service | ✅ Có | Tạo mới một giao dịch tài chính |
| `GET` | `/api/budgets` | Transaction Service | ✅ Có | Lấy danh sách hạn mức ngân sách chi tiêu |
| `POST` | `/api/budgets` | Transaction Service | ✅ Có | Thiết lập hạn mức ngân sách mới |
| `GET` | `/api/assets` | Asset Service | ✅ Có | Lấy danh sách tài sản (bất động sản, chứng khoán, tiền mặt...) |
| `POST` | `/api/assets` | Asset Service | ✅ Có | Thêm mới một tài sản vào danh mục quản lý |
| `GET` | `/api/loans` | Asset Service | ✅ Có | Danh sách các khoản vay & nợ phải trả |
| `GET` | `/api/analytics/summary` | Analytics Service | ✅ Có | Lấy báo cáo tổng hợp thu, chi và số dư dòng tiền |
| `GET` | `/api/analytics/trend` | Analytics Service | ✅ Có | Phân tích xu hướng biến động tài chính theo thời gian |
| `POST` | `/api/integrations/link-account` | Integration Service| ✅ Có | Liên kết tài khoản ngân hàng bên ngoài (Open Banking giả lập) |
| `POST` | `/api/integrations/sync` | Integration Service| ✅ Có | Kích hoạt tác vụ đồng bộ lịch sử giao dịch từ ngân hàng |
| `GET` | `/api/integrations/rates` | Integration Service| ❌ Không | Tra cứu tỷ giá ngoại tệ hiện hành |
| `GET` | `/api/notifications/stream` | Notification Serv. | ✅ Có | Mở luồng Server-Sent Events (SSE) nhận thông báo thời gian thực |
| `POST` | `/api/notifications/send` | Notification Serv. | ✅ Có | Gửi thông báo sự kiện qua Redis Pub/Sub |

### Truy cập Trực tiếp từng Microservice (Internal Ports)

Khi cần cô lập để kiểm tra từng service mà không qua Gateway:
- **Auth Service:** `http://localhost:8081/api/auth/*`
- **Transaction Service:** `http://localhost:8082/api/transactions/*`
- **Asset Service:** `http://localhost:8083/api/assets/*`
- **Analytics Service:** `http://localhost:8084/api/analytics/*`
- **Integration Service:** `http://localhost:8085/api/integrations/*`
- **Notification Service:** `http://localhost:8086/api/notifications/*`

---

## 🔧 Xử lý Sự cố Thường gặp

### 1. Trùng cổng (Port already in use)
Nếu gặp lỗi `bind: address already in use` trên các cổng `8080`, `8081-8086`, `3306`, `3307`, `3310`, `6379`, `3000`:
```powershell
# Tìm ID tiến trình (PID) đang chiếm dụng cổng (ví dụ cổng 8080):
netstat -ano | findstr :8080

# Dừng tiến trình đó (thay <PID> bằng mã số tìm được ở cột cuối cùng):
taskkill /F /PID <PID>
```

### 2. Lỗi MySQL "Table doesn't exist" (Error 1146)
Lỗi này thường xảy ra khi bạn đã từng chạy Docker trước đây và volume cũ chưa được dọn dẹp, khiến MySQL bỏ qua file khởi tạo `init.sql`:
```powershell
# Xóa hoàn toàn volumes cũ và tái tạo lại:
docker-compose -f docker-compose.midterm.yml down -v
docker-compose -f docker-compose.midterm.yml up -d --build
```

### 3. Asset Service (.NET) lỗi kết nối MySQL
Trong file kết nối C# với MySQL qua driver Oracle `MySql.Data`, **không được truyền `SslMode=None`**. Chuỗi kết nối hợp lệ:
```
Server=asset-db;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;
```

### 4. Lỗi thời gian chờ (Latency) trên Windows khi gọi `localhost`
Trên một số phiên bản Windows, `localhost` ưu tiên phân giải ra IPv6 (`::1`) trước khi thử IPv4 (`127.0.0.1`), có thể gây độ trễ khoảng 200–500ms mỗi request. Nếu chạy local, nên ưu tiên cấu hình `127.0.0.1`.

### 5. Xem chi tiết nguyên nhân khi một container bị thoát (Exited)
```powershell
docker ps -a
docker logs --tail 100 <tên-container-bị-lỗi>
```

---

## 👥 Phân công Thành viên Thực hiện

Dự án được phân chia theo tiêu chuẩn kiến trúc đa ngôn ngữ của môn học Kiến trúc Hướng Dịch vụ (SOA):

| STT | Họ và Tên | Vai trò | Microservices Phụ trách | Ngôn ngữ & Công nghệ | Cơ sở Dữ liệu / Broker |
|:---:|:---|:---|:---|:---|:---|
| **0** | **Nhóm trưởng** | Kiến trúc sư hệ thống | **API Gateway** & **Notification Service** & **Frontend Web** | Node.js 20, PHP 8.x | Redis 7 (Pub/Sub) |
| **1** | Thành viên 1 | Thành viên phát triển | **Auth Service** | Java 17 (`HttpServer` thuần) | MySQL 8.0 (`auth_db`) |
| **2** | Thành viên 2 | Thành viên phát triển | **Transaction Service** | PHP 8.2 (PDO Native) | MySQL 8.0 (`transaction_db`) |
| **3** | Thành viên 3 | Thành viên phát triển | **Asset Service** | C# .NET 10 (`HttpListener` thuần) | MySQL 8.0 (`asset_db`) |
| **4** | Thành viên 4 | Thành viên phát triển | **Analytics Service** | Python 3.11 (`http.server` & `sqlite3`) | SQLite nhúng (`analytics.sqlite3`) |
| **5** | Thành viên 5 | Thành viên phát triển | **Integration Service**| Go 1.22 (`net/http` thuần) | MySQL 8.0 (`integration_db`) |

---

## 📑 Các phiên bản Docker Compose

| Phiên bản | Tên file | Mô tả mục đích |
|:---|:---|:---|
| **Giữa kỳ (Khuyến nghị)** | `docker-compose.midterm.yml` | Toàn bộ 6 service viết bằng code thuần không phụ thuộc framework, sử dụng 12 containers khép kín. |
| **Cuối kỳ** | `docker-compose.final.yml` | Triển khai các service bằng các framework chuẩn công nghiệp (Spring Boot, Laravel, ASP.NET Core, FastAPI, Gin, NestJS). |
| **Chung** | `docker-compose.yml` | Cấu hình triển khai chuẩn với persistent volumes và chính sách restart tự động. |
