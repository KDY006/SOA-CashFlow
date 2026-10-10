-- =============================================================================
-- Asset Service Database Initialization Script (MySQL 8.0)
-- Bảng: assets, loans, investments, asset_history
-- =============================================================================
CREATE DATABASE IF NOT EXISTS asset_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asset_db;

SET NAMES utf8mb4;

-- 1. Bảng tài sản (Assets)
CREATE TABLE IF NOT EXISTS assets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    asset_type VARCHAR(50) NOT NULL, -- BANK_ACCOUNT, CASH, REAL_ESTATE, CRYPTO, STOCK
    purchase_price DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    current_value DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    salvage_value DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    useful_life_years INT NOT NULL DEFAULT 0,
    purchase_date DATE NULL,
    currency VARCHAR(10) DEFAULT 'VND',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_assets (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng khoản vay & nợ (Loans)
CREATE TABLE IF NOT EXISTS loans (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    loan_title VARCHAR(150) NOT NULL,
    principal_amount DECIMAL(18, 2) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL, -- %/năm
    term_months INT NOT NULL,
    start_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', -- ACTIVE, CLOSED
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_loans (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng danh mục đầu tư (Investments)
CREATE TABLE IF NOT EXISTS investments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    investment_type VARCHAR(50) NOT NULL, -- STOCK, CRYPTO, GOLD, BOND
    quantity DECIMAL(18, 4) NOT NULL,
    buy_price DECIMAL(18, 2) NOT NULL,
    current_price DECIMAL(18, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_investments (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng biến động giá trị tài sản (Asset History)
CREATE TABLE IF NOT EXISTS asset_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT NOT NULL,
    value DECIMAL(18, 2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA MẪU PHỤC VỤ TEST & DEMO GIỮA KỲ
-- =============================================================================

-- Dữ liệu tài sản mẫu cho User 1
INSERT INTO assets (id, user_id, asset_name, asset_type, purchase_price, current_value, salvage_value, useful_life_years, purchase_date, currency, note) VALUES
(1, 1, 'Tài khoản Tiết kiệm Vietcombank', 'BANK_ACCOUNT', 50000000.00, 50000000.00, 50000000.00, 1, '2026-01-01', 'VND', 'Tiết kiệm kỳ hạn 12 tháng'),
(2, 1, 'Quỹ khẩn cấp tiền mặt', 'CASH', 10000000.00, 10000000.00, 10000000.00, 1, '2026-01-01', 'VND', 'Tiền mặt để két sắt'),
(3, 1, 'MacBook Pro M3 Max', 'DEVICE', 65000000.00, 55000000.00, 10000000.00, 3, '2025-06-15', 'VND', 'Máy tính làm việc lập trình')
ON DUPLICATE KEY UPDATE current_value=VALUES(current_value);

-- Khoản vay mẫu cho User 1
INSERT INTO loans (id, user_id, loan_title, principal_amount, interest_rate, term_months, start_date, status) VALUES
(1, 1, 'Vay mua xe máy trả góp', 20000000.00, 9.50, 12, '2026-01-10', 'ACTIVE')
ON DUPLICATE KEY UPDATE principal_amount=VALUES(principal_amount);

-- Đầu tư mẫu cho User 1
INSERT INTO investments (id, user_id, symbol, investment_type, quantity, buy_price, current_price) VALUES
(1, 1, 'FPT', 'STOCK', 500.0000, 120000.00, 138000.00),
(2, 1, 'BTC', 'CRYPTO', 0.0500, 60000000.00, 68000000.00)
ON DUPLICATE KEY UPDATE current_price=VALUES(current_price);
