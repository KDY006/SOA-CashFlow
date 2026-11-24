# Thiết kế Asset & Investment Service (Final)

## Use case

```mermaid
flowchart LR
  U[User / Employee] --> A((Quản lý tài sản))
  U --> L((Quản lý khoản vay))
  U --> I((Quản lý đầu tư))
  U --> N((Xem tài sản ròng))
  U --> D((Tính khấu hao))
  U --> E((Tính EMI))
  A --> H((Xem lịch sử giá trị))
  A --> V((Định giá theo ngày))
  L --> S((Xem lịch trả góp dự kiến))
  L --> C((Cập nhật trạng thái khoản vay))
  I --> P((Xem lãi lỗ và ROI))
  AD[Admin] --> U
  AD --> T((Truy cập theo user_id được chỉ định))
```

Admin có thể chọn user_id khác; user/employee chỉ được thao tác bản ghi của mình. Auth Service cấp JWT; Asset Service kiểm tra chữ ký, thời hạn và định danh.

## ERD (schema thực tế)

```mermaid
erDiagram
  assets ||--o{ asset_history : records
  assets {
    bigint id PK
    bigint user_id
    varchar asset_name
    varchar asset_type
    decimal purchase_price
    decimal current_value
    decimal salvage_value
    int useful_life_years
    date purchase_date
    varchar currency
    text note
    timestamp created_at
    timestamp updated_at
  }
  asset_history {
    bigint id PK
    bigint asset_id FK
    decimal value
    timestamp recorded_at
  }
  loans {
    bigint id PK
    bigint user_id
    varchar loan_title
    decimal principal_amount
    decimal interest_rate
    int term_months
    date start_date
    varchar status
    timestamp created_at
    timestamp updated_at
  }
  investments {
    bigint id PK
    bigint user_id
    varchar symbol
    varchar investment_type
    decimal quantity
    decimal buy_price
    decimal current_price
    timestamp created_at
    timestamp updated_at
  }
```

`user_id` là tham chiếu logic đến Auth Service, không có FK xuyên DB. Quan hệ assets-history là 1:N, cascade delete. Constraints và seed nằm trong `db/init-asset-db.sql`.

## Kiến trúc phân lớp

```mermaid
flowchart TD
  F[PHP Frontend / Postman] --> G[API Gateway :8080]
  Auth[Auth Service] -->|HS256 JWT| F
  G -->|REST + Bearer token| M[ASP.NET Core Middleware :8083]
  M --> C[API Controllers]
  C --> S[AssetManager / LoanService / InvestmentService / NetWorthService]
  S --> Calc[FinanceMath]
  S --> E[EF Core DbContext + Pomelo]
  E --> DB[(MySQL asset_db)]
```

## Đối chiếu midterm → final

| Midterm | Final |
|---|---|
| Program.cs + HttpListener | Program.cs + Kestrel |
| Router.cs | Controllers (attribute routing) |
| Common/HttpHelper.cs | JSON snake_case, model binding, JWT, CurrentUser, exception middleware |
| Common/Database.cs | Data/AssetDbContext.cs, EF Core async LINQ |
| Handlers/AssetHandler.cs | AssetManager, FinanceMath, AssetsController |
| Handlers/LoanHandler.cs | LoanService, FinanceMath, LoansController |
| Handlers/InvestmentHandler.cs | InvestmentService, InvestmentResponse, InvestmentsController |
| Handlers/AnalyticsHandler.cs | NetWorthService |
| Models/*Models.cs | DTOs/Requests.cs, DTOs/Responses.cs, Models/Entities.cs |

Ghi lịch sử tài sản cùng SaveChanges: EF Core sử dụng transaction cho nhiều lệnh ghi; current_value là concurrency token, cập nhật xung đột trả 409. Đây không thay thế các bài test concurrency/idempotency bắt buộc của Transaction và Integration Service toàn nhóm.
