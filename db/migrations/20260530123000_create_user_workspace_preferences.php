<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserWorkspacePreferences extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('user_institutions')) {
            $table = $this->table('user_institutions');
            if (!$table->hasIndex(['user_id', 'is_active'])) {
                $table->addIndex(['user_id', 'is_active'], ['name' => 'idx_user_institutions_user_active']);
            }
            if (!$table->hasIndex(['institution_id', 'is_active'])) {
                $table->addIndex(['institution_id', 'is_active'], ['name' => 'idx_user_institutions_institution_active']);
            }
            if (!$table->hasIndex(['secondary_institution_id', 'is_active'])) {
                $table->addIndex(['secondary_institution_id', 'is_active'], ['name' => 'idx_user_institutions_secondary_active']);
            }
            $table->update();
        }

        if ($this->hasTable('clients_users')) {
            $table = $this->table('clients_users');
            if (!$table->hasIndex(['client_id'])) {
                $table->addIndex(['client_id'], ['name' => 'idx_clients_users_client']);
            }
            if (!$table->hasIndex(['id_owner_asociated'])) {
                $table->addIndex(['id_owner_asociated'], ['name' => 'idx_clients_users_owner']);
            }
            if (!$table->hasIndex(['client_id', 'id_owner_asociated'])) {
                $table->addIndex(['client_id', 'id_owner_asociated'], [
                    'unique' => true,
                    'name' => 'idx_clients_users_client_owner_unique',
                ]);
            }
            $table->update();
        }

        if (!$this->hasTable('user_workspace_preferences')) {
            $this->table('user_workspace_preferences')
                ->addColumn('user_id', 'integer', ['null' => false])
                ->addColumn('workspace_type', 'enum', [
                    'values' => ['BUSINESS_OWNER', 'TEAM_MEMBER', 'CLIENT'],
                    'null' => false,
                    'default' => 'CLIENT',
                ])
                ->addColumn('selected_owner_id', 'integer', ['null' => true])
                ->addColumn('selected_institution_id', 'integer', ['null' => true])
                ->addColumn('selected_role', 'string', ['limit' => 80, 'null' => true])
                ->addColumn('created_at', 'datetime', ['null' => true])
                ->addColumn('updated_at', 'datetime', ['null' => true])
                ->addIndex(['user_id'], ['unique' => true, 'name' => 'unique_user_workspace_preference'])
                ->addIndex(['selected_owner_id'], ['name' => 'idx_user_workspace_selected_owner'])
                ->addIndex(['selected_institution_id'], ['name' => 'idx_user_workspace_selected_institution'])
                ->create();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('user_workspace_preferences')) {
            $this->table('user_workspace_preferences')->drop()->save();
        }
    }
}
