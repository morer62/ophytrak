ALTER TABLE `store_order_workflow`
  ADD COLUMN IF NOT EXISTS `delivery_attempt_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `delivery_notes`,
  ADD COLUMN IF NOT EXISTS `delivery_occurrence_reason` VARCHAR(60) NULL AFTER `delivery_attempt_count`,
  ADD COLUMN IF NOT EXISTS `delivery_occurrence_photo_1` VARCHAR(500) NULL AFTER `delivery_occurrence_reason`,
  ADD COLUMN IF NOT EXISTS `delivery_occurrence_photo_2` VARCHAR(500) NULL AFTER `delivery_occurrence_photo_1`,
  ADD COLUMN IF NOT EXISTS `delivery_occurrence_at` DATETIME NULL AFTER `delivery_occurrence_photo_2`;
