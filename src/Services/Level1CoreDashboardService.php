<?php

namespace App\Services;

use App\Entity\User;
use App\Repositories\Connection;

class Level1CoreDashboardService
{
    private Connection $db;
    private CentralOperationsContextService $contextService;

    public function __construct()
    {
        $this->db = new Connection();
        $this->contextService = new CentralOperationsContextService();
    }

    public function build($user = null): array
    {
        $context = $this->contextService->getContext($user instanceof User ? $user : null);
        $operations = [];

        foreach ($context['operations'] ?? [] as $operation) {
            $operations[$operation['key']] = $operation;
        }

        $vnv = $operations['vnv_events'] ?? null;
        $avomeal = $operations['avomeal'] ?? null;

        return [
            'context' => $context,
            'ophyra' => $this->platformMetrics(),
            'vnv' => $this->vnvMetrics((int)($vnv['owner_id'] ?? 0), $vnv),
            'avomeal' => $this->avomealMetrics((int)($avomeal['owner_id'] ?? 0), $avomeal),
        ];
    }

    private function platformMetrics(): array
    {
        $moduleSlugs = [
            'base_profile' => ['base_profile', 'ophyra_base'],
            'service_operations' => ['orders_operations', 'service_operations', 'crm'],
            'store_logistics' => ['store_delivery_tracking', 'store_logistics'],
            'ai_advisor' => ['ai_advisor'],
            'tickets_rsvp' => ['tickets_rsvp', 'ticket_sales_rsvp'],
            'advanced_storage' => ['inventory_storage', 'advanced_storage'],
            'marketplace_connectors' => ['marketplace_connectors'],
        ];

        $modules = [];
        foreach ($moduleSlugs as $key => $slugs) {
            $modules[$key] = $this->countIn('user_modules', 'module_slug', $slugs, "status = 'ACTIVE'");
        }

        return [
            'total_users' => $this->countSql("SELECT COUNT(*) total FROM users"),
            'global_active_users' => $this->countSql("SELECT COUNT(*) total FROM users WHERE is_active = 1"),
            'businesses' => $this->countSql("SELECT COUNT(*) total FROM users WHERE level = '2' AND is_active = 1"),
            'team_users' => $this->countSql("SELECT COUNT(*) total FROM users WHERE level = '4' AND is_active = 1"),
            'operational_clients' => $this->countSql("SELECT COUNT(*) total FROM users WHERE level = '5' AND is_active = 1"),
            'active_memberships' => $this->countSql("SELECT COUNT(*) total FROM users WHERE membership_type = 'PAID' AND membership_due_date >= CURDATE()"),
            'renewals_due' => $this->countSql("SELECT COUNT(*) total FROM users WHERE membership_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            'expired_memberships' => $this->countSql("SELECT COUNT(*) total FROM users WHERE membership_due_date IS NOT NULL AND membership_due_date < CURDATE()"),
            'active_modules' => $this->countSql("SELECT COUNT(*) total FROM user_modules WHERE status = 'ACTIVE'"),
            'pending_modules' => $this->countSql("SELECT COUNT(*) total FROM user_modules WHERE status = 'PENDING'"),
            'pending_domains' => $this->countSql("SELECT COUNT(*) total FROM institution_profile WHERE custom_domain_status = 'PENDING'"),
            'subscription_revenue_month' => $this->floatSql("SELECT COALESCE(SUM(total), 0) total FROM payments_all WHERE concept = 'Membership' AND status = 'ACTIVE' AND payment_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())"),
            'cms_pages' => $this->ophyraPageCount(),
            'modules' => $modules,
        ];
    }

    private function vnvMetrics(int $ownerId, ?array $operation): array
    {
        if ($ownerId <= 0) {
            return $this->emptyOperationMetrics($operation);
        }

        $report = [];
        try {
            $report = (new BusinessOperationsReportService())->build($ownerId, 'this_month');
        } catch (\Throwable $e) {
            error_log('Level1CoreDashboardService report failed: ' . $e->getMessage());
        }

        return [
            'operation' => $operation,
            'owner_id' => $ownerId,
            'open_orders' => $this->countSql("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0", [':owner' => $ownerId]),
            'upcoming_events' => $this->countSql("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND event_date >= CURDATE()", [':owner' => $ownerId]),
            'pending_payments' => (int)($report['orders_pending_payment'] ?? 0),
            'monthly_sales' => (float)($report['service_revenue_collected'] ?? 0),
            'clients' => $this->countSql("SELECT COUNT(*) total FROM clients_users cu JOIN users u ON u.id = cu.client_id WHERE cu.id_owner_asociated = :owner AND u.is_active = 1", [':owner' => $ownerId]),
            'team_members' => $this->teamMembersByOwner($ownerId),
            'pending_tasks' => $this->countSql("SELECT COUNT(*) total FROM orders_team_tasks WHERE id_owner = :owner AND is_done = 0", [':owner' => $ownerId]),
            'pending_contracts' => $this->countSql("SELECT COUNT(*) total FROM orders o LEFT JOIN orders_acceptance_contracts ac ON ac.id_order = o.id WHERE (o.id_owner = :owner OR (o.id_owner IS NULL AND o.id_user = :owner)) AND o.is_archived = 0 AND o.id_contract IS NOT NULL AND ac.id IS NULL", [':owner' => $ownerId]),
            'recent_orders' => $this->rowsSql("
                SELECT
                    o.id,
                    o.event_date,
                    o.address,
                    o.payment_status,
                    o.status_workflow,
                    TRIM(CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.lastname, ''))) AS client_name
                FROM orders o
                LEFT JOIN users u ON u.id = o.id_client
                WHERE (o.id_owner = :owner OR (o.id_owner IS NULL AND o.id_user = :owner))
                  AND o.is_archived = 0
                ORDER BY o.created_at DESC
                LIMIT 5
            ", [':owner' => $ownerId]),
            'ai_recommendation' => $this->latestRecommendation($ownerId),
            'cms_pages' => $this->cmsPages($ownerId),
            'tickets_sold' => $this->countSql("SELECT COALESCE(SUM(ts.quantity), 0) total FROM ticket_sales ts INNER JOIN ticket_types tt ON tt.id = ts.id_ticket_type INNER JOIN venue_events_tickets vet ON vet.id = tt.id_venue_event_tickets INNER JOIN venue_events ve ON ve.id = vet.id_venue_event INNER JOIN venues v ON v.id = ve.venue_id WHERE v.user_id = :owner AND ts.payment_status = 'paid'", [':owner' => $ownerId]),
        ];
    }

    private function avomealMetrics(int $ownerId, ?array $operation): array
    {
        if ($ownerId <= 0) {
            return $this->emptyOperationMetrics($operation);
        }

        return [
            'operation' => $operation,
            'owner_id' => $ownerId,
            'new_orders' => $this->countSql("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND site_key = :site_key AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'pending_fulfillment' => $this->countSql("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND site_key = :site_key AND status IN ('NEW','CONFIRMED','PROCESSING','IN_PREPARATION','READY','READY_FOR_DELIVERY')", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'delivery_pending' => $this->countSql("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND site_key = :site_key AND status IN ('READY_FOR_DELIVERY','OUT_FOR_DELIVERY')", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'monthly_sales' => $this->floatSql("SELECT COALESCE(SUM(sp.amount), 0) total FROM store_payments sp INNER JOIN store_orders so ON so.id = sp.id_store_order WHERE sp.id_owner = :owner AND so.site_key = :site_key AND sp.status = 'PAID' AND sp.paid_at BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'active_products' => $this->countSql("SELECT COUNT(*) total FROM store_products WHERE id_owner = :owner AND site_key = :site_key AND status = 'ACTIVE'", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'inactive_products' => $this->countSql("SELECT COUNT(*) total FROM store_products WHERE id_owner = :owner AND site_key = :site_key AND status <> 'ACTIVE'", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'customers' => $this->countSql("SELECT COUNT(DISTINCT COALESCE(CAST(id_user AS CHAR), guest_email)) total FROM store_orders WHERE id_owner = :owner AND site_key = :site_key", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'abandoned_carts' => $this->countSql("SELECT COUNT(*) total FROM store_carts WHERE id_owner = :owner AND site_key = :site_key AND status IN ('ACTIVE','ABANDONED')", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'team_members' => $this->teamMembersByOwner($ownerId),
            'pending_tasks' => $this->countSql("SELECT COUNT(*) total FROM orders_team_tasks WHERE id_owner = :owner AND is_done = 0", [':owner' => $ownerId]),
            'recent_orders' => $this->rowsSql("SELECT id, guest_name, guest_email, total, payment_status, status, created_at FROM store_orders WHERE id_owner = :owner AND site_key = :site_key ORDER BY created_at DESC LIMIT 5", [':owner' => $ownerId, ':site_key' => 'avomeal']),
            'ai_recommendation' => $this->latestRecommendation($ownerId),
            'cms_pages' => $this->cmsPages($ownerId, 'avomeal'),
        ];
    }

    private function emptyOperationMetrics(?array $operation): array
    {
        return [
            'operation' => $operation,
            'owner_id' => 0,
            'open_orders' => 0,
            'upcoming_events' => 0,
            'pending_payments' => 0,
            'monthly_sales' => 0,
            'clients' => 0,
            'team_members' => 0,
            'pending_tasks' => 0,
            'pending_contracts' => 0,
            'new_orders' => 0,
            'pending_fulfillment' => 0,
            'delivery_pending' => 0,
            'active_products' => 0,
            'inactive_products' => 0,
            'customers' => 0,
            'abandoned_carts' => 0,
            'recent_orders' => [],
            'ai_recommendation' => null,
            'cms_pages' => 0,
            'tickets_sold' => 0,
        ];
    }

    private function latestRecommendation(int $ownerId): ?object
    {
        return $this->rowSql("SELECT recommendation_text, status, action_label, action_url FROM daily_recommendations WHERE id_owner = :owner ORDER BY recommendation_date DESC, id DESC LIMIT 1", [':owner' => $ownerId]);
    }

    private function ophyraPageCount(): int
    {
        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? 'https://ophyra.com'), '/');
        $service = new OphyraLandingPageService();
        return count($service->getModulePages($appUrl)) + count($service->getIndustryPages($appUrl)) + 4;
    }

    private function cmsPages(int $ownerId, ?string $siteKey = null): int
    {
        $params = [':owner' => $ownerId];
        $siteWhere = '';

        if ($siteKey !== null) {
            $params[':site_key'] = $siteKey;
            $siteWhere = ' AND site_key = :site_key';
        }

        return $this->countSql("SELECT COUNT(*) total FROM cms_contents WHERE id_owner = :owner{$siteWhere}", $params)
            + $this->countSql("SELECT COUNT(*) total FROM cms_location_pages WHERE id_owner = :owner{$siteWhere}", $params)
            + $this->countSql("SELECT COUNT(*) total FROM cms_routes WHERE id_owner = :owner{$siteWhere}", $params);
    }

    private function teamMembersByOwner(int $ownerId): int
    {
        return $this->countSql("
            SELECT COUNT(DISTINCT u.id) total
            FROM users u
            LEFT JOIN user_institutions ui ON ui.user_id = u.id AND ui.is_active = 1
            LEFT JOIN institution_profile ip ON ip.id = ui.institution_id OR ip.id = ui.secondary_institution_id
            WHERE u.is_active = 1
              AND u.level = 4
              AND (
                u.id_owner = :owner
                OR ip.id_owner = :owner
              )
        ", [':owner' => $ownerId]);
    }

    private function countIn(string $table, string $column, array $values, string $extraWhere = ''): int
    {
        if (empty($values)) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = ':v' . $index;
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        $where = "`{$column}` IN (" . implode(',', $placeholders) . ")";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        return $this->countSql("SELECT COUNT(*) total FROM `{$table}` WHERE {$where}", $params);
    }

    private function countSql(string $sql, array $params = []): int
    {
        return (int)$this->scalarSql($sql, $params);
    }

    private function floatSql(string $sql, array $params = []): float
    {
        return (float)$this->scalarSql($sql, $params);
    }

    private function scalarSql(string $sql, array $params = [])
    {
        $row = $this->rowSql($sql, $params);
        return $row->total ?? 0;
    }

    private function rowSql(string $sql, array $params = []): ?object
    {
        try {
            $this->db->query($sql);
            foreach ($params as $key => $value) {
                $this->db->bind($key, $value);
            }
            $row = $this->db->fetchOne();
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('Level1CoreDashboardService query failed: ' . $e->getMessage());
            return null;
        }
    }

    private function rowsSql(string $sql, array $params = []): array
    {
        try {
            $this->db->query($sql);
            foreach ($params as $key => $value) {
                $this->db->bind($key, $value);
            }
            return $this->db->fetchAll();
        } catch (\Throwable $e) {
            error_log('Level1CoreDashboardService query failed: ' . $e->getMessage());
            return [];
        }
    }
}
