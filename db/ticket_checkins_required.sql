CREATE TABLE IF NOT EXISTS `ticket_checkins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_ticket_sale` INT UNSIGNED NOT NULL,
  `ticket_code` VARCHAR(190) NOT NULL,
  `id_venue_event` INT UNSIGNED NOT NULL,
  `checked_in_by` INT NOT NULL,
  `checked_in_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ticket_checkin_code` (`ticket_code`),
  KEY `idx_ticket_checkin_event` (`id_venue_event`),
  KEY `idx_ticket_checkin_sale` (`id_ticket_sale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
