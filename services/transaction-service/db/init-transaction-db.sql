CREATE DATABASE IF NOT EXISTS transaction_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transaction_db;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 1. Bảng danh mục
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('INCOME', 'EXPENSE') NOT NULL,
    icon VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng giao dịch thu / chi
CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    category_id INT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    type ENUM('INCOME', 'EXPENSE', 'TRANSFER') NOT NULL,
    description TEXT,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_trans (user_id, transaction_date),
    CONSTRAINT fk_trans_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng ngân sách (Bổ sung phần Budget Service)
CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    category_id INT NOT NULL,
    amount_limit DECIMAL(15, 2) NOT NULL,
    period ENUM('MONTHLY', 'YEARLY') DEFAULT 'MONTHLY',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_budget (user_id, start_date, end_date),
    CONSTRAINT fk_budget_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA MẪU PHỤC VỤ TEST & DEMO GIỮA KỲ
-- ========================================================

-- Dữ liệu danh mục
INSERT INTO categories (id, name, type, icon) VALUES
(1, 'Lương cố định', 'INCOME', 'fa-wallet'),
(2, 'Thưởng & Đầu tư', 'INCOME', 'fa-chart-line'),
(3, 'Ăn uống & Cà phê', 'EXPENSE', 'fa-utensils'),
(4, 'Tiền nhà & Tiện ích', 'EXPENSE', 'fa-home'),
(5, 'Mua sắm & Giải trí', 'EXPENSE', 'fa-shopping-cart')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Ngân sách mẫu cho user_id = 1 (Tháng 10/2026)
INSERT INTO budgets (id, user_id, category_id, amount_limit, period, start_date, end_date) VALUES
(1, 1, 3, 5000000.00, 'MONTHLY', '2026-10-01', '2026-10-31'),
(2, 1, 5, 2000000.00, 'MONTHLY', '2026-10-01', '2026-10-31')
ON DUPLICATE KEY UPDATE amount_limit=VALUES(amount_limit);

-- Giao dịch mẫu cho user_id = 1
INSERT INTO transactions (user_id, category_id, category, amount, type, description, transaction_date) VALUES
(1, 1, 'Lương cố định', 20000000.00, 'INCOME', 'Lương nhận đầu tháng 10', '2026-10-01 09:00:00'),
(1, 3, 'Ăn uống & Cà phê', 150000.00, 'EXPENSE', 'Ăn tối cùng nhóm bạn', '2026-10-02 19:30:00'),
(1, 4, 'Tiền nhà & Tiện ích', 3500000.00, 'EXPENSE', 'Thanh toán tiền trọ tháng 10', '2026-10-03 10:00:00'),
(1, 3, 'Ăn uống & Cà phê', 450000.00, 'EXPENSE', 'Đi siêu thị mua thức ăn tuần', '2026-10-04 16:45:00'),
(1, 5, 'Mua sắm & Giải trí', 1200000.00, 'EXPENSE', 'Mua bàn phím cơ gõ code', '2026-10-05 14:10:00');