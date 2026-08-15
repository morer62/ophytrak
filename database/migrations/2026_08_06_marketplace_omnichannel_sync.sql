-- Ophyra omnichannel commerce upgrade (MariaDB 10.3+).
-- Idempotent and compatible with the production dump from 2026-08-06.

ALTER TABLE `marketplace_connectors`
  ADD COLUMN IF NOT EXISTS `external_shop_id` VARCHAR(160) NULL AFTER `account_id`,
  ADD COLUMN IF NOT EXISTS `shop_cipher` VARCHAR(255) NULL AFTER `external_shop_id`,
  ADD COLUMN IF NOT EXISTS `country_code` CHAR(2) NULL AFTER `shop_cipher`,
  ADD COLUMN IF NOT EXISTS `currency_code` CHAR(3) NULL AFTER `country_code`,
  ADD COLUMN IF NOT EXISTS `granted_scopes` TEXT NULL AFTER `currency_code`,
  ADD COLUMN IF NOT EXISTS `authorization_expires_at` DATETIME NULL AFTER `token_expires_at`,
  ADD COLUMN IF NOT EXISTS `last_products_sync_at` DATETIME NULL AFTER `last_sync_at`,
  ADD COLUMN IF NOT EXISTS `last_orders_sync_at` DATETIME NULL AFTER `last_products_sync_at`,
  ADD COLUMN IF NOT EXISTS `next_sync_at` DATETIME NULL AFTER `last_orders_sync_at`,
  ADD COLUMN IF NOT EXISTS `sync_products` TINYINT(1) NOT NULL DEFAULT 1 AFTER `next_sync_at`,
  ADD COLUMN IF NOT EXISTS `sync_orders` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sync_products`,
  ADD COLUMN IF NOT EXISTS `sync_inventory` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sync_orders`,
  ADD COLUMN IF NOT EXISTS `inventory_source` ENUM('MARKETPLACE','OPHYRA','MANUAL') NOT NULL DEFAULT 'MARKETPLACE' AFTER `sync_inventory`,
  ADD COLUMN IF NOT EXISTS `price_source` ENUM('MARKETPLACE','OPHYRA','MANUAL') NOT NULL DEFAULT 'MARKETPLACE' AFTER `inventory_source`,
  ADD COLUMN IF NOT EXISTS `settings_json` LONGTEXT NULL AFTER `price_source`;

ALTER TABLE `store_products`
  ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(100) NULL AFTER `sku`,
  ADD COLUMN IF NOT EXISTS `brand_name` VARCHAR(160) NULL AFTER `barcode`,
  ADD COLUMN IF NOT EXISTS `condition_code` VARCHAR(30) NULL AFTER `brand_name`,
  ADD COLUMN IF NOT EXISTS `currency_code` CHAR(3) NOT NULL DEFAULT 'USD' AFTER `promo_price`,
  ADD COLUMN IF NOT EXISTS `weight_grams` INT UNSIGNED NULL AFTER `gallery`,
  ADD COLUMN IF NOT EXISTS `length_cm` DECIMAL(10,2) NULL AFTER `weight_grams`,
  ADD COLUMN IF NOT EXISTS `width_cm` DECIMAL(10,2) NULL AFTER `length_cm`,
  ADD COLUMN IF NOT EXISTS `height_cm` DECIMAL(10,2) NULL AFTER `width_cm`,
  ADD COLUMN IF NOT EXISTS `marketplace_managed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `marketplace_updated_at` DATETIME NULL AFTER `marketplace_managed`;

ALTER TABLE `store_product_variations`
  ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(100) NULL AFTER `sku`,
  ADD COLUMN IF NOT EXISTS `currency_code` CHAR(3) NOT NULL DEFAULT 'USD' AFTER `promo_price`,
  ADD COLUMN IF NOT EXISTS `marketplace_updated_at` DATETIME NULL AFTER `status`;

ALTER TABLE `store_orders`
  ADD COLUMN IF NOT EXISTS `order_source` VARCHAR(40) NOT NULL DEFAULT 'OPHYRA' AFTER `public_token`,
  ADD COLUMN IF NOT EXISTS `external_order_id` VARCHAR(160) NULL AFTER `order_source`,
  ADD COLUMN IF NOT EXISTS `external_pack_id` VARCHAR(160) NULL AFTER `external_order_id`,
  ADD COLUMN IF NOT EXISTS `external_shipment_id` VARCHAR(160) NULL AFTER `external_pack_id`,
  ADD COLUMN IF NOT EXISTS `external_shop_id` VARCHAR(160) NULL AFTER `external_shipment_id`,
  ADD COLUMN IF NOT EXISTS `marketplace_updated_at` DATETIME NULL AFTER `external_shop_id`;

ALTER TABLE `store_order_items`
  ADD COLUMN IF NOT EXISTS `external_item_id` VARCHAR(160) NULL AFTER `id_product_variation`,
  ADD COLUMN IF NOT EXISTS `external_variant_id` VARCHAR(160) NULL AFTER `external_item_id`,
  ADD COLUMN IF NOT EXISTS `seller_sku_snapshot` VARCHAR(160) NULL AFTER `external_variant_id`,
  ADD COLUMN IF NOT EXISTS `marketplace_fee` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `line_total`;

CREATE TABLE IF NOT EXISTS `marketplace_resource_mappings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `connector_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(40) NOT NULL,
  `resource_type` ENUM('PRODUCT','VARIANT','ORDER','ORDER_ITEM','PAYMENT','SHIPMENT','PACKAGE','CUSTOMER') NOT NULL,
  `external_id` VARCHAR(190) NOT NULL,
  `external_parent_id` VARCHAR(190) NULL,
  `internal_table` VARCHAR(80) NOT NULL,
  `internal_id` BIGINT UNSIGNED NOT NULL,
  `external_sku` VARCHAR(160) NULL,
  `external_status` VARCHAR(100) NULL,
  `checksum` CHAR(64) NULL,
  `raw_payload` LONGTEXT NULL,
  `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketplace_external_resource` (`id_owner`,`provider`,`resource_type`,`external_id`),
  KEY `idx_marketplace_internal_resource` (`id_owner`,`internal_table`,`internal_id`),
  KEY `idx_marketplace_sku` (`id_owner`,`external_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marketplace_webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(40) NOT NULL,
  `external_event_id` VARCHAR(190) NOT NULL,
  `external_shop_id` VARCHAR(190) NULL,
  `topic` VARCHAR(120) NOT NULL,
  `signature_valid` TINYINT(1) NOT NULL DEFAULT 0,
  `headers_json` LONGTEXT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `status` ENUM('RECEIVED','PROCESSING','PROCESSED','RETRY','FAILED','IGNORED') NOT NULL DEFAULT 'RECEIVED',
  `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketplace_webhook_event` (`provider`,`external_event_id`),
  KEY `idx_marketplace_webhook_queue` (`status`,`available_at`),
  KEY `idx_marketplace_webhook_shop` (`provider`,`external_shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marketplace_sync_cursors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `connector_id` BIGINT UNSIGNED NOT NULL,
  `resource_type` VARCHAR(40) NOT NULL,
  `cursor_value` TEXT NULL,
  `last_external_updated_at` DATETIME NULL,
  `last_success_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketplace_sync_cursor` (`connector_id`,`resource_type`),
  KEY `idx_marketplace_cursor_owner` (`id_owner`,`connector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marketplace_sync_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `connector_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(40) NOT NULL,
  `job_type` ENUM('INITIAL','MANUAL','SCHEDULED','WEBHOOK','TOKEN_REFRESH','RETRY') NOT NULL,
  `resource_type` ENUM('ALL','PRODUCTS','ORDERS','INVENTORY','EVENT') NOT NULL DEFAULT 'ALL',
  `status` ENUM('PENDING','RUNNING','SUCCESS','PARTIAL','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `requested_by` INT UNSIGNED NULL,
  `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `products_read` INT UNSIGNED NOT NULL DEFAULT 0,
  `products_written` INT UNSIGNED NOT NULL DEFAULT 0,
  `orders_read` INT UNSIGNED NOT NULL DEFAULT 0,
  `orders_written` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `summary_json` LONGTEXT NULL,
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_jobs_queue` (`status`,`available_at`),
  KEY `idx_marketplace_jobs_connector` (`connector_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marketplace_sync_conflicts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `connector_id` BIGINT UNSIGNED NOT NULL,
  `resource_type` VARCHAR(40) NOT NULL,
  `external_id` VARCHAR(190) NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `ophyra_value` TEXT NULL,
  `marketplace_value` TEXT NULL,
  `resolution` ENUM('PENDING','USE_OPHYRA','USE_MARKETPLACE','MERGED','IGNORED') NOT NULL DEFAULT 'PENDING',
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_conflicts_owner` (`id_owner`,`resolution`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT
 (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='marketplace_resource_mappings') mappings_ok,
 (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='marketplace_webhook_events') webhooks_ok,
 (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='marketplace_sync_jobs') jobs_ok,
 (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='store_products' AND column_name='marketplace_managed') products_ok,
 (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='store_orders' AND column_name='external_order_id') orders_ok;
