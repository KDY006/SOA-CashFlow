-- =============================================================================
-- Auth Service Database Initialization Script (MySQL 8.0)
-- Bảng: users, refresh_tokens
-- =============================================================================
CREATE DATABASE IF NOT EXISTS auth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auth_db;

SET NAMES utf8mb4;

-- 1. Bảng người dùng (Users)
CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone_number VARCHAR(20),
    role VARCHAR(20) NOT NULL DEFAULT 'USER',
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng Refresh Tokens
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expiry_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA MẪU CHO AUTH SERVICE (Khớp với thuật toán PBKDF2 của PasswordUtil)
-- Mật khẩu mặc định của admin & member1 là: password123
-- =============================================================================
INSERT INTO users (id, username, email, password_hash, full_name, phone_number, role, status) VALUES
(1, 'admin', 'admin@cashflow.local', '210000:aYagp9baQD0LZ9O/sVLxPQ==:3+Pmnd0JLerckR8Jj1a2iHsMw/3xz4TllUi4Z5NsU9g=', 'System Administrator', '0901234567', 'ADMIN', 'ACTIVE'),
(2, 'member1', 'member1@cashflow.local', '210000:aYagp9baQD0LZ9O/sVLxPQ==:3+Pmnd0JLerckR8Jj1a2iHsMw/3xz4TllUi4Z5NsU9g=', 'Nguyen Van A', '0912345678', 'USER', 'ACTIVE')
ON DUPLICATE KEY UPDATE username=VALUES(username);