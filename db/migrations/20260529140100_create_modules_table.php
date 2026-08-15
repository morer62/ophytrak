<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateModulesTable extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('modules')) {
            return;
        }

        $this->execute("
            CREATE TABLE `modules` (
                `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` varchar(100) NOT NULL,
                `name` varchar(150) NOT NULL,
                `description` text DEFAULT NULL,
                `is_base` tinyint(1) NOT NULL DEFAULT 0,
                `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
                `sort_order` int(11) NOT NULL DEFAULT 0,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `modules`");
    }
}
