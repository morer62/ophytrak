<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExtendAffiliateProgramForOphyra extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('affiliate_profiles')) {
            $this->table('affiliate_profiles', [
                'id' => false,
                'primary_key' => ['id'],
                'signed' => false,
            ])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('id_user', 'integer', ['null' => false])
                ->addColumn('application_status', 'enum', [
                    'values' => ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'],
                    'default' => 'PENDING',
                ])
                ->addColumn('commission_rate', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'default' => 30.00,
                ])
                ->addColumn('legal_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('business_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('business_type', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('tax_country', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('tax_reference', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('tax_id_last4', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('contact_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('contact_phone', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('address_line1', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('state', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('zip', 'string', ['limit' => 30, 'null' => true])
                ->addColumn('country', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('payout_method', 'enum', [
                    'values' => ['PAYPAL', 'ACH', 'OTHER', 'MANUAL'],
                    'null' => true,
                ])
                ->addColumn('paypal_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('account_holder_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('bank_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('routing_number', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('account_number_last4', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('account_type', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('payout_notes', 'text', ['null' => true])
                ->addColumn('reviewed_by', 'integer', ['null' => true])
                ->addColumn('reviewed_at', 'datetime', ['null' => true])
                ->addColumn('rejection_reason', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['id_user'], ['unique' => true, 'name' => 'unique_affiliate_profile_user'])
                ->addIndex(['application_status'], ['name' => 'idx_affiliate_profiles_status'])
                ->addIndex(['commission_rate'], ['name' => 'idx_affiliate_profiles_commission_rate'])
                ->create();
        }

        $this->execute("
            INSERT INTO affiliate_profiles (id_user, application_status, commission_rate, contact_email, created_at, updated_at)
            SELECT ac.user_id, 'APPROVED', 30.00, u.email, NOW(), NOW()
            FROM affiliate_codes ac
            JOIN users u ON u.id = ac.user_id
            LEFT JOIN affiliate_profiles ap ON ap.id_user = ac.user_id
            WHERE ap.id IS NULL
        ");

        if ($this->hasTable('affiliate_commissions')) {
            $this->execute("
                ALTER TABLE affiliate_commissions
                MODIFY COLUMN transaction_type ENUM(
                    'membership_monthly',
                    'membership_annual',
                    'listing_fee',
                    'ophyra_base',
                    'ophyra_addon',
                    'ticket_sales',
                    'event_registration',
                    'order_payment',
                    'other'
                ) NOT NULL COMMENT 'Transaction type'
            ");

            $table = $this->table('affiliate_commissions');
            $this->addColumnIfMissing($table, 'product_name', 'string', ['limit' => 150, 'null' => true, 'after' => 'transaction_type']);
            $this->addColumnIfMissing($table, 'module_slug', 'string', ['limit' => 100, 'null' => true, 'after' => 'product_name']);
            $this->addColumnIfMissing($table, 'payment_source_table', 'string', ['limit' => 100, 'null' => true, 'after' => 'payment_id']);
            $this->addColumnIfMissing($table, 'payment_source_id', 'integer', ['null' => true, 'after' => 'payment_source_table']);
            $this->addColumnIfMissing($table, 'is_recurring', 'boolean', ['default' => true, 'after' => 'currency']);
            $this->addColumnIfMissing($table, 'is_addon', 'boolean', ['default' => false, 'after' => 'is_recurring']);
            $this->addColumnIfMissing($table, 'eligible_for_commission', 'boolean', ['default' => true, 'after' => 'is_addon']);
            $this->addColumnIfMissing($table, 'commission_period_year', 'integer', ['limit' => 4, 'null' => true, 'after' => 'eligible_for_commission']);
            $this->addColumnIfMissing($table, 'commission_period_month', 'integer', ['limit' => 2, 'null' => true, 'after' => 'commission_period_year']);
            $this->addColumnIfMissing($table, 'approved_at', 'datetime', ['null' => true, 'after' => 'paid_at']);
            $this->addColumnIfMissing($table, 'cancelled_at', 'datetime', ['null' => true, 'after' => 'approved_at']);
            $this->addColumnIfMissing($table, 'cancellation_reason', 'text', ['null' => true, 'after' => 'cancelled_at']);
            $table->update();
        }

        if ($this->hasTable('affiliate_commission_payments')) {
            $table = $this->table('affiliate_commission_payments');
            $this->addColumnIfMissing($table, 'paid_by_user_id', 'integer', ['null' => true, 'after' => 'referrer_id']);
            $this->addColumnIfMissing($table, 'payout_method', 'enum', [
                'values' => ['PAYPAL', 'ACH', 'OTHER', 'MANUAL', 'CARD', 'STRIPE_CONNECT'],
                'null' => true,
                'after' => 'payment_method',
            ]);
            $this->addColumnIfMissing($table, 'payment_reference', 'string', ['limit' => 150, 'null' => true, 'after' => 'payment_proof_url']);
            $this->addColumnIfMissing($table, 'payment_proof_original_name', 'string', ['limit' => 255, 'null' => true, 'after' => 'payment_reference']);
            $this->addColumnIfMissing($table, 'payment_proof_mime', 'string', ['limit' => 100, 'null' => true, 'after' => 'payment_proof_original_name']);
            $this->addColumnIfMissing($table, 'payment_proof_size', 'integer', ['null' => true, 'after' => 'payment_proof_mime']);
            $this->addColumnIfMissing($table, 'payout_year', 'integer', ['limit' => 4, 'null' => true, 'after' => 'paid_at']);
            $this->addColumnIfMissing($table, 'payout_month', 'integer', ['limit' => 2, 'null' => true, 'after' => 'payout_year']);
            $this->addColumnIfMissing($table, 'internal_notes', 'text', ['null' => true, 'after' => 'notes']);
            $this->addColumnIfMissing($table, 'external_notes', 'text', ['null' => true, 'after' => 'internal_notes']);
            $table->update();
        }

        if ($this->hasTable('affiliate_referrals')) {
            $table = $this->table('affiliate_referrals');
            $this->addColumnIfMissing($table, 'first_paid_at', 'datetime', ['null' => true, 'after' => 'confirmed_at']);
            $this->addColumnIfMissing($table, 'last_paid_at', 'datetime', ['null' => true, 'after' => 'first_paid_at']);
            $this->addColumnIfMissing($table, 'lifetime_gross_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => 0.00,
                'after' => 'last_paid_at',
            ]);
            $this->addColumnIfMissing($table, 'lifetime_commission_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => 0.00,
                'after' => 'lifetime_gross_amount',
            ]);
            $table->update();
        }

        if ($this->hasTable('affiliate_codes')) {
            $table = $this->table('affiliate_codes');
            $this->addColumnIfMissing($table, 'application_required', 'boolean', ['default' => true, 'after' => 'status']);
            $table->update();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('affiliate_profiles')) {
            $this->table('affiliate_profiles')->drop()->save();
        }
    }

    private function addColumnIfMissing($table, string $name, string $type, array $options = []): void
    {
        if (!$table->hasColumn($name)) {
            $table->addColumn($name, $type, $options);
        }
    }
}
