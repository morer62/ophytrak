-- Seller-to-carrier associations for OPHYTRACK. Organizations remain independent tenants.
CREATE TABLE IF NOT EXISTS `store_carrier_relationships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_owner_id` INT NOT NULL,
  `carrier_owner_id` INT NOT NULL,
  `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_user_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_carrier_relationship` (`seller_owner_id`,`carrier_owner_id`),
  KEY `idx_store_carrier_seller_status` (`seller_owner_id`,`status`),
  KEY `idx_store_carrier_carrier_status` (`carrier_owner_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
