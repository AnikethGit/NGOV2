-- =====================================================
-- NGOV2 Fresh Database Setup Script
-- WARNING: Drops all existing tables and data!
-- Run in phpMyAdmin SQL tab
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Users Table
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    pan_number VARCHAR(10),
    address TEXT,
    profile_image VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(64),
    reset_token VARCHAR(64),
    reset_token_expires DATETIME,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Donations Table
-- =====================================================
CREATE TABLE donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    transaction_id VARCHAR(50) NOT NULL UNIQUE,
    donor_name VARCHAR(100) NOT NULL,
    donor_email VARCHAR(255) NOT NULL,
    donor_phone VARCHAR(20),
    donor_pan VARCHAR(10),
    donor_address TEXT,
    amount DECIMAL(10,2) NOT NULL,
    cause VARCHAR(50) NOT NULL,
    frequency ENUM('one-time', 'monthly', 'quarterly', 'yearly') DEFAULT 'one-time',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_gateway_response TEXT,
    is_anonymous TINYINT(1) DEFAULT 0,
    is_recurring TINYINT(1) DEFAULT 0,
    tax_exemption_amount DECIMAL(10,2),
    certificate_issued TINYINT(1) DEFAULT 0,
    certificate_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Campaigns Table
-- =====================================================
CREATE TABLE campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    image_url VARCHAR(255),
    goal_amount DECIMAL(10,2) NOT NULL,
    raised_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'active',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    category VARCHAR(50),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Contact Messages Table
-- =====================================================
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    admin_notes TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Activity Logs Table
-- =====================================================
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Performance Indexes
-- =====================================================

-- Users indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_created ON users(created_at);

-- Donations indexes
CREATE INDEX idx_donations_user ON donations(user_id);
CREATE INDEX idx_donations_transaction ON donations(transaction_id);
CREATE INDEX idx_donations_status ON donations(payment_status);
CREATE INDEX idx_donations_date ON donations(created_at);
CREATE INDEX idx_donations_cause ON donations(cause);

-- Campaigns indexes
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_campaigns_dates ON campaigns(start_date, end_date);
CREATE INDEX idx_campaigns_slug ON campaigns(slug);

-- Contact messages indexes
CREATE INDEX idx_contact_status ON contact_messages(status);
CREATE INDEX idx_contact_created ON contact_messages(created_at);

-- Activity logs indexes
CREATE INDEX idx_logs_user ON activity_logs(user_id);
CREATE INDEX idx_logs_created ON activity_logs(created_at);

-- =====================================================
-- Sample Data (Optional - for testing)
-- =====================================================

-- Insert sample admin user
-- Password: ------ (hashed with bcrypt)
INSERT INTO users (email, password_hash, full_name, role, status, email_verified) 
VALUES (
    'admin@ngov2.org',
    '------',
    'Admin User',
    'admin',
    'active',
    1
);

-- Insert sample campaign
INSERT INTO campaigns (title, slug, description, goal_amount, raised_amount, status, start_date, end_date, category, created_by)
VALUES (
    'Education for Underprivileged Children',
    'education-2024',
    'Help us provide quality education and learning materials to children from low-income families.',
    500000.00,
    125000.00,
    'active',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 90 DAY),
    'education',
    1
);

-- =====================================================
-- Verification Queries
-- =====================================================

-- Check tables created
SHOW TABLES;

-- Check users table structure
DESCRIBE users;

-- Check donations table structure
DESCRIBE donations;

-- Verify indexes
SHOW INDEX FROM users;
SHOW INDEX FROM donations;
SHOW INDEX FROM campaigns;

-- Check sample data
SELECT * FROM users;
SELECT * FROM campaigns;
