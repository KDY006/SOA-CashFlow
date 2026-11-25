# Biểu đồ Use Case Tổng quát & Quy trình OTP — Phiên bản Cuối kỳ

Tài liệu này trình bày Biểu đồ Use Case Tổng quát của **SOA-CashFlow Bản Cuối kỳ (`Hình 1-2`)** dưới hai định dạng: **Mermaid** (hiển thị trực tiếp trong Markdown) và **PlantUML** (đồng bộ 1-1 với tệp độc lập [final-02-usecase-and-otp.puml](./final-02-usecase-and-otp.puml)).

---

## 1. Hình 1-2: Biểu đồ Use Case Tổng quát Bản Cuối kỳ (Mermaid)

```mermaid
flowchart LR
    USER(["Người dùng (USER)"])
    ADMIN(["Quản trị viên (ADMIN)"])
    BANK(["Hệ thống Ngân hàng / Webhook"])
    SCHED(["FastAPI Lifespan / Background Syncer"])

    subgraph SystemBoundary["Hệ thống Quản lý Dòng tiền Cá nhân SOA CashFlow (Final - Full Frameworks)"]
        subgraph SubAuth["1. Xác thực & Quản trị (Spring Boot :8081)"]
            UC_REG["Đăng ký tài khoản (USER)"]
            UC_LOGIN["Đăng nhập & Cấp JWT HS256"]
            UC_REFRESH["Làm mới Access Token (SHA-256 Refresh)"]
            UC_PROFILE["Xem/Cập nhật Hồ sơ & Đổi mật khẩu"]
            UC_ADMIN["Quản lý Người dùng / Khóa tài khoản"]
        end

        subgraph SubTx["2. Giao dịch & Ngân sách (Laravel 12 :8082)"]
            UC_CAT["Quản lý Danh mục riêng (user_id)"]
            UC_TX["Ghi nhận Thu/Chi & Số dư (/api/balance)"]
            UC_BUDGET["Thiết lập Ngân sách (MONTHLY / QUARTERLY)"]
            UC_ALERT["Kiểm tra Vượt ngưỡng (BCMath)"]
        end

        subgraph SubAsset["3. Tài sản, Nợ & Đầu tư (ASP.NET Core 8 :8083)"]
            UC_ASSET["Quản lý Tài sản & Khấu hao"]
            UC_VAL["Định giá Sổ sách (/valuation) & Lịch sử (/history)"]
            UC_LOAN["Quản lý Khoản vay, Lịch trả góp (/schedule) & Trạng thái"]
            UC_INV["Quản lý Đầu tư & Tổng hợp P/L, ROI (/summary)"]
            UC_NW["Tính Giá trị Ròng (Net Worth - RepeatableRead)"]
        end

        subgraph SubAna["4. Phân tích & Dự báo (FastAPI :8084)"]
            UC_SUMMARY["Thống kê Dòng tiền & Cơ cấu Chi tiêu"]
            UC_TREND["Xu hướng & Dự báo TB 3 tháng"]
            UC_ANOM["Phát hiện Bất thường (MAD)"]
            UC_REP["Tổng hợp & Lưu Báo cáo Tháng"]
        end

        subgraph SubInt["5. Tích hợp Ngân hàng (Go Gin + GORM :8085)"]
            UC_LINK["Liên kết Tài khoản & Đồng bộ Tỷ giá"]
            UC_WH["Nhận Webhook (KeyedLocker + FOR UPDATE + Idempotency)"]
            UC_SIM["Mô phỏng Tải đồng thời (Safe vs Unsafe)"]
        end

        subgraph SubNoti["6. Thông báo Realtime & OTP 6 số (Express + Socket.io :8086)"]
            UC_OTP_GEN["Sinh Mã OTP 6 số (TTL 300s, Single-use)"]
            UC_OTP_VER["Xác thực Mã OTP (Khóa sau 3 lần sai)"]
            UC_RT["Nhận Thông báo Kép (Socket.io WS + SSE + Redis)"]
        end

        subgraph SubGW["7. Giám sát & Điều phối Gateway (:8080)"]
            UC_HEALTH["Giám sát Sức khỏe Tập trung (/health/services)"]
        end
    end

    USER --> UC_REG & UC_LOGIN & UC_REFRESH & UC_PROFILE
    USER --> UC_CAT & UC_TX & UC_BUDGET
    USER --> UC_ASSET & UC_VAL & UC_LOAN & UC_INV & UC_NW
    USER --> UC_SUMMARY & UC_TREND & UC_ANOM & UC_REP
    USER --> UC_LINK & UC_SIM
    USER --> UC_OTP_GEN & UC_OTP_VER & UC_RT

    ADMIN -.->|"Kế thừa quyền USER"| USER
    ADMIN --> UC_ADMIN & UC_HEALTH

    BANK -->|"HMAC X-Signature + Idempotency-Key"| UC_WH
    SCHED -->|"Syncer Worker / Aggregation"| UC_REP & UC_TX

    UC_TX -.->|"<<include>>"| UC_ALERT
    UC_ALERT -.->|"<<extend>> Đẩy BUDGET_ALERT"| UC_RT
    UC_TX -.->|"<<extend>> Xác thực giao dịch nhạy cảm"| UC_OTP_GEN
    UC_OTP_GEN -.->|"<<include>>"| UC_RT
    UC_OTP_VER -.->|"<<include>>"| UC_RT
    UC_WH -.->|"<<include>>"| UC_TX
```

*Hình 1-2: Biểu đồ Use Case Tổng quát Hệ thống SOA CashFlow Bản Cuối kỳ (Mermaid)*

---

## 2. Biểu đồ Use Case Tổng quát (PlantUML — Đồng bộ với `final-02-usecase-and-otp.puml`)

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle
skinparam actorStyle awesome

actor "Người dùng (USER)" as USER
actor "Quản trị viên (ADMIN)" as ADMIN
actor "Ngân hàng / Webhook" as BANK
actor "Tiến trình Nền (Syncer)" as SYNCER

ADMIN -|> USER

rectangle "Hệ thống SOA-CashFlow (Phiên bản Cuối kỳ - 7 Frameworks)" {
  package "Auth & Profile (Spring Boot :8081)" {
    usecase "Đăng ký tài khoản" as UC_REG
    usecase "Đăng nhập (PBKDF2 + JWT HS256)" as UC_LOGIN
    usecase "Làm mới Access Token (SHA-256 Refresh)" as UC_REFRESH
    usecase "Cập nhật Hồ sơ & Đổi mật khẩu" as UC_PROFILE
    usecase "Quản trị Người dùng & Khóa tài khoản" as UC_ADMIN
  }

  package "Transaction & Budget (Laravel 12 :8082)" {
    usecase "Quản lý Danh mục riêng (user_id)" as UC_CAT
    usecase "Ghi nhận Thu/Chi & Xem Số dư (/api/balance)" as UC_TX
    usecase "Thiết lập Ngân sách (MONTHLY / QUARTERLY)" as UC_BUDGET
    usecase "Kiểm tra Ngưỡng Ngân sách (BCMath)" as UC_ALERT
  }

  package "Asset & Investment (ASP.NET Core 8 :8083)" {
    usecase "Quản lý Tài sản & Tính Khấu hao" as UC_ASSET
    usecase "Định giá Sổ sách (/valuation) & Lịch sử (/history)" as UC_VAL
    usecase "Quản lý Khoản vay, Lịch trả góp (/schedule) & Trạng thái" as UC_LOAN
    usecase "Quản lý Đầu tư & Tổng hợp P/L, ROI (/summary)" as UC_INV
    usecase "Tính Giá trị Ròng (Net Worth - RepeatableRead)" as UC_NW
  }

  package "Analytics (FastAPI :8084)" {
    usecase "Thống kê Tổng quan & Cơ cấu Chi tiêu" as UC_ANA_SUM
    usecase "Dự báo Xu hướng TB 3 tháng" as UC_TREND
    usecase "Phát hiện Chi tiêu Bất thường (MAD)" as UC_ANOM
    usecase "Tổng hợp & Lưu Báo cáo Tháng" as UC_REP
  }

  package "Integration & Concurrency (Go Gin :8085)" {
    usecase "Liên kết Tài khoản & Đồng bộ Tỷ giá" as UC_LINK
    usecase "Xử lý Webhook (KeyedLocker + FOR UPDATE)" as UC_WH
    usecase "Mô phỏng Tải đồng thời (Safe vs Unsafe)" as UC_SIM
  }

  package "Notification & OTP (Express + Socket.io :8086)" {
    usecase "Sinh Mã OTP 6 chữ số (TTL 300s)" as UC_OTP_GEN
    usecase "Xác thực Mã OTP (Single-use, Max 3 lần sai)" as UC_OTP_VER
    usecase "Nhận Thông báo Kép (Socket.io WS + SSE)" as UC_RT
  }

  package "API Gateway Observability (:8080)" {
    usecase "Giám sát Sức khỏe Tập trung (/health/services)" as UC_HEALTH
  }
}

USER --> UC_REG
USER --> UC_LOGIN
USER --> UC_REFRESH
USER --> UC_PROFILE
USER --> UC_CAT
USER --> UC_TX
USER --> UC_BUDGET
USER --> UC_ASSET
USER --> UC_VAL
USER --> UC_LOAN
USER --> UC_INV
USER --> UC_NW
USER --> UC_ANA_SUM
USER --> UC_TREND
USER --> UC_ANOM
USER --> UC_REP
USER --> UC_LINK
USER --> UC_SIM
USER --> UC_OTP_GEN
USER --> UC_OTP_VER
USER --> UC_RT

ADMIN --> UC_ADMIN
ADMIN --> UC_HEALTH

BANK --> UC_WH
SYNCER --> UC_TX

UC_TX ..> UC_ALERT : <<include>>
UC_ALERT ..> UC_RT : <<extend>>
UC_TX ..> UC_OTP_GEN : <<extend>>
UC_OTP_GEN ..> UC_RT : <<include>>
UC_OTP_VER ..> UC_RT : <<include>>
UC_WH ..> UC_TX : <<include>>
@enduml
```

### Phân tích chi tiết Hình 1-2:
1. **Phân cấp Tác nhân (Actors & RBAC):**
   - `USER` là tác nhân trung tâm sở hữu toàn bộ nghiệp vụ cá nhân (Auth, Thu/Chi, Ngân sách, Tài sản/Vay/Đầu tư, Phân tích, Liên kết ngân hàng, Sinh/Xác thực OTP và nhận thông báo realtime).
   - `ADMIN` kế thừa toàn bộ quyền của `USER` và được bổ sung quyền quản trị người dùng (`/api/admin/users/**` với `ROLE_ADMIN`) cùng quyền giám sát trạng thái tập trung toàn cụm microservice qua `GET /health/services`.
   - Hai tác nhân hệ thống bên ngoài là **Ngân hàng / Webhook** (gửi biến động số dư kèm chữ ký HMAC `X-Signature` và khóa `Idempotency-Key`) và **Tiến trình Nền (`Syncer`)** tự động chuyển tiếp giao dịch ngân hàng sang Transaction Service.
2. **Các Ca sử dụng Mở rộng Đặc trưng của Bản Cuối kỳ:**
   - **Xác thực Giao dịch bằng OTP 6 số (`POST /api/notifications/otp/generate` & `/verify`):** Cho phép sinh mã mật mã học 6 chữ số (hiệu lực 300s), phát tức thời tới Socket.io Room `user:<id>`, SSE và Redis Pub/Sub; khi xác thực sẽ kiểm tra hạn dùng, giới hạn tối đa 3 lần nhập sai và tiêu hủy ngay lập tức (Single-use).
   - **Nghiệp vụ Tài sản Chuyên sâu (ASP.NET Core 8):** Bổ sung ca sử dụng định giá sổ sách theo số ngày thực tế (`/valuation`), xem lịch sử biến động giá trị (`/history`), xuất lịch khấu trừ gốc & lãi từng tháng (`/schedule`), chuyển trạng thái khoản vay (`/status`) và tổng hợp P/L, ROI danh mục đầu tư (`/summary`).
   - **Giám sát & Bảo mật Tài khoản:** Bổ sung làm mới Access Token bằng `refreshToken` băm `SHA-256` và theo dõi sức khỏe 6 dịch vụ qua `GET /health/services`.
3. **Quan hệ `<<include>>` và `<<extend>>`:**
   - Mỗi lần ghi nhận giao dịch (`UC_TX`) đều `<<include>>` kiểm tra ngưỡng ngân sách bằng số học `BCMath` (`UC_ALERT`), và chỉ `<<extend>>` đẩy cảnh báo `BUDGET_ALERT` qua kênh realtime (`UC_RT`) khi mức chi tiêu chạm `>= 80%` (`WARNING`) hoặc `>= 100%` (`EXCEEDED`). Tương tự, quy trình sinh/xác thực OTP `<<include>>` đẩy sự kiện realtime (`otp:received`, `otp:verified`, SSE `notification`) tới người dùng sở hữu.
