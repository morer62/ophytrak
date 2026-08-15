-- Team member contract templates
-- Review and apply manually. These templates are only for employee/team contracts.
-- Do not use orders_contracts for employee agreements.

CREATE TABLE IF NOT EXISTS `team_member_contract_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_owner` int(11) NOT NULL,
  `title` varchar(190) NOT NULL,
  `content` longtext NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'ACTIVE',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_team_contract_templates_owner_status` (`id_owner`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
