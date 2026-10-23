# 💰 SOA CashFlow — Hệ thống Quản lý Tài chính & Phân tích Dòng tiền

> **Môn học:** Kiến trúc Hướng Dịch vụ (SOA) — Trường Đại học Tôn Đức Thắng (TDTU)  
> **Kiến trúc:** 6 Microservices (6 ngôn ngữ độc lập) + API Gateway + Frontend Web PHP  
> **Hạ tầng CSDL & Broker:** 5 MySQL 8.0 + 1 Redis 7  
> **Mô hình triển khai:** Docker Compose (13 containers) hoặc Local Native Windows  

---

## 📋 Mục lục

1. [Tổng quan Kiến trúc Hệ thống](#-1-tổng-quan-kiến-trúc-hệ-thống)
2. [Chi tiết & Trách nhiệm Từng Microservice](#-2-chi-tiết--trách-nhiệm-từng-microservice)
3. [Cấu trúc Thư mục Dự án](#-3-cấu-trúc-thư-mục-dự-án)
4. [Yêu cầu Môi trường](#-4-yêu-cầu-môi-trường)
5. [Hướng dẫn Chạy Dự án](#-5-hướng-dẫn-chạy-dự-án)
   - [Cách 1: Chạy bằng Docker Compose (Khuyến nghị số 1)](#51-cách-1-chạy-bằng-docker-compose-khuyến-nghị)
   - [Cách 2: Chạy Local trực tiếp trên Windows (start-services.ps1)](#52-cách-2-chạy-local-trực-tiếp-không-cần-docker)
   - [Cách 3: Khởi chạy Giao diện Frontend Web (Port 3000)](#53-khởi-chạy-giao-diện-frontend-web)
6. [Hướng dẫn Kiểm thử Hệ thống Toàn diện](#-6-hướng-dẫn-kiểm-thử-hệ-thống-toàn-diện)
   - [Cách A: Kiểm thử trực tiếp qua Giao diện Web iBanking](#61-cách-a-kiểm-thử-trực-tiếp-qua-giao-diện-web-ibanking)
   - [Cách B: Kiểm thử trực quan qua Swagger UI (Tích hợp sẵn)](#62-cách-b-kiểm-thử-trực-quan-qua-swagger-ui-tích-hợp-sẵn)
   - [Cách C: Kiểm thử tự động bằng Newman / Postman CLI](#63-cách-c-kiểm-thử-tự-động-bằng-newman--postman-cli)
   - [Cách D: Kiểm thử Tải & Hiệu năng Concurrency bằng k6](#64-cách-d-kiểm-thử-tải--hiệu-năng-concurrency-bằng-k6)
7. [Xem & Giám sát Dữ liệu trong CSDL (Kiểm tra biến động sau khi test)](#-7-xem--giám-sát-dữ-liệu-trong-csdl-sau-khi-thao-tác)
   - [Cách 1: Xem giao diện đồ họa Web như Excel qua phpMyAdmin](#71-xem-bảng-trực-quan-qua-web-bằng-phpmyadmin-khuyên-dùng)
   - [Cách 2: Lệnh Terminal xuất bảng kẻ khung tức thì](#72-lệnh-terminal-xuất-bảng-kẻ-khung-tức-thì)
   - [Cách 3: Hoàn tác CSDL về trạng thái gốc ban đầu (Reset Database)](#73-hoàn-tác-csdl-về-trạng-thái-gốc-ban-đầu-reset-database)
8. [Bảng Tra cứu API Endpoints & Định tuyến Gateway](#-8-bảng-tra-cứu-api-endpoints)
9. [Xử lý Sự cố Thường gặp (Troubleshooting)](#-9-xử-lý-sự-cố-thường-gặp)
10. [Phân công Thành viên Thực hiện](#-10-phân-công-thành-viên-thực-hiện)

---

## 🏗 1. Tổng quan Kiến trúc Hệ thống

Hệ thống được thiết kế theo đúng chuẩn **Kiến trúc Hướng Dịch vụ (Service-Oriented Architecture - SOA)**, kết hợp đa ngôn ngữ (Polyglot Microservices), giao tiếp phi tập trung qua HTTP RESTful API và sự kiện thời gian thực qua Redis Pub/Sub / SSE:

```
                              ┌────────────────────────┐
                              │  Frontend Web (PHP)    │
                              │   http://localhost:3000│
                              └───────────┬────────────┘
                                          │ HTTP Requests
                                          ▼
                              ┌────────────────────────┐
                              │  API Gateway (Node.js) │
                              │   http://localhost:8080│
                              └──┬───┬───┬───┬───┬───┬─┘
                                 │   │   │   │   │   │
        ┌────────────────────────┘   │   │   │   │   └────────────────────────┐
        ▼                            ▼   │   │   ▼                            ▼
┌───────────────┐  ┌──────────────────┐  │ ┌──────────────────┐  ┌────────────────────┐
│ Auth Service  │  │Transaction Serv. │  │ │Analytics Service │  │Notification Service│
│ Java 17 :8081 │  │  PHP 8.2  :8082  │  │ │Python 3.11 :8084 │  │  Node.js SSE :8086 │
│ (auth-db:3307)│  │ (transaction-db) │  │ │ (analytics-db)   │  │  (cashflow-redis)  │
└───────────────┘  └──────────────────┘  │ └──────────────────┘  └────────────────────┘
                                         │
                         ┌───────────────┴───────────────┐
                         ▼                               ▼
               ┌──────────────────┐            ┌────────────────────┐
               │  Asset Service   │            │Integration Service │
               │  C# .NET 10 :8083│            │   Go 1.22   :8085  │
               │    (asset-db)    │            │ integration-db:3310│
               └──────────────────┘            └────────────────────┘
```

---

## 🧩 2. Chi tiết & Trách nhiệm Từng Microservice

Hệ thống tuân thủ nguyên tắc **Database per Service** (mỗi service sở hữu cơ sở dữ liệu độc lập, không truy cập chéo DB của nhau):

| STT | Service | Công nghệ / Runtime | Port Host | Tên Container Service | Tên Container CSDL | Trách nhiệm Nghiệp vụ |
|:---:|:---|:---|:---:|:---|:---|:---|
| **0** | **API Gateway** | Node.js 20 Native | `8080` | `cashflow-gateway-service` | — | Điểm tiếp nhận duy nhất (Single Entry Point), xác thực JWT, định tuyến, CORS, Health Check tập trung, phục vụ Swagger UI. |
| **1** | **Auth Service** | Java 17 (`HttpServer` thuần) | `8081` | `cashflow-auth-service` | `cashflow-auth-db` (Port `3307`) | Quản lý người dùng, đăng ký, đăng nhập, băm mật khẩu BCrypt, cấp phát và thẩm định JWT Token. |
| **2** | **Transaction Service** | PHP 8.2 (PDO Native) | `8082` | `cashflow-transaction-service` | `cashflow-transaction-db` | Ghi nhận thu/chi, phân loại giao dịch, quản lý hạn mức ngân sách (Budgets), kiểm tra cảnh báo vượt chi tiêu. |
| **3** | **Asset Service** | C# .NET 10 (`HttpListener` thuần) | `8083` | `cashflow-asset-service` | `cashflow-asset-db` | Quản lý danh mục tài sản (tiền gửi, BĐS, chứng khoán), khoản nợ vay (Loans), tính toán tổng tài sản ròng (Net Worth). |
| **4** | **Analytics Service** | Python 3.11 (`http.server` & `PyMySQL`) | `8084` | `cashflow-analytics-service` | `cashflow-analytics-db` | Gọi Transaction Service lấy dữ liệu, phân tích cơ cấu chi tiêu, dự báo xu hướng dòng tiền và lưu trữ báo cáo tháng (`monthly_reports`). |
| **5** | **Integration Service** | Go 1.22 (`net/http` thuần) | `8085` | `cashflow-integration-service` | `cashflow-integration-db` (Port `3310`) | Giả lập Open Banking, liên kết tài khoản ngân hàng bên ngoài, đồng bộ lịch sử giao dịch, tra cứu tỷ giá. |
| **6** | **Notification Service** | Node.js 20 Native | `8086` | `cashflow-notification-service` | `cashflow-redis-notification` (Port `6379`) | Quản lý kênh Server-Sent Events (SSE) thời gian thực, lắng nghe và phát sóng sự kiện qua Redis Pub/Sub. |
| **7** | **Frontend Web** | PHP 8.x (Built-in Server) | `3000` | Chạy trên máy Host | — | Giao diện Web iBanking hoàn chỉnh, điều phối toàn bộ các nghiệp vụ người dùng qua Gateway. |

---

## 📁 3. Cấu trúc Thư mục Dự án

```
SOA-CashFlow/
├── gateway/
│   └── midterm/                   # API Gateway (Node.js Native Zero-dependency)
│       ├── Dockerfile
│       └── src/                   # index.js, proxy.js, jwt.js, docs.js (Swagger), openapi/
│
├── services/
│   ├── auth-service/
│   │   ├── db/init-auth-db.sql    # DDL & Seed bảng users
│   │   └── midterm/               # Java 17 HttpServer (pom.xml, src/)
│   ├── transaction-service/
│   │   ├── db/init-transaction-db.sql # Bảng transactions, budgets, categories
│   │   └── midterm/               # PHP 8.2 PDO (src/index.php, controllers/, models/)
│   ├── asset-service/
│   │   ├── db/init-asset-db.sql   # Bảng assets, loans, investments
│   │   └── midterm/               # C# .NET 10 HttpListener (AssetService.csproj, src/)
│   ├── analytics-service/
│   │   ├── db/init-analytics-db.sql # Bảng monthly_reports (MySQL JSON)
│   │   └── midterm/               # Python 3.11 Native & PyMySQL (src/main.py)
│   ├── integration-service/
│   │   ├── db/init-integration-db.sql # Bảng bank_accounts, sync_logs
│   │   └── midterm/               # Go 1.22 net/http (go.mod, src/)
│   └── notification-service/
│       └── midterm/               # Node.js SSE + Redis Pub/Sub (src/index.js, sse.js)
│
├── frontend/                      # Web Client PHP giao diện iBanking (Port 3000)
│   ├── router.php                 # Router định tuyến PHP Built-in Server
│   ├── index.php                  # Dashboard chính
│   ├── login.php / register.php   # Xác thực tài khoản
│   ├── transactions.php           # Quản lý thu/chi
│   ├── budgets.php                # Quản lý ngân sách
│   ├── assets.php                 # Quản lý tài sản
│   ├── analytics.php              # Xem biểu đồ phân tích
│   ├── banking.php                # Liên kết & đồng bộ ngân hàng
│   └── notifications.php          # Xem thông báo SSE
│
├── docs/
│   ├── api-docs/                  # Postman Collection & Environment JSON
│   ├── diagrams/                  # Sơ đồ kiến trúc, ERD, Use Case
│   └── reports/                   # Slide & Báo cáo đồ án
│
├── scripts/
│   ├── concurrency-test-k6.js     # Script kiểm thử tải k6
│   └── seed-data.sql              # Dữ liệu mẫu tổng hợp
│
├── docker-compose.midterm.yml     # File Docker Compose chính giữa kỳ (13 containers)
├── docker-compose.final.yml       # File Docker Compose cuối kỳ (Frameworks)
├── docker-compose.yml             # Compose cơ sở chung
├── start-services.ps1             # PowerShell script chạy local trực tiếp không Docker
├── run-frontend.bat               # File batch mở nhanh Frontend Web trên port 3000
└── README.md                      # Tài liệu hướng dẫn toàn diện
```

---

## ⚙ 4. Yêu cầu Môi trường

| Công cụ | Phiên bản | Mục đích |
|:---|:---:|:---|
| **Docker Desktop** | 4.x (Engine 24+) | Chạy hệ thống 13 containers đồng bộ |
| **PHP** | 8.1+ (hoặc XAMPP) | Khởi chạy Frontend Web iBanking |
| **Node.js & npm** | 18.x hoặc 20.x | Chạy kiểm thử tự động với Newman |
| **Git** | 2.x | Quản lý mã nguồn |

---

## 🚀 5. Hướng dẫn Chạy Dự án

### 5.1. Cách 1: Chạy bằng Docker Compose (Khuyến nghị)

Toàn bộ 6 services, API Gateway, 5 CSDL MySQL và 1 Redis sẽ được khởi chạy độc lập trong **13 containers**:

```powershell
# 1. Khởi động và build toàn bộ 13 containers:
docker-compose -f docker-compose.midterm.yml up -d --build
```

#### Kiểm tra trạng thái:
```powershell
# Xem danh sách 13 containers đang chạy:
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# Kiểm tra sức khỏe Gateway:
curl.exe http://localhost:8080/health
```

#### Build lại riêng từng Service khi sửa code (Tiết kiệm thời gian):
Không cần build lại cả 13 container, chỉ build service bạn vừa chỉnh sửa:
```powershell
# Ví dụ: chỉ build lại auth-service và analytics-service:
docker-compose -f docker-compose.midterm.yml up -d --build auth-service analytics-service
```

#### Dừng hệ thống:
```powershell
# Dừng các containers (giữ nguyên dữ liệu trong CSDL):
docker-compose -f docker-compose.midterm.yml down

# Dừng VÀ XÓA TOÀN BỘ dữ liệu phát sinh (Reset về trạng thái gốc):
docker-compose -f docker-compose.midterm.yml down -v
```

---

### 5.2. Cách 2: Chạy Local trực tiếp (Không cần Docker)

Nếu máy bạn đã bật XAMPP MySQL (cổng `3306`, root/root_password) và muốn chạy trực tiếp mã nguồn trên Windows:

```powershell
# Khởi động toàn bộ 6 services + Gateway + Frontend:
powershell -ExecutionPolicy Bypass -File start-services.ps1

# Chạy nhanh (bỏ qua bước build nếu code không đổi):
powershell -ExecutionPolicy Bypass -File start-services.ps1 -NoBuild

# Dừng toàn bộ các tiến trình đang chạy:
powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop
```

---

### 5.3. Khởi chạy Giao diện Frontend Web

Frontend hoạt động trực tiếp trên máy host ở cổng `3000`:

- **Cách 1:** Nhấp đúp chuột vào file [run-frontend.bat](file:///d:/3.1.%20SOA/SOA-CashFlow/run-frontend.bat).
- **Cách 2:** Mở Terminal và gõ:
  ```powershell
  php -S 127.0.0.1:3000 -t frontend frontend/router.php
  ```
- **Truy cập:** Mở trình duyệt vào **[http://localhost:3000](http://localhost:3000)**
- **Tài khoản mặc định:** `admin` (hoặc `user1`) / Mật khẩu: `password123`

---

## 🧪 6. Hướng dẫn Kiểm thử Hệ thống Toàn diện

### 6.1. Cách A: Kiểm thử trực tiếp qua Giao diện Web iBanking

1. Mở **http://localhost:3000**, đăng nhập tài khoản `user1` / `password123`.
2. **Thu/Chi:** Vào trang *Giao dịch* -> Thêm khoản chi mới -> Kiểm tra số dư cập nhật.
3. **Ngân sách:** Vào trang *Ngân sách* -> Đặt hạn mức ăn uống -> Tạo giao dịch chi tiêu lớn hơn hạn mức -> Nhận cảnh báo vượt ngân sách.
4. **Tài sản & Khoản nợ:** Vào trang *Tài sản* -> Thêm sổ tiết kiệm, khoản nợ -> Xem biến động Net Worth.
5. **Đồng bộ Ngân hàng:** Vào trang *Liên kết ngân hàng* -> Chọn ngân hàng Vietcombank/MB -> Bấm *Đồng bộ ngay*.
6. **Báo cáo Dòng tiền:** Vào trang *Phân tích* -> Xem biểu đồ thu chi, cơ cấu chi tiêu do Analytics Service tính toán.
7. **Thông báo Realtime:** Bấm vào biểu tượng quả chuông hoặc vào trang *Thông báo* để nhận tin nhắn Server-Sent Events.

---

### 6.2. Cách B: Kiểm thử trực quan qua Swagger UI (Tích hợp sẵn)

Dự án đã tích hợp sẵn Swagger UI tại API Gateway:

- **Trang tổng hợp:** **[http://localhost:8080/docs](http://localhost:8080/docs)** (hoặc `/swagger`)
- **Từng service riêng:** `/docs/auth`, `/docs/transaction`, `/docs/asset`, `/docs/analytics`, `/docs/integration`, `/docs/notification`.

#### 3 Bước test có bảo mật trên Swagger:
1. **Lấy Token:** Chọn `Auth Service` -> `POST /api/auth/login` -> Bấm *Try it out* -> Đăng nhập với `user1` / `password123` -> Sao chép chuỗi `token`.
2. **Authorize:** Bấm nút **Authorize 🔓** (màu xanh lá ở đầu trang Swagger) -> Dán token vào -> Bấm *Authorize* -> *Close*.
3. **Gọi API:** Chọn bất kỳ service nào (Transaction, Asset, Analytics...) -> Bấm *Try it out* -> *Execute* để gọi trực tiếp.

---

### 6.3. Cách C: Kiểm thử tự động bằng Newman / Postman CLI

Bộ test hồi quy tự động gồm **33 requests & hàng chục assertions**:

```powershell
# Cài đặt Newman (1 lần duy nhất):
npm install -g newman newman-reporter-htmlextra

# Chạy test toàn bộ API:
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on

# Xuất báo cáo HTML đẹp:
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json -r cli,htmlextra --reporter-htmlextra-export newman-report.html
```

> 💡 *Lưu ý:* Khi chạy lại từ lần 2, một số request `Register` sẽ trả về mã `409 Conflict` (do tài khoản đã tồn tại). Đây là kết quả đúng chuẩn logic.

---

### 6.4. Cách D: Kiểm thử Tải & Hiệu năng Concurrency bằng k6

```powershell
# Chạy stress test k6:
k6 run scripts/concurrency-test-k6.js
```

---

## 🔍 7. Xem & Giám sát Dữ liệu trong CSDL (Sau khi thao tác)

Khi bạn thao tác trên Web hoặc gọi API qua Swagger/Newman, dữ liệu sẽ được ghi thẳng vào 5 container MySQL trong Docker.

### 7.1. Xem bảng trực quan qua Web bằng phpMyAdmin (Khuyên dùng)

Chạy 1 dòng lệnh duy nhất để tạo Web GUI xem CSDL như Excel:

```powershell
docker run -d --name cashflow-phpmyadmin --network cashflow-midterm-net -p 8088:80 -e PMA_ARBITRARY=1 phpmyadmin
```

- Mở trình duyệt vào: **[http://localhost:8088](http://localhost:8088)**
- Đăng nhập vào CSDL muốn xem:
  - **Server:** Nhập một trong các tên:
    - `auth-db` (Xem thông tin tài khoản user)
    - `transaction-db` (Xem thu/chi, danh mục, ngân sách)
    - `asset-db` (Xem tài sản, khoản nợ)
    - `integration-db` (Xem tài khoản ngân hàng liên kết, sao kê)
    - `analytics-db` (Xem báo cáo phân tích dòng tiền)
  - **Username:** `root`
  - **Password:** `root_password`
- Nhấn **Log in**: Bạn có giao diện bảng để click chuột xem, lọc và sửa dữ liệu dễ dàng.

---

### 7.2. Lệnh Terminal xuất bảng kẻ khung tức thì

Bạn có thể mở một Terminal trong VS Code và chạy các lệnh sau để in bảng dữ liệu ra màn hình:

```powershell
# 1. Kiểm tra tài khoản người dùng vừa tạo (Auth Service):
docker exec -it cashflow-auth-db mysql -uroot -proot_password auth_db -e "SELECT id, username, email, full_name, role, created_at FROM users;"

# 2. Kiểm tra giao dịch vừa thêm (Transaction Service):
docker exec -it cashflow-transaction-db mysql -uroot -proot_password transaction_db -e "SELECT id, user_id, amount, type, category, description, transaction_date FROM transactions ORDER BY id DESC LIMIT 5;"

# 3. Kiểm tra hạn mức ngân sách (Budgets):
docker exec -it cashflow-transaction-db mysql -uroot -proot_password transaction_db -e "SELECT id, category, amount_limit, spent_amount, period FROM budgets;"

# 4. Kiểm tra danh mục tài sản vừa thêm (Asset Service):
docker exec -it cashflow-asset-db mysql -uroot -proot_password asset_db -e "SELECT id, name, type, value, status FROM assets ORDER BY id DESC;"

# 5. Kiểm tra tài khoản ngân hàng liên kết (Integration Service):
docker exec -it cashflow-integration-db mysql -uroot -proot_password integration_db -e "SELECT id, bank_code, account_number, account_holder, balance FROM bank_accounts;"

# 6. Kiểm tra báo cáo phân tích tháng đã lưu (Analytics Service):
docker exec -it cashflow-analytics-db mysql -uroot -proot_password analytics_db -e "SELECT report_id, user_id, year, month, generated_at FROM monthly_reports;"
```

---

### 7.3. Hoàn tác CSDL về trạng thái gốc ban đầu (Reset Database)

Sau khi kiểm thử xong, nếu bạn muốn **xóa sạch toàn bộ dữ liệu vừa tạo** và đưa hệ thống quay lại trạng thái dữ liệu mẫu ban đầu:

#### Cách 1: Reset toàn diện bằng Docker Compose (Triệt để nhất)
```powershell
# Xóa sạch toàn bộ volumes dữ liệu test:
docker-compose -f docker-compose.midterm.yml down -v

# Khởi động lại hệ thống (chỉ mất ~5-10s):
docker-compose -f docker-compose.midterm.yml up -d
```

#### Cách 2: Reset nóng CSDL mà không tắt các Service khác
```powershell
docker rm -f cashflow-auth-db cashflow-transaction-db cashflow-asset-db cashflow-integration-db cashflow-analytics-db
docker-compose -f docker-compose.midterm.yml up -d auth-db transaction-db asset-db integration-db analytics-db
```

---

## 📡 8. Bảng Tra cứu API Endpoints

Mọi request từ Frontend và Client đều gửi qua **API Gateway cổng `8080`**:

| Method | Endpoint | Service đích | Yêu cầu JWT? | Mô tả chi tiết |
|:---:|:---|:---|:---:|:---|
| `GET` | `/health` | API Gateway | ❌ Không | Báo cáo sức khỏe Gateway và danh sách 12 routes |
| `POST` | `/api/auth/register` | Auth Service | ❌ Không | Đăng ký tài khoản người dùng mới |
| `POST` | `/api/auth/login` | Auth Service | ❌ Không | Đăng nhập hệ thống, cấp Access Token JWT |
| `GET` | `/api/auth/profile` hoặc `/api/profile` | Auth Service | ✅ Có | Xem hồ sơ người dùng đang đăng nhập |
| `GET` | `/api/transactions` | Transaction Service | ✅ Có | Lấy danh sách giao dịch thu/chi |
| `POST` | `/api/transactions` | Transaction Service | ✅ Có | Tạo giao dịch mới & kiểm tra ngân sách |
| `GET` | `/api/budgets` | Transaction Service | ✅ Có | Lấy danh sách ngân sách chi tiêu |
| `POST` | `/api/budgets` | Transaction Service | ✅ Có | Thiết lập hạn mức ngân sách mới |
| `GET` | `/api/assets` | Asset Service | ✅ Có | Danh sách tài sản & danh mục đầu tư |
| `POST` | `/api/assets` | Asset Service | ✅ Có | Thêm mới một tài sản |
| `GET` | `/api/loans` | Asset Service | ✅ Có | Quản lý các khoản nợ / vay |
| `GET` | `/api/analytics/summary` | Analytics Service | ✅ Có | Tổng quan thu chi, số dư dòng tiền |
| `GET` | `/api/analytics/trend` | Analytics Service | ✅ Có | Xu hướng biến động tài chính theo thời gian |
| `POST` | `/api/integrations/link-account` | Integration Service| ✅ Có | Liên kết tài khoản ngân hàng Open Banking |
| `POST` | `/api/integrations/sync` | Integration Service| ✅ Có | Kích hoạt tác vụ đồng bộ lịch sử giao dịch |
| `GET` | `/api/integrations/rates` | Integration Service| ❌ Không | Tra cứu tỷ giá ngoại tệ hiện hành |
| `GET` | `/api/notifications/stream` | Notification Serv. | ✅ Có | Mở luồng Server-Sent Events (SSE) thời gian thực |
| `POST` | `/api/notifications/send` | Notification Serv. | ✅ Có | Phát thông báo sự kiện qua Redis Pub/Sub |

---

## 🔧 9. Xử lý Sự cố Thường gặp

### 1. Trùng cổng (Port already in use)
```powershell
# Tìm tiến trình đang chiếm cổng (ví dụ 8080):
netstat -ano | findstr :8080
# Tắt tiến trình theo PID:
taskkill /F /PID <PID>
```

### 2. Lỗi MySQL 1146 "Table doesn't exist"
Xảy ra khi volume cũ của Docker bị đọng:
```powershell
docker-compose -f docker-compose.midterm.yml down -v
docker-compose -f docker-compose.midterm.yml up -d --build
```

### 3. Asset Service (.NET) lỗi kết nối CSDL
Chuỗi kết nối MySQL trong C# không được dùng `SslMode=None`:
```
Server=asset-db;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;
```

---

## 👥 10. Phân công Thành viên Thực hiện

| STT | Vai trò | Microservices Phụ trách | Ngôn ngữ & Công nghệ | Cơ sở Dữ liệu / Broker |
|:---:|:---|:---|:---|:---|
| **0** | **Nhóm trưởng** | **API Gateway**, **Notification Service**, **Frontend Web** | Node.js 20, PHP 8.x | Redis 7 (`cashflow-redis-notification`) |
| **1** | Thành viên 1 | **Auth Service** | Java 17 (`HttpServer` thuần) | MySQL 8.0 (`cashflow-auth-db`) |
| **2** | Thành viên 2 | **Transaction Service** | PHP 8.2 (PDO Native) | MySQL 8.0 (`cashflow-transaction-db`) |
| **3** | Thành viên 3 | **Asset Service** | C# .NET 10 (`HttpListener` thuần) | MySQL 8.0 (`cashflow-asset-db`) |
| **4** | Thành viên 4 | **Analytics Service** | Python 3.11 (`http.server` & `PyMySQL`) | MySQL 8.0 (`cashflow-analytics-db`) |
| **5** | Thành viên 5 | **Integration Service** | Go 1.22 (`net/http` thuần) | MySQL 8.0 (`cashflow-integration-db`) |
