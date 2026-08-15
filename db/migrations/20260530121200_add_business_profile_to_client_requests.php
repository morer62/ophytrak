<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBusinessProfileToClientRequests extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('clients_request')) {
            return;
        }

        $this->execute("ALTER TABLE clients_request MODIFY profile_cat ENUM('venue','vendor','business_profile') NOT NULL");
    }

    public function down(): void
    {
        if (!$this->hasTable('clients_request')) {
            return;
        }

        $this->execute("ALTER TABLE clients_request MODIFY profile_cat ENUM('venue','vendor') NOT NULL");
    }
}
