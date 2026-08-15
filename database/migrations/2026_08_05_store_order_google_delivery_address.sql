-- Structured Google Places delivery address data for Store + Logistics.
ALTER TABLE `store_orders`
    ADD COLUMN IF NOT EXISTS `shipping_country` VARCHAR(2) NULL AFTER `shipping_zip`,
    ADD COLUMN IF NOT EXISTS `shipping_place_id` VARCHAR(255) NULL AFTER `shipping_country`,
    ADD COLUMN IF NOT EXISTS `shipping_latitude` DECIMAL(10,7) NULL AFTER `shipping_place_id`,
    ADD COLUMN IF NOT EXISTS `shipping_longitude` DECIMAL(10,7) NULL AFTER `shipping_latitude`,
    ADD COLUMN IF NOT EXISTS `shipping_instructions` TEXT NULL AFTER `shipping_longitude`;

