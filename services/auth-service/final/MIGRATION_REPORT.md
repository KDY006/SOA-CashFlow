# Báo cáo chuyển đổi Auth-Service

## 1. Mục tiêu và phạm vi

Bản giữa kỳ trong `midterm/` dùng `Main.java` đăng ký sáu context của Native HttpServer. Các handler tự đọc HTTP, kiểm tra token, serialize JSON và gọi nghiệp vụ hoặc JDBC. Bản cuối kỳ ở `final/` dùng Tomcat nhúng, Spring MVC và Spring Security. Spring Data JPA quản lý truy cập hai bảng hiện có của MySQL. Các đường dẫn API, cấu trúc phản hồi thành công và định dạng JWT được giữ để frontend và Gateway tiếp tục sử dụng.

## 2. Đối chiếu chức năng

| Chức năng | Giữa kỳ | Cuối kỳ |
| --- | --- | --- |
| POST `/api/auth/register` | RegisterHandler, 201, kiểm tra trùng, hỗ trợ snake_case | Spring MVC, Jakarta Validation, 201/400/409, mặc định USER/ACTIVE |
| POST `/api/auth/login` | LoginHandler, username hoặc email, 200/401 | Cùng JSON và status. Thay refresh token trong một giao dịch có khóa hàng |
| GET `/api/auth/validate` | Kiểm tra chữ ký và trạng thái, trả danh tính | Spring Security xác thực chữ ký, hạn và role hiện tại. Giữ các trường nhận diện và trạng thái |
| POST `/api/auth/refresh` | Tìm chuỗi refresh trong MySQL, cấp access token | Đọc token cũ và digest mới, kiểm tra người sở hữu, hạn, trạng thái. Vẫn hỗ trợ hai tên trường |
| GET `/api/profile` | ProfileHandler đọc user từ JWT | `AccountPrincipal` lấy từ JWT đã xác minh và user hiện hành. Giữ trường `profile` |
| PUT `/api/profile` | Sửa tên và số điện thoại | DTO giới hạn hai trường, lưu trong giao dịch và trả `profile` |
| PUT `/api/profile/password` | Xác minh mật khẩu hiện tại rồi cập nhật PBKDF2 | Cùng nghiệp vụ, thu hồi refresh token trong cùng giao dịch |
| GET `/api/admin/users` | Đọc danh sách tăng dần theo id | JPA sort theo id, giữ `success`, `count`, `users` |
| GET `/api/admin/users/{id}` | Đọc một user, id không tồn tại trả 400 | Giữ `user` và 400 cho id sai hoặc user không tồn tại |
| POST `/api/admin/users` | Tạo USER/ADMIN, ACTIVE/BLOCKED | Giữ 201, kiểm tra dữ liệu, role/status và unique key |
| PUT `/api/admin/users/{id}` | Cập nhật dữ liệu quản trị, không đổi mật khẩu | Cùng trường và mặc định. Trả 400 nếu thiếu tài nguyên và thu hồi refresh token khi BLOCKED |
| DELETE `/api/admin/users/{id}` | Xóa user, phản hồi 200 và DB cascade | Giữ 200 và cascade. Giữ 400 nếu user không tồn tại |

## 3. Thiết kế và bảo toàn dữ liệu

- **HTTP và hợp đồng.** Controller nhận request DTO có Jakarta Bean Validation. `UserView` bảo toàn các khóa JSON camelCase và hai timestamp số của admin/profile. Exception advice chuẩn hóa lỗi thành `success` và `message`. Mã lỗi 400 áp dụng cho JSON hỏng, dữ liệu không hợp lệ hoặc id sai định dạng. Các API admin giữ mã 400 cho user không tồn tại theo hợp đồng đã công bố của Gateway. Body lỗi DB dùng thông báo cố định.
- **Mật khẩu.** PasswordEncoder đọc `iterations:salt:hash` với PBKDF2WithHmacSHA256, salt Base64 và kết quả 256 bit. Hash mới dùng 210.000 vòng và salt 16 byte ngẫu nhiên. Login hỗ trợ mật khẩu có khoảng trắng ở đầu/cuối do luồng đổi mật khẩu hoặc admin tạo, đồng thời giữ cách trim cũ khi đối sánh.
- **Persistence.** MySQL tiếp tục quản lý timestamp, BIGINT auto-increment, unique key và khóa ngoại cascade. Hibernate kiểm tra schema qua `ddl-auto=validate`. Bản chuyển đổi không cần migration SQL. Unique key xử lý cả trường hợp hai yêu cầu đăng ký trùng chạy đồng thời. Đọc và cập nhật hồ sơ dùng id xác thực từ JWT. Trong refresh, lấy id chủ sở hữu rồi khóa hàng user và đọc lại token để kiểm tra việc thu hồi, với isolation READ_COMMITTED.
- **Refresh token.** Trả cùng dạng Base64URL 64 ký tự; cột hiện tại lưu tiền tố `sha256:` và digest cho token mới. Token cũ vẫn đọc được đến khi hết hạn hoặc bị thu hồi. So khớp chuỗi chính xác sau truy vấn để tránh collation không phân biệt hoa thường của MySQL. Cơ chế refresh gọi lại được giữ trong TTL bảy ngày. Quay lại bản giữa kỳ sẽ yêu cầu đăng nhập lại đối với token đã lưu dạng digest.
- **JWT và phân quyền.** Ký HS256 với 5 claim cũ, sử dụng secret UTF-8 đồng nhất ở Gateway và Auth-Service. Validate và các route được bảo vệ yêu cầu Bearer hợp lệ. JWT có role cũ sau khi đổi quyền trả 401 tại Auth-Service. JWT hợp lệ của USER vào admin trả 403. Refresh có thể cấp JWT với role mới khi tài khoản vẫn ACTIVE. Header nhận diện do Gateway thêm chỉ phục vụ các service đích. Auth-Service dùng danh tính đã xác minh.
- **Triển khai.** Compose cuối kỳ trỏ build context đến `final/`, chia sẻ secret JWT, đợi MySQL có cả schema và bảng refresh rồi khởi động. Compose độc lập dùng volume riêng và giữ nguyên source Gateway, các service của nhóm, schema gốc và bản giữa kỳ.

## 4. Minh chứng kiểm thử

| Hạng mục | Kết quả |
| --- | --- |
| Build Maven | `clean verify` thành công và tạo `target/auth-service.jar`. Maven chạy trên JDK 24, javac đặt `--release 17` |
| Kiểm thử tự động | 18 lượt test, 0 lỗi, 0 fail, 0 bỏ qua. MockMvc dùng Spring Boot đầy đủ và H2 với schema kiểm thử. Mockito xác nhận lỗi database được chuyển thành JSON |
| MySQL thực | MySQL 8.0.46 biệt lập khởi tạo bằng SQL gốc. Hibernate validate thành công. Đăng nhập được cả `admin` và `member1`; unique username trên collation MySQL được kiểm tra bằng tên khác chữ hoa/thường |
| Gateway thực | HTTP qua Node Gateway ở cổng 8080 với Auth-Service cổng 8081 và lần chạy lại bản cuối ở cổng biệt lập 28080/28081. Cả 12 route phản hồi thành công; có kiểm tra JWT sai, USER gọi admin, trùng username, tài nguyên đã xóa và refresh token bị thu hồi |
| Cấu hình Compose | `docker compose config --quiet` thành công với cả hai tệp Compose |
| MySQL mất kết nối | Dừng đúng MySQL biệt lập sau khi ứng dụng chạy. Gateway nhận 503 ở cả GET profile và POST login. Bổ sung hồi quy cho lỗi mở giao dịch trong bộ lọc Bearer |
| Docker | Build Auth-Service bằng Maven trên Java 17, chạy Auth-Service trên Temurin 17.0.21.1 với MySQL và Node Gateway trong Compose độc lập |
| Phạm vi tích hợp còn lại | Stack toàn dự án chưa được xác minh vì build context của Transaction-Service và Integration-Service thiếu Dockerfile ở vị trí Compose yêu cầu |


## 5. Danh mục tệp

Các đường dẫn trong bảng tính từ `services/auth-service/final/`, ngoại trừ Compose tích hợp ở gốc repo.

| Nhóm | Tệp |
| --- | --- |
| Khởi động | `src/main/java/com/cashflow/auth/AuthApplication.java` |
| Cấu hình | `config/JwtConfig.java`, `config/SecurityConfig.java`, `src/main/resources/application.yml` |
| HTTP và DTO | `controller/AuthController.java`, `controller/ProfileController.java`, `controller/AdminUserController.java`, `dto/Requests.java`, `dto/UserView.java` |
| Nghiệp vụ và bảo mật | `service/AuthService.java`, `service/UserService.java`, `security/AccessTokens.java`, `security/AccountPrincipal.java`, `security/JwtAuthenticationFilter.java`, `security/LegacyPasswordEncoder.java` |
| Persistence | `entity/User.java`, `entity/RefreshToken.java`, `repository/UserRepository.java`, `repository/RefreshTokenRepository.java` |
| Lỗi | `exception/ApiException.java`, `exception/ApiErrors.java` |
| Kiểm thử | `src/test/java/com/cashflow/auth/AuthApiTest.java`, `src/test/java/com/cashflow/auth/exception/ApiErrorsTest.java`, `src/test/resources/auth-test-schema.sql` |
| Build và triển khai | `pom.xml`, `Dockerfile`, `compose.yml`, `.env.example`, `.dockerignore`, `.gitignore` |
| Tài liệu | `README.md`, `MIGRATION_REPORT.md` |
| Tích hợp | Sửa `docker-compose.final.yml` ở gốc repo để nối Auth-Service, Auth DB và Gateway |

Các nhóm mã Java phía trên dùng chung tiền tố `src/main/java/com/cashflow/auth/`.
