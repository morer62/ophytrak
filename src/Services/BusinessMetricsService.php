<?php

namespace App\Services;

use App\Repositories\BusinessEvaluatorSettingsRepository;
use App\Repositories\Connection;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\ModulesRepository;
use App\Repositories\OrdersPaymentsRepository;
use App\Repositories\UserModulesRepository;

class BusinessMetricsService
{
    private Connection $db;
    private BusinessEvaluatorSettingsRepository $settingsRepo;
    private InstitutionProfileRepository $institutionRepo;
    private OrdersPaymentsRepository $ordersPaymentsRepo;
    private ModulesRepository $modulesRepo;
    private UserModulesRepository $userModulesRepo;

    public function __construct()
    {
        $this->db = new Connection();
        $this->settingsRepo = new BusinessEvaluatorSettingsRepository();
        $this->institutionRepo = new InstitutionProfileRepository();
        $this->ordersPaymentsRepo = new OrdersPaymentsRepository();
        $this->modulesRepo = new ModulesRepository();
        $this->userModulesRepo = new UserModulesRepository();
    }

    public function buildSnapshot(int $idOwner, ?int $year = null, ?int $month = null): array
    {
        $year = $year ?: (int)date('Y');
        $month = $month ?: (int)date('n');
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end = date('Y-m-t 23:59:59', strtotime($start));
        $previousStart = date('Y-m-01 00:00:00', strtotime($start . ' -1 month'));
        $previousEnd = date('Y-m-t 23:59:59', strtotime($previousStart));

        $institution = $this->institutionRepo->getByOwner($idOwner);
        $settings = $this->settingsRepo->getByOwner($idOwner);
        $collected = $this->sumCollected($idOwner, $start, $end);
        $previousCollected = $this->sumCollected($idOwner, $previousStart, $previousEnd);
        $activeAddons = $this->getActiveAddonSlugs($idOwner);

        return [
            'id_owner' => $idOwner,
            'period' => [
                'year' => $year,
                'month' => $month,
                'start' => substr($start, 0, 10),
                'end' => substr($end, 0, 10),
                'previous_start' => substr($previousStart, 0, 10),
                'previous_end' => substr($previousEnd, 0, 10),
            ],
            'profile' => $this->profileMetrics($institution, $settings),
            'leads' => [
                'total' => $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner", [':owner' => $idOwner]),
                'current_month' => $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
                'previous_month' => $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $previousStart, ':end' => $previousEnd]),
                'open' => $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND COALESCE(archived, 'NO') <> 'YES'", [':owner' => $idOwner]),
                'archived' => $this->scalarInt("SELECT COUNT(*) total FROM crm_leads WHERE id_owner = :owner AND archived = 'YES'", [':owner' => $idOwner]),
            ],
            'orders' => [
                'total' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0", [':owner' => $idOwner]),
                'current_month' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND event_date BETWEEN :start_date AND :end_date", [':owner' => $idOwner, ':start_date' => substr($start, 0, 10), ':end_date' => substr($end, 0, 10)]),
                'previous_month' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND event_date BETWEEN :start_date AND :end_date", [':owner' => $idOwner, ':start_date' => substr($previousStart, 0, 10), ':end_date' => substr($previousEnd, 0, 10)]),
                'upcoming' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND event_date >= CURDATE()", [':owner' => $idOwner]),
                'paid_full' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND (payment_status = 'paid_full' OR status_workflow = 'INVOICE_PAID')", [':owner' => $idOwner]),
                'pending_payment' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND COALESCE(payment_status, 'pending') <> 'paid_full' AND COALESCE(status_workflow, '') <> 'INVOICE_PAID'", [':owner' => $idOwner]),
                'missing_contract' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND id_contract IS NULL", [':owner' => $idOwner]),
                'pending_signature' => $this->scalarInt("SELECT COUNT(*) total FROM orders o LEFT JOIN orders_acceptance_contracts ac ON ac.id_order = o.id WHERE (o.id_owner = :owner OR (o.id_owner IS NULL AND o.id_user = :owner)) AND o.is_archived = 0 AND o.id_contract IS NOT NULL AND ac.id IS NULL", [':owner' => $idOwner]),
            ],
            'payments' => [
                'collected_current_month' => $collected,
                'collected_previous_month' => $previousCollected,
                'month_over_month_delta' => round($collected - $previousCollected, 2),
                'pending_orders' => $this->scalarInt("SELECT COUNT(*) total FROM orders WHERE (id_owner = :owner OR (id_owner IS NULL AND id_user = :owner)) AND is_archived = 0 AND COALESCE(payment_status, 'pending') <> 'paid_full'", [':owner' => $idOwner]),
            ],
            'team' => [
                'tasks_total' => $this->scalarInt("SELECT COUNT(*) total FROM orders_team_tasks WHERE id_owner = :owner", [':owner' => $idOwner]),
                'tasks_pending' => $this->scalarInt("SELECT COUNT(*) total FROM orders_team_tasks WHERE id_owner = :owner AND is_done = 0", [':owner' => $idOwner])
                    + $this->scalarInt("SELECT COUNT(*) total FROM store_order_tasks WHERE id_owner = :owner AND status NOT IN ('COMPLETED', 'CANCELED')", [':owner' => $idOwner]),
                'tasks_done' => $this->scalarInt("SELECT COUNT(*) total FROM orders_team_tasks WHERE id_owner = :owner AND is_done = 1", [':owner' => $idOwner]),
                'active_team_members' => $this->scalarInt("SELECT COUNT(*) total FROM users WHERE id_owner = :owner AND level = 4", [':owner' => $idOwner]),
            ],
            'modules' => [
                'base' => array_map(static fn($module) => $module->slug ?? '', $this->modulesRepo->getBaseModules()),
                'active_addons' => $activeAddons,
                'available_addons' => array_map(static fn($module) => $module->slug ?? '', $this->modulesRepo->getAddons()),
                'recommendations' => $this->moduleOpportunityMetrics($idOwner, $activeAddons),
            ],
            'commerce' => $this->commerceMetrics($idOwner, $start, $end),
            'snapshot_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function profileMetrics(?object $institution, ?object $settings): array
    {
        $social = [];
        if ($settings && !empty($settings->social_media)) {
            $decoded = json_decode((string)$settings->social_media, true);
            $social = is_array($decoded) ? $decoded : [];
        }

        $fields = [
            'company_name' => $institution->company_name ?? null,
            'business_nature' => $institution->business_nature ?? null,
            'business_operation_type' => $institution->business_operation_type ?? null,
            'logo_path' => $institution->logo_path ?? null,
            'phone' => $institution->phone ?? null,
            'email' => $institution->email ?? null,
            'city' => $institution->city ?? null,
            'state' => $institution->state ?? null,
            'zip' => $institution->zip ?? null,
            'payment_method_accepted' => $institution->payment_method_accepted ?? null,
        ];

        $missing = [];
        foreach ($fields as $field => $value) {
            if ($value === null || trim((string)$value) === '') {
                $missing[] = $field;
            }
        }
        if (empty($social)) {
            $missing[] = 'social_media';
        }

        $total = count($fields) + 1;
        $completed = max(0, $total - count($missing));

        return [
            'company_name' => $institution->company_name ?? null,
            'business_nature' => $institution->business_nature ?? null,
            'business_operation_type' => $institution->business_operation_type ?? null,
            'city' => $institution->city ?? null,
            'state' => $institution->state ?? null,
            'zip' => $institution->zip ?? null,
            'address' => $institution->address_line1 ?? null,
            'business_size' => $settings->business_size ?? null,
            'social_media_count' => count($social),
            'missing_fields' => $missing,
            'completion_percent' => (int)round(($completed / $total) * 100),
        ];
    }

    private function moduleOpportunityMetrics(int $idOwner, array $activeAddons): array
    {
        $storageItems = $this->scalarInt("SELECT COUNT(*) total FROM storage_items WHERE id_owner = :owner", [':owner' => $idOwner]);
        $storeOrders = $this->scalarInt("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner", [':owner' => $idOwner]);
        $ticketSales = 0;

        return [
            'inventory_storage' => [
                'has_signal' => $storageItems > 0,
                'is_active' => in_array('inventory_storage', $activeAddons, true),
                'signal_count' => $storageItems,
            ],
            'store_delivery_tracking' => [
                'has_signal' => $storeOrders > 0,
                'is_active' => in_array('store_delivery_tracking', $activeAddons, true),
                'signal_count' => $storeOrders,
            ],
            'tickets_rsvp' => [
                'has_signal' => $ticketSales > 0,
                'is_active' => in_array('tickets_rsvp', $activeAddons, true),
                'signal_count' => $ticketSales,
            ],
            'ai_advisor' => [
                'has_signal' => true,
                'is_active' => in_array('ai_advisor', $activeAddons, true),
                'signal_count' => 1,
            ],
        ];
    }

    private function commerceMetrics(int $idOwner, string $start, string $end): array
    {
        return [
            'store_orders_current_month' => $this->scalarInt("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'store_orders_pending' => $this->scalarInt("SELECT COUNT(*) total FROM store_orders WHERE id_owner = :owner AND status IN ('NEW', 'CONFIRMED', 'PROCESSING', 'IN_PREPARATION', 'READY', 'READY_FOR_DELIVERY', 'OUT_FOR_DELIVERY')", [':owner' => $idOwner]),
            'store_revenue_current_month' => $this->scalarFloat("SELECT COALESCE(SUM(amount), 0) total FROM store_payments WHERE id_owner = :owner AND status = 'PAID' AND paid_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
            'store_items_sold_current_month' => $this->scalarInt("SELECT COALESCE(SUM(quantity), 0) total FROM store_order_items WHERE id_owner = :owner AND created_at BETWEEN :start AND :end", [':owner' => $idOwner, ':start' => $start, ':end' => $end]),
        ];
    }

    private function getActiveAddonSlugs(int $idOwner): array
    {
        $slugs = [];
        foreach ($this->userModulesRepo->getActiveByUserId($idOwner) as $module) {
            $slug = (string)($module->slug ?? '');
            if ($slug !== '' && in_array($slug, ModulesRepository::ADDON_SLUGS, true)) {
                $slugs[] = $slug;
            }
        }
        return $slugs;
    }

    private function sumCollected(int $idOwner, string $start, string $end): float
    {
        $rows = $this->ordersPaymentsRepo->getCollectedByOwnerWithDateRange($idOwner, $start, $end);
        $total = 0.0;
        foreach ($rows as $row) {
            $amount = (float)($row->amount ?? 0);
            $refunded = (float)($row->refunded_amount ?? 0);
            $total += max(0, $amount - $refunded);
        }
        return round($total, 2);
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
