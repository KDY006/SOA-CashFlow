# Biểu đồ Use Case Tổng quát Hệ thống SOA CashFlow (Phiên bản Giữa kỳ)

Tài liệu này cung cấp cả hai định dạng mã nguồn cho **Hình 1-2: Biểu đồ Use Case Tổng quát Hệ thống SOA CashFlow (Giữa kỳ)**:
1. **Mermaid (`flowchart LR`)** để nhúng trực tiếp trong `Bao-Cao-Giua-Ky.md`.
2. **PlantUML (`.puml`)** chuẩn UML Use Case (đồng bộ với tệp độc lập [02-usecase-overall.puml](./02-usecase-overall.puml)) để xuất ảnh vector sắc nét khi biên tập Word/PDF.

---

## 1. Mã nguồn Mermaid (Hình 1-2)

```mermaid
flowchart LR
    USER["Người dùng cá nhân<br/>(Role: USER)"]
    ADMIN["Quản trị viên<br/>(Role: ADMIN)"]
    BANK["Đối tác Ngân hàng /<br/>Webhook Caller"]

    subgraph SYS["Hệ thống SOA CashFlow (Phiên bản Giữa kỳ - Native)"]
        subgraph UC_AUTH["Phân hệ 1: Xác thực & Hồ sơ (Auth Service :8081)"]
            UC1["UC1.1 Đăng ký / Đăng nhập & Cấp JWT"]
            UC2["UC1.2 Làm mới Token (/api/auth/refresh)"]
            UC3["UC1.3 Cập nhật Hồ sơ & Đổi mật khẩu"]
            UC4["UC1.4 Quản trị Tài khoản & Khóa/Mở USER"]
        end

        subgraph UC_TX["Phân hệ 2: Giao dịch & Ngân sách (Transaction Service :8082)"]
            UC5["UC2.1 Quản lý Danh mục & Giao dịch Thu/Chi"]
            UC6["UC2.2 Thiết lập Ngân sách & Theo dõi Tiến độ"]
            UC7["UC2.3 Kiểm tra Ngưỡng & Sinh Cảnh báo Ngân sách (80%/100%)"]
        end

        subgraph UC_ASSET["Phân hệ 3: Tài sản, Khoản vay & Đầu tư (Asset Service :8083)"]
            UC8["UC3.1 Quản lý Tài sản & Tính Khấu hao Tuyến tính"]
            UC9["UC3.2 Quản lý Khoản vay & Tính Lãi Trả góp EMI"]
            UC10["UC3.3 Quản lý Đầu tư & Tính Tài sản Ròng (Net Worth)"]
        end

        subgraph UC_ANA["Phân hệ 4: Phân tích Dòng tiền (Analytics Service :8084)"]
            UC11["UC4.1 Xem Tổng hợp, Cơ cấu Chi tiêu & Xu hướng 3 tháng"]
            UC12["UC4.2 Phát hiện Chi tiêu Bất thường (MAD) & Lưu Báo cáo"]
        end

        subgraph UC_INT["Phân hệ 5: Tích hợp Ngân hàng & Đồng thời (Integration Service :8085)"]
            UC13["UC5.1 Liên kết Tài khoản & Xem/Đồng bộ Tỷ giá"]
            UC14["UC5.2 Tiếp nhận Webhook Ngân hàng"]
            UC15["UC5.3 Khóa 2 tầng & Kiểm tra Idempotency 3 lớp"]
            UC16["UC5.4 Đồng bộ Nền sang Transaction (Worker + Sweeper)"]
            UC17["UC5.5 Thử nghiệm Tải Đồng thời (safe vs unsafe)"]
        end

        subgraph UC_NOTIF["Phân hệ 6: Thông báo Thời gian thực (Notification Service :8086)"]
            UC18["UC6.1 Nhận Thông báo Realtime qua SSE"]
            UC19["UC6.2 Xem Danh sách & Đánh dấu Đã đọc"]
        end
    end

    ADMIN -.->|"Kế thừa quyền USER"| USER
    USER --> UC1
    USER --> UC2
    USER --> UC3
    ADMIN --> UC4

    USER --> UC5
    USER --> UC6
    UC5 -.->|"&lt;&lt;extend&gt;&gt; khi EXPENSE >= 80%"| UC7

    USER --> UC8
    USER --> UC9
    USER --> UC10

    USER --> UC11
    USER --> UC12

    USER --> UC13
    USER --> UC17
    BANK --> UC14
    UC14 -.->|"&lt;&lt;include&gt;&gt;"| UC15
    UC14 -.->|"&lt;&lt;include&gt;&gt;"| UC16
    UC16 -.->|"&lt;&lt;extend&gt;&gt; khi có budget_alert"| UC18

    USER --> UC18
    USER --> UC19
```

---

## 2. Mã nguồn PlantUML (Đồng bộ với `02-usecase-overall.puml`)

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle
skinparam actorStyle awesome

actor "Người dùng cá nhân\n(USER)" as User
actor "Quản trị viên\n(ADMIN)" as Admin
actor "Đối tác Ngân hàng /\nWebhook Caller" as Bank

Admin -|> User

rectangle "Hệ thống SOA CashFlow (Phiên bản Giữa kỳ - 100% Native Code)" {
  package "Phân hệ 1: Xác thực & Hồ sơ (Java :8081)" {
    usecase "Đăng ký / Đăng nhập\n& Cấp JWT + Refresh" as UC_AuthLogin
    usecase "Làm mới Access Token\n(/api/auth/refresh)" as UC_AuthRefresh
    usecase "Cập nhật Hồ sơ &\nĐổi mật khẩu" as UC_Profile
    usecase "Quản trị Người dùng\n(CRUD / Khóa tài khoản)" as UC_AdminUsers
  }

  package "Phân hệ 2: Giao dịch & Ngân sách (PHP :8082)" {
    usecase "Quản lý Giao dịch Thu/Chi\n& Danh mục" as UC_TxCrud
    usecase "Thiết lập Ngân sách &\nXem Tiến độ" as UC_Budget
    usecase "Sinh Cảnh báo Ngân sách\n(WARNING >=80%, EXCEEDED >100%)" as UC_BudgetAlert
  }

  package "Phân hệ 3: Tài sản, Khoản vay & Đầu tư (C# :8083)" {
    usecase "Quản lý Tài sản &\nTính Khấu hao Tuyến tính" as UC_Asset
    usecase "Quản lý Khoản vay &\nTính Trả góp EMI" as UC_Loan
    usecase "Quản lý Đầu tư &\nTính Net Worth" as UC_Invest
  }

  package "Phân hệ 4: Phân tích Dòng tiền (Python :8084)" {
    usecase "Xem Tổng hợp, Phân bổ\n& Dự báo Xu hướng 3 tháng" as UC_AnalyticsView
    usecase "Phát hiện Bất thường (MAD)\n& Lưu Báo cáo Tháng" as UC_AnalyticsAgg
  }

  package "Phân hệ 5: Tích hợp Ngân hàng & Đồng thời (Go :8085)" {
    usecase "Liên kết Tài khoản &\nĐồng bộ Tỷ giá Ngoại tệ" as UC_IntAccount
    usecase "Tiếp nhận Webhook Ngân hàng\n(/webhooks/banking)" as UC_Webhook
    usecase "Khóa 2 tầng & Kiểm tra\nIdempotency 3 lớp" as UC_Idem
    usecase "Đồng bộ Nền sang Transaction\n(Worker Pool + Sweeper)" as UC_Syncer
    usecase "Mô phỏng Tải Đồng thời\n(safe vs unsafe)" as UC_Simulate
  }

  package "Phân hệ 6: Thông báo Thời gian thực (Node.js :8086)" {
    usecase "Nhận Cảnh báo Realtime\nqua Kênh SSE" as UC_Sse
    usecase "Xem & Đánh dấu Đã đọc\nThông báo" as UC_NotifRead
  }
}

User --> UC_AuthLogin
User --> UC_AuthRefresh
User --> UC_Profile
Admin --> UC_AdminUsers

User --> UC_TxCrud
User --> UC_Budget
UC_BudgetAlert .> UC_TxCrud : <<extend>>

User --> UC_Asset
User --> UC_Loan
User --> UC_Invest

User --> UC_AnalyticsView
User --> UC_AnalyticsAgg

User --> UC_IntAccount
User --> UC_Simulate
Bank --> UC_Webhook
UC_Webhook .> UC_Idem : <<include>>
UC_Webhook .> UC_Syncer : <<include>>
UC_Sse .> UC_Syncer : <<extend>>

User --> UC_Sse
User --> UC_NotifRead
@enduml
```
