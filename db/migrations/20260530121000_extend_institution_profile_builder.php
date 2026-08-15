<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExtendInstitutionProfileBuilder extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('institution_profile')) {
            return;
        }

        $table = $this->table('institution_profile');

        $columns = [
            'slug' => ['string', ['limit' => 150, 'null' => true, 'after' => 'payment_method_accepted']],
            'profile_layout' => ['string', ['limit' => 50, 'null' => false, 'default' => 'social_profile', 'after' => 'slug']],
            'show_map_section' => ['boolean', ['null' => false, 'default' => true, 'after' => 'profile_layout']],
            'show_events_section' => ['boolean', ['null' => false, 'default' => true, 'after' => 'show_map_section']],
            'show_quote_form' => ['boolean', ['null' => false, 'default' => true, 'after' => 'show_events_section']],
            'show_services_section' => ['boolean', ['null' => false, 'default' => true, 'after' => 'show_quote_form']],
            'show_contact_section' => ['boolean', ['null' => false, 'default' => true, 'after' => 'show_services_section']],
            'show_gallery_section' => ['boolean', ['null' => false, 'default' => true, 'after' => 'show_contact_section']],
            'show_description_section' => ['boolean', ['null' => false, 'default' => true, 'after' => 'show_gallery_section']],
            'short_description' => ['string', ['limit' => 500, 'null' => true, 'after' => 'show_description_section']],
            'rich_description' => ['text', ['null' => true, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM, 'after' => 'short_description']],
            'custom_domain' => ['string', ['limit' => 255, 'null' => true, 'after' => 'rich_description']],
            'custom_domain_status' => ['enum', ['values' => ['NONE', 'PENDING', 'APPROVED', 'REJECTED'], 'null' => false, 'default' => 'NONE', 'after' => 'custom_domain']],
            'custom_domain_requested_at' => ['datetime', ['null' => true, 'after' => 'custom_domain_status']],
            'custom_domain_approved_at' => ['datetime', ['null' => true, 'after' => 'custom_domain_requested_at']],
            'custom_domain_rejected_at' => ['datetime', ['null' => true, 'after' => 'custom_domain_approved_at']],
            'custom_domain_notes' => ['text', ['null' => true, 'after' => 'custom_domain_rejected_at']],
            'profile_completed_at' => ['datetime', ['null' => true, 'after' => 'custom_domain_notes']],
        ];

        foreach ($columns as $name => [$type, $options]) {
            if (!$table->hasColumn($name)) {
                $table->addColumn($name, $type, $options);
            }
        }

        if (!$table->hasIndex(['slug'])) {
            $table->addIndex(['slug'], ['unique' => true, 'name' => 'idx_institution_profile_slug_unique']);
        }

        if (!$table->hasIndex(['custom_domain'])) {
            $table->addIndex(['custom_domain'], ['unique' => true, 'name' => 'idx_institution_profile_custom_domain_unique']);
        }

        $table->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('institution_profile')) {
            return;
        }

        $table = $this->table('institution_profile');

        foreach (['idx_institution_profile_custom_domain_unique', 'idx_institution_profile_slug_unique'] as $indexName) {
            if ($table->hasIndexByName($indexName)) {
                $table->removeIndexByName($indexName);
            }
        }

        foreach ([
            'profile_completed_at',
            'custom_domain_notes',
            'custom_domain_rejected_at',
            'custom_domain_approved_at',
            'custom_domain_requested_at',
            'custom_domain_status',
            'custom_domain',
            'rich_description',
            'short_description',
            'show_description_section',
            'show_gallery_section',
            'show_contact_section',
            'show_services_section',
            'show_quote_form',
            'show_events_section',
            'show_map_section',
            'profile_layout',
            'slug',
        ] as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }

        $table->update();
    }
}
