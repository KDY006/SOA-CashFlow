# Auth-Service cuối kỳ

Auth-Service quản lý danh tính, cấp JWT và cung cấp API hồ sơ, quản trị người dùng cho hệ thống SOA CashFlow. Bản cuối kỳ chuyển máy chủ Java Native và JDBC sang Java 17, Spring Boot 3.5.16, Spring MVC, Spring Security, Spring Data JPA và MySQL Connector/J. Maven tạo JAR thực thi với Tomcat nhúng. Docker sử dụng JRE 17. [Yêu cầu hệ thống của Spring Boot 3.5](https://docs.spring.io/spring-boot/3.5/system-requirements.html)

| Thành phần | Giữa kỳ | Cuối kỳ |
| --- | --- | --- |
| HTTP | Native HttpServer và Handler | Spring MVC, controller, DTO và Jakarta Validation |
| Xác thực | JWT kiểm tra riêng trong từng handler | Spring Security stateless, Nimbus JOSE và kiểm tra tài khoản trong MySQL |
| Truy cập dữ liệu | JDBC thủ công | Spring Data JPA, HikariCP và giao dịch |
| Mật khẩu | PBKDF2-HMAC-SHA256 | Cùng định dạng PBKDF2, tương thích dữ liệu seed |
| Refresh token | Chuỗi ngẫu nhiên lưu trực tiếp | SHA-256 cho token mới, đọc được token giữa kỳ |
| Đóng gói | Maven, Docker | Spring Boot executable JAR và Docker multi-stage, user không đặc quyền |

## Cấu trúc mã nguồn

- `AuthApplication.java` khởi động Spring Boot.
- `config/` cấu hình JWT, Spring Security và CORS.
- `controller/` xử lý HTTP. `dto/` định nghĩa dữ liệu đầu vào và dữ liệu người dùng trả về.
- `service/` triển khai đăng nhập, refresh, quản trị và hồ sơ. `security/` mã hóa mật khẩu, cấp token và xác thực request.
- `entity/` và `repository/` ánh xạ và truy cập MySQL. `exception/` chuyển lỗi thành JSON.
- `src/test/` chứa bộ kiểm thử JUnit 5, Mockito và MockMvc.

## API và phân quyền

Các URL dưới đây dùng được qua Gateway `http://localhost:8080` hoặc trực tiếp tại `http://localhost:8081`. Các dòng yêu cầu Bearer nhận header `Authorization: Bearer <accessToken>`. Các request có body dùng `Content-Type: application/json`.

| HTTP và URL | Quyền | Body mẫu | Thành công | Lỗi nghiệp vụ |
| --- | --- | --- | --- | --- |
| POST `/api/auth/register` | Công khai | `{"username":"demo.user","email":"demo@cashflow.local","password":"demo1234","full_name":"Demo"}` | 201, `message`, `user` | 400, 409 |
| POST `/api/auth/login` | Công khai | `{"username":"demo.user","password":"demo1234"}` | 200, `message`, hai token, `tokenType`, `expiresIn`, `user` | 400 JSON lỗi, 401 sai thông tin hoặc tài khoản bị khóa |
| GET `/api/auth/validate` | Bearer | — | 200, `valid`, `userId`, `username`, `role`, `status` | 401 |
| POST `/api/auth/refresh` | Refresh token | `{"refreshToken":"<refreshToken>"}` | 200, `message`, `accessToken`, `tokenType`, `expiresIn` | 400, 401 |
| GET `/api/profile` | Bearer, chính chủ | — | 200, `profile` | 401 |
| PUT `/api/profile` | Bearer, chính chủ | `{"fullName":"Demo Updated","phoneNumber":"0901234567"}` | 200, `message`, `profile` | 400, 401 |
| PUT `/api/profile/password` | Bearer, chính chủ | `{"currentPassword":"demo1234","newPassword":"demo5678"}` | 200, `message` | 400, 401 |
| GET `/api/admin/users` | ADMIN | — | 200, `count`, `users` | 401, 403 |
| GET `/api/admin/users/{id}` | ADMIN | — | 200, `user` | 400, 401, 403 |
| POST `/api/admin/users` | ADMIN | `{"username":"demo.staff","email":"staff@cashflow.local","password":"demo1234","role":"USER","status":"ACTIVE"}` | 201, `message`, `user` | 400, 401, 403, 409 |
| PUT `/api/admin/users/{id}` | ADMIN | `{"username":"demo.staff","email":"staff@cashflow.local","role":"ADMIN","status":"BLOCKED"}` | 200, `message`, `user` | 400, 401, 403, 409 |
| DELETE `/api/admin/users/{id}` | ADMIN | — | 200, `message` | 400, 401, 403 |

Mọi phản hồi thành công có `success: true`. Đây là ví dụ cho đăng nhập và cho lỗi phân quyền.

```json
{
  "success": true,
  "message": "Login successful",
  "accessToken": "<JWT>",
  "refreshToken": "<opaque token>",
  "tokenType": "Bearer",
  "expiresIn": 3600,
  "user": {
    "id": 3,
    "username": "demo.user",
    "email": "demo@cashflow.local",
    "fullName": "Demo",
    "phoneNumber": null,
    "role": "USER",
    "status": "ACTIVE"
  }
}
```

```json
{"success":false,"message":"Admin permission is required"}
```

`profile` và các đối tượng `user` của admin có thêm `createdAt` và `updatedAt` theo Unix epoch tính bằng mili giây. Ví dụ validate trả `{"success":true,"valid":true,"userId":3,"username":"demo.user","role":"USER","status":"ACTIVE"}`. Sai phương thức trả 405 khi request đã đáp ứng yêu cầu xác thực. Auth-Service trả 503 khi không thể truy cập database tạm thời. Các lỗi được Gateway tạo trước khi chuyển tiếp có thể kèm trường `error` hoặc `path`.

Username dài 3–50 ký tự và dùng chữ ASCII, số, dấu chấm hoặc gạch dưới. Email được chuẩn hóa chữ thường và dài tối đa 100 ký tự. Mật khẩu mới dài 6–100 ký tự. `fullName` dài tối đa 100 ký tự. `phoneNumber` có 9–15 chữ số với dấu cộng tùy chọn. Register chấp nhận `full_name` và `phone_number`. Login chấp nhận username hoặc email ở trường `username`. Refresh chấp nhận `refresh_token`. Trường tùy chọn để trống có thể được lưu thành `null`. Admin PUT yêu cầu username và email, mặc định `USER` và `ACTIVE` khi bỏ qua role và status, giống bản giữa kỳ.

## Database và bảo mật

| Bảng | Cấu trúc |
| --- | --- |
| `users` | `id` BIGINT tự tăng, `username` và `email` UNIQUE, `password_hash`, `full_name`, `phone_number`, `role`, `status`, `created_at`, `updated_at` |
| `refresh_tokens` | `id` BIGINT tự tăng, `user_id` tham chiếu `users.id` với `ON DELETE CASCADE`, `token` UNIQUE, `expiry_date`, `created_at` |

Schema dùng [init-auth-db.sql](../db/init-auth-db.sql). Hibernate chạy `ddl-auto=validate`. Timestamp do MySQL quản lý và JDBC dùng UTC. Các tài khoản seed `admin` và `member1` dùng mật khẩu `password123` trên database demo. Mật khẩu lưu ở định dạng `iterations:salt:hash` của PBKDF2-HMAC-SHA256 với salt ngẫu nhiên.

Access token là JWT HS256 với `userId`, `username`, `role`, `iat` và `exp`, hiệu lực 3600 giây. Auth-Service kiểm tra chữ ký, thời hạn, trạng thái account và đối chiếu role hiện hành. Register luôn cấp USER/ACTIVE. Profile chỉ sửa tên và số điện thoại. Auth-Service dùng header Bearer do client chủ động gửi, không dùng cookie xác thực hoặc session, nên CSRF được tắt. CORS trực tiếp chỉ cấp phép cho origin cấu hình. Actuator chỉ công khai `/actuator/health` và không trả chi tiết thành phần.

Refresh token hiệu lực 7 ngày. Login thay token cũ cho tài khoản trong cùng giao dịch. Refresh cấp access token mới và giữ refresh token để tương thích client cũ. Đổi mật khẩu hoặc khóa tài khoản thu hồi refresh token. Access token đã cấp trước khi đổi mật khẩu vẫn có TTL tối đa một giờ. Gateway giữa kỳ xác minh JWT cục bộ cho các dịch vụ đích. Kiểm tra trạng thái và role mới nhất áp dụng cho request đi tới Auth-Service.

## Cấu hình

| Biến | Giá trị và cách dùng |
| --- | --- |
| `SERVER_PORT` | Mặc định 8081. Trong Compose, dùng `AUTH_SERVICE_PORT` để đổi cổng trên host |
| `DB_HOST`, `DB_PORT`, `DB_NAME` | Mặc định `localhost:3306/auth_db`. Docker dùng `auth-db:3306/auth_db` |
| `DB_USER`, `DB_PASSWORD` | Bắt buộc khi chạy Maven hoặc JAR. Compose độc lập nhận `DB_PASSWORD` để khởi tạo DB demo |
| `JWT_SECRET`, `AUTH_JWT_SECRET` | Cần ít nhất 32 byte UTF-8. Ưu tiên `JWT_SECRET`, fallback sang `AUTH_JWT_SECRET` nếu trống. Gateway dùng đúng cùng giá trị. Profile `prod` từ chối secret mặc định giữa kỳ |
| `SPRING_PROFILES_ACTIVE` | Dùng `prod` cho cấu hình triển khai. Kết nối JDBC và chính sách bảo mật dùng chung `application.yml` |
| `AUTH_CORS_ORIGINS` | Danh sách origin trực tiếp, phân cách bằng dấu phẩy. Mặc định trống |
| `DB_SSL_MODE` | Mặc định `PREFERRED`. Có thể đặt `VERIFY_IDENTITY` khi DB đã thiết lập TLS và chứng chỉ |
| `DB_ALLOW_PUBLIC_KEY_RETRIEVAL` | Mặc định `false`. Compose demo bật để hỗ trợ xác thực MySQL trong mạng Compose |

## Chạy bằng Maven

Cài JDK 17 và Maven. Chuẩn bị một MySQL 8 với schema Auth đã khởi tạo. Các lệnh sau dùng PowerShell và chạy từ gốc repo. Với một MySQL demo trống, chạy `mysql --host=127.0.0.1 --port=3306 -u root -p --execute="source services/auth-service/db/init-auth-db.sql"` để nạp schema và seed.

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_NAME = 'auth_db'
$env:DB_USER = Read-Host 'MySQL user'
$env:DB_PASSWORD = Read-Host 'MySQL password' -MaskInput
$env:JWT_SECRET = Read-Host 'JWT secret dùng chung với Gateway' -MaskInput
$env:SPRING_PROFILES_ACTIVE = 'prod'
mvn -f services/auth-service/final/pom.xml clean verify
java -jar services/auth-service/final/target/auth-service.jar
```

`-MaskInput` dùng PowerShell 7+. Có thể dùng `mvn -f services/auth-service/final/pom.xml spring-boot:run` để chạy từ source. Để chạy Gateway cục bộ, đặt cùng `JWT_SECRET` trong phiên PowerShell khác và chạy `$env:AUTH_SERVICE_URL = 'http://127.0.0.1:8081'` rồi `node gateway/midterm/src/index.js`.

## Chạy bằng Docker

Từ gốc repo, sao chép mẫu và điền `DB_PASSWORD`, `JWT_SECRET` mới cho demo. Compose độc lập chạy MySQL 8, Auth-Service và Gateway, dùng volume riêng. Script seed chỉ chạy khi MySQL khởi tạo volume trống.

```powershell
$authDir = 'services/auth-service/final'
if (!(Test-Path "$authDir/.env")) {
    Copy-Item "$authDir/.env.example" "$authDir/.env"
}
# Điền DB_PASSWORD và JWT_SECRET trong .env trước khi chạy.
docker compose -p cashflow-auth-final-demo --env-file "$authDir/.env" -f "$authDir/compose.yml" up -d --build
docker compose -p cashflow-auth-final-demo --env-file "$authDir/.env" -f "$authDir/compose.yml" ps
```

Compose toàn dự án nhận `AUTH_FINAL_DB_PASSWORD` cho Auth DB và `JWT_SECRET` hoặc `AUTH_JWT_SECRET` cho Gateway và Auth-Service. Với volume cũ, đặt đúng mật khẩu MySQL đang có. Compose toàn dự án dùng `docker compose -f docker-compose.final.yml up -d --build` sau khi các service còn lại có đủ Dockerfile. Có thể chạy riêng Auth-Service/MySQL trong Compose toàn dự án bằng cách thêm `auth-service` vào cuối lệnh.

## Kiểm thử HTTP và Gateway

Chạy các lệnh sau sau khi Gateway đã sẵn sàng. Postman có thể import cùng URL, JSON và header từ ví dụ này.

```powershell
$base = 'http://localhost:8080'
$login = Invoke-RestMethod -Method Post -Uri "$base/api/auth/login" -ContentType 'application/json' -Body '{"username":"admin","password":"password123"}'
$headers = @{ Authorization = "Bearer $($login.accessToken)" }
Invoke-RestMethod -Uri "$base/api/auth/validate" -Headers $headers
Invoke-RestMethod -Uri "$base/api/profile" -Headers $headers
Invoke-RestMethod -Uri "$base/api/admin/users" -Headers $headers
$body = @{ refreshToken = $login.refreshToken } | ConvertTo-Json -Compress
Invoke-RestMethod -Method Post -Uri "$base/api/auth/refresh" -ContentType 'application/json' -Body $body
Invoke-RestMethod -Uri 'http://localhost:8081/actuator/health'
```

Có thể kiểm tra trực tiếp bằng curl.

```powershell
curl.exe -i http://localhost:8081/actuator/health
curl.exe -i http://localhost:8080/api/auth/validate -H "Authorization: Bearer $($login.accessToken)"
```

Gateway định tuyến `/api/auth`, `/api/profile` và `/api/admin` sang Auth-Service, giữ phương thức, URL và Authorization. Auth-Service xác thực Bearer riêng trước khi thực thi nghiệp vụ. Chi tiết đối chiếu và kết quả thực thi nằm trong [báo cáo chuyển đổi](MIGRATION_REPORT.md).


## Kết quả và giới hạn

Maven `clean verify` tạo JAR thực thi và chạy đạt 18 bài kiểm thử. Kiểm thử HTTP qua Gateway xác minh cả 12 API với MySQL 8.0.46 và cả hai tài khoản seed. Thử nghiệm mất kết nối MySQL trả 503 ở login và profile. Docker build và chạy stack độc lập với Java 17. Stack toàn dự án còn phụ thuộc Dockerfile của Transaction-Service và Integration-Service.
