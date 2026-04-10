-- ================================================
-- NGOV2 DB Migration: Add Paytm payment columns
-- Run this in phpMyAdmin -> SQL tab
-- Safe to run on existing database with data
-- ================================================

ALTER TABLE donations
    ADD COLUMN IF NOT EXISTS paytm_order_id       VARCHAR(50)  DEFAULT NULL AFTER transaction_id,
    ADD COLUMN IF NOT EXISTS paytm_transaction_id VARCHAR(50)  DEFAULT NULL AFTER paytm_order_id,
    ADD COLUMN IF NOT EXISTS payment_mode         VARCHAR(30)  DEFAULT NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS bank_txn_id          VARCHAR(50)  DEFAULT NULL AFTER payment_mode,
    ADD COLUMN IF NOT EXISTS paytm_response_code  VARCHAR(10)  DEFAULT NULL AFTER bank_txn_id,
    ADD COLUMN IF NOT EXISTS paytm_response_msg   VARCHAR(255) DEFAULT NULL AFTER paytm_response_code,
    ADD COLUMN IF NOT EXISTS updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_donations_paytm_txn ON donations(paytm_transaction_id);
CREATE INDEX IF NOT EXISTS idx_donations_updated   ON donations(updated_at);

-- Verify result
DESCRIBE donations;
