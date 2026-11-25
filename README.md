# 💰 SOA CashFlow — Hệ thống Quản lý Tài chính & Phân tích Dòng tiền

> **Môn học:** Kiến trúc Hướng Dịch vụ (SOA) — Trường Đại học Tôn Đức Thắng (TDTU)  
> **Kiến trúc:** 6 Microservices (6 ngôn ngữ độc lập) + API Gateway + Frontend Web PHP  
> **Hạ tầng CSDL & Broker:** 5 MySQL 8.0 (Database-per-Service) + 1 Redis 7  
> **Hai phiên bản đầy đủ:** Giữa kỳ (**100% Native Code**) & Cuối kỳ (**Full Frameworks**)

---

## 🧭 Tài liệu Hướng dẫn Chạy Dự án Chi tiết

Vui lòng chọn đúng hướng dẫn theo giai đoạn bạn muốn chạy và kiểm thử:

- 📘 **[readme_giuaky.md](readme_giuaky.md)** — Hướng dẫn chi tiết **Bản Giữa kỳ (Midterm — 100% Native Code)** (`docker-compose.midterm.yml`, chạy Local `start-services.ps1`, Hybrid, thủ công từng Terminal, kiểm thử & xem CSDL).
- 🚀 **[readme_cuoiky.md](readme_cuoiky.md)** — Hướng dẫn chi tiết **Bản Cuối kỳ (Final — Full Frameworks)** (`docker-compose.final.yml` / `docker-compose.yml`, Spring Boot 3.5, Laravel 12, ASP.NET Core 8, FastAPI, Go Gin, Express + Socket.io + OTP, demo Concurrency & OTP).

---

## 📋 Mục lục

1. [Tổng quan Kiến trúc Hệ thống](#-1-tổng-quan-kiến-trúc-hệ-thống)
2. [Chi tiết & Trách nhiệm Từng Microservice (Giữa kỳ vs Cuối kỳ)](#-2-chi-tiết--trách-nhiệm-từng-microservice)
3. [Cấu trúc Thư mục Dự án](#-3-cấu-trúc-thư-mục-dự-án)
4. [Yêu cầu Môi trường](#-4-yêu-cầu-môi-trường)
5. [Khởi chạy Nhanh (Quick Start)](#-5-khởi-chạy-nhanh-quick-start)
6. [Hướng dẫn Kiểm thử Hệ thống](#-6-hướng-dẫn-kiểm-thử-hệ-thống)
7. [Xem & Giám sát Dữ liệu trong CSDL](#-7-xem--giám-sát-dữ-liệu-trong-csdl)
8. [Bảng Tra cứu API Endpoints & Định tuyến Gateway](#-8-bảng-tra-cứu-api-endpoints)
9. [Xử lý Sự cố Thường gặp](#-9-xử-lý-sự-cố-thường-gặp)
10. [Phân công Thành viên Thực hiện](#-10-phân-công-thành-viên-thực-hiện)

---

## 🏗 1. Tổng quan Kiến trúc Hệ thống

Hệ thống được thiết kế theo chuẩn **Kiến trúc Hướng Dịch vụ (Service-Oriented Architecture - SOA)**, kết hợp đa ngôn ngữ (**Polyglot Microservices**), giao tiếp qua HTTP RESTful API và sự kiện thời gian thực qua **Redis Pub/Sub / SSE / Socket.io**:

```
                              ┌────────────────────────┐
                              │  Frontend Web (PHP)    │
                              │   http://localhost:3000│
                              └───────────┬────────────┘
                                          │ HTTP / SSE / WebSocket
                                          ▼
                              ┌────────────────────────┐
                              │  API Gateway (Node.js) │
                              │   http://localhost:8080│
                              └──┬───┬───┬───┬───┬───┬─┘
                                 │   │   │   │   │   │
        ┌────────────────────────┘   │   │   │   │   └────────────────────────┐
        ▼                            ▼   │   │   ▼                            ▼
┌───────────────┐  ┌──────────────────┐  │ ┌──────────────────┐  ┌─────────────────────┐
│ Auth Service  │  │Transaction Serv. │  │ │Analytics Service │  │Notification & OTP   │
│ Java 17 :8081 │  │  PHP 8.2  :8082  │  │ │Python 3.11 :8084 │  │Node.js + Redis :8086│
│ (auth-db:3307)│  │ (transaction-db) │  │ │ (analytics-db)   │  │ (redis :6379)       │
└───────────────┘  └──────────────────┘  │ └──────────────────┘  └─────────────────────┘
                                         │
                         ┌───────────────┴───────────────┐
                         ▼                               ▼
               ┌──────────────────┐            ┌────────────────────┐
               │  Asset Service   │            │Integration Service │
               │    C# .NET :8083 │            │   Go 1.22   :8085  │
               │    (asset-db)    │            │ integration-db:3310│
               └──────────────────┘            └────────────────────┘
```

---

## 🧩 2. Chi tiết & Trách nhiệm Từng Microservice

Hệ thống tuân thủ nguyên tắc **Database-per-Service** (mỗi service sở hữu cơ sở dữ liệu độc lập, không truy cập chéo DB của nhau):

| STT | Service | Port | Giữa kỳ (Native Code) | Cuối kỳ (Framework) | Hạ tầng Dữ liệu | Trách nhiệm Nghiệp vụ |
|:---:|:---|:---:|:---|:---|:---|:---|
| **0** | **API Gateway** | `8080` | Node.js 20 Native (`http`) | **Express 4** + `http-proxy-middleware` + Rate Limit + Tracing | — | Single Entry Point, xác thực JWT, định tuyến, CORS, `/health/services`, Swagger UI (`/docs`). |
| **1** | **Auth Service** | `8081` | Java 17 (`HttpServer` + JDBC) | **Spring Boot 3.5** + Spring Security + JPA | MySQL 8.0 (`auth_db`, host `:3307`) | Đăng ký, đăng nhập, hash mật khẩu `PBKDF2WithHmacSHA256`, cấp & kiểm tra JWT, refresh token, phân quyền ADMIN/USER. |
| **2** | **Transaction Service** | `8082` | PHP 8.2 (PDO Native) | **Laravel 12** + Migrations + Auto-Seed | MySQL 8.0 (`transaction_db` / `transaction_final_db`) | Ghi nhận thu/chi, danh mục, hạn mức ngân sách (Budgets), tự động cảnh báo vượt ngân sách sang Notification. |
| **3** | **Asset Service** | `8083` | C# .NET 10 (`HttpListener`) | **ASP.NET Core 8 Web API** + EF Core 8 | MySQL 8.0 (`asset_db`) | Quản lý tài sản, khấu hao, khoản vay (EMI), danh mục đầu tư, lịch sử giá trị và tổng tài sản ròng (Net Worth). |
| **4** | **Analytics Service** | `8084` | Python 3.11 (`http.server`) | **FastAPI** + Uvicorn + Pydantic | MySQL 8.0 (`analytics_db`) | Tổng hợp thu chi từ Transaction Service, phát hiện chi tiêu bất thường, dự báo xu hướng và lưu báo cáo tháng. |
| **5** | **Integration Service** | `8085` | Go 1.22 (`net/http` + `sql`) | **Go Gin** + GORM + Concurrency/Idempotency | MySQL 8.0 (`integration_db`, host `:3310`) | Giả lập Open Banking, liên kết tài khoản ngân hàng, xử lý webhook đồng thời chống race condition & trùng lặp, tra cứu tỷ giá. |
| **6** | **Notification & OTP** | `8086` | Node.js 20 Native SSE + TCP Redis | **Express 4** + **Socket.io 4** + **`ioredis`** + **OTP** | Redis 7 (`:6379`) | Phát thông báo realtime qua SSE & Socket.io theo Redis Pub/Sub; sinh & xác thực mã OTP 6 số cho giao dịch thanh toán. |
| **7** | **Frontend Web** | `3000` | PHP 8.x Built-in Server | PHP 8.x Built-in Server | — | Giao diện Web iBanking điều phối đầy đủ nghiệp vụ người dùng qua API Gateway. |

---

## 📁 3. Cấu trúc Thư mục Dự án

```
SOA-CashFlow/
├── gateway/
│   ├── midterm/                        # API Gateway Giữa kỳ (Node.js Native Zero-dependency)
│   └── final/                          # API Gateway Cuối kỳ (Express 4 + Proxy + Rate Limit + Swagger)
│
├── services/
│   ├── auth-service/
│   │   ├── db/init-auth-db.sql         # DDL & Seed users (admin, member1) + refresh_tokens
│   │   ├── midterm/                    # Java 17 Native HttpServer + JDBC
│   │   └── final/                      # Java 17 Spring Boot 3.5 + Spring Security + JPA
│   ├── transaction-service/
│   │   ├── db/init-transaction-db.sql  # DDL & Seed Giữa kỳ (categories, transactions, budgets)
│   │   ├── midterm/                    # PHP 8.2 Native PDO
│   │   └── final/                      # PHP 8.2 Laravel 12 (Migrations + DatabaseSeeder)
│   ├── asset-service/
│   │   ├── db/init-asset-db.sql        # DDL & Seed Giữa kỳ (assets, loans, investments, asset_history)
│   │   ├── midterm/                    # C# .NET 10 Native HttpListener
│   │   └── final/                      # C# .NET 8 ASP.NET Core Web API + EF Core 8 + db/init-asset-db.sql
│   ├── analytics-service/
│   │   ├── db/init-analytics-db.sql    # DDL bảng monthly_reports (JSON)
│   │   ├── midterm/                    # Python 3.11 Native http.server + PyMySQL
│   │   └── final/                      # Python 3.11 FastAPI + Uvicorn + Pydantic
│   ├── integration-service/
│   │   ├── db/init-integration-db.sql  # DDL & Seed (exchange_rates, linked_accounts, bank_transactions)
│   │   ├── midterm/                    # Go 1.22 Native net/http + tools/loadtest
│   │   └── final/                      # Go 1.22 Gin + GORM + tools/loadtest
│   └── notification-service/
│       ├── midterm/                    # Node.js 20 Native SSE + Raw TCP Redis RESP
│       └── final/                      # Node.js 20 Express 4 + Socket.io 4 + ioredis + OTP
│
├── frontend/                           # Web Client PHP giao diện iBanking (Port 3000)
├── docs/
│   ├── api-docs/                       # Postman Collection & Environment JSON
│   ├── diagrams/                       # Sơ đồ kiến trúc, ERD, Use Case
│   └── reports/                        # Tài liệu báo cáo & slide
├── scripts/
│   ├── concurrency-test-k6.js          # Script kiểm thử tải k6
│   └── seed-data.sql                   # Dữ liệu mẫu tổng hợp
│
├── docker-compose.midterm.yml          # Docker Compose Giữa kỳ (13 containers - Native)
├── docker-compose.final.yml            # Docker Compose Cuối kỳ (13 containers - Frameworks)
├── docker-compose.yml                  # Compose mặc định (include docker-compose.final.yml)
├── start-services.ps1                  # Script chạy Local bản Giữa kỳ trên Windows không cần Docker
├── run-frontend.bat                    # Script mở nhanh Frontend Web cổng 3000
├── readme_giuaky.md                    # Hướng dẫn chạy chi tiết bản Giữa kỳ
├── readme_cuoiky.md                    # Hướng dẫn chạy chi tiết bản Cuối kỳ
└── README.md                           # Tài liệu tổng quan dự án
```

---

## ⚙ 4. Yêu cầu Môi trường

| Công cụ | Phiên bản | Mục đích |
|:---|:---:|:---|
| **Docker Desktop** | 4.x (Engine 24+) | Chạy đồng bộ toàn bộ 13 containers (khuyến nghị) |
| **PHP** | 8.1+ (hoặc XAMPP) | Khởi chạy Frontend Web iBanking (Port `3000`) |
| **Node.js & npm** | 20.x | Chạy kiểm thử tự động Newman / chạy local Gateway & Notification |
| **Git** | 2.x | Quản lý mã nguồn |

---

## 🚀 5. Khởi chạy Nhanh (Quick Start)

> Lưu ý: Bản Giữa kỳ và Bản Cuối kỳ dùng chung dải cổng `8080–8086`, nên chỉ chạy **1 phiên bản tại một thời điểm** (dừng bản này trước khi bật bản kia).

### Chạy bản Cuối kỳ (Full Frameworks — Mặc định)
```powershell
docker compose -f docker-compose.final.yml up -d --build
# Kiểm tra sức khỏe toàn bộ 6 microservices:
curl.exe http://localhost:8080/health/services
```
👉 Xem đầy đủ các cách chạy (Docker, Hybrid, thủ công từng Framework, biến môi trường, demo OTP & Concurrency) tại **[readme_cuoiky.md](readme_cuoiky.md)**.

### Chạy bản Giữa kỳ (100% Native Code)
```powershell
docker compose -f docker-compose.midterm.yml up -d --build
# Hoặc chạy Local Windows không cần Docker (cần XAMPP MySQL):
powershell -ExecutionPolicy Bypass -File start-services.ps1
```
👉 Xem đầy đủ các cách chạy (Docker, `start-services.ps1`, Hybrid, thủ công từng Terminal) tại **[readme_giuaky.md](readme_giuaky.md)**.

### Mở Giao diện Frontend Web iBanking (Port `3000`)
- Nhấp đúp file [run-frontend.bat](run-frontend.bat) hoặc chạy:
  ```powershell
  php -S 127.0.0.1:3000 -t frontend frontend/router.php
  ```
- Truy cập: **[http://localhost:3000](http://localhost:3000)**
- Tài khoản mẫu: `admin` / `password123` (ADMIN) hoặc `member1` / `password123` (USER).

---

## 🧪 6. Hướng dẫn Kiểm thử Hệ thống

- **Web iBanking:** [http://localhost:3000](http://localhost:3000) — Trải nghiệm đầy đủ Đăng nhập, Thu/Chi, Ngân sách, Tài sản/Vay, Đồng bộ Ngân hàng, Phân tích Dòng tiền, Thông báo Realtime.
- **Swagger UI tập trung tại Gateway:** [http://localhost:8080/docs](http://localhost:8080/docs) (hoặc `/docs/auth`, `/docs/transaction`, `/docs/asset`, `/docs/analytics`, `/docs/integration`, `/docs/notification`).
- **Newman / Postman CLI (33 requests tự động):**
  ```powershell
  npm install -g newman newman-reporter-htmlextra
  newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on
  ```
- **Kiểm thử tải & Concurrency:**
  ```powershell
  # Go loadtest (200 giao dịch x 3 lần lặp retry, 50 worker song song):
  cd services/integration-service/final
  go run ./tools/loadtest -url http://localhost:8085 -account 1 -n 200 -dup 3 -c 50

  # Stress test bằng k6:
  k6 run scripts/concurrency-test-k6.js
  ```

---

## 🔍 7. Xem & Giám sát Dữ liệu trong CSDL

### Giao diện Web phpMyAdmin (Cổng `8088`)
```powershell
# Nếu đang chạy bản Cuối kỳ:
docker run -d --name cashflow-phpmyadmin-final --network cashflow-final-net -p 8088:80 -e PMA_ARBITRARY=1 phpmyadmin

# Nếu đang chạy bản Giữa kỳ:
docker run -d --name cashflow-phpmyadmin-midterm --network cashflow-midterm-net -p 8088:80 -e PMA_ARBITRARY=1 phpmyadmin
```
Mở **[http://localhost:8088](http://localhost:8088)** $\rightarrow$ Server: `auth-db`, `transaction-db`, `asset-db`, `integration-db` hoặc `analytics-db` | User: `root` | Password: `root_password`.

> Chi tiết toàn bộ câu lệnh `docker exec` truy vấn từng bảng CSDL, kiểm tra Redis Pub/Sub và cách Reset DB cho từng giai đoạn được liệt kê đầy đủ trong [readme_giuaky.md](readme_giuaky.md) và [readme_cuoiky.md](readme_cuoiky.md).

---

## 📡 8. Bảng Tra cứu API Endpoints

Mọi request từ Frontend và Client đều gửi qua **API Gateway cổng `8080`**:

| Method | Endpoint | Service đích | Yêu cầu JWT? | Mô tả chi tiết |
|:---:|:---|:---|:---:|:---|
| `GET` | `/health` & `/health/services` | API Gateway | ❌ Không | Kiểm tra sức khỏe Gateway và tổng hợp 6 microservices (Final) |
| `POST` | `/api/auth/register` | Auth Service | ❌ Không | Đăng ký tài khoản mới (hash mật khẩu `PBKDF2WithHmacSHA256`) |
| `POST` | `/api/auth/login` | Auth Service | ❌ Không | Đăng nhập hệ thống, cấp Access Token JWT + Refresh Token |
| `GET` / `PUT` | `/api/profile` | Auth Service | ✅ Có | Xem & cập nhật hồ sơ cá nhân người dùng |
| `GET` / `POST` / `PUT` / `DELETE` | `/api/transactions` | Transaction Service | ✅ Có | CRUD giao dịch thu/chi & tự động kiểm tra vượt ngân sách |
| `GET` / `POST` / `PUT` / `DELETE` | `/api/budgets` | Transaction Service | ✅ Có | Quản lý hạn mức ngân sách theo danh mục & kỳ |
| `GET` / `POST` / `PUT` / `DELETE` | `/api/assets`, `/api/loans`, `/api/investments` | Asset Service | ✅ Có | Quản lý tài sản, khoản vay, đầu tư và tổng tài sản ròng |
| `GET` | `/api/analytics/summary`, `/trend`, `/anomalies` | Analytics Service | ✅ Có | Phân tích thu chi, xu hướng dòng tiền & cảnh báo bất thường |
| `POST` | `/api/integrations/link-account`, `/sync`, `/webhook` | Integration Service | ✅ Có* | Liên kết ngân hàng, đồng bộ giao dịch & xử lý webhook đồng thời |
| `GET` | `/api/integrations/rates` | Integration Service | ❌ Không | Tra cứu tỷ giá ngoại tệ hiện hành |
| `GET` | `/api/notifications/stream` | Notification Serv. | ✅ Có | Luồng Server-Sent Events (SSE) thời gian thực |
| `POST` | `/api/notifications/send` | Notification Serv. | ✅ Có | Gửi thông báo qua SSE / Socket.io / Redis Pub/Sub |
| `POST` | `/api/notifications/otp/generate` & `/verify` | Notification Serv. | ✅ Có | Sinh & xác thực mã OTP 6 chữ số cho thanh toán (Bản Cuối kỳ) |

---

## 🔧 9. Xử lý Sự cố Thường gặp

- **Trùng cổng (`8080–8086`, `3307`, `3310`, `6379`):** Đảm bảo đã tắt phiên bản còn lại (`docker compose -f docker-compose.midterm.yml down` hoặc `docker compose -f docker-compose.final.yml down`) trước khi khởi chạy, hoặc kiểm tra tiến trình bằng `netstat -ano | findstr :8080`.
- **Lỗi bảng không tồn tại / Volume MySQL lỗi:** Reset volume sạch bằng `docker compose -f <file>.yml down -v` rồi `up -d --build`.
- Xem bảng xử lý lỗi chi tiết cho từng giai đoạn tại [readme_giuaky.md](readme_giuaky.md#-10-xử-lý-sự-cố-thường-gặp) và [readme_cuoiky.md](readme_cuoiky.md#-10-xử-lý-sự-cố-thường-gặp).

---

## 👥 10. Phân công Thành viên Thực hiện

| STT | Họ và Tên | MSSV | Vai trò & Service Phụ trách | Ngôn ngữ (Giữa kỳ → Cuối kỳ) | Cơ sở Dữ liệu / Broker |
|:---:|:---|:---:|:---|:---|:---|
| **1** | **Nguyễn Văn Duy** | `52400010` | **Nhóm trưởng** — Architecture, **API Gateway**, DevOps & **Notification/OTP Service**, Frontend | Node.js Native → **Express 4 + Socket.io + Redis** | Redis 7 |
| **2** | **Nguyễn Trọng Phúc** | `52400028` | **Auth & Profile Service** | Java 17 Native → **Spring Boot 3.5 + Security + JPA** | MySQL 8.0 (`auth_db`) |
| **3** | **Lê Văn Quý** | `52400153` | **Transaction & Budget Service** | PHP 8.2 Native PDO → **Laravel 12** | MySQL 8.0 (`transaction_db` / `transaction_final_db`) |
| **4** | **Lê Tiến Đạt** | `52400105` | **Asset & Investment Service** | C# .NET 10 Native → **ASP.NET Core 8 + EF Core 8** | MySQL 8.0 (`asset_db`) |
| **5** | **Lê Khải Hoàn** | `52400114` | **Analytics Service** | Python 3.11 Native → **FastAPI + Pydantic** | MySQL 8.0 (`analytics_db`) |
| **6** | **Trần Hữu Long** | `52400138` | **Integration & Concurrency Service** | Go 1.22 Native → **Go Gin + GORM** | MySQL 8.0 (`integration_db`) |
