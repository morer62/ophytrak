<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBusinessFieldsToInstitutionProfile extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('institution_profile')) {
            return;
        }

        $table = $this->table('institution_profile');

        if (!$table->hasColumn('business_nature')) {
            $table->addColumn('business_nature', 'string', [
                'limit' => 100,
                'null' => true,
                'after' => 'company_name',
            ]);
        }

        if (!$table->hasColumn('business_operation_type')) {
            $table->addColumn('business_operation_type', 'string', [
                'limit' => 100,
                'null' => true,
                'after' => 'business_nature',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('institution_profile')) {
            return;
        }

        $table = $this->table('institution_profile');

        if ($table->hasColumn('business_operation_type')) {
            $table->removeColumn('business_operation_type');
        }

        if ($table->hasColumn('business_nature')) {
            $table->removeColumn('business_nature');
        }

        $table->update();
    }
}
