-- ============================================================
-- Migration: Add Ongole Annadanam cause
-- Run in phpMyAdmin SQL tab on your production database
-- ============================================================

ALTER TABLE `donations`
  MODIFY COLUMN `cause`
    ENUM('general-fund','shirdi-annadanam','ganagapur-annadanam','ongole-annadanam','corpus-fund')
    NOT NULL DEFAULT 'general-fund';

-- Update donation_summary view to reflect new enum
DROP VIEW IF EXISTS `donation_summary`;

CREATE VIEW `donation_summary` AS
  SELECT
    CAST(`created_at` AS DATE)        AS `date`,
    `cause`,
    COUNT(0)                           AS `count`,
    SUM(`amount`)                      AS `total_amount`,
    AVG(`amount`)                      AS `avg_amount`
  FROM `donations`
  WHERE `payment_status` = 'completed'
  GROUP BY CAST(`created_at` AS DATE), `cause`;

-- Verify
SHOW COLUMNS FROM `donations` LIKE 'cause';
