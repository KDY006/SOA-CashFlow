# 📘 Hướng dẫn Chạy Dự án — Giai đoạn Giữa kỳ (Midterm: 100% Native Code)

> **Tài liệu này dành riêng cho phiên bản Giữa kỳ (`docker-compose.midterm.yml` & thư mục `*/midterm/`)** — toàn bộ 6 Microservices và API Gateway được xây dựng **thuần bằng thư viện chuẩn (Zero-Framework / Native Code)** của 6 ngôn ngữ độc lập.
>
> 👉 Bạn muốn chạy phiên bản nâng cấp **Cuối kỳ (Full Frameworks)**? Xem **[readme_cuoiky.md](readme_cuoiky.md)**.  
> 👉 Quay về trang tổng quan dự án: **[README.md](README.md)**.

---

## 📋 Mục lục

1. [Tổng quan Kiến trúc Bản Giữa kỳ](#-1-tổng-quan-kiến-trúc-bản-giữa-kỳ)
2. [Yêu cầu Môi trường](#-2-yêu-cầu-môi-trường)
3. [Cách 1: Chạy bằng Docker Compose (Khuyến nghị số 1)](#-3-cách-1-chạy-bằng-docker-compose-khuyến-nghị-số-1)
4. [Cách 2: Chạy Local tự động trên Windows (start-services.ps1)](#-4-cách-2-chạy-local-tự-động-trên-windows-start-servicesps1)
5. [Cách 3: Chạy chế độ Hybrid (DB/Redis trong Docker + Code chạy Local)](#-5-cách-3-chạy-chế-độ-hybrid-dbredis-trong-docker--code-chạy-local)
6. [Cách 4: Chạy thủ công từng Service trên từng Terminal](#-6-cách-4-chạy-thủ-công-từng-service-trên-từng-terminal)
7. [Khởi chạy Giao diện Frontend Web iBanking (Port 3000)](#-7-khởi-chạy-giao-diện-frontend-web-ibanking-port-3000)
8. [Hướng dẫn Kiểm thử Bản Giữa kỳ](#-8-hướng-dẫn-kiểm-thử-bản-giữa-kỳ)
9. [Xem, Giám sát & Reset Dữ liệu trong CSDL](#-9-xem-giám-sát--reset-dữ-liệu-trong-csdl)
10. [Xử lý Sự cố Thường gặp](#-10-xử-lý-sự-cố-thường-gặp)

---

## 🏗 1. Tổng quan Kiến trúc Bản Giữa kỳ

Bản Giữa kỳ chạy **13 containers** trên mạng Docker nội bộ `cashflow-midterm-net` (tuân thủ nguyên tắc **Database-per-Service**):

| STT | Thành phần | Công nghệ Native (Thuần) | Port Host | Tên Container Service | Tên Container CSDL / Broker | Database Name |
|:---:|:---|:---|:---:|:---|:---|:---|
| **0** | **API Gateway** | Node.js 20 (`http`, `crypto`) Zero-dependency | `8080` | `cashflow-gateway-service` | — | — |
| **1** | **Auth Service** | Java 17 (`com.sun.net.httpserver` + JDBC + PBKDF2) | `8081` | `cashflow-auth-service` | `cashflow-auth-db` (Host `:3307`) | `auth_db` |
| **2** | **Transaction Service** | PHP 8.2 (Built-in Server + PDO Native) | `8082` | `cashflow-transaction-service` | `cashflow-transaction-db` | `transaction_db` |
| **3** | **Asset Service** | C# .NET 10 (`HttpListener` + `MySql.Data`) | `8083` | `cashflow-asset-service` | `cashflow-asset-db` | `asset_db` |
| **4** | **Analytics Service** | Python 3.11 (`http.server` + `urllib` + `PyMySQL`) | `8084` | `cashflow-analytics-service` | `cashflow-analytics-db` | `analytics_db` |
| **5** | **Integration Service** | Go 1.22 (`net/http` + `database/sql`) | `8085` | `cashflow-integration-service` | `cashflow-integration-db` (Host `:3310`) | `integration_db` |
| **6** | **Notification Service** | Node.js 20 (Native `http` SSE + Raw TCP Redis RESP) | `8086` | `cashflow-notification-service` | `cashflow-redis-notification` (Host `:6379`) | Redis Pub/Sub |
| **7** | **Frontend Web** | PHP 8.x Built-in Server (Chạy trên máy Host) | `3000` | — | — | — |

### Tài khoản Demo có sẵn (từ `services/auth-service/db/init-auth-db.sql`)

| Username | Password | Vai trò (`role`) | Thuật toán Hash Password |
|:---|:---|:---:|:---|
| `admin` | `password123` | `ADMIN` | `PBKDF2WithHmacSHA256` (210.000 iterations + salt ngẫu nhiên) |
| `member1` | `password123` | `USER` | `PBKDF2WithHmacSHA256` (210.000 iterations + salt ngẫu nhiên) |

---

## ⚙ 2. Yêu cầu Môi trường

- **Nếu dùng Cách 1 (Docker Compose — Khuyên dùng):**
  - **Docker Desktop** 4.x (Docker Engine 24+, hỗ trợ lệnh `docker compose`).
  - **PHP 8.1+** (hoặc XAMPP) để chạy giao diện Frontend Web cổng `3000`.
- **Nếu dùng Cách 2 / Cách 4 (Chạy Local không cần Docker):**
  - **XAMPP** (PHP 8.2 + MySQL 8.0/MariaDB cổng `3306`, tài khoản `root` / `root_password`).
  - **Node.js 20**, **Python 3.11+**, **JDK 17 + Maven**, **.NET 10 SDK**, **Go 1.22** (hoặc dùng thư mục toolchain portable `.tools/` đi kèm với `start-services.ps1`).
- **Tùy chọn kiểm thử tự động:** `newman` (Node.js), `k6`.

---

## 🐳 3. Cách 1: Chạy bằng Docker Compose (Khuyến nghị số 1)

> [!IMPORTANT]
> **Lưu ý xung đột cổng:** Bản Giữa kỳ và Bản Cuối kỳ dùng chung dải cổng `8080–8086`, `3307`, `3310`, `6379`. Nếu trước đó bạn đang bật bản Cuối kỳ, hãy dừng nó trước:
> ```powershell
> docker compose -f docker-compose.final.yml down
> ```

### Bước 1: Mở Docker Desktop và kiểm tra cấu hình
Mở Terminal tại thư mục gốc `SOA-CashFlow`:
```powershell
# Kiểm tra cú pháp file compose (không cần chạy container)
docker compose -f docker-compose.midterm.yml config --quiet
```

### Bước 2: Build và khởi động toàn bộ 13 containers
```powershell
docker compose -f docker-compose.midterm.yml up -d --build
```
*(Lần đầu build sẽ mất khoảng 2–5 phút để tải các base image và biên dịch Java, C#, Go. Từ lần thứ 2 trở đi, chỉ cần chạy `docker compose -f docker-compose.midterm.yml up -d` trong vài giây).*

### Bước 3: Kiểm tra trạng thái container và Health Check
```powershell
# 1. Xem danh sách 13 containers đang hoạt động
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 2. Kiểm tra sức khỏe Gateway (cổng 8080)
curl.exe http://localhost:8080/health

# 3. Kiểm tra nhanh health của từng Microservice (8081..8086)
curl.exe http://localhost:8081/health
curl.exe http://localhost:8082/health
curl.exe http://localhost:8083/health
curl.exe http://localhost:8084/health
curl.exe http://localhost:8085/health
curl.exe http://localhost:8086/health
```

### Các lệnh quản lý thường dùng
```powershell
# Build lại CHỈ 1 hoặc vài service bạn vừa chỉnh sửa code (tiết kiệm thời gian)
docker compose -f docker-compose.midterm.yml up -d --build auth-service gateway

# Xem log trực tiếp (real-time) của một service cụ thể
docker compose -f docker-compose.midterm.yml logs -f auth-service

# Xem 50 dòng log gần nhất của toàn bộ hệ thống
docker compose -f docker-compose.midterm.yml logs --tail=50

# Dừng toàn bộ containers (GIỮ NGUYÊN dữ liệu CSDL)
docker compose -f docker-compose.midterm.yml down

# Dừng và XÓA SẠCH toàn bộ dữ liệu/volume để reset về trạng thái gốc ban đầu
docker compose -f docker-compose.midterm.yml down -v
```

---

## 💻 4. Cách 2: Chạy Local tự động trên Windows (`start-services.ps1`)

Cách này dành cho máy Windows **không cài Docker** nhưng có sẵn **XAMPP (MySQL + PHP)**, **Node.js**, **Python 3** và thư mục toolchain portable `.tools/` (JDK 17, Maven, Go 1.22, .NET 10 SDK).

### Bước 1: Chuẩn bị MySQL trên XAMPP
1. Mở **XAMPP Control Panel** $\rightarrow$ bấm **Start** cho **MySQL** (cổng `3306`).
2. Đảm bảo tài khoản MySQL là `root` với mật khẩu `root_password` (hoặc chỉnh biến `$env:DB_PASSWORD` ở dòng 44 trong `start-services.ps1`).
3. Nạp 5 file SQL khởi tạo CSDL (chạy 1 lần duy nhất):
```powershell
C:\xampp\mysql\bin\mysql.exe -uroot -proot_password < services\auth-service\db\init-auth-db.sql
C:\xampp\mysql\bin\mysql.exe -uroot -proot_password < services\transaction-service\db\init-transaction-db.sql
C:\xampp\mysql\bin\mysql.exe -uroot -proot_password < services\asset-service\db\init-asset-db.sql
C:\xampp\mysql\bin\mysql.exe -uroot -proot_password -e "CREATE DATABASE IF NOT EXISTS analytics_db;"
C:\xampp\mysql\bin\mysql.exe -uroot -proot_password analytics_db < services\analytics-service\db\init-analytics-db.sql
C:\xampp\mysql\bin\mysql.exe -uroot -proot_password < services\integration-service\db\init-integration-db.sql
```

### Bước 2: Chạy script khởi động toàn bộ hệ thống
Script `start-services.ps1` sẽ tự động biên dịch Java/C#/Go và bật cả **6 Microservices + API Gateway + Frontend Web (`:3000`)**:

```powershell
# Chạy đầy đủ (Build + Start toàn bộ 8 tiến trình):
powershell -ExecutionPolicy Bypass -File start-services.ps1

# Chạy nhanh khi code Java/C#/Go không đổi (bỏ qua bước build):
powershell -ExecutionPolicy Bypass -File start-services.ps1 -NoBuild

# Giữ cửa sổ terminal chạy nền cho đến khi nhấn Ctrl+C:
powershell -ExecutionPolicy Bypass -File start-services.ps1 -NoBuild -KeepAlive

# Dừng toàn bộ các tiến trình đang chạy trên cổng 3000 và 8080..8086:
powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop
```

### Xem log khi chạy Local
Toàn bộ stdout/stderr của từng service được tự động ghi vào `.tools\logs\`:
```powershell
Get-Content .tools\logs\auth-service.out.log -Tail 30
Get-Content .tools\logs\auth-service.err.log -Tail 30
```

---

## 🔀 5. Cách 3: Chạy chế độ Hybrid (DB/Redis trong Docker + Code chạy Local)

Rất tiện khi bạn muốn dùng **MySQL 8.0 & Redis 7 sạch trong Docker** nhưng chạy 1–2 service trực tiếp trên máy thật (VS Code / IntelliJ / Visual Studio) để đặt breakpoint debug.

### Bước 1: Khởi động 6 container hạ tầng (5 MySQL + 1 Redis)
```powershell
docker compose -f docker-compose.midterm.yml up -d auth-db transaction-db asset-db integration-db analytics-db redis
```

### Bước 2: Kết nối từ code Local
- **Auth DB:** đã mở sẵn cổng Host `127.0.0.1:3307` (`auth_db`, `root` / `root_password`).
- **Integration DB:** đã mở sẵn cổng Host `127.0.0.1:3310` (`integration_db`, `root` / `root_password`).
- **Redis:** đã mở sẵn cổng Host `127.0.0.1:6379`.
- **Transaction / Asset / Analytics DB:** mặc định chỉ mở nội bộ Docker. Nếu cần debug local 3 service này, bạn có thể tạm mở thêm `ports` trong `docker-compose.midterm.yml` (ví dụ `3308:3306`, `3309:3306`, `3311:3306`) hoặc kết nối MySQL XAMPP cục bộ ở cổng `3306`.

Ví dụ chạy riêng **API Gateway** & **Auth Service** ở Local kết nối tới `auth-db` (`:3307`):
```powershell
# Terminal 1: Chạy Auth Service local
$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3307"; $env:DB_NAME="auth_db"; $env:DB_USER="root"; $env:DB_PASSWORD="root_password"
$env:JWT_SECRET="cashflow-auth-midterm-development-secret-key"
# Sau đó chạy java -cp ... như Cách 4 bên dưới
```

---

## 🛠 6. Cách 4: Chạy thủ công từng Service trên từng Terminal

Dành cho trường hợp bạn muốn bật độc lập từng dịch vụ để quan sát log trực tiếp trên từng cửa sổ Terminal (yêu cầu đã khởi tạo 5 database trên MySQL cục bộ cổng `3306` với `root` / `root_password` và Redis cổng `6379`).

### Terminal 1 — Auth Service (Java 17 Native `HttpServer` — Port `8081`)
```powershell
cd services/auth-service/midterm
mvn clean package dependency:copy-dependencies -DskipTests -DoutputDirectory=target/lib

$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_NAME="auth_db"; $env:DB_USER="root"; $env:DB_PASSWORD="root_password"
$env:JWT_SECRET="cashflow-auth-midterm-development-secret-key"
java -cp "target/auth-service-1.0.0.jar;target/lib/*" com.cashflow.auth.Main
```

### Terminal 2 — Transaction Service (PHP 8.2 Native PDO — Port `8082`)
```powershell
$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_NAME="transaction_db"; $env:DB_USER="root"; $env:DB_PASSWORD="root_password"
php -d date.timezone=Asia/Ho_Chi_Minh -S 127.0.0.1:8082 -t services/transaction-service/midterm/src services/transaction-service/midterm/src/index.php
```

### Terminal 3 — Asset Service (C# .NET 10 Native `HttpListener` — Port `8083`)
```powershell
cd services/asset-service/midterm
$env:PORT="8083"; $env:LISTEN_HOST="localhost"
$env:DB_CONNECTION="Server=127.0.0.1;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;"
dotnet run --project AssetService.csproj
```

### Terminal 4 — Analytics Service (Python 3.11 Native `http.server` — Port `8084`)
```powershell
cd services/analytics-service/midterm
pip install -r requirements.txt

$env:HOST="127.0.0.1"; $env:PORT="8084"
$env:TRANSACTION_SERVICE_URL="http://127.0.0.1:8082"
$env:ANALYTICS_DB_HOST="127.0.0.1"; $env:ANALYTICS_DB_PORT="3306"; $env:ANALYTICS_DB_NAME="analytics_db"; $env:ANALYTICS_DB_USER="root"; $env:ANALYTICS_DB_PASSWORD="root_password"
python src/main.py
```

### Terminal 5 — Integration Service (Go 1.22 Native `net/http` — Port `8085`)
```powershell
cd services/integration-service/midterm
$env:PORT="8085"
$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_NAME="integration_db"; $env:DB_USER="root"; $env:DB_PASSWORD="root_password"
$env:TRANSACTION_SERVICE_URL="http://127.0.0.1:8082"
$env:NOTIFICATION_SERVICE_URL="http://127.0.0.1:8086"
go run ./src
```

### Terminal 6 — Notification Service (Node.js 20 Native SSE + Redis TCP — Port `8086`)
```powershell
$env:HOST="127.0.0.1"; $env:NOTIFICATION_SERVICE_PORT="8086"
$env:REDIS_HOST="127.0.0.1"; $env:REDIS_PORT="6379"
node services/notification-service/midterm/src/index.js
```

### Terminal 7 — API Gateway (Node.js 20 Native Zero-dependency — Port `8080`)
```powershell
$env:GATEWAY_HOST="127.0.0.1"; $env:GATEWAY_PORT="8080"
$env:JWT_SECRET="cashflow-auth-midterm-development-secret-key"
$env:AUTH_SERVICE_URL="http://127.0.0.1:8081"
$env:TRANSACTION_SERVICE_URL="http://127.0.0.1:8082"
$env:ASSET_SERVICE_URL="http://localhost:8083"
$env:ANALYTICS_SERVICE_URL="http://127.0.0.1:8084"
$env:INTEGRATION_SERVICE_URL="http://127.0.0.1:8085"
$env:NOTIFICATION_SERVICE_URL="http://127.0.0.1:8086"
node gateway/midterm/src/index.js
```

---

## 🌐 7. Khởi chạy Giao diện Frontend Web iBanking (Port `3000`)

Khi backend đã chạy (bằng Docker Compose Cách 1 hoặc thủ công Cách 4), mở giao diện Web PHP trên máy Host *(lưu ý: Cách 2 `start-services.ps1` đã tự bật sẵn Frontend)*:

- **Cách nhanh:** Nhấp đúp chuột vào file `run-frontend.bat` tại thư mục gốc.
- **Hoặc chạy bằng Terminal:**
  ```powershell
  php -S 127.0.0.1:3000 -t frontend frontend/router.php
  ```
- **Truy cập trình duyệt:** [http://localhost:3000](http://localhost:3000) (hoặc `http://127.0.0.1:3000`)
- **Đăng nhập:** `admin` / `password123` hoặc `member1` / `password123`.

---

## 🧪 8. Hướng dẫn Kiểm thử Bản Giữa kỳ

### 8.1. Kiểm thử qua Giao diện Web iBanking (`http://localhost:3000`)
1. Đăng nhập với `admin` / `password123` (hoặc đăng ký tài khoản mới tại `/register.php`).
2. **Thu/Chi & Ngân sách:** Thêm/sửa/xóa giao dịch, tạo hạn mức ngân sách và kiểm tra cảnh báo vượt hạn mức.
3. **Tài sản & Khoản vay:** Thêm tài sản, khoản vay, danh mục đầu tư và xem tổng tài sản ròng (Net Worth).
4. **Liên kết & Đồng bộ Ngân hàng:** Liên kết tài khoản ngân hàng giả lập, kích hoạt đồng bộ và xem tỷ giá ngoại tệ.
5. **Phân tích Dòng tiền:** Xem tổng quan thu chi, xu hướng theo tháng và báo cáo lưu trong `analytics_db`.
6. **Thông báo Realtime (SSE):** Mở mục *Thông báo* để nhận luồng sự kiện Server-Sent Events thời gian thực.

### 8.2. Kiểm thử trực quan qua Swagger UI tích hợp tại Gateway
- **Trang tổng hợp:** [http://localhost:8080/docs](http://localhost:8080/docs)
- **Từng service riêng:** `/docs/auth`, `/docs/transaction`, `/docs/asset`, `/docs/analytics`, `/docs/integration`, `/docs/notification`.
- **Quy trình test có JWT:**
  1. Gọi `POST /api/auth/login` với `{"username":"admin","password":"password123"}` $\rightarrow$ sao chép `accessToken`.
  2. Bấm nút **Authorize** ở góc trên Swagger $\rightarrow$ dán token $\rightarrow$ **Authorize**.
  3. Thử nghiệm trực tiếp mọi API CRUD của các dịch vụ còn lại.

### 8.3. Kiểm thử tự động toàn bộ API với Newman (Postman CLI)
```powershell
# Cài Newman 1 lần duy nhất
npm install -g newman newman-reporter-htmlextra

# Chạy toàn bộ bộ test 33 requests
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json --color on

# Xuất báo cáo HTML
newman run docs/api-docs/Postman_Collection_All_Services.json -e docs/api-docs/Postman_Environment.json -r cli,htmlextra --reporter-htmlextra-export newman-midterm-report.html
```

### 8.4. Kiểm thử Concurrency & Idempotency (Go Loadtest & k6)
```powershell
# Cách A: Go loadtest tích hợp sẵn (bắn 200 giao dịch x 3 lần lặp = 600 request với 50 worker song song)
cd services/integration-service/midterm
go run ./tools/loadtest -url http://localhost:8085 -account 1 -n 200 -dup 3 -c 50

# Cách B: Kiểm thử tải bằng k6
cd ../../..
k6 run scripts/concurrency-test-k6.js
```

---

## 🔍 9. Xem, Giám sát & Reset Dữ liệu trong CSDL

### 9.1. Xem trực quan bằng phpMyAdmin (Giao diện Web cổng `8088`)
```powershell
docker run -d --name cashflow-phpmyadmin-midterm --network cashflow-midterm-net -p 8088:80 -e PMA_ARBITRARY=1 phpmyadmin
```
- Mở trình duyệt: **[http://localhost:8088](http://localhost:8088)**
- Đăng nhập với:
  - **Server:** `auth-db` | `transaction-db` | `asset-db` | `integration-db` | `analytics-db`
  - **Username:** `root`
  - **Password:** `root_password`
- Khi không dùng nữa: `docker rm -f cashflow-phpmyadmin-midterm`

### 9.2. Truy vấn nhanh qua Terminal (`docker exec`)
```powershell
# 1. Auth Service — Xem danh sách user và kiểm chứng cột password_hash (PBKDF2)
docker exec -it cashflow-auth-db mysql -uroot -proot_password auth_db -e "SELECT id, username, email, role, LEFT(password_hash, 40) AS hash_preview FROM users;"

# 2. Transaction Service — Xem 10 giao dịch mới nhất
docker exec -it cashflow-transaction-db mysql -uroot -proot_password transaction_db -e "SELECT id, user_id, category, amount, type, description, transaction_date FROM transactions ORDER BY id DESC LIMIT 10;"

# 3. Transaction Service — Xem danh sách hạn mức ngân sách
docker exec -it cashflow-transaction-db mysql -uroot -proot_password transaction_db -e "SELECT b.id, b.user_id, c.name AS category, b.amount_limit, b.period, b.start_date, b.end_date FROM budgets b JOIN categories c ON b.category_id = c.id;"

# 4. Asset Service — Xem danh mục tài sản
docker exec -it cashflow-asset-db mysql -uroot -proot_password asset_db -e "SELECT id, user_id, asset_name, asset_type, purchase_price, current_value, currency FROM assets ORDER BY id DESC;"

# 5. Integration Service — Xem tài khoản ngân hàng liên kết & số dư
docker exec -it cashflow-integration-db mysql -uroot -proot_password integration_db -e "SELECT id, user_id, bank_code, account_no, holder_name, balance, version FROM linked_accounts;"

# 6. Analytics Service — Xem các báo cáo tháng đã lưu
docker exec -it cashflow-analytics-db mysql -uroot -proot_password analytics_db -e "SELECT report_id, user_id, year, month, generated_at FROM monthly_reports;"

# 7. Notification Service — Xem danh sách thông báo đang lưu trong bộ nhớ
Invoke-RestMethod -Uri "http://localhost:8086/api/notifications?userId=1" | ConvertTo-Json -Depth 4

# 8. Redis Broker — Giám sát các kênh Pub/Sub đang hoạt động
docker exec -it cashflow-redis-notification redis-cli PUBSUB CHANNELS
```

### 9.3. Reset CSDL về trạng thái dữ liệu mẫu ban đầu
```powershell
# Reset toàn bộ hệ thống Giữa kỳ (xóa sạch volumes và khởi tạo lại seed data)
docker compose -f docker-compose.midterm.yml down -v
docker compose -f docker-compose.midterm.yml up -d
```

---

## 🔧 10. Xử lý Sự cố Thường gặp

| Triệu chứng | Nguyên nhân | Cách khắc phục |
|:---|:---|:---|
| `Bind for 0.0.0.0:8080 failed: port is already allocated` | Đang bật bản Cuối kỳ hoặc tiến trình Local chiếm cổng | Dừng bản Final (`docker compose -f docker-compose.final.yml down`) hoặc chạy `powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop`. Kiểm tra cổng cụ thể: `netstat -ano \| findstr :8080`. |
| `Table doesn't exist` hoặc lỗi InnoDB redo log sau khi tắt máy đột ngột | Volume MySQL cũ bị lỗi / chưa chạy script `init.sql` | Chạy `docker compose -f docker-compose.midterm.yml down -v` rồi `docker compose -f docker-compose.midterm.yml up -d --build`. |
| Service khởi động lỗi kết nối DB ngay những giây đầu | MySQL 8.0 đang khởi tạo lần đầu (mất 10–15 giây) | Đợi ~15 giây rồi khởi động lại service bị ngắt: `docker compose -f docker-compose.midterm.yml restart auth-service transaction-service asset-service`. |
| `start-services.ps1` báo lỗi không tìm thấy MySQL | XAMPP MySQL chưa bật hoặc sai mật khẩu `root` | Mở XAMPP Control Panel, bật MySQL cổng `3306` và kiểm tra mật khẩu `root_password`. |
| Asset Service (C#) lỗi kết nối MySQL | Chuỗi kết nối có `SslMode=None` không tương thích cấu hình driver | Giữ đúng định dạng chuẩn: `Server=asset-db;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;`. |
