CREATE DATABASE IF NOT EXISTS integration_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE integration_db;

SET NAMES utf8mb4;

-- tỷ giá ngoại tệ, đồng bộ định kỳ từ các ngân hàng (giả lập)
CREATE TABLE IF NOT EXISTS exchange_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(10) NOT NULL UNIQUE,
    rate_to_vnd DECIMAL(15, 4) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_rate_positive CHECK (rate_to_vnd > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tài khoản ngân hàng / ví mà người dùng liên kết vào hệ thống
-- user_id chỉ tham chiếu logic sang auth-service, không đặt khóa ngoại cứng
CREATE TABLE IF NOT EXISTS linked_accounts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    bank_code VARCHAR(20) NOT NULL,
    account_no VARCHAR(30) NOT NULL,
    holder_name VARCHAR(100) NOT NULL,
    balance DECIMAL(18, 2) NOT NULL DEFAULT 0,
    version BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bank_account (bank_code, account_no),
    INDEX idx_linked_user (user_id),
    CONSTRAINT chk_balance_not_negative CHECK (balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- biến động số dư nhận từ ngân hàng
-- 2 unique key bên dưới là chốt chặn cuối cùng cho idempotency:
-- dù có bao nhiêu request trùng chạy song song thì db cũng chỉ cho 1 bản ghi
CREATE TABLE IF NOT EXISTS bank_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT NOT NULL,
    provider VARCHAR(20) NOT NULL,
    external_ref VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(150) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    direction ENUM('CREDIT', 'DEBIT') NOT NULL,
    amount DECIMAL(18, 2) NOT NULL,
    balance_after DECIMAL(18, 2) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    occurred_at DATETIME NOT NULL,
    status ENUM('RECEIVED', 'SYNCING', 'SYNCED', 'FAILED', 'REJECTED') NOT NULL DEFAULT 'RECEIVED',
    transaction_ref BIGINT NULL,
    sync_attempts INT NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_idempotency_key (idempotency_key),
    UNIQUE KEY uq_provider_ref (provider, external_ref),
    INDEX idx_bank_tx_account (account_id, occurred_at),
    INDEX idx_bank_tx_status (status, updated_at),
    CONSTRAINT fk_bank_tx_account FOREIGN KEY (account_id) REFERENCES linked_accounts (id) ON DELETE CASCADE,
    CONSTRAINT chk_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- log thô mọi webhook gọi tới (kể cả bị trùng) để đối soát
CREATE TABLE IF NOT EXISTS external_webhooks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(50) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload JSON,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_provider (provider_name, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- dữ liệu mẫu
-- ========================================================

INSERT INTO exchange_rates (currency_code, rate_to_vnd) VALUES
('USD', 25410.0000),
('EUR', 27620.5000),
('GBP', 32280.0000),
('JPY', 168.4500),
('SGD', 18920.0000),
('CNY', 3518.2000)
ON DUPLICATE KEY UPDATE rate_to_vnd = VALUES(rate_to_vnd);

INSERT INTO linked_accounts (id, user_id, bank_code, account_no, holder_name, balance) VALUES
(1, 1, 'VCB', '0071000123456', 'NGUYEN VAN A', 15000000.00),
(2, 1, 'MOMO', '0909123456', 'NGUYEN VAN A', 2000000.00),
(3, 2, 'TCB', '19033445566011', 'TRAN THI B', 8500000.00),
(4, 3, 'BIDV', '31410001234567', 'LE VAN C', 500000.00)
ON DUPLICATE KEY UPDATE holder_name = VALUES(holder_name);

INSERT IGNORE INTO bank_transactions
(account_id, provider, external_ref, idempotency_key, request_hash, direction, amount, balance_after, category, description, occurred_at, status, transaction_ref, sync_attempts)
VALUES
(1, 'VCB', 'FT26275000001', 'VCB:FT26275000001', SHA2('VCB|FT26275000001|VCB|0071000123456|CREDIT|2000000000', 256),
 'CREDIT', 20000000.00, 20000000.00, 'Lương cố định', 'CTY ABC TRA LUONG T10', '2026-10-01 08:55:00', 'SYNCED', NULL, 1),
(1, 'VCB', 'FT26276000417', 'VCB:FT26276000417', SHA2('VCB|FT26276000417|VCB|0071000123456|DEBIT|350000000', 256),
 'DEBIT', 3500000.00, 16500000.00, 'Tiền nhà & Tiện ích', 'CK tien tro thang 10', '2026-10-03 09:58:00', 'SYNCED', NULL, 1),
(1, 'VCB', 'FT26278001122', 'VCB:FT26278001122', SHA2('VCB|FT26278001122|VCB|0071000123456|DEBIT|150000000', 256),
 'DEBIT', 1500000.00, 15000000.00, 'Mua sắm & Giải trí', 'THANH TOAN SHOPEE', '2026-10-05 14:05:00', 'SYNCED', NULL, 1),
(2, 'MOMO', 'MM8812345001', 'MOMO:MM8812345001', SHA2('MOMO|MM8812345001|MOMO|0909123456|DEBIT|4500000', 256),
 'DEBIT', 45000.00, 2000000.00, 'Ăn uống & Cà phê', 'Highlands Coffee', '2026-10-06 07:40:00', 'SYNCED', NULL, 1);
