-- ============================================================
-- NGOV2 Database Schema
-- Run this in phpMyAdmin → SQL tab
-- Creates all required tables from scratch
-- ============================================================

CREATE DATABASE IF NOT EXISTS ngov2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ngov2;

-- ── Users ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    phone         VARCHAR(15)   DEFAULT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    user_type     ENUM('user','volunteer','admin') NOT NULL DEFAULT 'user',
    status        ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    newsletter    TINYINT(1)    NOT NULL DEFAULT 0,
    last_login    DATETIME      DEFAULT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email      (email),
    INDEX idx_user_type  (user_type),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Login Attempts (rate-limiting) ─────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45)  NOT NULL,
    email      VARCHAR(150) NOT NULL,
    success    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_email (ip_address, email),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Password Resets ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Donations ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS donations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED DEFAULT NULL,
    transaction_id      VARCHAR(50)  NOT NULL UNIQUE,
    donor_name          VARCHAR(100) NOT NULL,
    donor_email         VARCHAR(150) NOT NULL,
    donor_phone         VARCHAR(15)  DEFAULT NULL,
    donor_pan           VARCHAR(10)  DEFAULT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    cause_name          VARCHAR(100) DEFAULT 'General Donation',
    donation_frequency  ENUM('one-time','monthly','yearly') DEFAULT 'one-time',
    payment_method      VARCHAR(50)  DEFAULT 'Paytm',
    payment_status      ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    paytm_order_id      VARCHAR(100) DEFAULT NULL,
    paytm_transaction_id VARCHAR(100) DEFAULT NULL,
    tax_exemption_amount DECIMAL(10,2) DEFAULT 0.00,
    message             TEXT         DEFAULT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id      (user_id),
    INDEX idx_transaction  (transaction_id),
    INDEX idx_status       (payment_status),
    INDEX idx_created      (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Contact Messages ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    phone      VARCHAR(15)  DEFAULT NULL,
    subject    VARCHAR(200) DEFAULT NULL,
    message    TEXT         NOT NULL,
    status     ENUM('new','read','replied') DEFAULT 'new',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status  (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Activity Log ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED DEFAULT NULL,
    action     VARCHAR(100) NOT NULL,
    details    TEXT         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action  (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default Admin Account ──────────────────────────────────
-- Password: Admin@123  (CHANGE THIS IMMEDIATELY after first login)
INSERT IGNORE INTO users (name, email, password_hash, user_type, status)
VALUES (
    'Site Administrator',
    'admin@sadgurubharadwaja.org',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXI5XLgW6',
    'admin',
    'active'
);
