<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStorageContainerCategories extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('storage_container_categories')) {
            $this->table('storage_container_categories')
                ->addColumn('id_owner', 'integer')
                ->addColumn('name', 'string', ['limit' => 120])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['id_owner', 'name'], ['unique' => true, 'name' => 'uq_storage_category_owner_name'])
                ->addIndex(['id_owner'], ['name' => 'idx_storage_category_owner'])
                ->create();
        }

        if ($this->hasTable('storage_containers')
            && !$this->table('storage_containers')->hasColumn('id_category')) {
            $this->table('storage_containers')
                ->addColumn('id_category', 'integer', ['null' => true, 'after' => 'id_owner'])
                ->addIndex(['id_category'], ['name' => 'idx_storage_container_category'])
                ->update();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('storage_containers')
            && $this->table('storage_containers')->hasColumn('id_category')) {
            $this->table('storage_containers')->removeColumn('id_category')->update();
        }
        if ($this->hasTable('storage_container_categories')) {
            $this->table('storage_container_categories')->drop()->save();
        }
    }
}
