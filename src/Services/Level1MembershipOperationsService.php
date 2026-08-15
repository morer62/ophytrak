<?php

namespace App\Services;

use App\Repositories\AdminAccountActionsRepository;
use App\Repositories\Connection;
use App\Repositories\ModulesRepository;
use App\Repositories\UserModulesRepository;
use App\Utils\BillingDateUtils;
use DateTimeImmutable;
use PDOException;
use Throwable;

final class Level1MembershipOperationsService
{
    private Connection $db;
    private OphyraPricingService $pricing;
    private CurrencyPricingService $currencyPricing;
    private AddonBillingService $addonBilling;
    private ModulesRepository $modulesRepository;
    private array $columnCache = [];

    public function __construct()
    {
        $this->db = new Connection();
        $this->pricing = new OphyraPricingService();
        $this->currencyPricing = new CurrencyPricingService();
        $this->addonBilling = new AddonBillingService(null, null, $this->currencyPricing, $this->pricing);
        $this->modulesRepository = new ModulesRepository();
    }

    public function buildHomeAlert(): array
    {
        $summary = $this->getRenewalSummary();
        $lastCronRun = $this->getLastCronRun();
        $cronStale = $lastCronRun === '';

        if ($summary['failed_count'] > 0) {
            return [
                'tone' => 'danger',
                'title' => 'Some membership payments failed',
                'text' => $summary['failed_count'] . ' customers need payment attention.',
                'primary_url' => 'panel/membership-renewal-queue?filter=failed_payment',
                'primary_label' => 'Review failed payments',
                'secondary_url' => 'panel/membership-reports?tab=failed',
                'secondary_label' => 'View reports',
                'eligible_count' => $summary['eligible_count'],
                'ineligible_count' => $summary['ineligible_count'],
                'last_cron_run' => $lastCronRun ?: 'Not detected',
                'cron_stale' => $cronStale,
            ];
        }

        if ($summary['due_count'] > 0) {
            return [
                'tone' => 'warning',
                'title' => 'Membership renewals need review',
                'text' => $summary['due_count'] . ' customers are due or expired. Estimated amount: ' . $summary['estimated_amount_label'] . '.',
                'primary_url' => 'panel/membership-renewal-queue',
                'primary_label' => 'Review renewal queue',
                'secondary_url' => 'panel/membership-renewal-queue?preview=1&filter=eligible',
                'secondary_label' => 'Charge eligible customers',
                'eligible_count' => $summary['eligible_count'],
                'ineligible_count' => $summary['ineligible_count'],
                'last_cron_run' => $lastCronRun ?: 'Not detected',
                'cron_stale' => $cronStale,
            ];
        }

        return [
            'tone' => $cronStale ? 'warning' : 'success',
            'title' => $cronStale ? 'Membership cron needs review' : 'No pending membership charges in Ophyra.',
            'text' => $cronStale ? 'Last run was not detected. Open the renewal queue to run a manual review.' : 'All membership renewals are up to date.',
            'primary_url' => 'panel/membership-renewal-queue',
            'primary_label' => $cronStale ? 'Run manual check' : 'View renewal queue',
            'secondary_url' => 'panel/membership-reports',
            'secondary_label' => 'Membership reports',
            'eligible_count' => $summary['eligible_count'],
            'ineligible_count' => $summary['ineligible_count'],
            'last_cron_run' => $lastCronRun ?: 'Not detected',
            'cron_stale' => $cronStale,
        ];
    }

    public function getRenewalSummary(): array
    {
        $rows = $this->listCustomers(['filter' => 'due_or_expired', 'per_page' => 500, 'page' => 1])['rows'];
        $dueCount = count($rows);
        $eligible = 0;
        $ineligible = 0;
        $failed = 0;
        $estimated = 0.0;

        foreach ($rows as $row) {
            if ($row['failed_attempts'] > 0 || strtolower($row['billing_status']) === 'payment_failed') {
                $failed++;
            }
            if ($row['eligible_to_charge']) {
                $eligible++;
                $estimated += $row['amount_due'];
            } else {
                $ineligible++;
            }
        }

        return [
            'due_count' => $dueCount,
            'eligible_count' => $eligible,
            'ineligible_count' => $ineligible,
            'failed_count' => $failed,
            'estimated_amount' => $estimated,
            'estimated_amount_label' => $this->formatMoney($estimated),
        ];
    }

    public function listCustomers(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }
        $offset = ($page - 1) * $perPage;
        $search = trim((string)($filters['search'] ?? ''));
        $filter = trim((string)($filters['filter'] ?? 'all'));
        $module = trim((string)($filters['module'] ?? ''));

        $where = ['u.level = 2'];
        $params = [];

        if ($search !== '') {
            $businessSlugSearch = $this->hasColumn('institution_profile', 'slug') ? ' OR ip.slug LIKE :search' : '';
            $where[] = '(u.name LIKE :search OR u.lastname LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR CAST(u.id AS CHAR) LIKE :search OR ip.company_name LIKE :search' . $businessSlugSearch . ' OR CAST(ip.id AS CHAR) LIKE :search OR EXISTS (SELECT 1 FROM user_modules ums WHERE ums.id_user = u.id AND ums.module_slug LIKE :search))';
            $params[':search'] = '%' . $search . '%';
        }

        if ($module !== '') {
            $where[] = "EXISTS (SELECT 1 FROM user_modules umf WHERE umf.id_user = u.id AND umf.module_slug = :module_slug AND umf.status = 'ACTIVE')";
            $params[':module_slug'] = $module;
        }

        switch ($filter) {
            case 'active_membership':
                $where[] = 'u.membership_due_date IS NOT NULL AND u.membership_due_date >= CURDATE()';
                break;
            case 'expired_membership':
            case 'past_due':
                $where[] = 'u.membership_due_date IS NOT NULL AND u.membership_due_date < CURDATE()';
                break;
            case 'due_soon':
                $where[] = 'u.membership_due_date IS NOT NULL AND u.membership_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'failed_payment':
                $where[] = "(u.billing_failure_count > 0 OR u.billing_status IN ('payment_failed','past_due','failed'))";
                break;
            case 'has_payment_method':
                $where[] = 'EXISTS (SELECT 1 FROM user_cards uc WHERE uc.id_user = u.id)';
                break;
            case 'no_payment_method':
                $where[] = 'NOT EXISTS (SELECT 1 FROM user_cards uc WHERE uc.id_user = u.id)';
                break;
            case 'has_paid_modules':
                $where[] = "EXISTS (SELECT 1 FROM user_modules um WHERE um.id_user = u.id AND um.status = 'ACTIVE' AND COALESCE(um.price, 0) > 0)";
                break;
            case 'courtesy_modules':
                $courtesySourceClause = $this->hasColumn('user_modules', 'activation_source') ? " OR um.activation_source IN ('manual_gift','courtesy','admin_courtesy')" : '';
                $where[] = "EXISTS (SELECT 1 FROM user_modules um WHERE um.id_user = u.id AND um.status = 'ACTIVE' AND (COALESCE(um.price, 0) = 0{$courtesySourceClause}))";
                break;
            case 'no_modules':
                $where[] = "NOT EXISTS (SELECT 1 FROM user_modules um WHERE um.id_user = u.id AND um.status = 'ACTIVE')";
                break;
            case 'pending_cancellation':
                $where[] = $this->hasColumn('user_modules', 'billing_status')
                    ? "EXISTS (SELECT 1 FROM user_modules um WHERE um.id_user = u.id AND um.billing_status = 'cancel_at_period_end')"
                    : '1=0';
                break;
            case 'spam_suspected':
                $where[] = "(u.is_active = 0 OR u.email LIKE '%test%' OR u.email LIKE '%spam%' OR u.email LIKE '%mailinator%' OR u.email LIKE '%tempmail%')";
                break;
            case 'new_signups':
                $where[] = $this->hasColumn('users', 'created_at') ? 'u.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)' : 'u.id > 0';
                break;
            case 'email_not_verified':
                if ($this->hasColumn('users', 'email_verified_at')) {
                    $where[] = 'u.email_verified_at IS NULL';
                } elseif ($this->hasColumn('users', 'phone_validation')) {
                    $where[] = 'COALESCE(u.phone_validation, 0) = 0';
                }
                break;
            case 'eligible':
            case 'due_or_expired':
                $where[] = 'u.membership_due_date IS NOT NULL AND u.membership_due_date <= CURDATE()';
                break;
        }

        $whereSql = implode(' AND ', $where);
        $createdSelect = $this->hasColumn('users', 'created_at') ? 'u.created_at' : 'NULL AS created_at';
        $lastLoginSelect = $this->hasColumn('users', 'last_login') ? 'u.last_login' : 'NULL AS last_login';
        $emailVerifiedSelect = $this->hasColumn('users', 'email_verified_at') ? 'u.email_verified_at' : 'NULL AS email_verified_at';
        $ipSlugSelect = $this->hasColumn('institution_profile', 'slug') ? 'ip.slug' : 'NULL AS slug';

        $this->db->query("SELECT COUNT(*) AS total FROM users u LEFT JOIN institution_profile ip ON ip.id_owner = u.id WHERE {$whereSql}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $total = (int)($this->db->fetchOne()->total ?? 0);

        $this->db->query("
            SELECT u.id, u.name, u.lastname, u.email, u.phone, u.level, u.is_active, u.membership_type, u.membership_due_date,
                   u.billing_status, u.billing_failure_count, u.billing_status_updated_at,
                   {$createdSelect}, {$lastLoginSelect}, {$emailVerifiedSelect},
                   ip.id AS business_id, ip.company_name, {$ipSlugSelect}
            FROM users u
            LEFT JOIN institution_profile ip ON ip.id_owner = u.id
            WHERE {$whereSql}
            ORDER BY u.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $rows = [];
        foreach ($this->db->fetchAll() as $raw) {
            $rows[] = $this->normalizeCustomerRow($raw);
        }

        if ($filter === 'eligible') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => $row['eligible_to_charge']));
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
            'filters' => compact('search', 'filter', 'module'),
        ];
    }

    public function getCustomerBilling(int $customerId): ?array
    {
        $slugSelect = $this->hasColumn('institution_profile', 'slug') ? 'ip.slug' : 'NULL AS slug';
        $this->db->query("
            SELECT u.*, ip.id AS business_id, ip.company_name, {$slugSelect}
            FROM users u
            LEFT JOIN institution_profile ip ON ip.id_owner = u.id
            WHERE u.id = :customer_id AND u.level = 2
            LIMIT 1
        ");
        $this->db->bind(':customer_id', $customerId);
        $customer = $this->db->fetchOne();
        if (!$customer) {
            return null;
        }

        $row = $this->normalizeCustomerRow($customer, true);
        $row['modules'] = $this->getCustomerModules($customerId);
        $row['payments'] = $this->getPayments($customerId, 20);
        $row['actions'] = (new AdminAccountActionsRepository())->getByUserId($customerId, 25);
        $row['statements'] = $this->buildStatements($row);
        $row['available_modules'] = $this->getAvailableModuleCards($customerId);
        return $row;
    }

    public function getRenewalQueue(array $filters = []): array
    {
        $filters['filter'] = $filters['filter'] ?? 'due_or_expired';
        return $this->listCustomers($filters);
    }

    public function buildBatchPreview(array $filters = []): array
    {
        $queue = $this->getRenewalQueue($filters + ['per_page' => 100, 'page' => 1]);
        $eligible = [];
        $ineligible = [];
        $total = 0.0;
        foreach ($queue['rows'] as $row) {
            if ($row['eligible_to_charge']) {
                $eligible[] = $row;
                $total += $row['amount_due'];
            } else {
                $ineligible[] = $row;
            }
        }
        return [
            'eligible' => $eligible,
            'ineligible' => $ineligible,
            'eligible_count' => count($eligible),
            'ineligible_count' => count($ineligible),
            'estimated_total' => $total,
            'estimated_total_label' => $this->formatMoney($total),
        ];
    }

    public function getReports(): array
    {
        $all = $this->listCustomers(['per_page' => 500, 'page' => 1])['rows'];
        $metrics = [
            'total_users' => count($all),
            'active_paid' => 0,
            'expired' => 0,
            'past_due' => 0,
            'failed' => 0,
            'with_card' => 0,
            'without_card' => 0,
            'courtesy_modules' => 0,
            'paid_mrr' => 0.0,
            'courtesy_value' => 0.0,
            'expected_renewal' => 0.0,
            'outstanding' => 0.0,
        ];
        foreach ($all as $row) {
            if ($row['membership_status'] === 'active') { $metrics['active_paid']++; }
            if ($row['membership_status'] === 'expired') { $metrics['expired']++; }
            if ($row['membership_status'] === 'past_due') { $metrics['past_due']++; }
            if ($row['failed_attempts'] > 0) { $metrics['failed']++; }
            if ($row['has_card']) { $metrics['with_card']++; } else { $metrics['without_card']++; }
            $metrics['courtesy_modules'] += $row['courtesy_modules'];
            $metrics['paid_mrr'] += $row['paid_module_value'];
            $metrics['courtesy_value'] += $row['courtesy_value'];
            $metrics['expected_renewal'] += $row['next_charge'];
            $metrics['outstanding'] += $row['amount_due'];
        }

        return [
            'metrics' => $metrics + [
                'paid_mrr_label' => $this->formatMoney($metrics['paid_mrr']),
                'courtesy_value_label' => $this->formatMoney($metrics['courtesy_value']),
                'expected_renewal_label' => $this->formatMoney($metrics['expected_renewal']),
                'outstanding_label' => $this->formatMoney($metrics['outstanding']),
            ],
            'renewals' => $this->listCustomers(['filter' => 'due_soon', 'per_page' => 25])['rows'],
            'failed' => $this->listCustomers(['filter' => 'failed_payment', 'per_page' => 25])['rows'],
            'expired' => $this->listCustomers(['filter' => 'expired_membership', 'per_page' => 25])['rows'],
            'courtesy' => $this->listCustomers(['filter' => 'courtesy_modules', 'per_page' => 25])['rows'],
            'no_card' => $this->listCustomers(['filter' => 'no_payment_method', 'per_page' => 25])['rows'],
            'distribution' => $this->getModuleDistribution(),
        ];
    }

    public function getUserHygiene(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = in_array((int)($filters['per_page'] ?? 25), [25, 50, 100], true) ? (int)$filters['per_page'] : 25;
        $offset = ($page - 1) * $perPage;
        $search = trim((string)($filters['search'] ?? ''));
        $filter = trim((string)($filters['filter'] ?? 'suspicious'));
        $where = ['u.level IN (2,4,5)'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.email LIKE :search OR u.name LIKE :search OR u.lastname LIKE :search OR u.phone LIKE :search OR CAST(u.id AS CHAR) LIKE :search OR ip.company_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($filter === 'suspicious') {
            $where[] = "(u.is_active = 0 OR u.email LIKE '%test%' OR u.email LIKE '%spam%' OR u.email LIKE '%mailinator%' OR u.email LIKE '%tempmail%' OR NOT EXISTS (SELECT 1 FROM institution_profile ip2 WHERE ip2.id_owner = u.id))";
        } elseif ($filter === 'email_not_verified') {
            $where[] = $this->hasColumn('users', 'email_verified_at') ? 'u.email_verified_at IS NULL' : '1=1';
        } elseif ($filter === 'no_business_profile') {
            $where[] = 'ip.id IS NULL';
        } elseif ($filter === 'no_payment_method') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM user_cards uc WHERE uc.id_user = u.id)';
        } elseif ($filter === 'marked_spam') {
            $where[] = "EXISTS (SELECT 1 FROM admin_account_actions aaa WHERE aaa.id_user = u.id AND aaa.action_type IN ('marked_spam','quarantine','suspended_for_spam'))";
        }
        $whereSql = implode(' AND ', $where);
        $createdSelect = $this->hasColumn('users', 'created_at') ? 'u.created_at' : 'NULL AS created_at';
        $this->db->query("SELECT COUNT(*) AS total FROM users u LEFT JOIN institution_profile ip ON ip.id_owner = u.id WHERE {$whereSql}");
        foreach ($params as $key => $value) { $this->db->bind($key, $value); }
        $total = (int)($this->db->fetchOne()->total ?? 0);
        $this->db->query("
            SELECT u.id, u.name, u.lastname, u.email, u.phone, u.level, u.is_active, {$createdSelect},
                   ip.id AS business_id, ip.company_name,
                   (SELECT COUNT(*) FROM user_cards uc WHERE uc.id_user = u.id) AS card_count,
                   (SELECT COUNT(*) FROM user_modules um WHERE um.id_user = u.id AND um.status = 'ACTIVE') AS active_modules,
                   (SELECT COUNT(*) FROM payments_all pa WHERE pa.user_id = u.id) AS payment_count
            FROM users u
            LEFT JOIN institution_profile ip ON ip.id_owner = u.id
            WHERE {$whereSql}
            ORDER BY u.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        foreach ($params as $key => $value) { $this->db->bind($key, $value); }
        $rows = [];
        foreach ($this->db->fetchAll() as $raw) {
            $reasons = [];
            if ((int)($raw->is_active ?? 1) === 0) { $reasons[] = 'inactive'; }
            if (!$raw->business_id) { $reasons[] = 'no business profile'; }
            if ((int)$raw->payment_count === 0 && (int)$raw->active_modules === 0) { $reasons[] = 'no commercial activity'; }
            if (preg_match('/(test|spam|mailinator|tempmail|example)/i', (string)$raw->email)) { $reasons[] = 'suspicious email'; }
            $risk = count($reasons) >= 3 ? 'High' : (count($reasons) >= 1 ? 'Medium' : 'Low');
            $rows[] = [
                'id' => (int)$raw->id,
                'name' => trim(($raw->name ?? '') . ' ' . ($raw->lastname ?? '')) ?: 'User #' . (int)$raw->id,
                'email' => (string)$raw->email,
                'business' => (string)($raw->company_name ?? ''),
                'created_at' => BillingDateUtils::format((string)($raw->created_at ?? '')),
                'level' => (int)$raw->level,
                'is_active' => (int)($raw->is_active ?? 1) === 1,
                'has_business_profile' => (bool)$raw->business_id,
                'has_payment_method' => (int)$raw->card_count > 0,
                'has_paid_modules' => (int)$raw->active_modules > 0,
                'risk_score' => $risk,
                'reason' => implode(', ', $reasons) ?: 'No obvious issue',
                'can_delete_safely' => (int)$raw->payment_count === 0 && (int)$raw->active_modules === 0 && (int)$raw->card_count === 0,
            ];
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int)ceil($total / $perPage)), 'filters' => compact('search', 'filter')];
    }

    public function getCaptchaHealth(): array
    {
        $envEnabled = !empty($_ENV['RECAPTCHA_SECRET_KEY']) || !empty($_ENV['CAPTCHA_SECRET_KEY']) || !empty($_ENV['GOOGLE_RECAPTCHA_SECRET']);
        return [
            'enabled' => $envEnabled,
            'server_verification' => $envEnabled,
            'protected_routes_count' => $envEnabled ? 1 : 0,
            'unprotected_routes' => $envEnabled ? [] : ['signup configuration'],
            'failed_last_24h' => 'Not logged',
            'suspicious_last_24h' => $this->getUserHygiene(['filter' => 'suspicious', 'per_page' => 25])['total'],
            'last_scan' => date('Y-m-d H:i:s'),
        ];
    }

    public function grantCourtesy(int $adminId, int $customerId, string $moduleSlug, string $expiresAt, string $reason): bool
    {
        $repo = new UserModulesRepository();
        $repo->upsertUserModuleBySlug($customerId, $moduleSlug, UserModulesRepository::STATUS_ACTIVE, $expiresAt ?: null, 0.0);
        $this->updateUserModuleMeta($customerId, $moduleSlug, [
            'activation_source' => 'courtesy',
            'activated_by_admin_id' => $adminId,
            'activation_reason' => $reason,
            'billing_status' => 'courtesy',
            'current_period_start' => date('Y-m-d'),
        ]);
        (new AdminAccountActionsRepository())->add($adminId, $customerId, 'courtesy_module_granted', $reason, [
            'module_slug' => $moduleSlug,
            'expires_at' => $expiresAt,
            'statement_line' => 'Courtesy by Ophyra',
        ]);
        return true;
    }

    public function cancelModuleAtPeriodEnd(int $adminId, int $customerId, string $moduleSlug, string $reason): bool
    {
        $repo = new UserModulesRepository();
        $ok = $repo->setBillingStatusByCompatibleSlugs($customerId, $this->pricing->compatibleSlugs($moduleSlug), 'cancel_at_period_end');
        (new AdminAccountActionsRepository())->add($adminId, $customerId, 'module_cancel_at_period_end', $reason, ['module_slug' => $moduleSlug]);
        return $ok;
    }

    public function reactivateModuleRenewal(int $adminId, int $customerId, string $moduleSlug, string $reason): bool
    {
        $repo = new UserModulesRepository();
        $ok = $repo->setBillingStatusByCompatibleSlugs($customerId, $this->pricing->compatibleSlugs($moduleSlug), 'current');
        (new AdminAccountActionsRepository())->add($adminId, $customerId, 'module_renewal_reactivated', $reason, ['module_slug' => $moduleSlug]);
        return $ok;
    }

    public function markUserHygieneAction(int $adminId, int $customerId, string $action, string $reason): void
    {
        if (in_array($action, ['quarantine', 'suspend', 'marked_spam'], true)) {
            $this->db->query('UPDATE users SET is_active = 0 WHERE id = :user_id LIMIT 1');
            $this->db->bind(':user_id', $customerId);
            $this->db->execute();
        }
        (new AdminAccountActionsRepository())->add($adminId, $customerId, $action, $reason);
    }

    private function normalizeCustomerRow(object $raw, bool $full = false): array
    {
        $userId = (int)$raw->id;
        $modules = $this->getCustomerModules($userId);
        $cards = $this->getCards($userId);
        $hasCard = count($cards) > 0;
        $mainCard = $cards[0] ?? null;
        $activeModules = array_values(array_filter($modules, static fn(array $module): bool => $module['status'] === 'ACTIVE'));
        $paidModuleValue = array_sum(array_map(static fn(array $module): float => $module['is_courtesy'] ? 0.0 : $module['monthly_price'], $activeModules));
        $courtesyValue = array_sum(array_map(static fn(array $module): float => $module['is_courtesy'] ? $module['monthly_price'] : 0.0, $activeModules));
        $dueDate = (string)($raw->membership_due_date ?? '');
        $daysDue = $this->daysUntil($dueDate);
        $membershipStatus = $dueDate === '' ? 'not_started' : ($daysDue < 0 ? 'expired' : ($daysDue <= 0 ? 'due_today' : 'active'));
        $amountDue = $daysDue <= 0 ? max($this->pricing->baseProfilePrice(), 0.0) + $paidModuleValue : 0.0;
        $billingStatus = (string)($raw->billing_status ?? 'current');
        $failedAttempts = (int)($raw->billing_failure_count ?? 0);
        $eligible = $amountDue > 0 && $hasCard && $paidModuleValue > 0 && $failedAttempts < 3 && strtolower($billingStatus) !== 'manual_hold';
        $risk = 'Low';
        if (!$hasCard || $failedAttempts > 0) { $risk = 'Medium'; }
        if (!$raw->is_active || $failedAttempts >= 3 || preg_match('/(spam|test|mailinator|tempmail)/i', (string)$raw->email)) { $risk = 'High'; }

        return [
            'id' => $userId,
            'name' => trim(($raw->name ?? '') . ' ' . ($raw->lastname ?? '')) ?: 'User #' . $userId,
            'email' => (string)($raw->email ?? ''),
            'phone' => (string)($raw->phone ?? ''),
            'business_id' => (int)($raw->business_id ?? 0),
            'business' => (string)($raw->company_name ?? ''),
            'business_slug' => (string)($raw->slug ?? ''),
            'level' => (int)($raw->level ?? 2),
            'created_at' => BillingDateUtils::format((string)($raw->created_at ?? '')),
            'last_login' => BillingDateUtils::format((string)($raw->last_login ?? '')),
            'email_verified' => !empty($raw->email_verified_at),
            'is_active' => (int)($raw->is_active ?? 1) === 1,
            'membership_type' => (string)($raw->membership_type ?? ''),
            'membership_due_date' => $dueDate,
            'membership_due_label' => BillingDateUtils::format($dueDate),
            'membership_status' => $membershipStatus,
            'billing_status' => $billingStatus ?: 'current',
            'failed_attempts' => $failedAttempts,
            'billing_status_updated_at' => BillingDateUtils::format((string)($raw->billing_status_updated_at ?? '')),
            'active_modules_count' => count($activeModules),
            'courtesy_modules' => count(array_filter($activeModules, static fn(array $module): bool => $module['is_courtesy'])),
            'paid_module_value' => $paidModuleValue,
            'paid_module_value_label' => $this->formatMoney($paidModuleValue),
            'courtesy_value' => $courtesyValue,
            'courtesy_value_label' => $this->formatMoney($courtesyValue),
            'next_charge' => $paidModuleValue,
            'next_charge_label' => $this->formatMoney($paidModuleValue),
            'amount_due' => $amountDue,
            'amount_due_label' => $this->formatMoney($amountDue),
            'has_card' => $hasCard,
            'payment_method' => $mainCard ? trim(($mainCard->brand ?: 'Card') . ' ending in ' . ($mainCard->last4 ?: '')) : 'No card',
            'autorenew' => $this->getAutorenew($userId),
            'eligible_to_charge' => $eligible,
            'eligibility_reason' => $eligible ? 'Ready' : $this->eligibilityReason($amountDue, $hasCard, $paidModuleValue, $failedAttempts, $billingStatus),
            'risk' => $risk,
            'modules' => $full ? $modules : [],
            'cards' => $full ? $cards : [],
        ];
    }

    private function getCustomerModules(int $userId): array
    {
        $this->db->query("
            SELECT um.*, m.name, m.monthly_price
            FROM user_modules um
            LEFT JOIN modules m ON m.slug = um.module_slug
            WHERE um.id_user = :user_id
            ORDER BY um.status DESC, m.name ASC
        ");
        $this->db->bind(':user_id', $userId);
        $rows = [];
        foreach ($this->db->fetchAll() as $module) {
            $monthlyPrice = (float)($module->monthly_price ?? $module->price ?? 0);
            $isCourtesy = (float)($module->price ?? $monthlyPrice) <= 0 || in_array((string)($module->activation_source ?? ''), ['manual_gift','courtesy','admin_courtesy'], true) || (string)($module->billing_status ?? '') === 'courtesy';
            $rows[] = [
                'slug' => (string)$module->module_slug,
                'name' => (string)($module->name ?? $module->module_slug),
                'status' => strtoupper((string)$module->status),
                'billing_status' => (string)($module->billing_status ?? 'current'),
                'renewal_at' => (string)($module->renewal_at ?? ''),
                'renewal_label' => BillingDateUtils::format((string)($module->renewal_at ?? '')),
                'monthly_price' => $monthlyPrice,
                'monthly_price_label' => $this->formatMoney($monthlyPrice),
                'is_courtesy' => $isCourtesy,
                'statement_description' => $isCourtesy ? 'Courtesy by Ophyra' : 'Paid module',
            ];
        }
        return $rows;
    }

    private function getAvailableModuleCards(int $customerId): array
    {
        $cards = [];
        foreach ($this->modulesRepository->getAddons() as $module) {
            $quote = $this->addonBilling->getAddonQuote($customerId, (string)$module->slug, $this->pricing->getBaseCurrency());
            $cards[] = [
                'slug' => (string)$module->slug,
                'name' => (string)$module->name,
                'description' => (string)($module->description ?? ''),
                'monthly_price' => $quote['monthly_price_formatted'] ?? $this->formatMoney((float)($module->monthly_price ?? 0)),
                'amount_due' => (float)($quote['amount_due'] ?? $module->monthly_price ?? 0),
                'amount_due_label' => $quote['formatted_amount_due'] ?? $this->formatMoney((float)($module->monthly_price ?? 0)),
                'renewal_at' => BillingDateUtils::format((string)($quote['renewal_at'] ?? '')),
            ];
        }
        return $cards;
    }

    private function getCards(int $userId): array
    {
        $this->db->query('SELECT id, brand, last4, exp, main_card, token FROM user_cards WHERE id_user = :user_id ORDER BY main_card DESC, id DESC');
        $this->db->bind(':user_id', $userId);
        return array_map(static fn($card): object => (object)[
            'id' => (int)$card->id,
            'brand' => (string)($card->brand ?? 'Card'),
            'last4' => (string)($card->last4 ?? ''),
            'exp' => (string)($card->exp ?? ''),
            'main_card' => (string)($card->main_card ?? ''),
            'token' => (string)($card->token ?? ''),
        ], $this->db->fetchAll());
    }

    private function getPayments(int $userId, int $limit): array
    {
        $this->db->query('SELECT * FROM payments_all WHERE user_id = :user_id ORDER BY payment_date DESC, id DESC LIMIT ' . max(1, min(100, $limit)));
        $this->db->bind(':user_id', $userId);
        $payments = [];
        foreach ($this->db->fetchAll() as $payment) {
            $amount = (float)($payment->total ?? $payment->amount ?? 0);
            $payments[] = [
                'id' => (int)($payment->id ?? 0),
                'concept' => (string)($payment->concept ?? 'Payment'),
                'date' => BillingDateUtils::format((string)($payment->payment_date ?? '')),
                'amount' => $this->formatMoney($amount),
                'status' => (string)($payment->status ?? ''),
                'reference' => (string)($payment->reference ?? ''),
            ];
        }
        return $payments;
    }

    private function buildStatements(array $customer): array
    {
        $lines = [];
        foreach ($customer['modules'] as $module) {
            if ($module['status'] !== 'ACTIVE') {
                continue;
            }
            $lines[] = [
                'module' => $module['name'],
                'description' => $module['is_courtesy'] ? ('Courtesy by Ophyra' . ($module['renewal_label'] ? ' until ' . $module['renewal_label'] : '')) : 'Monthly module charge',
                'regular_price' => $module['monthly_price_label'],
                'courtesy_credit' => $module['is_courtesy'] ? '-' . $module['monthly_price_label'] : $this->formatMoney(0),
                'amount_due' => $module['is_courtesy'] ? $this->formatMoney(0) : $module['monthly_price_label'],
                'status' => $module['is_courtesy'] ? 'Courtesy' : 'Upcoming',
            ];
        }
        return $lines;
    }

    private function getAutorenew(int $userId): bool
    {
        $this->db->query('SELECT enabled FROM autopay_settings WHERE user_id = :user_id LIMIT 1');
        $this->db->bind(':user_id', $userId);
        $row = $this->db->fetchOne();
        return $row && (int)$row->enabled === 1;
    }

    private function getModuleDistribution(): array
    {
        $courtesyCase = $this->hasColumn('user_modules', 'activation_source')
            ? "SUM(CASE WHEN COALESCE(um.price, 0) = 0 OR um.activation_source IN ('manual_gift','courtesy','admin_courtesy') THEN 1 ELSE 0 END) AS courtesy_count"
            : "SUM(CASE WHEN COALESCE(um.price, 0) = 0 THEN 1 ELSE 0 END) AS courtesy_count";
        $this->db->query("
            SELECT um.module_slug,
                   COUNT(*) AS total,
                   SUM(CASE WHEN um.status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                   {$courtesyCase},
                   SUM(CASE WHEN COALESCE(um.price, 0) > 0 THEN COALESCE(um.price, 0) ELSE 0 END) AS expected_revenue
            FROM user_modules um
            GROUP BY um.module_slug
            ORDER BY active_count DESC
        ");
        $rows = [];
        foreach ($this->db->fetchAll() as $row) {
            $rows[] = [
                'module' => (string)$row->module_slug,
                'total' => (int)$row->total,
                'active' => (int)$row->active_count,
                'courtesy' => (int)$row->courtesy_count,
                'expected_revenue' => $this->formatMoney((float)$row->expected_revenue),
            ];
        }
        return $rows;
    }

    private function updateUserModuleMeta(int $userId, string $moduleSlug, array $values): void
    {
        $sets = [];
        $params = [':user_id' => $userId, ':module_slug' => $moduleSlug];
        foreach ($values as $column => $value) {
            if (!$this->hasColumn('user_modules', $column)) {
                continue;
            }
            $sets[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
        }
        if ($sets === []) {
            return;
        }
        $this->db->query('UPDATE user_modules SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id_user = :user_id AND module_slug = :module_slug LIMIT 1');
        foreach ($params as $key => $value) { $this->db->bind($key, $value); }
        $this->db->execute();
    }

    private function eligibilityReason(float $amountDue, bool $hasCard, float $paidModuleValue, int $failedAttempts, string $billingStatus): string
    {
        if ($amountDue <= 0) { return 'No payment due'; }
        if (!$hasCard) { return 'No payment method'; }
        if ($paidModuleValue <= 0) { return 'Courtesy-only or no paid modules'; }
        if ($failedAttempts >= 3) { return 'Manual review after failed attempts'; }
        if (strtolower($billingStatus) === 'manual_hold') { return 'Manual hold'; }
        return 'Not eligible';
    }

    private function daysUntil(string $date): int
    {
        if ($date === '') {
            return 9999;
        }
        try {
            return (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date))->format('%r%a');
        } catch (Throwable $e) {
            return 9999;
        }
    }

    private function formatMoney(float $amount, string $currency = OphyraPricingService::BASE_CURRENCY): string
    {
        return $this->pricing->format($amount, $currency);
    }

    private function getLastCronRun(): string
    {
        $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.logs';
        if (!is_dir($logDir)) {
            return '';
        }
        $files = glob($logDir . DIRECTORY_SEPARATOR . 'autopay_*.log') ?: [];
        if ($files === []) {
            return '';
        }
        usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return date('Y-m-d H:i:s', (int)filemtime($files[0]));
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }
        try {
            $this->db->query('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
            $this->db->bind(':column', $column);
            $this->columnCache[$key] = (bool)$this->db->fetchOne();
        } catch (PDOException $e) {
            $this->columnCache[$key] = false;
        }
        return $this->columnCache[$key];
    }
}
