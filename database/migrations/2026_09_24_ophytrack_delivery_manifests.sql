-- OPHYTRACK carrier dispatch manifests and ordered delivery routes.
-- Apply after the carrier custody workflow migrations.

CREATE TABLE IF NOT EXISTS `store_delivery_manifests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_owner_id` INT NOT NULL,
  `delivery_user_id` INT UNSIGNED NOT NULL,
  `manifest_code` VARCHAR(40) NOT NULL,
  `status` ENUM('DRAFT','GENERATED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `package_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `route_strategy` VARCHAR(30) NOT NULL DEFAULT 'POSTAL_CODE',
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `generated_at` DATETIME NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_delivery_manifest_code` (`manifest_code`),
  KEY `idx_store_delivery_manifest_driver` (`carrier_owner_id`,`delivery_user_id`,`status`,`created_at`),
  KEY `idx_store_delivery_manifest_status` (`carrier_owner_id`,`status`,`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_delivery_manifest_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_manifest` BIGINT UNSIGNED NOT NULL,
  `id_store_package` BIGINT UNSIGNED NOT NULL,
  `stop_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('PENDING','DELIVERED','FAILED','RETURNED','REMOVED') NOT NULL DEFAULT 'PENDING',
  `failure_code` VARCHAR(50) NULL,
  `failure_notes` VARCHAR(500) NULL,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_delivery_manifest_package` (`id_manifest`,`id_store_package`),
  KEY `idx_store_delivery_manifest_items_route` (`id_manifest`,`status`,`stop_sequence`),
  KEY `idx_store_delivery_manifest_items_package` (`id_store_package`,`status`,`created_at`),
  CONSTRAINT `fk_store_delivery_manifest_item_manifest`
    FOREIGN KEY (`id_manifest`) REFERENCES `store_delivery_manifests` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_store_delivery_manifest_item_package`
    FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification without information_schema permissions.
SELECT COUNT(*) AS manifests FROM `store_delivery_manifests`;
SELECT COUNT(*) AS manifest_items FROM `store_delivery_manifest_items`;
