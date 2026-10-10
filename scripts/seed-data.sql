-- =============================================================================
-- SOA-CashFlow: Global Seed Data & Initial Setup Script
-- Khớp với thuật toán của Auth Service (PBKDF2), Transaction, Asset, Integration
-- Mật khẩu mặc định cho các tài khoản test là: password123
-- =============================================================================

-- 1. Seed for Auth Database
USE auth_db;
INSERT IGNORE INTO users (id, username, email, password_hash, full_name, role, status) VALUES
(1, 'admin', 'admin@cashflow.local', '210000:aYagp9baQD0LZ9O/sVLxPQ==:3+Pmnd0JLerckR8Jj1a2iHsMw/3xz4TllUi4Z5NsU9g=', 'System Administrator', 'ADMIN', 'ACTIVE'),
(2, 'member1', 'member1@cashflow.local', '210000:aYagp9baQD0LZ9O/sVLxPQ==:3+Pmnd0JLerckR8Jj1a2iHsMw/3xz4TllUi4Z5NsU9g=', 'Nguyen Van A', 'USER', 'ACTIVE');

-- 2. Seed for Transaction Database
USE transaction_db;
INSERT IGNORE INTO categories (id, name, type, icon) VALUES
(1, 'Lương cố định', 'INCOME', 'fa-wallet'),
(2, 'Thưởng & Đầu tư', 'INCOME', 'fa-chart-line'),
(3, 'Ăn uống & Cà phê', 'EXPENSE', 'fa-utensils'),
(4, 'Tiền nhà & Tiện ích', 'EXPENSE', 'fa-home'),
(5, 'Mua sắm & Giải trí', 'EXPENSE', 'fa-shopping-cart');

INSERT IGNORE INTO budgets (id, user_id, category_id, amount_limit, period, start_date, end_date) VALUES
(1, 1, 3, 5000000.00, 'MONTHLY', '2026-10-01', '2026-10-31'),
(2, 1, 5, 2000000.00, 'MONTHLY', '2026-10-01', '2026-10-31');

INSERT IGNORE INTO transactions (id, user_id, category_id, category, amount, type, description, transaction_date) VALUES
(1, 1, 1, 'Lương cố định', 20000000.00, 'INCOME', 'Lương nhận đầu tháng 10', '2026-10-01 09:00:00'),
(2, 1, 3, 'Ăn uống & Cà phê', 150000.00, 'EXPENSE', 'Ăn tối cùng nhóm bạn', '2026-10-02 19:30:00'),
(3, 1, 4, 'Tiền nhà & Tiện ích', 3500000.00, 'EXPENSE', 'Thanh toán tiền trọ tháng 10', '2026-10-03 10:00:00'),
(4, 1, 3, 'Ăn uống & Cà phê', 450000.00, 'EXPENSE', 'Đi siêu thị mua thức ăn tuần', '2026-10-04 16:45:00'),
(5, 1, 5, 'Mua sắm & Giải trí', 1200000.00, 'EXPENSE', 'Mua bàn phím cơ gõ code', '2026-10-05 14:10:00');

-- 3. Seed for Asset Database
USE asset_db;
INSERT IGNORE INTO assets (id, user_id, asset_name, asset_type, purchase_price, current_value, salvage_value, useful_life_years, purchase_date, currency, note) VALUES
(1, 1, 'Tài khoản Tiết kiệm Vietcombank', 'BANK_ACCOUNT', 50000000.00, 50000000.00, 50000000.00, 1, '2026-01-01', 'VND', 'Tiết kiệm kỳ hạn 12 tháng'),
(2, 1, 'Quỹ khẩn cấp tiền mặt', 'CASH', 10000000.00, 10000000.00, 10000000.00, 1, '2026-01-01', 'VND', 'Tiền mặt để két sắt'),
(3, 1, 'MacBook Pro M3 Max', 'DEVICE', 65000000.00, 55000000.00, 10000000.00, 3, '2025-06-15', 'VND', 'Máy tính làm việc lập trình');

INSERT IGNORE INTO loans (id, user_id, loan_title, principal_amount, interest_rate, term_months, start_date, status) VALUES
(1, 1, 'Vay mua xe máy trả góp', 20000000.00, 9.50, 12, '2026-01-10', 'ACTIVE');

INSERT IGNORE INTO investments (id, user_id, symbol, investment_type, quantity, buy_price, current_price) VALUES
(1, 1, 'FPT', 'STOCK', 500.0000, 120000.00, 138000.00),
(2, 1, 'BTC', 'CRYPTO', 0.0500, 60000000.00, 68000000.00);

-- 4. Seed for Integration Database
USE integration_db;
INSERT INTO exchange_rates (currency_code, rate_to_vnd) VALUES
('USD', 25410.0000),
('EUR', 27620.5000),
('GBP', 32280.0000),
('JPY', 168.4500),
('SGD', 18920.0000),
('CNY', 3518.2000)
ON DUPLICATE KEY UPDATE rate_to_vnd = VALUES(rate_to_vnd);

INSERT IGNORE INTO linked_accounts (id, user_id, bank_code, account_no, holder_name, balance) VALUES
(1, 1, 'VCB', '0071000123456', 'NGUYEN VAN A', 15000000.00),
(2, 1, 'MOMO', '0909123456', 'NGUYEN VAN A', 2000000.00);
