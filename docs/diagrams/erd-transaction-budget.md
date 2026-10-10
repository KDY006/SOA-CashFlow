# Sơ đồ Thực thể Liên kết (ERD) - Transaction & Budget Service

## 1. Hình ảnh Sơ đồ ERD Trực quan


![alt text](image.png)

> 💡 **Cách xem trực tiếp trong VS Code:**
> - Nhấn phím tắt **`Ctrl + Shift + V`** (hoặc nhấn vào biểu tượng **Open Preview to the Side** ở góc trên cùng bên phải màn hình) để xem văn bản kèm ảnh trực quan.


---

## 2. Mã nguồn Sơ đồ Mermaid (Dự phòng)

```mermaid
erDiagram
    CATEGORIES ||--o{ TRANSACTIONS : "categorizes (1:N)"
    CATEGORIES ||--o{ BUDGETS : "limits (1:N)"

    CATEGORIES {
        int id PK "Khóa chính tự tăng"
        varchar name "Tên danh mục (Ăn uống, Tiền nhà, Lương...)"
        enum type "Loại danh mục: INCOME | EXPENSE"
        varchar icon "Tên icon FontAwesome (fa-utensils, fa-wallet...)"
    }

    TRANSACTIONS {
        bigint id PK "Khóa chính giao dịch"
        bigint user_id "ID người dùng (tham chiếu logical từ Auth Service)"
        int category_id FK "Khóa ngoại tham chiếu categories.id (NULL on delete)"
        varchar category "Tên danh mục lưu snapshot phục vụ truy vấn nhanh"
        decimal amount "Số tiền giao dịch (15, 2)"
        enum type "Loại giao dịch: INCOME | EXPENSE | TRANSFER"
        text description "Ghi chú/Mô tả chi tiết giao dịch"
        datetime transaction_date "Thời gian phát sinh giao dịch thực tế"
        timestamp created_at "Thời gian tạo bản ghi"
        timestamp updated_at "Thời gian cập nhật"
    }

    BUDGETS {
        int id PK "Khóa chính ngân sách"
        bigint user_id "ID người dùng thiết lập ngân sách"
        int category_id FK "Khóa ngoại tham chiếu categories.id (CASCADE on delete)"
        decimal amount_limit "Số tiền hạn mức tối đa cho danh mục (15, 2)"
        enum period "Chu kỳ: MONTHLY | YEARLY"
        date start_date "Ngày bắt đầu áp dụng hạn mức"
        date end_date "Ngày kết thúc áp dụng hạn mức"
        timestamp created_at "Thời gian tạo ngân sách"
        timestamp updated_at "Thời gian cập nhật ngân sách"
    }
```

---

## 3. Bảng Mô tả Chi tiết Thực thể & Thuộc tính

### 3.1. Bảng `categories` (Danh mục thu / chi)
- **Mục đích:** Phân loại các khoản tiền thu vào hoặc chi ra của người dùng (ví dụ: Lương, Freelance, Ăn uống, Tiền nhà, Mua sắm...).
- **Khóa chính:** `id` (INT, AUTO_INCREMENT).
- **Ràng buộc:** 
  - `type` chỉ nhận `INCOME` hoặc `EXPENSE`.
  - Hỗ trợ gắn icon giao diện (`icon`).

### 3.2. Bảng `transactions` (Giao dịch thu / chi / chuyển khoản)
- **Mục đích:** Lưu trữ mọi dòng tiền biến động của người dùng theo thời gian thực.
- **Khóa chính:** `id` (BIGINT, AUTO_INCREMENT).
- **Liên kết logic (SOA):** `user_id` liên kết với dịch vụ `Auth Service` (không tạo foreign key cứng để bảo toàn nguyên tắc Database-per-Service trong Microservices).
- **Khóa ngoại:** `category_id` tham chiếu `categories(id)` với hành vi `ON DELETE SET NULL`.
- **Chỉ mục (Index):** `idx_user_trans (user_id, transaction_date)` để tối ưu tốc độ lọc lịch sử và phân tích biểu đồ của Analytics Service.

### 3.3. Bảng `budgets` (Hạn mức ngân sách)
- **Mục đích:** Đặt ngưỡng chi tiêu tối đa cho từng danh mục trong một khoảng thời gian (theo tháng hoặc theo năm).
- **Khóa chính:** `id` (INT, AUTO_INCREMENT).
- **Khóa ngoại:** `category_id` tham chiếu `categories(id)` với hành vi `ON DELETE CASCADE`.
- **Chỉ mục (Index):** `idx_user_budget (user_id, start_date, end_date)` phục vụ việc tra cứu nhanh hạn mức đang hiệu lực khi người dùng tạo giao dịch chi tiêu mới.

---

## 4. Mối quan hệ giữa các Thực thể (Relationships)
1. **`categories` - `transactions` (Quan hệ 1 - N):** Một danh mục có thể chứa nhiều giao dịch thu/chi. Một giao dịch thuộc về một danh mục (hoặc NULL nếu danh mục bị xóa).
2. **`categories` - `budgets` (Quan hệ 1 - N):** Một danh mục có thể được thiết lập nhiều ngân sách qua các tháng/năm khác nhau.
3. **Mối quan hệ nghiệp vụ giữa `budgets` và `transactions`:** 
   - Không nối khóa ngoại trực tiếp mà được liên kết qua logic nghiệp vụ (`user_id`, `category_id`, khoảng thời gian `start_date` đến `end_date`).
   - Khi tạo giao dịch chi tiêu (`EXPENSE`), hệ thống tự động tính tổng tiền các giao dịch trong kỳ so sánh với `amount_limit` của bảng `budgets` để đưa ra cảnh báo thời gian thực (`budget_alert`).
