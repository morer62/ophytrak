-- Ophyra module checkout hardening.
-- Safe to run more than once.
--
-- Purpose:
-- 1. Keep paid modules locked until confirmed payment.
-- 2. Preserve manual Level 1 activations as a distinct source.
-- 3. Give support a non-destructive audit query for suspicious active modules.

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN activation_source VARCHAR(40) NULL AFTER billing_status', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'activation_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN activated_by_admin_id INT NULL AFTER activation_source', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'activated_by_admin_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN activation_reason TEXT NULL AFTER activated_by_admin_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'activation_reason'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN current_period_start DATE NULL AFTER started_at', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'current_period_start'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE user_modules ADD COLUMN last_payment_id INT NULL AFTER renewal_at', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_modules' AND COLUMN_NAME = 'last_payment_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Non-destructive audit query. Review before changing any data.
SELECT
    um.id,
    um.id_user,
    u.email,
    um.module_slug,
    um.status,
    um.billing_status,
    um.activation_source,
    um.started_at,
    um.renewal_at
FROM user_modules um
INNER JOIN users u ON u.id = um.id_user
LEFT JOIN payments_all pa
    ON pa.user_id = um.id_user
   AND pa.concept = 'OphyraAddon'
   AND pa.module_slug = um.module_slug
   AND pa.status = 'ACTIVE'
   AND (
        pa.billing_transaction_id IS NOT NULL
        OR pa.stripe_event_id IS NOT NULL
        OR pa.stripe_invoice_id IS NOT NULL
        OR pa.stripe_payment_intent_id IS NOT NULL
        OR pa.provider_type = 'manual'
   )
WHERE um.status = 'ACTIVE'
  AND um.module_slug IN ('services','store_delivery_tracking','inventory_storage','ai_advisor','tickets_rsvp','marketplace_connectors')
  AND COALESCE(um.activation_source, '') <> 'manual'
  AND pa.id IS NULL
ORDER BY um.updated_at DESC, um.id DESC;
