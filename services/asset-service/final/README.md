# Asset & Investment Service — Final (ASP.NET Core)

Nâng cấp từ Native C# sang ASP.NET Core 8 Web API + EF Core 8/Pomelo + MySQL 8. Giữ nguyên `midterm/` và `../db/` để so sánh. Các endpoint và trường JSON cũ được duy trì; thêm định giá, lịch sử, lịch trả góp và tổng hợp đầu tư. Không cần frontend mới.

## 1. Chạy bằng Docker (từ gốc SOA-CashFlow)

```powershell
# Nếu chưa có .env: Copy-Item .env.example .env
# Kiểm tra JWT_SECRET / AUTH_JWT_SECRET đồng nhất (ít nhất 32 bytes).
docker compose -f docker-compose.final.yml config --quiet
docker compose -f docker-compose.final.yml up -d --build asset-service
docker compose -f docker-compose.final.yml logs -f asset-service
```

- Health: http://localhost:8083/health
- Database readiness: http://localhost:8083/health/ready
- Swagger: http://localhost:8083/swagger/index.html
- API Gateway: http://localhost:8080 (khi chạy hệ thống chung).

Cấu hình `docker-compose.final.yml` sử dụng image riêng, port host chỉ bind loopback; Auth/Gateway/Asset dùng chung JWT secret. Để login và demo qua Gateway, khởi chạy Auth và Gateway theo cấu hình nhóm (`docker compose -f docker-compose.final.yml up -d --build`); các service khác vẫn ở trạng thái từ ZIP gốc, có thể cần nhóm sửa lỗi riêng. Không dùng `docker-compose.yml` hoặc `docker-compose.midterm.yml` để chạy phiên bản mới.

## 2. Chạy local và test

Cần .NET SDK 8.0, MySQL 8 và NuGet access. Import `db/init-asset-db.sql` của final vào MySQL **mới**. Từ thư mục này:

```powershell
$env:DB_CONNECTION='Server=localhost;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;'
$env:JWT_SECRET='cashflow-auth-midterm-development-secret-key' # chỉ dùng demo local, đồng bộ Auth Service
$env:ASPNETCORE_URLS='http://localhost:8083'
dotnet restore AssetService.csproj
dotnet build AssetService.csproj
dotnet test Tests/AssetService.Tests.csproj
dotnet run --project AssetService.csproj
```

Có thể tắt Swagger với `ENABLE_SWAGGER=false`. Secret mặc định trong compose chỉ dùng cho môi trường học tập; đặt secret mạnh trong `.env` khi triển khai. API không nhận danh tính từ header X-User-Id / X-User-Role; Bearer JWT đã ký là bắt buộc cho tất cả `/api/...`. Token có claim `userId`, `role`, `exp` theo Auth Native. User không được truy cập ID người khác. Admin có thể chỉ định `user_id` query/body; nếu cả hai có mặt phải giống nhau. Nếu record không thuộc user đã xác định, trả 404.

## 3. Database

Final dùng 4 bảng cũ; bổ sung constraints và seed lịch sử ban đầu. Không gọi EnsureCreated/Migrate tự động. Nếu volume final đã tồn tại, Docker không chạy lại script init: backup trước, kiểm tra dữ liệu rồi áp dụng `db/upgrade-existing.sql` **một lần** cho schema cũ (DDL MySQL có implicit commit; không chạy lại mù quáng nếu đã áp dụng một phần). Không chạy `down -v` vì có thể xóa dữ liệu toàn nhóm.

## 4. API

Chi tiết request/response và status: [docs/api.md](docs/api.md). Import [docs/Asset-Final.postman_collection.json](docs/Asset-Final.postman_collection.json) vào Postman. Swagger sinh OpenAPI từ Controllers. [docs/design.md](docs/design.md) có Use Case/ERD/kiến trúc bằng Mermaid.

## 5. Quy ước tính toán

- Khấu hao đường thẳng; giá trị sổ sách theo ngày, không thấp hơn salvage_value; đây không phải giá thị trường dự báo.
- EMI theo lãi suất năm (%), kỳ tháng. `total_payment` và `total_interest` tính từ EMI chưa làm tròn rồi làm tròn 2 chữ số. Lịch trả góp làm tròn từng kỳ, điều chỉnh kỳ cuối để tất toán; tổng có thể lệch vài xu so với EMI lý thuyết, không phản ánh thanh toán thật.
- Danh mục đầu tư: cost = quantity × buy_price; market value = quantity × current_price; P/L = market value − cost; ROI null nếu giá vốn 0. Giá do người dùng nhập, không có feed thị trường. Các khoản vay và đầu tư ngầm dùng VND theo schema gốc.
- Net worth: giữ `total_net_worth = total_assets - total_debt` (dư nợ theo principal khoản ACTIVE vì chưa có payment ledger). Chỉ cộng tài sản VND, trả số tài sản ngoại tệ bị loại; `investment_value` riêng để tránh đếm trùng với assets. Không tự chuyển đổi ngoại tệ.
- `asset_history` ghi snapshot ban đầu và khi giá trị thay đổi, không suy diễn dữ liệu lịch sử chưa có; giao dịch atomic. Concurrency token current_value bảo vệ xung đột định giá; không phải cơ chế khóa toàn bộ field.

## 6. Trạng thái kiểm tra

Mã nguồn và cấu hình được kiểm tra tĩnh; không có SDK/Docker/MySQL trong môi trường tạo ZIP nên chưa xác nhận compile, runtime, NuGet restore hoặc test C# thực tế. Chạy các lệnh trên và test Postman trước khi merge. Các test tự động không thay thế kiểm thử tích hợp toàn nhóm. Midterm gốc có merge conflict và không được sửa trong bản nâng cấp này.
