# 💰 SOA CashFlow — Hệ thống Quản lý Tài chính & Phân tích Dòng tiền

> **Môn học:** Kiến trúc Hướng Dịch vụ (SOA) — Trường Đại học Tôn Đức Thắng  
> **Kiến trúc:** 6 Microservices (6 ngôn ngữ) + API Gateway + Frontend PHP  
> **CSDL:** 4 MySQL + 1 MongoDB + 1 Redis

---

## 📋 Mục lục

- [Tổng quan Kiến trúc](#-tổng-quan-kiến-trúc)
- [Cấu trúc Thư mục](#-cấu-trúc-thư-mục)
- [Yêu cầu Hệ thống](#-yêu-cầu-hệ-thống)
- [Cách 1 — Chạy bằng Docker Compose (Khuyến nghị)](#-cách-1--chạy-bằng-docker-compose-khuyến-nghị)
- [Cách 2 — Chạy Local không Docker](#-cách-2--chạy-local-không-docker)
- [Chạy Frontend Web](#-chạy-frontend-web)
- [Test API bằng Newman / Postman](#-test-api-bằng-newman--postman)
- [Danh sách API Endpoints](#-danh-sách-api-endpoints)
- [Xử lý Sự cố](#-xử-lý-sự-cố)
- [Phân công Thành viên](#-phân-công-thành-viên)

---

## 🏗 Tổng quan Kiến trúc

```
                          ┌─────────────────────┐
                          │   Frontend Web PHP   │
                          │      :3000           │
                          └──────────┬───────────┘
                                     │ HTTP
                          ┌──────────▼───────────┐
                          │    API Gateway        │
                          │   Node.js  :8080      │
                          └──┬──┬──┬──┬──┬──┬────┘
                             │  │  │  │  │  │
          ┌──────────────────┘  │  │  │  │  └──────────────────┐
          ▼                     ▼  │  ▼  ▼                     ▼
 ┌────────────────┐  ┌────────────┐│┌────────────┐  ┌────────────────────┐
 │ Auth Service   │  │Transaction ││ │ Analytics  │  │ Notification       │
 │ Java 17 :8081  │  │ PHP  :8082 ││ │Python :8084│  │ Node.js SSE  :8086 │
 │    MySQL       │  │   MySQL    ││ │  MongoDB   │  │      Redis         │
 └────────────────┘  └────────────┘│ └────────────┘  └────────────────────┘
                                   │
                    ┌──────────────┴──────────────┐
                    ▼                              ▼
          ┌────────────────┐            ┌────────────────────┐
          │ Asset Service  │            │ Integration Service│
          │ C# .NET :8083  │            │   Go 1.22  :8085   │
          │    MySQL       │            │      MySQL         │
          └────────────────┘            └────────────────────┘
```

| Service | Ngôn ngữ | Port | Database | Mô tả |
|:--------|:---------|:----:|:---------|:------|
| **API Gateway** | Node.js 20 | `8080` | — | Reverse proxy, JWT verify, routing |
| **Auth Service** | Java 17 (`HttpServer`) | `8081` | MySQL `auth_db` (port 3307) | Đăng ký, đăng nhập, JWT |
| **Transaction Service** | PHP 8.2 (PDO Native) | `8082` | MySQL `transaction_db` | Thu/chi, ngân sách |
| **Asset Service** | C# .NET 10 (`HttpListener`) | `8083` | MySQL `asset_db` | Quản lý tài sản |
| **Analytics Service** | Python 3.11 (`http.server`) | `8084` | MongoDB `analytics_db` | Phân tích & báo cáo |
| **Integration Service** | Go 1.22 (`net/http`) | `8085` | MySQL `integration_db` (port 3310) | Liên kết ngân hàng, đồng bộ |
| **Notification Service** | Node.js 20 (SSE + Redis) | `8086` | Redis (port 6379) | Thông báo real-time |
| **Frontend** | PHP built-in server | `3000` | — | Giao diện web iBanking |

> **Lưu ý:** Phiên bản **Giữa kỳ** (midterm) viết code thuần không dùng framework. Phiên bản **Cuối kỳ** (final) sử dụng Spring Boot, Laravel, ASP.NET Core, FastAPI, Gin, NestJS.

---

## 📁 Cấu trúc Thư mục

```
SOA-CashFlow/
├── gateway/
│   └── midterm/                   # API Gateway - Native Node.js
│       ├── Dockerfile
│       └── src/                   # index.js, proxy.js, jwt.js, config.js, docs.js
│
├── services/
│   ├── auth-service/
│   │   ├── db/init-auth-db.sql    # Script khởi tạo bảng users
│   │   └── midterm/               # Java 17 HttpServer
│   │       ├── Dockerfile
│   │       ├── pom.xml
│   │       └── src/
│   │
│   ├── transaction-service/
│   │   ├── db/init-transaction-db.sql
│   │   └── midterm/               # PHP 8.2 PDO
│   │       ├── Dockerfile
│   │       ├── composer.json
│   │       └── src/
│   │
│   ├── asset-service/
│   │   ├── db/init-asset-db.sql
│   │   └── midterm/               # C# .NET 10 HttpListener
│   │       ├── Dockerfile
│   │       ├── AssetService.csproj
│   │       └── src/
│   │
│   ├── analytics-service/
│   │   ├── db/init-mongo.js       # Script khởi tạo MongoDB
│   │   └── midterm/               # Python 3.11 http.server
│   │       ├── Dockerfile
│   │       ├── requirements.txt
│   │       └── src/
│   │
│   ├── integration-service/
│   │   ├── db/init-integration-db.sql
│   │   └── midterm/               # Go 1.22 net/http
│   │       ├── Dockerfile
│   │       ├── go.mod / go.sum
│   │       └── src/
│   │
│   └── notification-service/
│       └── midterm/               # Node.js SSE + Redis Pub/Sub
│           ├── Dockerfile
│           ├── package.json
│           └── src/
│
├── frontend/                      # Giao diện Web PHP (iBanking)
│
├── docs/
│   ├── api-docs/                  # Postman Collection & Environment
│   ├── diagrams/                  # Sơ đồ kiến trúc, ERD, Use Case
│   └── reports/                   # Slide & báo cáo
│
├── scripts/
│   ├── concurrency-test-k6.js     # K6 stress test
│   └── seed-data.sql              # Dữ liệu mẫu
│
├── docker-compose.yml             # Compose chung (có volume persist)
├── docker-compose.midterm.yml     # Compose Giữa kỳ (code thuần, không volume)
├── docker-compose.final.yml       # Compose Cuối kỳ (framework)
├── start-services.ps1             # Script chạy local trên Windows (PowerShell)
├── run-frontend.bat               # Chạy frontend bằng PHP built-in server
├── .env.example                   # Biến môi trường mẫu
└── README.md
```

---

## ⚙ Yêu cầu Hệ thống

### Chạy bằng Docker (Cách 1)

| Công cụ | Phiên bản tối thiểu |
|:--------|:--------------------|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | 4.x (Engine 24+) |
| [Git](https://git-scm.com/) | 2.x |
| [Node.js](https://nodejs.org/) + npm | 18.x (để cài Newman) |

### Chạy Local (Cách 2)

| Công cụ | Ghi chú |
|:--------|:--------|
| XAMPP | PHP 8.2 + MySQL (port 3306, root/root_password) |
| JDK 17 + Maven 3.9 | Hoặc dùng portable trong `.tools/` |
| .NET 10 SDK | Hoặc portable trong `.tools/` |
| Go 1.22 | Hoặc portable trong `.tools/` |
| Python 3.11+ | Với pip |
| Node.js 20 | Cho Gateway + Notification |

---

## 🐳 Cách 1 — Chạy bằng Docker Compose (Khuyến nghị)

### Bước 1: Clone dự án

```bash
git clone https://github.com/<your-org>/SOA-CashFlow.git
cd SOA-CashFlow
```

### Bước 2: Tạo file .env (tùy chọn)

```bash
cp .env.example .env
# Chỉnh sửa .env nếu muốn đổi port hoặc mật khẩu
```

> Nếu không tạo `.env`, hệ thống sẽ dùng giá trị mặc định trong `docker-compose.midterm.yml`.

### Bước 3: Khởi chạy hệ thống (13 containers)

**Phiên bản Giữa kỳ (code thuần — không framework):**
```bash
docker-compose -f docker-compose.midterm.yml up -d --build
```

**Phiên bản Cuối kỳ (framework):**
```bash
docker-compose -f docker-compose.final.yml up -d --build
```

**Phiên bản chung (có volume persist data):**
```bash
docker-compose up -d --build
```

> ⏱ **Lần build đầu** mất khoảng **5–15 phút** (tải base images + build). Các lần sau nhanh hơn nhờ Docker cache.

### Bước 4: Kiểm tra trạng thái

```bash
# Xem danh sách containers
docker-compose -f docker-compose.midterm.yml ps

# Kiểm tra health gateway
curl http://localhost:8080/health

# Xem log một service cụ thể
docker logs cashflow-auth-midterm
docker logs cashflow-asset-midterm
docker logs cashflow-integration-midterm
```

### Bước 5: Build lại một service cụ thể (khi sửa code)

Không cần build lại toàn bộ. Chỉ build service đã sửa:

```bash
# Ví dụ: chỉ build lại auth-service và integration-service
docker-compose -f docker-compose.midterm.yml up -d --build auth-service integration-service

# Build lại 3 service đã sửa (không ảnh hưởng database)
docker-compose -f docker-compose.midterm.yml up -d --build auth-service asset-service integration-service
```

### Bước 6: Dừng & Dọn dẹp

```bash
# Dừng tất cả containers
docker-compose -f docker-compose.midterm.yml down

# Dừng + xóa volume (reset toàn bộ database — dữ liệu sẽ mất!)
docker-compose -f docker-compose.midterm.yml down -v

# Xóa images đã build để build lại từ đầu
docker-compose -f docker-compose.midterm.yml down --rmi local
```

---

## 💻 Cách 2 — Chạy Local không Docker

> Yêu cầu: XAMPP đang chạy (MySQL port 3306, user `root`, password `root_password`).

### Tự động bằng PowerShell script

```powershell
# Chạy tất cả services + frontend (build + start)
powershell -ExecutionPolicy Bypass -File start-services.ps1

# Chạy không build lại (nếu đã build trước đó)
powershell -ExecutionPolicy Bypass -File start-services.ps1 -NoBuild

# Dừng tất cả
powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop
```

Script sẽ:
1. Build auth-service (Java), asset-service (.NET), integration-service (Go)
2. Khởi chạy cả 6 services + gateway + frontend
3. Kiểm tra health trên từng port 8081–8086
4. Mở giao diện tại `http://127.0.0.1:3000`

> **Toolchain portable:** Script tự dùng JDK, Maven, Go, .NET từ thư mục `.tools/` (không cần cài đặt system-wide).

---

## 🌐 Chạy Frontend Web

Frontend là ứng dụng PHP chạy trên built-in server, **không nằm trong Docker**.

### Cách 1: Dùng batch file

```bash
# Windows - double click hoặc chạy từ terminal:
run-frontend.bat
```

### Cách 2: Chạy thủ công

```bash
php -S 127.0.0.1:3000 -t frontend frontend/router.php
```

Truy cập: **http://127.0.0.1:3000**

> Frontend giao tiếp với backend thông qua API Gateway cổng `8080`.

---

## 🧪 Test API bằng Newman / Postman

### Cài đặt Newman (chỉ cần 1 lần)

```bash
npm install -g newman
```

### Chạy toàn bộ bộ test (33 requests, 22 assertions)

```bash
newman run docs/api-docs/Postman_Collection_All_Services.json \
       -e docs/api-docs/Postman_Environment.json \
       --color on
```

> **Trên Windows PowerShell**, dùng backtick `` ` `` thay `\` cho xuống dòng, hoặc viết 1 dòng:
> ```powershell
> newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on
> ```

### Xuất báo cáo HTML

```bash
# Cài reporter (1 lần)
npm install -g newman-reporter-htmlextra

# Chạy test + xuất báo cáo
newman run docs/api-docs/Postman_Collection_All_Services.json \
       -e docs/api-docs/Postman_Environment.json \
       --reporters cli,htmlextra \
       --reporter-htmlextra-export newman-report.html
```

### Lưu ý về lỗi 409 Conflict

Khi chạy Newman **lần thứ 2 trở đi**, một số request `Register User` hoặc `Link Account` sẽ trả về `409 Conflict` (do dữ liệu đã tồn tại từ lần chạy trước). **Đây là hành vi đúng** — collection đã xử lý cả 2 trường hợp `201` và `409`.

Để reset lại dữ liệu và luôn nhận `201`:

```bash
# Cách 1: Xóa volume database rồi chạy lại
docker-compose -f docker-compose.midterm.yml down -v
docker-compose -f docker-compose.midterm.yml up -d --build

# Chờ ~15 giây cho database khởi tạo xong, rồi chạy test
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on
```

---

## 📡 Danh sách API Endpoints

### Qua Gateway (port 8080)

| Method | Endpoint | Service | Mô tả |
|:-------|:---------|:--------|:-------|
| `GET` | `/health` | Gateway | Health check |
| `POST` | `/api/auth/register` | Auth | Đăng ký tài khoản |
| `POST` | `/api/auth/login` | Auth | Đăng nhập, nhận JWT |
| `GET` | `/api/auth/profile` | Auth | Thông tin người dùng |
| `GET` | `/api/transactions` | Transaction | Danh sách giao dịch |
| `POST` | `/api/transactions` | Transaction | Tạo giao dịch mới |
| `GET` | `/api/assets` | Asset | Danh sách tài sản |
| `POST` | `/api/assets` | Asset | Thêm tài sản |
| `GET` | `/api/analytics/summary` | Analytics | Phân tích tổng quan |
| `POST` | `/api/integration/link-account` | Integration | Liên kết tài khoản ngân hàng |
| `POST` | `/api/integration/sync` | Integration | Đồng bộ giao dịch |
| `GET` | `/api/notifications/stream` | Notification | SSE stream thông báo |

### Truy cập trực tiếp từng service

| Service | URL trực tiếp |
|:--------|:-------------|
| Auth | `http://localhost:8081/api/auth/*` |
| Transaction | `http://localhost:8082/api/transactions/*` |
| Asset | `http://localhost:8083/api/assets/*` |
| Analytics | `http://localhost:8084/api/analytics/*` |
| Integration | `http://localhost:8085/api/integration/*` |
| Notification | `http://localhost:8086/api/notifications/*` |

---

## 🔧 Xử lý Sự cố

### Container không khởi động được

```bash
# Xem log chi tiết
docker logs cashflow-auth-midterm --tail 50
docker logs cashflow-asset-midterm --tail 50

# Kiểm tra kết nối DB bên trong container
docker exec -it cashflow-auth-db mysql -uroot -proot_password -e "SHOW DATABASES;"
```

### Lỗi "Table doesn't exist" (Error 1146)

MySQL chỉ chạy script `init.sql` khi volume **trống** (lần đầu). Nếu đã tồn tại volume cũ:

```bash
# Cách 1: Xóa volume rồi up lại
docker-compose -f docker-compose.midterm.yml down -v
docker-compose -f docker-compose.midterm.yml up -d

# Cách 2: Chạy SQL thủ công vào container đang chạy
Get-Content services/integration-service/db/init-integration-db.sql | docker exec -i cashflow-integration-db mysql -uroot -proot_password integration_db
```

### Asset Service lỗi "400 Bad Request: SslMode=None"

Driver `MySql.Data` (Oracle) không hỗ trợ giá trị `None`. Chuỗi kết nối đúng:
```
Server=asset-db;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;
```
(Không có `SslMode=None;`)

### Port bị chiếm

```powershell
# Kiểm tra ai đang dùng port 8080
netstat -ano | findstr :8080

# Dừng process theo PID
taskkill /PID <PID> /F
```

### Build lại sạch từ đầu

```bash
docker-compose -f docker-compose.midterm.yml down -v --rmi local
docker-compose -f docker-compose.midterm.yml up -d --build
```

---

## 🧪 Kiểm thử Tải & Concurrency

Sử dụng [k6](https://k6.io/) với script tại `scripts/concurrency-test-k6.js`:

```bash
k6 run scripts/concurrency-test-k6.js
```

---

## 👥 Phân công Thành viên

| STT | Vai trò | Service | Ngôn ngữ | CSDL |
|:---:|:--------|:--------|:---------|:-----|
| 0 | **Nhóm trưởng** | API Gateway + Notification + Frontend | Node.js | Redis |
| 1 | Thành viên 1 | Auth Service | Java 17 | MySQL |
| 2 | Thành viên 2 | Transaction Service | PHP 8.2 | MySQL |
| 3 | Thành viên 3 | Asset Service | C# .NET 10 | MySQL |
| 4 | Thành viên 4 | Analytics Service | Python 3.11 | MongoDB |
| 5 | Thành viên 5 | Integration Service | Go 1.22 | MySQL |

---

## 📝 Ghi chú phiên bản

| Phiên bản | Docker Compose file | Đặc điểm |
|:----------|:-------------------|:----------|
| **Giữa kỳ** | `docker-compose.midterm.yml` | Code thuần, không framework, 13 containers |
| **Cuối kỳ** | `docker-compose.final.yml` | Sử dụng framework chuẩn công nghiệp |
| **Chung** | `docker-compose.yml` | Volume persist data, `restart: always` |
