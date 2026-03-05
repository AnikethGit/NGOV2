-- NGO Phase 2 - Core Database Schema
-- Run this once to create all required tables

CREATE DATABASE IF NOT EXISTS u701659873_ngo_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE u701659873_ngo_management;

-- 1. Users Table (Donors, Volunteers, Admins)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_type ENUM('admin', 'volunteer', 'donor') NOT NULL DEFAULT 'donor',
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    
    -- Indian Identity Documents
    pan_number VARCHAR(10),
    aadhar_number VARCHAR(12),
    
    -- Account Status
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    -- Security
    password_reset_token VARCHAR(255),
    password_reset_expires DATETIME,
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME,
    
    -- Preferences
    anonymous_donations BOOLEAN DEFAULT FALSE,
    newsletter_subscribed BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_status (status)
);

-- 2. Donations Table (Core Donation Processing)
CREATE TABLE donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL, -- Null for guest donations
    
    -- Transaction Details
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    phonepe_transaction_id VARCHAR(100),
    
    -- Donor Information
    donor_name VARCHAR(255) NOT NULL,
    donor_email VARCHAR(255) NOT NULL,
    donor_phone VARCHAR(15),
    donor_pan VARCHAR(10),
    donor_address TEXT,
    
    -- Donation Details
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    cause ENUM('general', 'poor-feeding', 'education', 'medical', 'disaster') NOT NULL,
    frequency ENUM('one-time', 'monthly', 'yearly') DEFAULT 'one-time',
    
    -- Payment Information
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_gateway_response JSON,
    
    -- Tax & Receipt
    tax_exemption_amount DECIMAL(10,2),
    receipt_number VARCHAR(50),
    receipt_issued_at DATETIME,
    
    -- Flags
    is_anonymous BOOLEAN DEFAULT FALSE,
    is_recurring BOOLEAN DEFAULT FALSE,
    
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_cause (cause),
    INDEX idx_donor_email (donor_email),
    INDEX idx_created_at (created_at)
);

-- 3. Volunteers Table (Extended Volunteer Management)
CREATE TABLE volunteers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    
    -- Volunteer Details
    skills TEXT,
    experience TEXT,
    availability ENUM('weekends', 'weekdays', 'flexible', 'limited') DEFAULT 'flexible',
    emergency_contact VARCHAR(255),
    
    -- Status & Verification
    volunteer_status ENUM('active', 'inactive', 'on_hold') DEFAULT 'active',
    background_check_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    background_check_date DATE,
    
    -- Statistics
    total_hours DECIMAL(8,2) DEFAULT 0.00,
    total_events INT DEFAULT 0,
    total_collections DECIMAL(10,2) DEFAULT 0.00,
    volunteer_since DATE,
    last_activity_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_volunteer_status (volunteer_status)
);

-- 4. Volunteer Collections (Physical Donation Tracking)
CREATE TABLE volunteer_collections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    volunteer_id INT NOT NULL,
    
    -- Collection Details
    collection_date DATE NOT NULL,
    collection_type ENUM('cash', 'goods', 'food', 'clothes', 'books', 'medical', 'other') NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00, -- For cash collections
    description TEXT,
    location VARCHAR(255),
    
    -- Receipt Management
    receipt_image VARCHAR(255), -- Path to uploaded receipt image
    receipt_number VARCHAR(100),
    
    -- Verification
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT NULL, -- Admin who verified
    verified_at DATETIME NULL,
    verification_notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_volunteer_id (volunteer_id),
    INDEX idx_collection_date (collection_date),
    INDEX idx_status (status)
);

-- 5. Contact Messages Table
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    
    -- Status
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    
    -- Admin Response
    admin_response TEXT,
    responded_by INT NULL,
    responded_at DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 6. Activity Logs (User Activity Tracking)
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Request Details
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    -- Additional Data
    entity_type VARCHAR(50), -- donation, user, volunteer, etc.
    entity_id INT,
    additional_data JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- 7. Email Notifications Queue
CREATE TABLE email_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    
    -- Template Info
    template_name VARCHAR(100),
    template_data JSON,
    
    -- Status
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    
    -- Scheduling
    scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME,
    attempts INT DEFAULT 0,
    last_error TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
);

-- 8. User Sessions (Security)
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- 9. CSRF Tokens (Security)
CREATE TABLE csrf_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(64) UNIQUE NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    expires_at DATETIME NOT NULL,
    used_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
);

-- 10. File Uploads (Secure File Management)
CREATE TABLE file_uploads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    
    -- Context
    upload_context VARCHAR(100), -- 'receipt', 'profile', 'collection', etc.
    context_id INT, -- Related record ID
    
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_context (upload_context, context_id)
);

-- Insert Default Admin User
-- Password: admin123 (CHANGE THIS IMMEDIATELY)
INSERT INTO users (user_type, email, password_hash, full_name, status, email_verified) VALUES
('admin', 'admin@sridutt asevaorg.org', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj6LKjdqm4Vq', 'System Administrator', 'active', 1);

-- Create Database Views for Quick Access
CREATE VIEW donation_summary AS
SELECT 
    DATE(created_at) as date,
    cause,
    COUNT(*) as count,
    SUM(amount) as total_amount,
    AVG(amount) as avg_amount
FROM donations 
WHERE payment_status = 'completed'
GROUP BY DATE(created_at), cause;

CREATE VIEW volunteer_stats AS
SELECT 
    v.id,
    u.full_name,
    u.email,
    v.total_hours,
    v.total_collections,
    COUNT(vc.id) as total_collection_entries,
    v.volunteer_since,
    v.last_activity_date
FROM volunteers v
JOIN users u ON v.user_id = u.id
LEFT JOIN volunteer_collections vc ON v.id = vc.volunteer_id
GROUP BY v.id;
