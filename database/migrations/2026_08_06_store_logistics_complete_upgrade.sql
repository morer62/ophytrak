-- Ophyra Store + Logistics: cumulative upgrade for production dumps that do
-- not yet contain physical package tracking or carrier custody.
-- Safe to run more than once on MariaDB 10.3+.

CREATE TABLE IF NOT EXISTS `store_packages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `id_store_order` INT NOT NULL,
  `current_custodian_owner_id` INT NULL,
  `current_custodian_user_id` INT UNSIGNED NULL,
  `custody_status` VARCHAR(50) NOT NULL DEFAULT 'WITH_SELLER',
  `logistics_mode` ENUM('INTERNAL','EXTERNAL_CARRIER') NOT NULL DEFAULT 'INTERNAL',
  `custody_started_at` DATETIME NULL,
  `package_sequence` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `package_code` VARCHAR(80) NOT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'CREATED',
  `current_location_label` VARCHAR(190) NULL,
  `last_event_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_packages_code` (`package_code`),
  UNIQUE KEY `uq_store_packages_order_sequence` (`id_owner`, `id_store_order`, `package_sequence`),
  KEY `idx_store_packages_owner_status` (`id_owner`, `current_status`),
  KEY `idx_store_packages_order` (`id_store_order`),
  KEY `idx_store_packages_custodian` (`current_custodian_owner_id`, `custody_status`),
  KEY `idx_store_packages_custodian_user` (`current_custodian_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- These statements repair a partially-created store_packages table too.
ALTER TABLE `store_packages`
  ADD COLUMN IF NOT EXISTS `current_custodian_owner_id` INT NULL AFTER `id_store_order`,
  ADD COLUMN IF NOT EXISTS `current_custodian_user_id` INT UNSIGNED NULL AFTER `current_custodian_owner_id`,
  ADD COLUMN IF NOT EXISTS `custody_status` VARCHAR(50) NOT NULL DEFAULT 'WITH_SELLER' AFTER `current_custodian_user_id`,
  ADD COLUMN IF NOT EXISTS `logistics_mode` ENUM('INTERNAL','EXTERNAL_CARRIER') NOT NULL DEFAULT 'INTERNAL' AFTER `custody_status`,
  ADD COLUMN IF NOT EXISTS `custody_started_at` DATETIME NULL AFTER `logistics_mode`;

CREATE TABLE IF NOT EXISTS `store_package_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `id_store_package` BIGINT UNSIGNED NOT NULL,
  `id_store_order` INT NOT NULL,
  `id_user` INT UNSIGNED NULL,
  `event_type` VARCHAR(60) NOT NULL,
  `status_from` VARCHAR(50) NULL,
  `status_to` VARCHAR(50) NULL,
  `location_label` VARCHAR(190) NULL,
  `notes` TEXT NULL,
  `metadata_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_store_package_events_package_date` (`id_store_package`, `created_at`, `id`),
  KEY `idx_store_package_events_owner_order` (`id_owner`, `id_store_order`),
  KEY `idx_store_package_events_user` (`id_user`),
  CONSTRAINT `fk_store_package_events_package`
    FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_package_custody_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_store_package` BIGINT UNSIGNED NOT NULL,
  `seller_owner_id` INT NOT NULL,
  `carrier_owner_id` INT NOT NULL,
  `requested_by_user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `request_method` ENUM('MANUAL_CODE','SELLER_ASSIGNMENT') NOT NULL DEFAULT 'MANUAL_CODE',
  `request_notes` VARCHAR(500) NULL,
  `decided_by_user_id` INT UNSIGNED NULL,
  `decided_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_custody_requests_seller_status` (`seller_owner_id`, `status`),
  KEY `idx_custody_requests_carrier_status` (`carrier_owner_id`, `status`),
  KEY `idx_custody_requests_package` (`id_store_package`),
  CONSTRAINT `fk_custody_request_package`
    FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_package_carrier_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_store_package` BIGINT UNSIGNED NOT NULL,
  `carrier_owner_id` INT NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `assigned_by_user_id` INT UNSIGNED NULL,
  `assignment_role` ENUM('PICKUP','HUB_RECEIVING','SORTING','DELIVERY','SUPERVISOR') NOT NULL DEFAULT 'PICKUP',
  `status` ENUM('PENDING','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carrier_assignments_user` (`carrier_owner_id`, `assigned_user_id`, `status`),
  KEY `idx_carrier_assignments_package` (`id_store_package`, `status`),
  CONSTRAINT `fk_carrier_assignment_package`
    FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structured receiver identity used by proof of delivery.
ALTER TABLE `store_order_workflow`
  ADD COLUMN IF NOT EXISTS `delivery_receiver_type` VARCHAR(40) NULL AFTER `delivery_photo_url`,
  ADD COLUMN IF NOT EXISTS `delivery_receiver_name` VARCHAR(150) NULL AFTER `delivery_receiver_type`,
  ADD COLUMN IF NOT EXISTS `delivery_document_type` VARCHAR(30) NULL AFTER `delivery_receiver_name`,
  ADD COLUMN IF NOT EXISTS `delivery_document_number` VARCHAR(80) NULL AFTER `delivery_document_type`;

-- Create one primary package for each existing store order.
INSERT INTO `store_packages`
  (`id_owner`, `id_store_order`, `current_custodian_owner_id`, `custody_status`,
   `logistics_mode`, `package_sequence`, `package_code`, `current_status`,
   `current_location_label`, `last_event_at`, `created_at`, `updated_at`)
SELECT o.id_owner, o.id, o.id_owner,
       CASE WHEN o.status IN ('DELIVERED','COMPLETED') THEN 'DELIVERED' ELSE 'WITH_SELLER' END,
       'INTERNAL', 1, CONCAT('OPH-', o.id_owner, '-', o.id, '-01'), o.status,
       CASE
         WHEN o.status IN ('NEW','CONFIRMED','PROCESSING','IN_PREPARATION') THEN 'At business / preparation area'
         WHEN o.status IN ('READY','READY_FOR_DELIVERY') THEN 'At business / ready for pickup'
         WHEN o.status IN ('OUT_FOR_DELIVERY','DELIVERY_ATTEMPTED','REDELIVERY_SCHEDULED') THEN 'With delivery team'
         WHEN o.status = 'RETURNED_TO_BUSINESS' THEN 'Returned to business'
         WHEN o.status IN ('DELIVERED','COMPLETED') THEN 'Delivered to customer'
         WHEN o.status IN ('RETURN_REQUESTED','RETURN_APPROVED','RETURN_REJECTED','RETURNED') THEN 'Return workflow'
         WHEN o.status IN ('CANCELLED','CLOSED') THEN 'Closed'
         ELSE 'Status pending confirmation'
       END,
       COALESCE(o.updated_at, o.created_at, NOW()),
       COALESCE(o.created_at, NOW()), COALESCE(o.updated_at, NOW())
FROM `store_orders` o
LEFT JOIN `store_packages` p
  ON p.id_owner=o.id_owner
 AND p.id_store_order=o.id
 AND p.package_sequence=1
WHERE p.id IS NULL;

-- Seed the immutable tracking history once per existing package.
INSERT INTO `store_package_events`
  (`id_owner`, `id_store_package`, `id_store_order`, `event_type`, `status_to`,
   `location_label`, `notes`, `created_at`)
SELECT p.id_owner, p.id, p.id_store_order, 'HISTORY_STARTED', p.current_status,
       p.current_location_label,
       'Package tracking initialized from the current order state.', p.created_at
FROM `store_packages` p
LEFT JOIN `store_package_events` e ON e.id_store_package=p.id
WHERE e.id IS NULL;

-- Mark organizations created through the carrier signup flow.
UPDATE `institution_profile`
SET `organization_type`='CARRIER'
WHERE `business_nature`='carrier_logistics';

-- Carrier organizations always receive Logistics at no charge.
INSERT INTO `user_modules`
  (`id_user`,`module_slug`,`status`,`billing_status`,`activation_source`,
   `activation_reason`,`is_included_in_base`,`price`,`started_at`,`renewal_at`,
   `created_at`,`updated_at`)
SELECT ip.id_owner, 'store_delivery_tracking', 'ACTIVE', 'not_required',
       'carrier_included',
       'Logistics is permanently included for verified carrier organizations.',
       1, 0, CURDATE(), NULL, NOW(), NOW()
FROM `institution_profile` ip
WHERE ip.organization_type='CARRIER'
ON DUPLICATE KEY UPDATE
  status='ACTIVE',
  billing_status='not_required',
  activation_source='carrier_included',
  activation_reason='Logistics is permanently included for verified carrier organizations.',
  is_included_in_base=1,
  price=0,
  renewal_at=NULL,
  canceled_at=NULL,
  updated_at=NOW();

-- Verification: all values must be 1 except package_rows/event_rows, which
-- must be at least the number of existing store orders.
SELECT
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='store_packages') AS store_packages_ok,
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='store_package_events') AS package_events_ok,
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='store_package_custody_requests') AS custody_requests_ok,
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='store_package_carrier_assignments') AS carrier_assignments_ok,
  (SELECT COUNT(*) FROM `store_orders`) AS order_rows,
  (SELECT COUNT(*) FROM `store_packages`) AS package_rows,
  (SELECT COUNT(*) FROM `store_package_events`) AS event_rows;
