# Asset Service (C#)

- **Phụ trách:** Thành viên 3
- **Công nghệ:** C# (Bản giữa kỳ: C# Native HttpListener / Bản cuối kỳ: ASP.NET Core Web API)
- **Cơ sở dữ liệu:** MySQL (`asset_db` trên port 3306)
- **Port phục vụ:** `8083`

## Các API Endpoints chính
- `GET /api/assets` - Danh sách tài sản/danh mục đầu tư của người dùng
- `POST /api/assets` - Khai báo tài sản mới (tài khoản ngân hàng, cổ phiếu, tiền tiết kiệm...)
- `GET /api/assets/{id}` - Chi tiết tài sản
- `PUT /api/assets/{id}` - Cập nhật giá trị tài sản
- `GET /api/assets/net-worth` - Tính toán tổng giá trị tài sản ròng (Net worth)

## Chạy Local
```bash
dotnet run
```
