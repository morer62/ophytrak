<?php

namespace App\Services;

use App\Repositories\Connection;
use App\Repositories\OrdersPaymentsRepository;

class BusinessOperationsReportService
{
    private Connection $db;
    private OrdersPaymentsRepository $ordersPaymentsRepo;

    public function __construct()
    {
        $this->db = new Connection();
        $this->ordersPaymentsRepo = new OrdersPaymentsRepository();
    }

    public function build(int $idOwner, string $preset = 'this_month', ?string $from = null, ?string $to = null): array
    {
        [$start, $end, $label] = $this->resolveRange($preset, $from, $to);
        $serviceRevenue = $this->sumServicePayments($idOwner, $start, $end);
        $storeRevenue = $this->scalarFloat("
            SELECT COALESCE(SUM(amount), 0) total
            FROM store_payments
            WHERE id_owner = :owner AND status = 'PAID' AND paid_at BETWEEN :start AND :end
        ", [':owner' => $idOwner, ':start' => $start, ':end' => $end]);

        $leadsOpen = $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND COALESCE(archived, 'NO') <> 'YES'", [':owner' => $idOwner]);
        $leadsClosed = $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND archived = 'YES' AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]);
        $leadsCreated = $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]);

        return [
            'preset' => $preset,
            'from' => substr($start, 0, 10),
            'to' => substr($end, 0, 10),
            'label' => $label,
            'orders_created' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'orders_paid' => $this->scalarInt("
                SELECT COUNT(DISTINCT o.id) total
                FROM orders o
                LEFT JOIN orders_payments p ON p.id_order = o.id
                LEFT JOIN orders_advances oa ON oa.id_order = o.id AND oa.is_suborder = 0
                WHERE (o.id_owner = :owner OR (o.id_owner IS NULL AND o.id_user = :owner))
                  AND o.is_archived = 0
                  AND (o.payment_status = 'paid_full' OR o.status_workflow = 'INVOICE_PAID')
                  AND (
                    p.paid_at BETWEEN :start1 AND :end1
                    OR oa.created_at BETWEEN :start2 AND :end2
                  )
            ", [':owner' => $idOwner, ':start1' => $start, ':end1' => $end, ':start2' => $start, ':end2' => $end]),
            'orders_pending_payment' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND COALESCE(payment_status, 'pending') <> 'paid_full'", [':owner' => $idOwner]),
            'orders_unsigned_contracts' => $this->scalarInt("SELECT COUNT(*) total FROM orders o LEFT JOIN orders_acceptance_contracts ac ON ac.id_order = o.id WHERE (o.id_owner = :owner OR (o.id_owner IS NULL AND o.id_user = :owner)) AND o.is_archived = 0 AND o.id_contract IS NOT NULL AND ac.id IS NULL", [':owner' => $idOwner]),
            'service_revenue_collected' => round($serviceRevenue, 2),
            'store_orders_paid' => $this->scalarInt("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND payment_status = 'PAID' AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'store_orders_pending' => $this->scalarInt("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND payment_status = 'PENDING'", [':owner' => $idOwner]),
            'store_revenue_collected' => round($storeRevenue, 2),
            'revenue_collected' => round($serviceRevenue + $storeRevenue, 2),
            'failed_store_payments' => $this->scalarInt("SELECT COUNT(*) total FROM store_payments WHERE id_owner = :owner AND status = 'FAILED' AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'refunded_store_payments' => $this->scalarInt("SELECT COUNT(*) total FROM store_payments WHERE id_owner = :owner AND status = 'REFUNDED' AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'abandoned_carts' => $this->scalarInt("SELECT COUNT(*) total FROM store_carts WHERE id_owner = :owner AND status IN ('ACTIVE', 'ABANDONED') AND COALESCE(last_activity_at, created_at) BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'payroll_pending_hours' => $this->scalarFloat("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))) / 60, 0) total FROM payroll_hours WHERE id_owner = :owner AND is_paid = 0 AND start_time BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'payroll_paid_batches' => $this->scalarInt("SELECT COUNT(*) total FROM payroll_payments WHERE id_owner = :owner AND paid_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'tasks_pending' => $this->scalarInt("SELECT COUNT(*) total FROM orders_team_tasks WHERE id_owner = :owner AND is_done = 0", [':owner' => $idOwner])
                + $this->scalarInt("SELECT COUNT(*) total FROM store_order_tasks WHERE id_owner = :owner AND status NOT IN ('COMPLETED', 'CANCELED')", [':owner' => $idOwner]),
            'leads_open' => $leadsOpen,
            'leads_closed' => $leadsClosed,
            'leads_created' => $leadsCreated,
            'lead_conversion_rate' => $leadsCreated > 0 ? round(($leadsClosed / $leadsCreated) * 100, 1) : 0.0,
        ];
    }

    private function resolveRange(string $preset, ?string $from, ?string $to): array
    {
        $today = date('Y-m-d');
        if ($preset === 'today') {
            return ["{$today} 00:00:00", "{$today} 23:59:59", 'Today'];
        }
        if ($preset === 'this_week') {
            return [date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 23:59:59', strtotime('sunday this week')), 'This week'];
        }
        if ($preset === 'last_month') {
            return [date('Y-m-01 00:00:00', strtotime('first day of last month')), date('Y-m-t 23:59:59', strtotime('last day of last month')), 'Last month'];
        }
        if ($preset === 'custom' && $this->validDate($from) && $this->validDate($to)) {
            return ["{$from} 00:00:00", "{$to} 23:59:59", "{$from} to {$to}"];
        }
        return [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'), 'This month'];
    }

    private function validDate(?string $date): bool
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    private function sumServicePayments(int $idOwner, string $start, string $end): float
    {
        $total = 0.0;
        foreach ($this->ordersPaymentsRepo->getCollectedByOwnerWithDateRange($idOwner, $start, $end) as $payment) {
            $total += max(0, (float)($payment->amount ?? 0) - (float)($payment->refunded_amount ?? 0));
        }
        return $total;
    }

    private function scalarInt(string $sql, array $params): int
    {
        return (int)$this->scalar($sql, $params);
    }

    private function scalarFloat(string $sql, array $params): float
    {
        return (float)$this->scalar($sql, $params);
    }

    private function scalar(string $sql, array $params)
    {
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $row = $this->db->fetchOne();
        return $row->total ?? 0;
    }
}
