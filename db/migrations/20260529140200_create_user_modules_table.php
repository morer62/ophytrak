<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserModulesTable extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('user_modules')) {
            return;
        }

        $this->execute("
            CREATE TABLE `user_modules` (
                `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_user` int(11) NOT NULL,
                `module_slug` varchar(100) NOT NULL,
                `status` enum('ACTIVE','INACTIVE','PENDING','EXPIRED','CANCELED') NOT NULL DEFAULT 'ACTIVE',
                `is_included_in_base` tinyint(1) NOT NULL DEFAULT 0,
                `price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `started_at` date DEFAULT NULL,
                `renewal_at` date DEFAULT NULL,
                `canceled_at` date DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_module` (`id_user`,`module_slug`),
                KEY `idx_user_modules_user` (`id_user`),
                KEY `idx_user_modules_slug` (`module_slug`),
                KEY `idx_user_modules_status` (`status`),
                KEY `idx_user_modules_renewal` (`renewal_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `user_modules`");
    }
}
