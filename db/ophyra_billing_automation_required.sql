-- Ophyra Billing Automation support.
-- Safe to run more than once.
--
-- This script creates the Stripe event ledger, adds idempotency columns to
-- payments_all, and adds non-destructive billing status fields for users/add-ons.

CREATE TABLE IF NOT EXISTS stripe_billing_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(120) NOT NULL,
    stripe_customer_id VARCHAR(255) NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    stripe_invoice_id VARCHAR(255) NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    stripe_checkout_session_id VARCHAR(255) NULL,
    user_id INT NULL,
    id_owner INT NULL,
    raw_payload LONGTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'received',
    error_message TEXT NULL,
    processing_notes TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY unique_stripe_billing_event (stripe_event_id),
    INDEX idx_stripe_billing_events_type (event_type),
    INDEX idx_stripe_billing_events_status (status),
    INDEX idx_stripe_billing_events_user (user_id),
    INDEX idx_stripe_billing_events_customer (stripe_customer_id),
    INDEX idx_stripe_billing_events_invoice (stripe_invoice_id),
    INDEX idx_stripe_billing_events_payment_intent (stripe_payment_intent_id),
    INDEX idx_stripe_billing_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = (
    SELECT IF(
        COLUMN_TYPE LIKE "%'OphyraAddon'%",
        'SELECT 1',
        "ALTER TABLE payments_all MODIFY COLUMN concept ENUM('Venue','Service','Membership','Event','OphyraAddon') NOT NULL"
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments_all'
      AND COLUMN_NAME = 'concept'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD COLUMN billing_transaction_id VARCHAR(255) NULL AFTER reference', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND COLUMN_NAME = 'billing_transaction_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD COLUMN stripe_event_id VARCHAR(255) NULL AFTER billing_transaction_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND COLUMN_NAME = 'stripe_event_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD COLUMN stripe_invoice_id VARCHAR(255) NULL AFTER stripe_event_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND COLUMN_NAME = 'stripe_invoice_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD COLUMN stripe_payment_intent_id VARCHAR(255) NULL AFTER stripe_invoice_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND COLUMN_NAME = 'stripe_payment_intent_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD COLUMN module_slug VARCHAR(100) NULL AFTER stripe_payment_intent_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND COLUMN_NAME = 'module_slug'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD UNIQUE KEY unique_payments_all_billing_transaction (billing_transaction_id)', 'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND INDEX_NAME = 'unique_payments_all_billing_transaction'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD INDEX idx_payments_all_stripe_invoice (stripe_invoice_id)', 'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND INDEX_NAME = 'idx_payments_all_stripe_invoice'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE payments_all ADD INDEX idx_payments_all_stripe_payment_intent (stripe_payment_intent_id)', 'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_all' AND INDEX_NAME = 'idx_payments_all_stripe_payment_intent'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE users ADD COLUMN billing_status VARCHAR(40) NOT NULL DEFAULT ''current'' AFTER membership_type', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'billing_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE users ADD COLUMN billing_status_updated_at DATETIME NULL AFTER billing_status', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'billing_status_updated_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE users ADD COLUMN billing_failure_count INT NOT NULL DEFAULT 0 AFTER billing_status_updated_at', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'billing_failure_count'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN billing_status VARCHAR(40) NOT NULL DEFAULT ''current'' AFTER status', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'billing_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN last_payment_failed_at DATETIME NULL AFTER billing_status', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'last_payment_failed_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
