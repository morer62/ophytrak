-- Carrier organizations, cross-business custody and internal carrier assignments.
ALTER TABLE `institution_profile`
  ADD COLUMN `organization_type` VARCHAR(40) NOT NULL DEFAULT 'BUSINESS' AFTER `business_operation_type`;

ALTER TABLE `store_packages`
  ADD COLUMN `current_custodian_owner_id` INT NULL AFTER `id_store_order`,
  ADD COLUMN `current_custodian_user_id` INT UNSIGNED NULL AFTER `current_custodian_owner_id`,
  ADD COLUMN `custody_status` VARCHAR(50) NOT NULL DEFAULT 'WITH_SELLER' AFTER `current_custodian_user_id`,
  ADD COLUMN `logistics_mode` ENUM('INTERNAL','EXTERNAL_CARRIER') NOT NULL DEFAULT 'INTERNAL' AFTER `custody_status`,
  ADD COLUMN `custody_started_at` DATETIME NULL AFTER `logistics_mode`,
  ADD KEY `idx_store_packages_custodian` (`current_custodian_owner_id`, `custody_status`),
  ADD KEY `idx_store_packages_custodian_user` (`current_custodian_user_id`);

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
  CONSTRAINT `fk_custody_request_package` FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
  CONSTRAINT `fk_carrier_assignment_package` FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE store_packages p
JOIN store_orders o ON o.id=p.id_store_order AND o.id_owner=p.id_owner
SET p.current_custodian_owner_id=o.id_owner,
    p.custody_status=CASE WHEN o.status IN ('DELIVERED','COMPLETED') THEN 'DELIVERED' ELSE 'WITH_SELLER' END
WHERE p.current_custodian_owner_id IS NULL;

-- Existing organizations explicitly configured as logistics/delivery become carriers.
UPDATE institution_profile
SET organization_type='CARRIER'
WHERE business_nature='carrier_logistics';

INSERT INTO user_modules
  (id_user,module_slug,status,billing_status,activation_source,activation_reason,is_included_in_base,price,started_at,renewal_at,created_at,updated_at)
SELECT ip.id_owner,'store_delivery_tracking','ACTIVE','not_required','carrier_included',
       'Logistics is permanently included for verified carrier organizations.',1,0,CURDATE(),NULL,NOW(),NOW()
FROM institution_profile ip
WHERE ip.organization_type='CARRIER'
ON DUPLICATE KEY UPDATE
  status='ACTIVE',billing_status='not_required',activation_source='carrier_included',
  activation_reason='Logistics is permanently included for verified carrier organizations.',
  is_included_in_base=1,price=0,renewal_at=NULL,canceled_at=NULL,updated_at=NOW();
