<?php

namespace App\Repositories;

class AffiliateCommissionsRepository extends BaseRepository
{
    protected array $fields = [
        'referrer_id',
        'referred_id', 
        'referral_id',
        'transaction_type',
        'product_name',
        'module_slug',
        'transaction_id',
        'order_id',
        'payment_id',
        'payment_source_table',
        'payment_source_id',
        'gross_amount',
        'payment_amount',
        'payment_currency',
        'commission_rate',
        'commission_percentage_snapshot',
        'commission_amount',
        'commission_currency',
        'currency',
        'is_recurring',
        'is_addon',
        'eligible_for_commission',
        'commission_period_year',
        'commission_period_month',
        'status',
        'payment_method',
        'paid_at',
        'approved_at',
        'cancelled_at',
        'cancellation_reason',
        'payout_batch_id',
        'notes',
        'created_at',
        'updated_at'
    ];

    public function __construct()
    {
        $this->table = "affiliate_commissions";
        $this->db = new Connection();
    }

    /**
     * Obtener comisiones pendientes por usuario (referrer)
     */
    public function getPendingByReferrer(int $referrerId): array
    {
        $sql = "
            SELECT ac.*, u.name as referred_name, u.email as referred_email
            FROM {$this->table} ac
            JOIN users u ON ac.referred_id = u.id
            WHERE ac.referrer_id = :referrer_id 
            AND ac.status IN ('pending', 'approved')
            ORDER BY ac.created_at ASC
        ";
        
        $this->db->query($sql);
        $this->db->bind(':referrer_id', $referrerId);
        return $this->db->fetchAll();
    }

    /**
     * Obtener todas las comisiones por usuario (para admin)
     */
    public function getAllByReferrer(int $referrerId): array
    {
        $sql = "
            SELECT ac.*, u.name as referred_name, u.email as referred_email
            FROM {$this->table} ac
            JOIN users u ON ac.referred_id = u.id
            WHERE ac.referrer_id = :referrer_id 
            ORDER BY ac.created_at DESC
        ";
        
        $this->db->query($sql);
        $this->db->bind(':referrer_id', $referrerId);
        return $this->db->fetchAll();
    }

    /**
     * Obtener comisiones agrupadas por usuario para el panel de admin
     */
    public function getGroupedPendingCommissions(): array
    {
        $sql = "
            SELECT 
                ac.referrer_id,
                u.name as referrer_name,
                u.email as referrer_email,
                COUNT(*) as commission_count,
                SUM(ac.commission_amount) as total_amount,
                MIN(ac.created_at) as oldest_commission,
                MAX(ac.created_at) as newest_commission
            FROM {$this->table} ac
            JOIN users u ON ac.referrer_id = u.id
            WHERE ac.status IN ('pending', 'approved', 'payable')
            GROUP BY ac.referrer_id, u.name, u.email
            HAVING total_amount > 0
            ORDER BY total_amount DESC
        ";
        
        $this->db->query($sql);
        return $this->db->fetchAll();
    }

    public function getMonthlySummaryByReferrer(int $referrerId): array
    {
        $sql = "
            SELECT
                COALESCE(commission_period_year, YEAR(created_at)) as year,
                COALESCE(commission_period_month, MONTH(created_at)) as month,
                COUNT(*) as commission_count,
                SUM(gross_amount) as gross_amount,
                SUM(commission_amount) as commission_amount,
                SUM(CASE WHEN status IN ('pending', 'approved', 'payable') THEN commission_amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid_amount
            FROM {$this->table}
            WHERE referrer_id = :referrer_id
            GROUP BY year, month
            ORDER BY year DESC, month DESC
            LIMIT 12
        ";

        $this->db->query($sql);
        $this->db->bind(':referrer_id', $referrerId);
        return $this->db->fetchAll();
    }

    public function getPendingTotalByReferrer(int $referrerId): float
    {
        $sql = "
            SELECT COALESCE(SUM(commission_amount), 0) as total
            FROM {$this->table}
            WHERE referrer_id = :referrer_id
              AND status IN ('pending', 'approved', 'payable')
        ";

        $this->db->query($sql);
        $this->db->bind(':referrer_id', $referrerId);
        $row = $this->db->fetchOne();

        return $row ? (float)$row->total : 0.0;
    }

    /**
     * Marcar comisiones como pagadas
     */
    public function markAsPaid(array $commissionIds, string $paymentMethod = 'manual', ?string $payoutBatchId = null): bool
    {
        if (empty($commissionIds)) {
            return false;
        }

        $placeholders = str_repeat('?,', count($commissionIds) - 1) . '?';
        
        $sql = "
            UPDATE {$this->table} 
            SET status = 'paid',
                payment_method = ?,
                paid_at = NOW(),
                approved_at = COALESCE(approved_at, NOW()),
                payout_batch_id = ?,
                updated_at = NOW()
            WHERE id IN ($placeholders)
        ";
        
        $this->db->query($sql);
        
        // Bind parameters
        $this->db->bind(1, $paymentMethod);
        $this->db->bind(2, $payoutBatchId);
        
        foreach ($commissionIds as $index => $id) {
            $this->db->bind($index + 3, $id);
        }
        
        $result = $this->db->execute();
        return $result !== false;
    }

    /**
     * Obtener comisiones por IDs específicos
     */
    public function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $sql = "
            SELECT ac.*, u.name as referred_name, u.email as referred_email
            FROM {$this->table} ac
            JOIN users u ON ac.referred_id = u.id
            WHERE ac.id IN ($placeholders)
            ORDER BY ac.created_at ASC
        ";
        
        $this->db->query($sql);
        foreach ($ids as $index => $id) {
            $this->db->bind($index + 1, $id);
        }
        
        return $this->db->fetchAll();
    }

    /**
     * Crear comisión
     */
    public function createCommission(array $data): bool
    {
        return $this->add($data);
    }

    public function getTotalsByStatus(): object
    {
        $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN commission_amount ELSE 0 END), 0) as approved,
                COALESCE(SUM(CASE WHEN status = 'payable' THEN commission_amount ELSE 0 END), 0) as payable,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid,
                COALESCE(SUM(gross_amount), 0) as gross
            FROM {$this->table}
        ");

        return $this->db->fetchOne() ?: (object)[
            'pending' => 0,
            'approved' => 0,
            'payable' => 0,
            'paid' => 0,
            'gross' => 0,
        ];
    }

    public function getMonthlyRanking(?int $year = null, ?int $month = null, int $limit = 10): array
    {
        $year = $year ?: (int)date('Y');
        $month = $month ?: (int)date('n');

        $this->db->query("
            SELECT
                ac.referrer_id,
                u.name,
                u.lastname,
                u.email,
                COUNT(*) as sales_count,
                COALESCE(SUM(ac.gross_amount), 0) as revenue,
                COALESCE(SUM(ac.commission_amount), 0) as commissions
            FROM {$this->table} ac
            JOIN users u ON u.id = ac.referrer_id
            WHERE COALESCE(ac.commission_period_year, YEAR(ac.created_at)) = :year
              AND COALESCE(ac.commission_period_month, MONTH(ac.created_at)) = :month
            GROUP BY ac.referrer_id, u.name, u.lastname, u.email
            ORDER BY revenue DESC, sales_count DESC
            LIMIT :limit
        ");
        $this->db->bind(':year', $year);
        $this->db->bind(':month', $month);
        $this->db->bind(':limit', $limit, \PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    public function getAllByReferrerWithFilters(int $referrerId, array $statuses = []): array
    {
        $sql = "
            SELECT ac.*, u.name as referred_name, u.email as referred_email
            FROM {$this->table} ac
            JOIN users u ON ac.referred_id = u.id
            WHERE ac.referrer_id = :referrer_id
        ";

        if ($statuses) {
            $placeholders = [];
            foreach ($statuses as $index => $status) {
                $placeholders[] = ':status' . $index;
            }
            $sql .= ' AND ac.status IN (' . implode(',', $placeholders) . ')';
        }

        $sql .= " ORDER BY ac.created_at DESC";

        $this->db->query($sql);
        $this->db->bind(':referrer_id', $referrerId);
        foreach ($statuses as $index => $status) {
            $this->db->bind(':status' . $index, $status);
        }

        return $this->db->fetchAll();
    }
}
