-- ============================================================
-- Migration: Add receipt_number column to donations table
-- Run once in phpMyAdmin SQL tab on your ngov2 / u701659873_ngo_management database
-- Safe to run: uses IF NOT EXISTS
-- ============================================================

ALTER TABLE `donations`
    ADD COLUMN IF NOT EXISTS `receipt_number` VARCHAR(30) DEFAULT NULL
        COMMENT 'Format: SDSMBT-YYYYMMDD-XXXXXX — set by ReceiptService after payment'
        AFTER `transaction_id`;

CREATE INDEX IF NOT EXISTS `idx_donations_receipt_number`
    ON `donations` (`receipt_number`);

-- Verify
SHOW COLUMNS FROM `donations` LIKE 'receipt_number';
