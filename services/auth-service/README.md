# Auth Service (Java)

- **Phụ trách:** Thành viên 1
- **Công nghệ:** Java (Bản giữa kỳ: Java Native Socket/HTTP / Bản cuối kỳ: Spring Boot 3)
- **Cơ sở dữ liệu:** MySQL (`auth_db` trên port 3306)
- **Port phục vụ:** `8081`

## Các API Endpoints chính
- `POST /api/auth/register` - Đăng ký tài khoản mới
- `POST /api/auth/login` - Đăng nhập và cấp JWT Token
- `POST /api/auth/refresh` - Refresh access token
- `GET /api/auth/validate` - Xác thực token (dùng cho Gateway / Internal services)

## Chạy Local
```bash
# Compile & Run
mvn clean spring-boot:run
```
