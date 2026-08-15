CREATE TABLE IF NOT EXISTS `store_order_stock_returns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_owner` INT NOT NULL,
  `id_store_order` INT NOT NULL,
  `restored_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_order_stock_return` (`id_owner`, `id_store_order`),
  KEY `idx_store_stock_return_order` (`id_store_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
