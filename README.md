# SOA CashFlow - Multi-language Microservices Architecture

Dự án môn học **Kiến trúc hướng Dịch vụ (Service-Oriented Architecture - SOA)**: Hệ thống quản trị dòng tiền và tài chính cá nhân/doanh nghiệp (`SOA-CashFlow`).

Hệ thống được thiết kế theo kiến trúc Microservices gồm **6 services độc lập viết bằng 6 ngôn ngữ khác nhau**, kết nối thông qua **API Gateway**, lưu trữ trên **4 MySQL, 1 MongoDB và 1 Redis**.

---

## 🏗️ Cấu trúc Hệ thống & Phân công Thành viên

| Service | Thành viên đảm nhiệm | Ngôn ngữ / Công nghệ | Database | Cổng Container / Host |
| :--- | :--- | :--- | :--- | :--- |
| **API Gateway** | Nhóm trưởng | Node.js (Proxy / Express / Kong) | - | `8080` |
| **Auth Service** | Thành viên 1 | Java (Native -> Spring Boot) | MySQL (`auth_db`) | `8081` |
| **Transaction Service** | Thành viên 2 | PHP (Native -> Laravel) | MySQL (`transaction_db`) | `8082` |
| **Asset Service** | Thành viên 3 | C# (Native -> ASP.NET Core) | MySQL (`asset_db`) | `8083` |
| **Analytics Service** | Thành viên 4 | Python (Native -> FastAPI) | MongoDB (`analytics_db`) | `8084` |
| **Integration Service** | Thành viên 5 | Go (Native -> Gin) | MySQL (`integration_db`) | `8085` |
| **Notification Service** | Nhóm trưởng | Node.js (Native -> Express/NestJS) | Redis Pub/Sub | `8086` |

---

## 📁 Cấu trúc Thư mục Dự án

```text
SOA-CashFlow/
├── .github/                       # GitHub Actions / CI-CD
│   └── workflows/
├── docs/                          # Tài liệu báo cáo, sơ đồ kiến trúc & API
│   ├── diagrams/                  # File ảnh sơ đồ (Use Case, ERD, Microservices Diagram)
│   ├── api-docs/                  # Postman Collection & Swagger Specs
│   └── reports/                   # Slide thuyết trình & Báo cáo PDF/Word
├── scripts/                       # Scripts kiểm thử Concurrency & Benchmark
│   ├── concurrency-test-k6.js     # Script K6 stress-test
│   └── seed-data.sql              # Script SQL chung hoặc dữ liệu khởi tạo
│
├── services/                      # THƯ MỤC CHỨA 6 MICROSERVICES (6 NGÔN NGỮ)
│   ├── auth-service/              # Java
│   ├── transaction-service/       # PHP
│   ├── asset-service/             # C#
│   ├── analytics-service/         # Python
│   ├── integration-service/       # Go
│   └── notification-service/      # Node.js
│
├── gateway/                       # API Gateway do Nhóm trưởng quản lý
│
├── docker-compose.yml             # Môi trường chạy chính: 6 Services + Gateway + 4 MySQL + 1 Mongo + 1 Redis
├── docker-compose.midterm.yml     # Docker Compose cấu hình cho bản Giữa kỳ (Code thuần)
├── docker-compose.final.yml       # Docker Compose cấu hình cho bản Cuối kỳ (Framework)
├── .env.example                   # File cấu hình biến môi trường mẫu
├── .gitignore                     # Cấu hình bỏ qua file build, dependencies
└── README.md                      # Hướng dẫn tổng thể cài đặt & chạy dự án
```

---

## 🚀 Hướng dẫn Cài đặt & Khởi chạy

### 1. Yêu cầu môi trường
- Docker & Docker Compose
- Node.js (>= 18.x) (tùy chọn khi dev local)
- Git

### 2. Thiết lập Biến Môi trường
Sao chép file cấu hình môi trường mẫu:
```bash
cp .env.example .env
```

### 3. Khởi chạy toàn bộ hệ thống bằng Docker Compose

- **Chạy phiên bản hoàn chỉnh:**
  ```bash
  docker-compose up -d --build
  ```

- **Chạy phiên bản Giữa kỳ (Code thuần):**
  ```bash
  docker-compose -f docker-compose.midterm.yml up -d --build
  ```

- **Chạy phiên bản Cuối kỳ (Framework):**
  ```bash
  docker-compose -f docker-compose.final.yml up -d --build
  ```

### 4. Kiểm tra trạng thái
```bash
docker-compose ps
```

---

## 🧪 Kiểm thử Tải & Concurrency
Sử dụng script k6 tại thư mục `scripts/`:
```bash
k6 run scripts/concurrency-test-k6.js
```
