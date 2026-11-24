# Transaction & Budget — Cuối kỳ Laravel

Laravel 12, PHP 8.2+, MySQL 8; SQLite dùng cho chạy thử và kiểm thử. Bản Native giữa kỳ vẫn nằm ở `../src`.

## Chức năng

- CRUD danh mục riêng từng người dùng; không xóa hoặc đổi loại danh mục đang được sử dụng.
- CRUD giao dịch INCOME/EXPENSE, lọc ngày/danh mục/loại, phân trang limit/offset.
- Số dư = tổng thu − tổng chi, tính từ sổ giao dịch nên phản ánh ngay khi thêm/sửa/xóa/đổi loại. Số dư đầu bằng 0, cho phép âm. Đây là số dư tổng của người dùng, chưa đồng bộ tài khoản ngân hàng hoặc Asset Service.
- Ngân sách MONTHLY/QUARTERLY tự xác định ngày đầu/cuối tháng hoặc quý từ start_date. Không trùng danh mục + loại kỳ + ngày đầu kỳ; cho phép ngân sách tháng và quý cùng tồn tại.
- Tiến độ SAFE (<80%), WARNING (80% đến dưới 100%), EXCEEDED (từ 100%), trả tiền đã chi/còn lại/vượt mức và tỷ lệ. Tính cả ngày cuối kỳ. Cảnh báo trả trong JSON, chưa gửi qua Notification Service.
- Tiền dương, tối đa 13 chữ số nguyên và 2 chữ số thập phân; MySQL DECIMAL và PHP BCMath. Mọi thay đổi giao dịch trả lại số dư và tiến độ ngân sách.

## Chạy local trên Windows

Từ `services/transaction-service/final`, chỉ copy .env khi chưa có:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8082
```

Mặc định SQLite; không cần npm. Cần PHP BCMath, PDO SQLite, Mbstring, XML và extensions chuẩn Laravel. Docker dùng PDO MySQL.
Nếu XAMPP thiếu ZIP khi chạy Composer, bật tạm bằng:

```powershell
php -d extension=zip C:/ProgramData/ComposerSetup/bin/composer.phar install
```

## Docker / MySQL

Bật Docker Desktop rồi chạy tại gốc repo:

```powershell
docker compose -f docker-compose.final.yml up -d --build transaction-service
```

Compose đợi MySQL healthy rồi chạy migration. Database `transaction_final_db` dùng volume `transaction_laravel_data` riêng; không nhập dữ liệu Native. Chỉ khởi động Transaction Service và database phụ thuộc. Các service khác do nhóm tích hợp riêng.
MySQL ngoài Docker: tạo database rồi cấu hình DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD trong .env và chạy migration.

## Danh tính và gateway

API nghiệp vụ yêu cầu header `X-User-Id` là số nguyên dương. Không dùng user_id từ query/body, không mặc định user 1. Tất cả bản ghi thuộc người dùng đó.

Đây là hợp đồng giữa service, **không phải xác thực JWT**. Gateway phải xác thực token và ghi đè header, chặn client tự đặt danh tính; khi triển khai thật service phải nằm trong mạng nội bộ sau gateway. Port trực tiếp hiện mở để demo. Nhánh hiện tại chưa có mã nguồn gateway để tích hợp xác thực.

## API

Thành công: `{"success":true,"data":...}`. Lỗi validation: HTTP 422 (`message`, `errors`); thiếu danh tính 401; bản ghi không thuộc người dùng 404; danh mục đang được sử dụng 409.

| Endpoint | Chức năng |
|---|---|
| GET /health, GET /api/health | Kiểm tra service, không cần danh tính |
| GET/POST /api/categories | Danh sách/thêm danh mục, lọc type |
| GET/PUT/PATCH/DELETE /api/categories/{id} | Chi tiết/cập nhật/xóa |
| GET/POST /api/transactions | Danh sách/thêm giao dịch |
| GET/PUT/PATCH/DELETE /api/transactions/{id} | Chi tiết/cập nhật/xóa |
| GET /api/balance | Tổng thu, tổng chi, số dư |
| GET /api/transactions/balance | Alias số dư |
| GET/POST /api/budgets | Danh sách/tạo ngân sách |
| GET/PUT/PATCH/DELETE /api/budgets/{id} | Chi tiết/cập nhật/xóa |
| GET /api/budgets/progress | Tiến độ ngân sách |

PUT/PATCH yêu cầu đủ trường bắt buộc như POST. Giao dịch hỗ trợ bộ lọc type, category_id, from_date/to_date (Y-m-d), limit (1–100), offset (>=0). transaction_date dùng Y-m-d H:i:s, mặc định thời điểm tạo. Danh mục phải cùng loại giao dịch; ngân sách chỉ cho danh mục chi tiêu. end_date do server tính.

Ví dụ PowerShell:

```powershell
$headers = @{ 'X-User-Id' = '1'; Accept = 'application/json' }
$category = Invoke-RestMethod http://localhost:8082/api/categories -Method Post -Headers $headers -ContentType 'application/json' -Body '{"name":"Food","type":"EXPENSE"}'
$categoryId = $category.data.id
$budget = @{ category_id = $categoryId; amount_limit = '5000000.00'; period = 'QUARTERLY'; start_date = '2026-11-01' } | ConvertTo-Json
Invoke-RestMethod http://localhost:8082/api/budgets -Method Post -Headers $headers -ContentType 'application/json' -Body $budget
# Kỳ chuẩn hóa thành 2026-10-01 đến 2026-12-31.
$transaction = @{ category_id = $categoryId; amount = '150000.00'; type = 'EXPENSE'; transaction_date = '2026-11-24 12:00:00' } | ConvertTo-Json
Invoke-RestMethod http://localhost:8082/api/transactions -Method Post -Headers $headers -ContentType 'application/json' -Body $transaction
Invoke-RestMethod http://localhost:8082/api/balance -Headers $headers
Invoke-RestMethod http://localhost:8082/api/budgets/progress -Headers $headers
```

## Kiểm thử

```powershell
php artisan test
php artisan route:list
```

Tests dùng SQLite in-memory, không sửa database thật: CRUD, đổi loại giao dịch, số dư, ngân sách quý/tháng/năm nhuận, ngày cuối kỳ, cảnh báo sau sửa/xóa, chống trùng, giới hạn người dùng và validation.
Docker/MySQL cần kiểm tra tích hợp riêng khi Docker Desktop hoạt động. SQLite không chứng minh được hành vi khóa đồng thời hoặc độ chính xác DECIMAL của MySQL.
