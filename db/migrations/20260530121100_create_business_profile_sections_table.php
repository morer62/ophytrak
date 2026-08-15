<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBusinessProfileSectionsTable extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('business_profile_sections')) {
            return;
        }

        $this->table('business_profile_sections')
            ->addColumn('institution_profile_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('section_key', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('section_label', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('is_visible', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 100])
            ->addColumn('is_fixed', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['institution_profile_id', 'section_key'], [
                'unique' => true,
                'name' => 'idx_business_profile_sections_profile_key',
            ])
            ->addIndex(['institution_profile_id', 'is_visible'], [
                'name' => 'idx_business_profile_sections_profile_visible',
            ])
            ->create();
    }

    public function down(): void
    {
        if ($this->hasTable('business_profile_sections')) {
            $this->table('business_profile_sections')->drop()->save();
        }
    }
}
