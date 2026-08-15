<?php

namespace App\Repositories;

class AffiliateCommissionPaymentsRepository extends BaseRepository
{
    protected array $fields = [
        'referrer_id',
        'paid_by_user_id',
        'commission_ids',
        'total_amount',
        'commission_count',
        'payment_method',
        'payout_method',
        'payment_proof_url',
        'payment_reference',
        'payment_proof_original_name',
        'payment_proof_mime',
        'payment_proof_size',
        'stripe_transfer_id',
        'payout_batch_id',
        'status',
        'paid_at',
        'payout_year',
        'payout_month',
        'notes',
        'internal_notes',
        'external_notes',
        'created_at',
        'updated_at'
    ];

    public function __construct()
    {
        $this->table = "affiliate_commission_payments";
        $this->db = new Connection();
    }

    /**
     * Crear registro de pago de comisiones
     */
    public function createPayment(array $data): bool
    {
        try {
            $result = $this->add($data);
            return $result !== false;
        } catch (\Exception $e) {
            error_log("Error creating commission payment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener pagos por usuario
     */
    public function getByReferrer(int $referrerId): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE referrer_id = :referrer_id
            ORDER BY paid_at DESC, created_at DESC
        ";

        $this->db->query($sql);
        $this->db->bind(':referrer_id', $referrerId);
        return $this->db->fetchAll();
    }

    /**
     * Obtener todos los pagos (para admin)
     */
    public function getAllPayments(): array
    {
        $sql = "
            SELECT acp.*, u.name as referrer_name, u.email as referrer_email
            FROM {$this->table} acp
            JOIN users u ON acp.referrer_id = u.id
            ORDER BY acp.created_at DESC
        ";
        
        $this->db->query($sql);
        return $this->db->fetchAll();
    }
}
