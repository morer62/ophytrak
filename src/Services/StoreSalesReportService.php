<?php

namespace App\Services;

use App\Repositories\Connection;

class StoreSalesReportService
{
    private Connection $db;

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function build(int $ownerId, array $input = []): array
    {
        [$start, $end, $label] = $this->resolveRange(
            (string)($input['preset'] ?? 'this_month'),
            $input['date_from'] ?? null,
            $input['date_to'] ?? null
        );

        $filters = [
            'preset' => (string)($input['preset'] ?? 'this_month'),
            'date_from' => substr($start, 0, 10),
            'date_to' => substr($end, 0, 10),
            'payment_status' => strtoupper(trim((string)($input['payment_status'] ?? ''))),
            'status' => strtoupper(trim((string)($input['status'] ?? ''))),
            'product_id' => max(0, (int)($input['product_id'] ?? 0)),
            'email' => trim((string)($input['email'] ?? '')),
            'search' => trim((string)($input['search'] ?? '')),
        ];

        $page = max(1, (int)($input['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $base = $this->baseWhere($ownerId, $start, $end, $filters);
        $totalRecords = 0;

        try {
            $totalRecords = $this->countOrders($base);
        } catch (\Throwable $exception) {
            error_log('Store sales report count failed: ' . $exception->getMessage());
        }

        $totalPages = max(1, (int)ceil($totalRecords / $limit));

        return [
            'label' => $label,
            'filters' => $filters,
            'stats' => $this->stats($base),
            'orders' => $this->orders($base, $limit, $offset),
            'products' => $this->products($ownerId),
            'productSummary' => $this->productSummary($base),
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'limit' => $limit,
            ],
        ];
    }

    private function baseWhere(int $ownerId, string $start, string $end, array $filters): array
    {
        $where = ['so.id_owner = :owner', 'so.created_at BETWEEN :start AND :end'];
        $params = [':owner' => $ownerId, ':start' => $start, ':end' => $end];

        if ($filters['payment_status'] !== '') {
            $where[] = 'so.payment_status = :payment_status';
            $params[':payment_status'] = $filters['payment_status'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'so.status = :status';
            $params[':status'] = $filters['status'];
        }
        if ($filters['product_id'] > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM store_order_items soi_filter WHERE soi_filter.id_store_order = so.id AND soi_filter.id_owner = so.id_owner AND soi_filter.id_product = :product_id)';
            $params[':product_id'] = $filters['product_id'];
        }
        if ($filters['email'] !== '') {
            $where[] = 'so.guest_email LIKE :email';
            $params[':email'] = '%' . $filters['email'] . '%';
        }
        if ($filters['search'] !== '') {
            $where[] = '(so.guest_name LIKE :search OR so.guest_email LIKE :search OR so.guest_phone LIKE :search OR so.id = :search_id)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search_id'] = ctype_digit($filters['search']) ? (int)$filters['search'] : 0;
        }

        return ['where' => implode(' AND ', $where), 'params' => $params];
    }

    private function stats(array $base): array
    {
        $fallback = [
            'total_orders' => 0,
            'paid_orders' => 0,
            'pending_orders' => 0,
            'return_requested' => 0,
            'returned_orders' => 0,
            'gross_sales' => 0.0,
            'returned_amount' => 0.0,
            'net_sales' => 0.0,
            'average_order' => 0.0,
        ];

        try {
            $row = $this->one("SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN so.payment_status = 'PAID' THEN 1 ELSE 0 END) AS paid_orders,
                SUM(CASE WHEN so.payment_status = 'PENDING' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN so.status = 'RETURN_REQUESTED' THEN 1 ELSE 0 END) AS return_requested,
                SUM(CASE WHEN so.status = 'RETURN_APPROVED' THEN 1 ELSE 0 END) AS return_approved,
                SUM(CASE WHEN so.status = 'RETURN_REJECTED' THEN 1 ELSE 0 END) AS return_rejected,
                SUM(CASE WHEN so.status = 'REDELIVERY_SCHEDULED' THEN 1 ELSE 0 END) AS redelivery_scheduled,
                SUM(CASE WHEN so.status = 'RETURNED' OR so.payment_status = 'REFUNDED' THEN 1 ELSE 0 END) AS returned_orders,
                SUM(CASE WHEN so.status = 'CLOSED' THEN 1 ELSE 0 END) AS closed_orders,
                COALESCE(SUM(CASE WHEN so.payment_status = 'PAID' AND so.status NOT IN ('RETURNED','CANCELLED','CLOSED') THEN so.total ELSE 0 END), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN so.status = 'RETURNED' OR so.payment_status = 'REFUNDED' THEN so.total ELSE 0 END), 0) AS returned_amount,
                COALESCE(AVG(CASE WHEN so.payment_status = 'PAID' AND so.status NOT IN ('RETURNED','CANCELLED','CLOSED') THEN so.total ELSE NULL END), 0) AS average_order
            FROM store_orders so
            WHERE {$base['where']}", $base['params']);
        } catch (\Throwable $exception) {
            error_log('Store sales report stats failed: ' . $exception->getMessage());
            return $fallback;
        }

        if (!is_object($row)) {
            return $fallback;
        }

        $gross = (float)($row->gross_sales ?? 0);
        $returns = (float)($row->returned_amount ?? 0);

        return [
            'total_orders' => (int)($row->total_orders ?? 0),
            'paid_orders' => (int)($row->paid_orders ?? 0),
            'pending_orders' => (int)($row->pending_orders ?? 0),
            'return_requested' => (int)($row->return_requested ?? 0),
            'return_approved' => (int)($row->return_approved ?? 0),
            'return_rejected' => (int)($row->return_rejected ?? 0),
            'redelivery_scheduled' => (int)($row->redelivery_scheduled ?? 0),
            'returned_orders' => (int)($row->returned_orders ?? 0),
            'closed_orders' => (int)($row->closed_orders ?? 0),
            'gross_sales' => round($gross, 2),
            'returned_amount' => round($returns, 2),
            'net_sales' => round(max(0, $gross - $returns), 2),
            'average_order' => round((float)($row->average_order ?? 0), 2),
        ];
    }

    private function orders(array $base, int $limit, int $offset): array
    {
        $sql = "SELECT so.*, latest_payment.id AS payment_id, latest_payment.status AS latest_payment_status,
                latest_payment.payment_method AS latest_payment_method, latest_payment.proof_url AS payment_proof_url,
                latest_payment.external_payment_id, latest_payment.external_reference AS payment_reference,
                workflow.delivery_photo_url, workflow.delivery_notes,
                (SELECT COUNT(*) FROM store_order_items soi_count WHERE soi_count.id_store_order = so.id AND soi_count.id_owner = so.id_owner) AS item_count,
                (SELECT COALESCE(SUM(quantity), 0) FROM store_order_items soi_qty WHERE soi_qty.id_store_order = so.id AND soi_qty.id_owner = so.id_owner) AS quantity_total
            FROM store_orders so
            LEFT JOIN store_payments latest_payment ON latest_payment.id = (
                SELECT sp2.id FROM store_payments sp2 WHERE sp2.id_store_order = so.id AND sp2.id_owner = so.id_owner ORDER BY sp2.id DESC LIMIT 1
            )
            LEFT JOIN store_order_workflow workflow ON workflow.id_store_order = so.id AND workflow.id_owner = so.id_owner
            WHERE {$base['where']}
            ORDER BY so.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";

        try {
            return $this->all($sql, $base['params']);
        } catch (\Throwable $exception) {
            error_log('Store sales report orders failed: ' . $exception->getMessage());
            return [];
        }
    }

    private function productSummary(array $base): array
    {
        try {
            return $this->all("SELECT
                soi.id_product,
                COALESCE(NULLIF(soi.product_name_snapshot, ''), CONCAT('Product #', soi.id_product)) AS product_name,
                SUM(CASE WHEN so.status NOT IN ('RETURNED','CANCELLED','CLOSED') AND so.payment_status <> 'REFUNDED' THEN soi.quantity ELSE 0 END) AS sold_quantity,
                SUM(CASE WHEN so.status = 'RETURNED' OR so.payment_status = 'REFUNDED' THEN soi.quantity ELSE 0 END) AS returned_quantity,
                COALESCE(SUM(CASE WHEN so.status NOT IN ('RETURNED','CANCELLED','CLOSED') AND so.payment_status <> 'REFUNDED' THEN soi.line_total ELSE 0 END), 0) AS sold_amount,
                COALESCE(SUM(CASE WHEN so.status = 'RETURNED' OR so.payment_status = 'REFUNDED' THEN soi.line_total ELSE 0 END), 0) AS returned_amount
            FROM store_orders so
            INNER JOIN store_order_items soi ON soi.id_store_order = so.id AND soi.id_owner = so.id_owner
            WHERE {$base['where']}
            GROUP BY soi.id_product, product_name
            ORDER BY sold_amount DESC, sold_quantity DESC
            LIMIT 50", $base['params']);
        } catch (\Throwable $exception) {
            error_log('Store sales report product summary failed: ' . $exception->getMessage());
            return [];
        }
    }

    private function products(int $ownerId): array
    {
        try {
            return $this->all("SELECT id, name FROM store_products WHERE id_owner = :owner ORDER BY name", [':owner' => $ownerId]);
        } catch (\Throwable $exception) {
            error_log('Store sales report products failed: ' . $exception->getMessage());
            return [];
        }
    }

    private function countOrders(array $base): int
    {
        $row = $this->one("SELECT COUNT(*) total FROM store_orders so WHERE {$base['where']}", $base['params']);
        return (int)($row->total ?? 0);
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

    private function one(string $sql, array $params): object|bool
    {
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        return $this->db->fetchOne();
    }

    private function all(string $sql, array $params): array
    {
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        return $this->db->fetchAll();
    }
}

