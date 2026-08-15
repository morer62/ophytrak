-- Currency, pricing and payment availability sprint
-- Safe to run after the current ophyra_vnv_venue dump. It only creates new
-- tables and appends nullable/defaulted columns used by the compatibility layer.

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_currency` char(3) NOT NULL DEFAULT 'USD',
  `target_currency` char(3) NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `rate_date` date NOT NULL,
  `source` varchar(80) NOT NULL DEFAULT 'manual',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exchange_rate_day` (`base_currency`, `target_currency`, `rate_date`, `source`),
  KEY `idx_exchange_rates_lookup` (`base_currency`, `target_currency`, `is_active`, `rate_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_provider_availability_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_type` varchar(40) NOT NULL,
  `country_code` varchar(8) NOT NULL DEFAULT '*',
  `currency` varchar(8) NOT NULL DEFAULT '*',
  `id_user_business` int(11) DEFAULT NULL,
  `site_key` varchar(80) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_provider_rules_lookup` (`provider_type`, `country_code`, `currency`, `id_user_business`, `site_key`, `vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_transfer_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user_business` int(11) NOT NULL,
  `site_key` varchar(80) DEFAULT NULL,
  `country_code` varchar(8) NOT NULL DEFAULT '*',
  `currency` varchar(8) NOT NULL DEFAULT '*',
  `bank_name` varchar(160) DEFAULT NULL,
  `account_name` varchar(160) DEFAULT NULL,
  `account_last4` varchar(8) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bank_transfer_scope` (`id_user_business`, `site_key`, `country_code`, `currency`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `user_billing_info`
  ADD COLUMN IF NOT EXISTS `billing_country` varchar(8) DEFAULT 'US' AFTER `billing_zip`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `preferred_currency` varchar(8) DEFAULT 'USD' AFTER `system_language`;

ALTER TABLE `store_payments`
  ADD COLUMN IF NOT EXISTS `base_amount` decimal(12,2) DEFAULT NULL AFTER `currency`,
  ADD COLUMN IF NOT EXISTS `base_currency` varchar(8) DEFAULT 'USD' AFTER `base_amount`,
  ADD COLUMN IF NOT EXISTS `display_amount` decimal(12,2) DEFAULT NULL AFTER `base_currency`,
  ADD COLUMN IF NOT EXISTS `display_currency` varchar(8) DEFAULT NULL AFTER `display_amount`,
  ADD COLUMN IF NOT EXISTS `payment_amount` decimal(12,2) DEFAULT NULL AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `payment_currency` varchar(8) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(18,8) DEFAULT NULL AFTER `payment_currency`,
  ADD COLUMN IF NOT EXISTS `exchange_rate_source` varchar(80) DEFAULT NULL AFTER `exchange_rate`,
  ADD COLUMN IF NOT EXISTS `provider_type` varchar(40) DEFAULT NULL AFTER `exchange_rate_source`,
  ADD COLUMN IF NOT EXISTS `payment_provider_id` int(11) DEFAULT NULL AFTER `provider_type`,
  ADD COLUMN IF NOT EXISTS `manual_review_status` varchar(40) DEFAULT NULL AFTER `payment_provider_id`,
  ADD COLUMN IF NOT EXISTS `manual_review_notes` text DEFAULT NULL AFTER `manual_review_status`,
  ADD COLUMN IF NOT EXISTS `reviewed_by` int(11) DEFAULT NULL AFTER `manual_review_notes`,
  ADD COLUMN IF NOT EXISTS `reviewed_at` datetime DEFAULT NULL AFTER `reviewed_by`,
  ADD COLUMN IF NOT EXISTS `id_user_business` int(11) DEFAULT NULL AFTER `reviewed_at`;

ALTER TABLE `orders_payments`
  ADD COLUMN IF NOT EXISTS `base_amount` decimal(12,2) DEFAULT NULL AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `base_currency` varchar(8) DEFAULT 'USD' AFTER `base_amount`,
  ADD COLUMN IF NOT EXISTS `display_amount` decimal(12,2) DEFAULT NULL AFTER `base_currency`,
  ADD COLUMN IF NOT EXISTS `display_currency` varchar(8) DEFAULT NULL AFTER `display_amount`,
  ADD COLUMN IF NOT EXISTS `payment_amount` decimal(12,2) DEFAULT NULL AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `payment_currency` varchar(8) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(18,8) DEFAULT NULL AFTER `payment_currency`,
  ADD COLUMN IF NOT EXISTS `provider_type` varchar(40) DEFAULT NULL AFTER `exchange_rate`,
  ADD COLUMN IF NOT EXISTS `payment_provider_id` int(11) DEFAULT NULL AFTER `provider_type`,
  ADD COLUMN IF NOT EXISTS `manual_review_status` varchar(40) DEFAULT NULL AFTER `payment_provider_id`,
  ADD COLUMN IF NOT EXISTS `id_user_business` int(11) DEFAULT NULL AFTER `manual_review_status`,
  ADD COLUMN IF NOT EXISTS `site_key` varchar(80) DEFAULT NULL AFTER `id_user_business`,
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL AFTER `site_key`;

ALTER TABLE `payments_all`
  ADD COLUMN IF NOT EXISTS `base_amount` decimal(12,2) DEFAULT NULL AFTER `total`,
  ADD COLUMN IF NOT EXISTS `base_currency` varchar(8) DEFAULT 'USD' AFTER `base_amount`,
  ADD COLUMN IF NOT EXISTS `display_amount` decimal(12,2) DEFAULT NULL AFTER `base_currency`,
  ADD COLUMN IF NOT EXISTS `display_currency` varchar(8) DEFAULT NULL AFTER `display_amount`,
  ADD COLUMN IF NOT EXISTS `payment_amount` decimal(12,2) DEFAULT NULL AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `payment_currency` varchar(8) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(18,8) DEFAULT NULL AFTER `payment_currency`,
  ADD COLUMN IF NOT EXISTS `provider_type` varchar(40) DEFAULT NULL AFTER `exchange_rate`,
  ADD COLUMN IF NOT EXISTS `payment_method` varchar(60) DEFAULT NULL AFTER `provider_type`,
  ADD COLUMN IF NOT EXISTS `payment_provider_id` int(11) DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `manual_review_status` varchar(40) DEFAULT NULL AFTER `payment_provider_id`,
  ADD COLUMN IF NOT EXISTS `id_user_business` int(11) DEFAULT NULL AFTER `manual_review_status`,
  ADD COLUMN IF NOT EXISTS `site_key` varchar(80) DEFAULT NULL AFTER `id_user_business`,
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL AFTER `site_key`;

ALTER TABLE `membership_payments`
  ADD COLUMN IF NOT EXISTS `base_amount` decimal(12,2) DEFAULT NULL AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `base_currency` varchar(8) DEFAULT 'USD' AFTER `base_amount`,
  ADD COLUMN IF NOT EXISTS `display_amount` decimal(12,2) DEFAULT NULL AFTER `base_currency`,
  ADD COLUMN IF NOT EXISTS `display_currency` varchar(8) DEFAULT NULL AFTER `display_amount`,
  ADD COLUMN IF NOT EXISTS `payment_amount` decimal(12,2) DEFAULT NULL AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `payment_currency` varchar(8) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(18,8) DEFAULT NULL AFTER `payment_currency`,
  ADD COLUMN IF NOT EXISTS `provider_type` varchar(40) DEFAULT NULL AFTER `exchange_rate`,
  ADD COLUMN IF NOT EXISTS `payment_method` varchar(60) DEFAULT NULL AFTER `provider_type`,
  ADD COLUMN IF NOT EXISTS `payment_provider_id` int(11) DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `manual_review_status` varchar(40) DEFAULT NULL AFTER `payment_provider_id`;

ALTER TABLE `event_payments`
  ADD COLUMN IF NOT EXISTS `base_amount` decimal(12,2) DEFAULT NULL AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `base_currency` varchar(8) DEFAULT 'USD' AFTER `base_amount`,
  ADD COLUMN IF NOT EXISTS `display_amount` decimal(12,2) DEFAULT NULL AFTER `base_currency`,
  ADD COLUMN IF NOT EXISTS `display_currency` varchar(8) DEFAULT NULL AFTER `display_amount`,
  ADD COLUMN IF NOT EXISTS `payment_amount` decimal(12,2) DEFAULT NULL AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `payment_currency` varchar(8) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(18,8) DEFAULT NULL AFTER `payment_currency`,
  ADD COLUMN IF NOT EXISTS `provider_type` varchar(40) DEFAULT NULL AFTER `exchange_rate`,
  ADD COLUMN IF NOT EXISTS `payment_provider_id` int(11) DEFAULT NULL AFTER `provider_type`,
  ADD COLUMN IF NOT EXISTS `manual_review_status` varchar(40) DEFAULT NULL AFTER `payment_provider_id`,
  ADD COLUMN IF NOT EXISTS `id_user_business` int(11) DEFAULT NULL AFTER `manual_review_status`,
  ADD COLUMN IF NOT EXISTS `site_key` varchar(80) DEFAULT NULL AFTER `id_user_business`,
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL AFTER `site_key`;

INSERT IGNORE INTO `exchange_rates` (`base_currency`, `target_currency`, `rate`, `rate_date`, `source`, `is_active`)
VALUES ('USD', 'USD', 1.00000000, CURRENT_DATE(), 'system', 1);

INSERT INTO `payment_provider_availability_rules` (`provider_type`, `country_code`, `currency`, `is_enabled`, `reason`)
SELECT 'stripe', '*', '*', 1, 'Default enabled; provider SDK and account settings still apply.'
WHERE NOT EXISTS (
  SELECT 1 FROM `payment_provider_availability_rules`
  WHERE `provider_type` = 'stripe' AND `country_code` = '*' AND `currency` = '*'
    AND `id_user_business` IS NULL AND `site_key` IS NULL AND `vendor_id` IS NULL
);

INSERT INTO `payment_provider_availability_rules` (`provider_type`, `country_code`, `currency`, `is_enabled`, `reason`)
SELECT 'paypal', '*', '*', 1, 'Default enabled; provider SDK and account settings still apply.'
WHERE NOT EXISTS (
  SELECT 1 FROM `payment_provider_availability_rules`
  WHERE `provider_type` = 'paypal' AND `country_code` = '*' AND `currency` = '*'
    AND `id_user_business` IS NULL AND `site_key` IS NULL AND `vendor_id` IS NULL
);

INSERT INTO `payment_provider_availability_rules` (`provider_type`, `country_code`, `currency`, `is_enabled`, `reason`)
SELECT 'square', '*', '*', 1, 'Default enabled; service layer restricts country/currency pairs.'
WHERE NOT EXISTS (
  SELECT 1 FROM `payment_provider_availability_rules`
  WHERE `provider_type` = 'square' AND `country_code` = '*' AND `currency` = '*'
    AND `id_user_business` IS NULL AND `site_key` IS NULL AND `vendor_id` IS NULL
);

INSERT INTO `payment_provider_availability_rules` (`provider_type`, `country_code`, `currency`, `is_enabled`, `reason`)
SELECT 'bank_transfer', '*', '*', 1, 'Manual review fallback for all countries and currencies.'
WHERE NOT EXISTS (
  SELECT 1 FROM `payment_provider_availability_rules`
  WHERE `provider_type` = 'bank_transfer' AND `country_code` = '*' AND `currency` = '*'
    AND `id_user_business` IS NULL AND `site_key` IS NULL AND `vendor_id` IS NULL
);
