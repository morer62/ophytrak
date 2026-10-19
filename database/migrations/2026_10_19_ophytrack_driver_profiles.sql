CREATE TABLE IF NOT EXISTS `ophytrack_driver_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` INT UNSIGNED NOT NULL,
  `profile_photo_url` VARCHAR(500) NULL,
  `bank_account_name` VARCHAR(190) NULL,
  `document_type` VARCHAR(40) NULL,
  `document_number` VARCHAR(80) NULL,
  `bank_name` VARCHAR(190) NULL,
  `bank_agency` VARCHAR(80) NULL,
  `bank_account_type` VARCHAR(60) NULL,
  `bank_account_number` VARCHAR(100) NULL,
  `pix_key` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ophytrack_driver_profile_user` (`id_user`),
  CONSTRAINT `fk_ophytrack_driver_profile_user`
    FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
