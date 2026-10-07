-- =============================================================================
-- SOA-CashFlow: Global Seed Data & Initial Setup Script
-- =============================================================================

-- Seed for Auth Database
USE auth_db;
INSERT IGNORE INTO users (id, username, email, password_hash, full_name, role) VALUES
(1, 'admin', 'admin@cashflow.local', '$2a$10$abcdefghijklmnopqrstuvwxyz123456', 'System Administrator', 'ADMIN'),
(2, 'member1', 'member1@cashflow.local', '$2a$10$abcdefghijklmnopqrstuvwxyz123456', 'Nguyen Van A', 'USER');

-- Seed for Transaction Database
USE transaction_db;
INSERT IGNORE INTO transactions (id, user_id, amount, type, category, description, created_at) VALUES
(1, 1, 5000000.00, 'INCOME', 'Salary', 'Monthly salary', NOW()),
(2, 1, 150000.00, 'EXPENSE', 'Food', 'Grocery shopping', NOW()),
(3, 2, 2000000.00, 'INCOME', 'Freelance', 'Design gig payment', NOW());

-- Seed for Asset Database
USE asset_db;
INSERT IGNORE INTO assets (id, user_id, asset_name, asset_type, current_value, currency) VALUES
(1, 1, 'Savings Account', 'BANK_ACCOUNT', 50000000.00, 'VND'),
(2, 1, 'Emergency Fund', 'CASH', 10000000.00, 'VND'),
(3, 2, 'Tech Stocks', 'INVESTMENT', 15000000.00, 'VND');

-- Seed for Integration Database
USE integration_db;
INSERT IGNORE INTO exchange_rates (currency_code, rate_to_vnd, updated_at) VALUES
('USD', 25400.00, NOW()),
('EUR', 27200.00, NOW()),
('JPY', 165.50, NOW());
