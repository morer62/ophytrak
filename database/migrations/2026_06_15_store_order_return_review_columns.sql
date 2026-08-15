-- Store order return review fields for Store + Logistics.
-- Apply after 2026_06_15_store_logistics_statuses.sql.
ALTER TABLE `store_orders`
    ADD COLUMN IF NOT EXISTS `return_notes` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `return_admin_message` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `return_decision_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `return_closed_at` DATETIME NULL;
