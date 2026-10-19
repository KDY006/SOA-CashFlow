# CashFlow · Web Frontend (PHP)

Giao diện web cho hệ thống quản lý chi tiêu CashFlow. Viết bằng PHP thuần + Vanilla CSS/JS.
Frontend **không truy cập database**: mọi dữ liệu đều đi qua API Gateway (`:8080`).

## Chạy local

Yêu cầu: PHP ≥ 8.1 có extension `curl`. API Gateway và các service phải đang chạy.

```powershell
# từ thư mục gốc của repo
php -S 127.0.0.1:3000 -t frontend
# XAMPP: & "C:\xampp\php\php.exe" -S 127.0.0.1:3000 -t frontend
```

Mở http://localhost:3000 và đăng nhập bằng tài khoản demo: `admin / password123` hoặc `member1 / password123`.

| Biến môi trường        | Mặc định                | Ý nghĩa                                   |
|------------------------|-------------------------|-------------------------------------------|
| `GATEWAY_URL`          | `http://localhost:8080` | API Gateway                               |
| `NOTIFICATION_SSE_URL` | `http://localhost:8086` | Notification Service (SSE realtime trực tiếp) |

## Các trang

| Trang                | Service                | API sử dụng |
|----------------------|------------------------|-------------|
| `login.php`, `register.php` | Auth (Java)     | `/api/auth/login`, `/api/auth/register`, `/api/auth/refresh` |
| `index.php`          | Nhiều service           | net-worth, analytics summary/trends/breakdown, transactions, budgets/progress, rates |
| `transactions.php`   | Transaction (PHP)       | `GET/POST /api/transactions`, `PUT/DELETE /api/transactions/{id}`, `/api/categories` |
| `budgets.php`        | Transaction (PHP)       | `/api/budgets/progress`, `POST /api/budgets`, `PUT/DELETE /api/budgets/{id}` |
| `assets.php`         | Asset (C#)              | assets, loans, investments, net-worth, `/api/loans/calculate`, `/api/assets/depreciation` |
| `analytics.php`      | Analytics (Python)      | `/api/analytics/summary`, `/trends`, `/category-breakdown` |
| `banking.php`        | Integration (Go)        | accounts, account transactions, webhooks/banking, rates, sync-rates, simulate, retry |
| `notifications.php`  | Notification (Node.js)  | `/api/notifications`, `/{id}/read`, `/read-all`, SSE stream |

## Cấu trúc

```
frontend/
├── includes/        config.php (API client, helper), layout.php (navbar/hero/footer), tx_modal.php
├── assets/css/      app.css – design system (sapphire navbar, hero, glass panel, bank card...)
├── assets/js/       app.js – modal, toast, Chart.js, SSE, CF.api() gọi qua api.php
├── api.php          proxy AJAX: JWT nằm trong session phía server, trình duyệt không thấy token
└── *.php            các trang
```
