-- Physical package identity and immutable operational trace for Store + Logistics.
CREATE TABLE IF NOT EXISTS `store_packages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `id_store_order` INT NOT NULL,
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
  KEY `idx_store_packages_order` (`id_store_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Give every existing order one primary physical package. Additional packages can use sequence 02, 03, etc.
INSERT INTO `store_packages`
  (`id_owner`, `id_store_order`, `package_sequence`, `package_code`, `current_status`, `current_location_label`, `last_event_at`, `created_at`, `updated_at`)
SELECT o.id_owner, o.id, 1, CONCAT('OPH-', o.id_owner, '-', o.id, '-01'), o.status,
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
       COALESCE(o.updated_at, o.created_at, NOW()), COALESCE(o.created_at, NOW()), COALESCE(o.updated_at, NOW())
FROM store_orders o
LEFT JOIN store_packages p
  ON p.id_owner = o.id_owner AND p.id_store_order = o.id AND p.package_sequence = 1
WHERE p.id IS NULL;

INSERT INTO `store_package_events`
  (`id_owner`, `id_store_package`, `id_store_order`, `event_type`, `status_to`, `location_label`, `notes`, `created_at`)
SELECT p.id_owner, p.id, p.id_store_order, 'HISTORY_STARTED', p.current_status,
       p.current_location_label, 'Package tracking initialized from the current order state.', p.created_at
FROM store_packages p
LEFT JOIN store_package_events e ON e.id_store_package = p.id
WHERE e.id IS NULL;
