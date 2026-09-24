-- OPHYTRACK per-package usage and seller-to-carrier settlement ledger.
CREATE TABLE IF NOT EXISTS `ophytrack_package_charges` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_store_package` BIGINT UNSIGNED NOT NULL,
  `id_store_order` INT NOT NULL,
  `seller_owner_id` INT NOT NULL,
  `carrier_owner_id` INT NULL,
  `payer_owner_id` INT NOT NULL,
  `payee_owner_id` INT NULL,
  `charge_kind` ENUM('PLATFORM_USAGE','CARRIER_SERVICE') NOT NULL,
  `amount_brl` DECIMAL(12,2) NOT NULL,
  `status` ENUM('PENDING','PROOF_SUBMITTED','PAID','VOID') NOT NULL DEFAULT 'PENDING',
  `service_at` DATETIME NOT NULL,
  `cycle_start` DATE NOT NULL,
  `cycle_end` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `payment_proof_url` VARCHAR(500) NULL,
  `payment_reference` VARCHAR(180) NULL,
  `proof_submitted_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ophytrack_package_charge` (`id_store_package`,`charge_kind`),
  KEY `idx_ophytrack_charge_payer_due` (`payer_owner_id`,`status`,`due_date`),
  KEY `idx_ophytrack_charge_payee` (`payee_owner_id`,`status`,`cycle_end`),
  CONSTRAINT `fk_ophytrack_charge_package` FOREIGN KEY (`id_store_package`) REFERENCES `store_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carrier companies now activate the same paid logistics license as sellers.
UPDATE `user_modules` um
INNER JOIN `institution_profile` ip ON ip.id_owner=um.id_user AND ip.organization_type='CARRIER'
SET um.status='INACTIVE',um.billing_status='pending_payment',um.activation_source='carrier_license',
    um.activation_reason='Carrier organizations activate the same OPHYTRACK logistics license as sellers.',
    um.is_included_in_base=0,um.price=169.00,um.renewal_at=NULL,um.updated_at=NOW()
WHERE um.module_slug='store_delivery_tracking' AND um.activation_source='carrier_included';
