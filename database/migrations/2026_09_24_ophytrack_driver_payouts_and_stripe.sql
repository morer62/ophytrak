-- OPHYTRACK delivery-person settlements and auditable Stripe package-fee payments.
-- Review and apply this migration before enabling the end-to-end payout workflow.

ALTER TABLE `ophytrack_package_charges`
  ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(30) NULL AFTER `payment_reference`,
  ADD COLUMN IF NOT EXISTS `stripe_payment_intent_id` VARCHAR(190) NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `paid_by_user_id` INT UNSIGNED NULL AFTER `stripe_payment_intent_id`;

CREATE INDEX IF NOT EXISTS `idx_ophytrack_charge_stripe_intent`
  ON `ophytrack_package_charges` (`stripe_payment_intent_id`);

CREATE TABLE IF NOT EXISTS `ophytrack_driver_payouts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_store_package` BIGINT UNSIGNED NOT NULL,
  `id_store_order` INT NOT NULL,
  `id_store_package_event` BIGINT UNSIGNED NOT NULL,
  `seller_owner_id` INT NOT NULL,
  `carrier_owner_id` INT NULL,
  `payer_owner_id` INT NOT NULL,
  `delivery_user_id` INT UNSIGNED NOT NULL,
  `amount_brl` DECIMAL(12,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
  `status` ENUM('PENDING','PROOF_SUBMITTED','ACCEPTED','REJECTED','VOID') NOT NULL DEFAULT 'PENDING',
  `payment_proof_url` VARCHAR(500) NULL,
  `payment_reference` VARCHAR(180) NULL,
  `payment_method` VARCHAR(30) NULL,
  `proof_submitted_by_user_id` INT UNSIGNED NULL,
  `proof_submitted_at` DATETIME NULL,
  `reviewed_by_user_id` INT UNSIGNED NULL,
  `review_notes` VARCHAR(500) NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ophytrack_driver_payout_event` (`id_store_package_event`),
  KEY `idx_ophytrack_driver_payout_payer` (`payer_owner_id`,`status`,`created_at`),
  KEY `idx_ophytrack_driver_payout_user` (`delivery_user_id`,`status`,`created_at`),
  KEY `idx_ophytrack_driver_payout_package` (`id_store_package`,`created_at`),
  CONSTRAINT `fk_ophytrack_driver_payout_package`
    FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ophytrack_driver_payout_event`
    FOREIGN KEY (`id_store_package_event`) REFERENCES `store_package_events` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification: both values must be 1 after applying the migration.
SELECT
  (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema=DATABASE() AND table_name='ophytrack_driver_payouts') AS driver_payouts_ok,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name='ophytrack_package_charges'
     AND column_name='stripe_payment_intent_id') AS stripe_payment_reference_ok;
