<?php

namespace App\Repositories;

class OrdersPaymentsRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = "orders_payments";
        $this->db = new Connection();
    }

    public function getAllByOrder(int $orderId): array
    {
        return $this->getAllBy(["id_order" => $orderId]);
    }

    public function getMainByOrder(int $orderId): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE id_order = :orderId
              AND (id_suborder IS NULL OR id_suborder = 0)
        ";
        $this->db->query($sql);
        $this->db->bind(':orderId', $orderId);
        return $this->db->fetchAll();
    }

    public function getAllByOwner(int $ownerId): array
    {
        $sql = "
            SELECT p.*
            FROM orders_payments p
            JOIN orders o ON p.id_order = o.id
            WHERE (o.id_owner = :ownerId OR (o.id_owner IS NULL AND o.id_user = :ownerId))
            ORDER BY p.paid_at DESC
        ";
        $this->db->query($sql);
        $this->db->bind(":ownerId", $ownerId);
        return $this->db->fetchAll();
    }

    /**
     * Pagos cobrados por empresa en un mes (para ganancias).
     */
    public function getPaidByOwnerInMonth(int $ownerId, int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $end = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $lastDay);
        $sql = "
            SELECT p.*
            FROM orders_payments p
            JOIN orders o ON p.id_order = o.id
            WHERE (o.id_owner = :ownerId OR (o.id_owner IS NULL AND o.id_user = :ownerId))
            AND p.paid_at >= :start AND p.paid_at <= :end
            ORDER BY p.paid_at DESC
        ";
        $this->db->query($sql);
        $this->db->bind(":ownerId", $ownerId);
        $this->db->bind(":start", $start);
        $this->db->bind(":end", $end);
        return $this->db->fetchAll();
    }

    public function getPaidByOwnerInYear(int $ownerId, int $year): array
    {
        $start = "{$year}-01-01 00:00:00";
        $end = "{$year}-12-31 23:59:59";
        $sql = "
            SELECT p.*
            FROM orders_payments p
            JOIN orders o ON p.id_order = o.id
            WHERE (o.id_owner = :ownerId OR (o.id_owner IS NULL AND o.id_user = :ownerId))
            AND p.paid_at >= :start AND p.paid_at <= :end
            ORDER BY p.paid_at DESC
        ";
        $this->db->query($sql);
        $this->db->bind(":ownerId", $ownerId);
        $this->db->bind(":start", $start);
        $this->db->bind(":end", $end);
        return $this->db->fetchAll();
    }

    public function markRefunded(string $chargeId, float $amount): void {
        $this->update([
            'refunded_at' => date('Y-m-d H:i:s'),
            'refunded_amount' => $amount
        ], [
            'stripe_charge_id' => $chargeId
        ]);
    }

    /**
     * Pagos cobrados por owner en un rango de fechas (orders_payments + orders_advances).
     * Incluye quién pagó, monto, fecha, plataforma, tipo (full/partial).
     */
    public function getCollectedByOwnerWithDateRange(int $ownerId, string $start, string $end): array
    {
        $sql = "
            SELECT p.id, p.id_order, p.id_suborder, p.amount, p.paid_at, p.method, p.stripe_charge_id,
                   COALESCE(p.refunded_amount, 0) as refunded_amount,
                   o.id_client, o.payment_split_type,
                   s.id as suborder_id,
                   CONCAT(COALESCE(c.name,''), ' ', COALESCE(c.lastname,'')) as payer_name,
                   c.email as payer_email,
                   'payment' as entry_type
            FROM orders_payments p
            JOIN orders o ON p.id_order = o.id
            LEFT JOIN orders_suborder s ON s.id = p.id_suborder
            LEFT JOIN users c ON c.id = o.id_client
            WHERE (o.id_owner = :ownerId OR (o.id_owner IS NULL AND o.id_user = :ownerId))
              AND o.is_archived = 0
              AND (p.id_suborder IS NULL OR p.id_suborder = 0 OR COALESCE(s.is_archived, 0) = 0)
              AND p.paid_at >= :start AND p.paid_at <= :end
        ";
        $this->db->query($sql);
        $this->db->bind(":ownerId", $ownerId);
        $this->db->bind(":start", $start);
        $this->db->bind(":end", $end);
        $payments = $this->db->fetchAll();

        // Advances (main order)
        $sqlAdv = "
            SELECT oa.id, oa.id_order, NULL as id_suborder, oa.amount, oa.created_at as paid_at, oa.stripe_charge_id,
                   COALESCE(oa.refunded_amount, 0) as refunded_amount,
                   o.id_client,
                   NULL as suborder_id,
                   CONCAT(COALESCE(c.name,''), ' ', COALESCE(c.lastname,'')) as payer_name,
                   c.email as payer_email,
                   'advance' as entry_type
            FROM orders_advances oa
            JOIN orders o ON oa.id_order = o.id AND oa.is_suborder = 0
            LEFT JOIN users c ON c.id = o.id_client
            WHERE (o.id_owner = :ownerId OR (o.id_owner IS NULL AND o.id_user = :ownerId))
              AND o.is_archived = 0
              AND oa.created_at >= :start AND oa.created_at <= :end
        ";
        $this->db->query($sqlAdv);
        $this->db->bind(":ownerId", $ownerId);
        $this->db->bind(":start", $start);
        $this->db->bind(":end", $end);
        $advances = $this->db->fetchAll();

        // Advances (suborder)
        $sqlSubAdv = "
            SELECT oa.id, s.id_order, s.id as id_suborder, oa.amount, oa.created_at as paid_at, oa.stripe_charge_id,
                   COALESCE(oa.refunded_amount, 0) as refunded_amount,
                   o.id_client,
                   s.id as suborder_id,
                   CONCAT(COALESCE(c.name,''), ' ', COALESCE(c.lastname,'')) as payer_name,
                   c.email as payer_email,
                   'suborder_advance' as entry_type
            FROM orders_advances oa
            JOIN orders_suborder s ON s.id = oa.id_suborder AND oa.is_suborder = 1
            JOIN orders o ON o.id = s.id_order
            LEFT JOIN users c ON c.id = o.id_client
            WHERE (o.id_owner = :ownerId OR (o.id_owner IS NULL AND o.id_user = :ownerId))
              AND o.is_archived = 0
              AND s.is_archived = 0
              AND oa.created_at >= :start AND oa.created_at <= :end
        ";
        $this->db->query($sqlSubAdv);
        $this->db->bind(":ownerId", $ownerId);
        $this->db->bind(":start", $start);
        $this->db->bind(":end", $end);
        $subAdvances = $this->db->fetchAll();

        $all = array_merge($payments, $advances, $subAdvances);

        foreach ($all as $row) {
            $platform = $this->resolvePlatform($row);
            $paymentKind = $this->resolvePaymentKind($row, $all);
            $row->platform = $platform;
            $row->payment_kind = $paymentKind;
            $row->payment_scope = !empty($row->id_suborder) ? 'suborder' : 'order';
            $row->display_order = !empty($row->id_suborder)
                ? ('#' . (int)$row->id_order . ' / Sub-' . (int)$row->id_suborder)
                : ('#' . (int)$row->id_order);
        }

        usort($all, function ($a, $b) {
            return strtotime($b->paid_at ?? '1970-01-01') <=> strtotime($a->paid_at ?? '1970-01-01');
        });
        return $all;
    }

    private function resolvePlatform(object $row): string
    {
        if (($row->entry_type ?? '') === 'advance') {
            if (empty($row->stripe_charge_id)) {
                return 'manual';
            }
            $cid = (string)$row->stripe_charge_id;
            return (strpos($cid, 'ch_') === 0) ? 'stripe' : 'square';
        }
        $m = strtolower($row->method ?? '');
        if (in_array($m, ['square', 'stripe', 'paypal'])) {
            return $m;
        }
        return 'manual';
    }

    private function resolvePaymentKind(object $row, array $all): string
    {
        if (in_array(($row->entry_type ?? ''), ['advance', 'suborder_advance'], true)) {
            return 'advance';
        }

        if (!empty($row->id_suborder)) {
            return 'suborder';
        }

        $splitType = (int)($row->payment_split_type ?? 1);
        if ($splitType === 1) {
            return 'full';
        }
        $orderPayments = array_filter($all, function ($x) use ($row) {
            return ($x->entry_type ?? '') === 'payment' && ($x->id_order ?? 0) === ($row->id_order ?? 0);
        });
        usort($orderPayments, function ($a, $b) {
            return strtotime($a->paid_at ?? '') <=> strtotime($b->paid_at ?? '');
        });
        $idx = 0;
        foreach ($orderPayments as $i => $p) {
            if (($p->id ?? 0) === ($row->id ?? 0)) {
                $idx = $i + 1;
                break;
            }
        }
        return $idx === 1 ? 'first' : ($idx >= 2 ? 'second' : 'full');
    }

    /**
     * Ingresos diarios por owner (últimos N días). Incluye orders_payments y orders_advances.
     * @return array [{date: 'Y-m-d', total: float}, ...]
     */
    public function getDailyTotalsByOwner(int $ownerId, int $days = 30): array
    {
        $start = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
        $end = date('Y-m-d 23:59:59');

        $sql = "
            SELECT DATE(p.paid_at) as dt, SUM(p.amount - COALESCE(p.refunded_amount, 0)) as total
            FROM (
                SELECT p.amount, p.refunded_amount, p.paid_at
                FROM orders_payments p
                JOIN orders o ON p.id_order = o.id
                LEFT JOIN orders_suborder s ON s.id = p.id_suborder
                WHERE (o.id_owner = :ownerId1 OR (o.id_owner IS NULL AND o.id_user = :ownerId1))
                AND o.is_archived = 0
                AND (p.id_suborder IS NULL OR p.id_suborder = 0 OR COALESCE(s.is_archived, 0) = 0)
                AND p.paid_at >= :start1 AND p.paid_at <= :end1
                UNION ALL
                SELECT oa.amount, oa.refunded_amount, oa.created_at as paid_at
                FROM orders_advances oa
                JOIN orders o ON oa.id_order = o.id AND oa.is_suborder = 0
                WHERE (o.id_owner = :ownerId2 OR (o.id_owner IS NULL AND o.id_user = :ownerId2))
                AND oa.created_at >= :start2 AND oa.created_at <= :end2
                UNION ALL
                SELECT oa.amount, oa.refunded_amount, oa.created_at as paid_at
                FROM orders_advances oa
                JOIN orders_suborder s ON s.id = oa.id_suborder AND oa.is_suborder = 1
                JOIN orders o ON o.id = s.id_order
                WHERE (o.id_owner = :ownerId3 OR (o.id_owner IS NULL AND o.id_user = :ownerId3))
                AND oa.created_at >= :start3 AND oa.created_at <= :end3
            ) p
            GROUP BY DATE(p.paid_at)
            ORDER BY dt ASC
        ";
        $this->db->query($sql);
        $this->db->bind(":ownerId1", $ownerId);
        $this->db->bind(":ownerId2", $ownerId);
        $this->db->bind(":ownerId3", $ownerId);
        $this->db->bind(":start1", $start);
        $this->db->bind(":end1", $end);
        $this->db->bind(":start2", $start);
        $this->db->bind(":end2", $end);
        $this->db->bind(":start3", $start);
        $this->db->bind(":end3", $end);
        $rows = $this->db->fetchAll();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->dt] = (float)($r->total ?? 0);
        }
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date' => $d, 'total' => $byDate[$d] ?? 0];
        }
        return $result;
    }

    /**
     * Ingresos mensuales por owner (últimos N meses). Incluye orders_payments y orders_advances.
     * @return array [{year: int, month: int, total: float, label: string}, ...]
     */
    public function getMonthlyTotalsByOwner(int $ownerId, int $months = 12): array
    {
        $start = date('Y-m-01 00:00:00', strtotime("-{$months} months"));
        $end = date('Y-m-t 23:59:59');

        $sql = "
            SELECT YEAR(p.paid_at) as yr, MONTH(p.paid_at) as mo, SUM(p.amount - COALESCE(p.refunded_amount, 0)) as total
            FROM (
                SELECT p.amount, p.refunded_amount, p.paid_at
                FROM orders_payments p
                JOIN orders o ON p.id_order = o.id
                LEFT JOIN orders_suborder s ON s.id = p.id_suborder
                WHERE (o.id_owner = :ownerId1 OR (o.id_owner IS NULL AND o.id_user = :ownerId1))
                AND o.is_archived = 0
                AND (p.id_suborder IS NULL OR p.id_suborder = 0 OR COALESCE(s.is_archived, 0) = 0)
                AND p.paid_at >= :start1 AND p.paid_at <= :end1
                UNION ALL
                SELECT oa.amount, oa.refunded_amount, oa.created_at as paid_at
                FROM orders_advances oa
                JOIN orders o ON oa.id_order = o.id AND oa.is_suborder = 0
                WHERE (o.id_owner = :ownerId2 OR (o.id_owner IS NULL AND o.id_user = :ownerId2))
                AND oa.created_at >= :start2 AND oa.created_at <= :end2
                UNION ALL
                SELECT oa.amount, oa.refunded_amount, oa.created_at as paid_at
                FROM orders_advances oa
                JOIN orders_suborder s ON s.id = oa.id_suborder AND oa.is_suborder = 1
                JOIN orders o ON o.id = s.id_order
                WHERE (o.id_owner = :ownerId3 OR (o.id_owner IS NULL AND o.id_user = :ownerId3))
                AND oa.created_at >= :start3 AND oa.created_at <= :end3
            ) p
            GROUP BY YEAR(p.paid_at), MONTH(p.paid_at)
            ORDER BY yr ASC, mo ASC
        ";
        $this->db->query($sql);
        $this->db->bind(":ownerId1", $ownerId);
        $this->db->bind(":ownerId2", $ownerId);
        $this->db->bind(":ownerId3", $ownerId);
        $this->db->bind(":start1", $start);
        $this->db->bind(":end1", $end);
        $this->db->bind(":start2", $start);
        $this->db->bind(":end2", $end);
        $this->db->bind(":start3", $start);
        $this->db->bind(":end3", $end);
        $rows = $this->db->fetchAll();

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r->yr . '-' . str_pad($r->mo, 2, '0', STR_PAD_LEFT)] = (float)($r->total ?? 0);
        }
        $result = [];
        $monthsEn = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime("-{$i} months");
            $yr = (int)date('Y', $ts);
            $mo = (int)date('n', $ts);
            $key = $yr . '-' . str_pad($mo, 2, '0', STR_PAD_LEFT);
            $result[] = [
                'year' => $yr,
                'month' => $mo,
                'total' => $byKey[$key] ?? 0,
                'label' => $monthsEn[$mo] . ' ' . $yr
            ];
        }
        return $result;
    }

}
