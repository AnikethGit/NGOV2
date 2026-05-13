-- ============================================================
-- Migration: Add donor_address column to donations table
-- Run once in phpMyAdmin SQL tab on your ngov2 database
-- Safe to run: uses IF NOT EXISTS
-- Note: api/donations.php already collects and inserts this
--       field; this migration makes the column persistent.
-- ============================================================

ALTER TABLE `donations`
    ADD COLUMN IF NOT EXISTS `donor_address` TEXT DEFAULT NULL
        COMMENT 'Full postal address supplied on the donation form'
        AFTER `donor_pan`;

-- Verify
SHOW COLUMNS FROM `donations` LIKE 'donor_address';
