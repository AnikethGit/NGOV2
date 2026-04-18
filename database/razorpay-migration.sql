-- ============================================================
-- Razorpay Migration
-- Run once in phpMyAdmin SQL tab on your ngov2 database
-- Safe to run: uses IF NOT EXISTS / ignore-duplicate pattern
-- Paytm columns are untouched.
-- ============================================================

-- 1. Add payment_gateway column (tracks which gateway processed the payment)
ALTER TABLE donations
    ADD COLUMN IF NOT EXISTS payment_gateway VARCHAR(20) DEFAULT 'paytm'
        COMMENT 'paytm | razorpay';

-- 2. Add Razorpay-specific columns
ALTER TABLE donations
    ADD COLUMN IF NOT EXISTS razorpay_order_id   VARCHAR(100) DEFAULT NULL
        COMMENT 'Razorpay order ID (order_XXXX)',
    ADD COLUMN IF NOT EXISTS razorpay_payment_id VARCHAR(100) DEFAULT NULL
        COMMENT 'Razorpay payment ID (pay_XXXX)';

-- 3. Index for fast lookups
CREATE INDEX IF NOT EXISTS idx_donations_rzp_order
    ON donations(razorpay_order_id);

CREATE INDEX IF NOT EXISTS idx_donations_rzp_payment
    ON donations(razorpay_payment_id);

CREATE INDEX IF NOT EXISTS idx_donations_gateway
    ON donations(payment_gateway);

-- ============================================================
-- Verify
-- ============================================================
SHOW COLUMNS FROM donations LIKE 'razorpay%';
SHOW COLUMNS FROM donations LIKE 'payment_gateway';
