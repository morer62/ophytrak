<?php

namespace App\Services;

use App\Repositories\BusinessMonthlySnapshotRepository;
use App\Repositories\DailyRecommendationRepository;

class BusinessEvaluatorService
{
    private DailyRecommendationRepository $recommendationRepo;
    private BusinessMonthlySnapshotRepository $monthlySnapshotRepo;
    private BusinessMetricsService $metricsService;

    public function __construct()
    {
        $this->recommendationRepo = new DailyRecommendationRepository();
        $this->monthlySnapshotRepo = new BusinessMonthlySnapshotRepository();
        $this->metricsService = new BusinessMetricsService();
    }

    public function getMetricsSnapshot(int $idOwner): array
    {
        return $this->metricsService->buildSnapshot($idOwner);
    }

    public function getDashboardInsights(int $idOwner): array
    {
        $today = date('Y-m-d');
        $year = (int)date('Y');
        $month = (int)date('n');

        $monthlySnapshot = $this->getOrCreateMonthlySnapshot($idOwner, $year, $month);
        $todayRecommendation = $this->getOrCreateRecommendationForDate($idOwner, $today);
        $recentRecommendations = $this->getRecommendationsByOwner($idOwner, 6);
        $metrics = $this->metricsService->buildSnapshot($idOwner, $year, $month);

        return [
            'monthly_snapshot' => $monthlySnapshot,
            'today_recommendation' => $todayRecommendation,
            'recent_recommendations' => $recentRecommendations,
            'health_cards' => $this->buildHealthCards($metrics),
        ];
    }

    public function getOrCreateRecommendationForDate(int $idOwner, string $date): ?object
    {
        TranslationService::detectLocale();
        $locale = TranslationService::getCurrentLocale();

        $metrics = $this->metricsService->buildSnapshot(
            $idOwner,
            (int)date('Y', strtotime($date)),
            (int)date('n', strtotime($date))
        );
        $insight = $this->buildDailyInsight($metrics);
        $existing = $this->recommendationRepo->getByOwnerAndDate($idOwner, $date, $locale, 'daily_insight');
        if ($existing) {
            $this->recommendationRepo->refreshById((int)$existing->id, [
                'metrics_snapshot' => $metrics,
                'recommendation_text' => $insight['text'],
                'status' => $insight['status'],
                'module_slug' => $insight['module_slug'],
                'action_label' => $insight['action_label'],
                'action_url' => $insight['action_url'],
            ]);
            return $this->recommendationRepo->getByOwnerAndDate($idOwner, $date, $locale, 'daily_insight');
        }

        $ok = $this->recommendationRepo->addWithExplicitOwner([
            'id_owner' => $idOwner,
            'recommendation_date' => $date,
            'recommendation_type' => 'daily_insight',
            'locale' => $locale,
            'metrics_snapshot' => $metrics,
            'recommendation_text' => $insight['text'],
            'status' => $insight['status'],
            'module_slug' => $insight['module_slug'],
            'action_label' => $insight['action_label'],
            'action_url' => $insight['action_url'],
            'source_type' => 'internal_data',
        ]);

        if (!$ok) {
            return null;
        }

        return $this->recommendationRepo->getByOwnerAndDate($idOwner, $date, $locale, 'daily_insight');
    }

    public function getOrCreateMonthlySnapshot(int $idOwner, ?int $year = null, ?int $month = null): ?object
    {
        TranslationService::detectLocale();
        $locale = TranslationService::getCurrentLocale();
        $year = $year ?: (int)date('Y');
        $month = $month ?: (int)date('n');

        $existing = $this->monthlySnapshotRepo->getByOwnerPeriod($idOwner, $year, $month, $locale);
        if ($existing) {
            return $existing;
        }

        $metrics = $this->metricsService->buildSnapshot($idOwner, $year, $month);
        $snapshot = $this->buildMonthlySnapshot($metrics);

        $ok = $this->monthlySnapshotRepo->addWithExplicitOwner([
            'id_owner' => $idOwner,
            'snapshot_year' => $year,
            'snapshot_month' => $month,
            'locale' => $locale,
            'status' => $snapshot['status'],
            'metrics_snapshot' => $metrics,
            'snapshot_text' => $snapshot['text'],
            'recommended_action' => $snapshot['recommended_action'],
            'primary_module_slug' => $snapshot['module_slug'],
            'action_label' => $snapshot['action_label'],
            'action_url' => $snapshot['action_url'],
            'source_type' => 'internal_data',
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$ok) {
            return null;
        }

        return $this->monthlySnapshotRepo->getByOwnerPeriod($idOwner, $year, $month, $locale);
    }

    /** @return object[] */
    public function getRecommendationsByOwner(int $idOwner, int $limit = 30): array
    {
        TranslationService::detectLocale();
        $locale = TranslationService::getCurrentLocale();
        return $this->recommendationRepo->getByOwner($idOwner, $limit, $locale, 'daily_insight');
    }

    private function buildDailyInsight(array $metrics): array
    {
        $profile = $metrics['profile'];
        $leads = $metrics['leads'];
        $orders = $metrics['orders'];
        $payments = $metrics['payments'];
        $team = $metrics['team'];
        $modules = $metrics['modules']['recommendations'];
        $commerce = $metrics['commerce'];

        if ($profile['completion_percent'] < 70) {
            return $this->insight(
                'setup_needed',
                'business_profile',
                'Complete profile',
                'panel/planner-hub/institution-profile',
                'Complete your Business Profile location and contact details so insights use your real operating data.'
            );
        }

        if ((int)$leads['open'] > max(3, (int)$orders['current_month'])) {
            return $this->insight(
                'attention',
                'crm',
                'Review leads',
                'panel/planner-hub/management/crm',
                sprintf('You have %d open leads and %d orders this month. Review follow-ups today.', (int)$leads['open'], (int)$orders['current_month'])
            );
        }

        if ((int)$orders['pending_signature'] > 0) {
            return $this->insight(
                'urgent',
                'contracts',
                'Review contracts',
                'panel/planner-hub/management/orders/contracts',
                sprintf('%d order(s) still need a signed contract. Review signatures before work moves forward.', (int)$orders['pending_signature'])
            );
        }

        if ((int)$orders['pending_payment'] > 0) {
            return $this->insight(
                'attention',
                'orders',
                'Collect payments',
                'panel/planner-hub/management/orders',
                sprintf('%d order(s) still need payment attention. Review balances today.', (int)$orders['pending_payment'])
            );
        }

        if ((int)$team['tasks_pending'] > 0) {
            return $this->insight(
                'attention',
                'team',
                'Review team tasks',
                'panel/planner-hub/management/users',
                sprintf('Your team has %d pending task(s). Clear or reassign them before upcoming work.', (int)$team['tasks_pending'])
            );
        }

        if (($commerce['store_orders_pending'] ?? 0) > 0 && !$modules['store_delivery_tracking']['is_active']) {
            return $this->insight(
                'attention',
                'store_delivery_tracking',
                'Review Store + Delivery',
                'panel/membership/manage',
                'Store activity is waiting for fulfillment. Review Store + Delivery activation.'
            );
        }

        if (($payments['collected_current_month'] ?? 0) < ($payments['collected_previous_month'] ?? 0)) {
            return $this->insight(
                'attention',
                'orders',
                'Review revenue',
                'panel/planner-hub/management/payments',
                'Collected revenue is below last month so far. Review unpaid orders and open leads.'
            );
        }

        return $this->insight(
            'healthy',
            'orders',
            'Open operations',
            'panel/planner-hub',
            'Operations look stable today. Keep orders, payments and team tasks current.'
        );
    }

    private function buildMonthlySnapshot(array $metrics): array
    {
        $leads = $metrics['leads'];
        $orders = $metrics['orders'];
        $payments = $metrics['payments'];
        $team = $metrics['team'];
        $profile = $metrics['profile'];

        $status = 'healthy';
        $action = 'Keep CRM, orders, payments and team tasks current.';
        $moduleSlug = 'orders';
        $actionLabel = 'Open operations';
        $actionUrl = 'panel/planner-hub';

        if ($profile['completion_percent'] < 70) {
            $status = 'setup_needed';
            $action = 'Complete your business profile so recommendations can use your real industry, location and operating type.';
            $moduleSlug = 'business_profile';
            $actionLabel = 'Complete profile';
            $actionUrl = 'panel/planner-hub/institution-profile';
        } elseif ((int)$orders['pending_signature'] > 0 || (int)$orders['pending_payment'] > 0) {
            $status = 'urgent';
            $action = 'Resolve pending signatures and payments before pushing more new work into the pipeline.';
            $moduleSlug = (int)$orders['pending_signature'] > 0 ? 'contracts' : 'orders';
            $actionLabel = (int)$orders['pending_signature'] > 0 ? 'Review contracts' : 'Review payments';
            $actionUrl = (int)$orders['pending_signature'] > 0 ? 'panel/planner-hub/management/orders/contracts' : 'panel/planner-hub/management/orders';
        } elseif ((int)$leads['current_month'] > (int)$orders['current_month']) {
            $status = 'attention';
            $action = 'Turn this month\'s leads into confirmed orders with sharper follow-up and cleaner handoff into contracts.';
            $moduleSlug = 'crm';
            $actionLabel = 'Review CRM';
            $actionUrl = 'panel/planner-hub/management/crm';
        } elseif ((int)$team['tasks_pending'] > 0) {
            $status = 'attention';
            $action = 'Clean up pending team tasks before they become bottlenecks in upcoming work.';
            $moduleSlug = 'team';
            $actionLabel = 'Review team';
            $actionUrl = 'panel/planner-hub/management/users';
        }

        $revenueText = $this->formatMoney((float)$payments['collected_current_month']);
        $previousRevenueText = $this->formatMoney((float)$payments['collected_previous_month']);
        $delta = (float)$payments['month_over_month_delta'];
        $trend = $delta >= 0
            ? 'up ' . $this->formatMoney($delta) . ' from last month'
            : 'down ' . $this->formatMoney(abs($delta)) . ' from last month';

        $text = sprintf(
            'This month, your business has %d new lead(s), %d order(s), %s collected revenue and %d pending team task(s). Last month collected revenue was %s, so revenue is %s. %s',
            (int)$leads['current_month'],
            (int)$orders['current_month'],
            $revenueText,
            (int)$team['tasks_pending'],
            $previousRevenueText,
            $trend,
            $action
        );

        return [
            'status' => $status,
            'text' => $text,
            'recommended_action' => $action,
            'module_slug' => $moduleSlug,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
        ];
    }

    private function buildHealthCards(array $metrics): array
    {
        return [
            $this->healthCard('Leads', (string)$metrics['leads']['open'], 'Open leads', $metrics['leads']['open'] > $metrics['orders']['current_month'] ? 'attention' : 'healthy', 'panel/planner-hub/management/crm'),
            $this->healthCard('Orders', (string)$metrics['orders']['current_month'], 'This month', $metrics['orders']['pending_payment'] > 0 ? 'attention' : 'healthy', 'panel/planner-hub/management/orders'),
            $this->healthCard('Payments', $this->formatMoney((float)$metrics['payments']['collected_current_month']), 'Collected this month', $metrics['payments']['pending_orders'] > 0 ? 'attention' : 'healthy', 'panel/planner-hub/management/payments'),
            $this->healthCard('Contracts', (string)$metrics['orders']['pending_signature'], 'Pending signatures', $metrics['orders']['pending_signature'] > 0 ? 'urgent' : 'healthy', 'panel/planner-hub/management/orders/contracts'),
            $this->healthCard('Team', (string)$metrics['team']['tasks_pending'], 'Pending tasks', $metrics['team']['tasks_pending'] > 0 ? 'attention' : 'healthy', 'panel/planner-hub/management/users'),
            $this->healthCard('Profile', $metrics['profile']['completion_percent'] . '%', 'Complete', $metrics['profile']['completion_percent'] < 70 ? 'setup_needed' : 'healthy', 'panel/planner-hub/institution-profile'),
            $this->healthCard('Modules', (string)count($metrics['modules']['active_addons']), 'Active add-ons', 'healthy', 'panel/membership/manage'),
        ];
    }

    private function insight(string $status, string $moduleSlug, string $actionLabel, string $actionUrl, string $text): array
    {
        return [
            'status' => $status,
            'module_slug' => $moduleSlug,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'text' => $text,
        ];
    }

    private function healthCard(string $label, string $value, string $helper, string $status, string $url): array
    {
        return compact('label', 'value', 'helper', 'status', 'url');
    }

    private function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }
}
