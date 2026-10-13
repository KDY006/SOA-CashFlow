# Auth & Profile Service

Auth & Profile Service là microservice chịu trách nhiệm quản lý đăng ký, đăng nhập, xác thực JWT, refresh token và thông tin cá nhân của người dùng trong hệ thống SOA CashFlow.

## Thông tin Service

- Thành viên phụ trách: Thành viên 1
- Ngôn ngữ: Java 17
- Phiên bản giữa kỳ: Native Java
- HTTP Server: `com.sun.net.httpserver.HttpServer`
- Database: MySQL 8.0
- Database name: `auth_db`
- Service port: `8081`
- Database host port khi chạy local: `3307`
- Authentication: JWT HS256
- Password hashing: PBKDF2WithHmacSHA256
- JSON processing: Jackson
- Database access: JDBC

## Chức năng

Service hỗ trợ:

- Đăng ký tài khoản
- Đăng nhập
- Hash và kiểm tra mật khẩu bằng PBKDF2WithHmacSHA256
- Cấp JWT Access Token
- Xác thực JWT
- Cấp Refresh Token
- Lưu Refresh Token trong MySQL
- Làm mới Access Token
- Lấy thông tin cá nhân từ JWT
- Kiểm tra trạng thái tài khoản

## API Endpoints

| Method | Endpoint             | Chức năng                                     |
| ------ | -------------------- | --------------------------------------------- |
| POST   | `/api/auth/register` | Đăng ký tài khoản                             |
| POST   | `/api/auth/login`    | Đăng nhập và cấp Access Token + Refresh Token |
| POST   | `/api/auth/refresh`  | Cấp Access Token mới từ Refresh Token         |
| GET    | `/api/auth/validate` | Xác thực Access Token                         |
| GET    | `/api/profile`       | Lấy thông tin người dùng đang đăng nhập       |

## Register

### Request

```http
POST /api/auth/register
Content-Type: application/json
```

```json
{
  "username": "phuc",
  "email": "phuc@example.com",
  "password": "Phuc1234",
  "fullName": "Nguyen Trong Phuc",
  "phoneNumber": "0901234567"
}
```

### Response

```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "username": "phuc",
    "email": "phuc@example.com",
    "fullName": "Nguyen Trong Phuc",
    "phoneNumber": "0901234567",
    "role": "USER",
    "status": "ACTIVE"
  }
}
```

### Lỗi thường gặp

| Status | Khi nào |
| ------ | ------- |
| 400 | body rỗng / sai JSON, thiếu field, username không hợp lệ (3-50 ký tự: chữ, số, `.`, `_`), email sai định dạng, password < 6 ký tự, số điện thoại sai (9-15 số) |
| 409 | username hoặc email đã tồn tại |

`fullName` / `phoneNumber` nhận cả dạng `full_name` / `phone_number`. Email được lưu chữ thường.

## Login

### Request

```http
POST /api/auth/login
Content-Type: application/json
```

```json
{
  "username": "phuc",
  "password": "Phuc1234"
}
```

### Response

```json
{
  "success": true,
  "message": "Login successful",
  "accessToken": "eyJ...",
  "refreshToken": "...",
  "tokenType": "Bearer",
  "expiresIn": 3600,
  "user": {
    "id": 1,
    "username": "phuc",
    "email": "phuc@example.com",
    "fullName": "Nguyen Trong Phuc",
    "phoneNumber": "0901234567",
    "role": "USER",
    "status": "ACTIVE"
  }
}
```

## Validate Token

### Request

```http
GET /api/auth/validate
Authorization: Bearer <access_token>
```

### Response

```json
{
  "success": true,
  "valid": true,
  "userId": 1,
  "username": "phuc",
  "role": "USER"
}
```

## Refresh Token

### Request

```http
POST /api/auth/refresh
Content-Type: application/json
```

```json
{
  "refreshToken": "<refresh_token>"
}
```

### Response

```json
{
  "success": true,
  "message": "Access token refreshed successfully",
  "accessToken": "eyJ...",
  "tokenType": "Bearer",
  "expiresIn": 3600
}
```

## Profile

### Request

```http
GET /api/profile
Authorization: Bearer <access_token>
```

### Response

```json
{
  "success": true,
  "profile": {
    "id": 1,
    "username": "phuc",
    "email": "phuc@example.com",
    "fullName": "Nguyen Trong Phuc",
    "phoneNumber": "0901234567",
    "role": "USER",
    "status": "ACTIVE"
  }
}
```

## Database

Auth Service sử dụng hai bảng chính:

### users

Lưu thông tin tài khoản người dùng.

Các trường chính:

- `id`
- `username`
- `email`
- `password_hash`
- `full_name`
- `phone_number`
- `role`
- `status`
- `created_at`
- `updated_at`

### refresh_tokens

Lưu Refresh Token.

Các trường chính:

- `id`
- `user_id`
- `token`
- `expiry_date`
- `created_at`

## Security

Password không được lưu dưới dạng plain text.

Password được hash bằng:

```text
PBKDF2WithHmacSHA256
```

với random salt và nhiều vòng lặp.

Access Token sử dụng:

```text
JWT HS256
```

Access Token có thời hạn:

```text
1 hour
```

Refresh Token được sinh bằng `SecureRandom` và lưu trong MySQL.

Các endpoint yêu cầu authentication sử dụng:

```http
Authorization: Bearer <access_token>
```

## Environment Variables

Auth Service sử dụng:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
JWT_SECRET
```

Khi chạy Docker Compose, các biến `AUTH_DB_*` và `AUTH_JWT_SECRET` được ánh xạ sang các biến trên.

## Chạy Local

Yêu cầu:

- Java 17 trở lên
- Maven
- MySQL hoặc Docker MySQL

Compile:

```bash
mvn clean compile
```

Run:

```bash
mvn exec:java
```

Service chạy tại:

```text
http://localhost:8081
```

Khi Java chạy trực tiếp trên máy và MySQL chạy bằng Docker:

```text
DB_HOST=localhost
DB_PORT=3307
```

## Chạy bằng Docker Compose

Từ thư mục root của project:

```bash
docker compose -f docker-compose.midterm.yml up -d --build auth-service
```

Kiểm tra:

```bash
docker compose -f docker-compose.midterm.yml ps
```

Xem log:

```bash
docker logs cashflow-auth-midterm
```

Trong Docker network:

```text
auth-service -> auth-db:3306
```

Từ máy host:

```text
Auth Service: localhost:8081
MySQL: localhost:3307
```

## Cấu trúc Source

```text
com.cashflow.auth
├── Main.java
├── config
│   └── DatabaseConfig.java
├── handler
│   ├── LoginHandler.java
│   ├── ProfileHandler.java
│   ├── RefreshHandler.java
│   ├── RegisterHandler.java
│   └── ValidateHandler.java
├── model
│   └── User.java
├── repository
│   ├── RefreshTokenRepository.java
│   └── UserRepository.java
├── service
│   └── AuthService.java
└── util
    ├── JsonUtil.java
    ├── JwtUtil.java
    ├── MessageDigestUtil.java
    ├── PasswordUtil.java
    └── RefreshTokenUtil.java
```

## Midterm Architecture

Phiên bản giữa kỳ sử dụng Native Java theo yêu cầu project.

Không sử dụng Spring Boot hoặc Spring Security.

Phiên bản cuối kỳ có thể được refactor sang Spring Boot theo kiến trúc của project.
