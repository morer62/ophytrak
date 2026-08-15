CREATE TABLE IF NOT EXISTS `storage_container_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_storage_category_owner_name` (`id_owner`, `name`),
  KEY `idx_storage_category_owner` (`id_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `storage_containers`
  ADD COLUMN IF NOT EXISTS `id_category` INT NULL AFTER `id_owner`,
  ADD KEY IF NOT EXISTS `idx_storage_container_category` (`id_category`);
