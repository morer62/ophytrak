-- Auditable carrier adjustments applied to a delivery person's earnings.
-- Positive credits and debits remain separate from the base per-package payout.

CREATE TABLE IF NOT EXISTS `ophytrack_driver_earnings_adjustments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_owner_id` INT NOT NULL,
  `delivery_user_id` INT UNSIGNED NOT NULL,
  `adjustment_type` ENUM('CREDIT','DEBIT') NOT NULL,
  `amount_brl` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `effective_at` DATETIME NOT NULL,
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_driver_earnings_adjustment_period` (`delivery_user_id`,`effective_at`,`adjustment_type`),
  KEY `idx_driver_earnings_adjustment_carrier` (`carrier_owner_id`,`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) AS earnings_adjustments FROM `ophytrack_driver_earnings_adjustments`;
