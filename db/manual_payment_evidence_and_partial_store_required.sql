ALTER TABLE `orders_advances`
  ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) NULL AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `payment_reference` VARCHAR(190) NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `proof_url` VARCHAR(500) NULL AFTER `payment_reference`,
  ADD COLUMN IF NOT EXISTS `created_by` INT NULL AFTER `note`;

ALTER TABLE `store_payments`
  MODIFY COLUMN `payment_type` ENUM('FULL','PARTIAL','SUBSCRIPTION_INITIAL','RECOVERY') NULL DEFAULT 'FULL';
