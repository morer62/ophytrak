<?php

namespace App\Services;

use App\Repositories\AffiliateProfilesRepository;
use App\Repositories\Connection;

class AffiliateService
{
    private const COMMISSIONABLE_MODULES = [
        'service_operations',
        'services',
        'store_logistics',
        'store_delivery_tracking',
        'advanced_storage_qr_inventory',
        'inventory_storage',
        'ai_advisor',
        'ticket_sales_rsvp',
        'tickets_rsvp',
    ];

    private Connection $db;
    private AffiliateProfilesRepository $profilesRepository;

    public function __construct()
    {
        $this->db = new Connection();
        $this->profilesRepository = new AffiliateProfilesRepository();
    }

    public function getOrCreateAffiliateCode(int $userId): string
    {
        if (!$this->profilesRepository->isApproved($userId)) {
            return '';
        }

        $this->db->query("SELECT affiliate_code FROM affiliate_codes WHERE user_id = :user_id");
        $this->db->bind(":user_id", $userId);
        $this->db->execute();
        $existing = $this->db->fetchAll();

        if (!empty($existing)) {
            return $existing[0]->affiliate_code;
        }

        $affiliateCode = $this->generateAffiliateCode($userId);

        $this->db->query("INSERT INTO affiliate_codes (user_id, affiliate_code, status) VALUES (:user_id, :affiliate_code, 'active')");
        $this->db->bind(":user_id", $userId);
        $this->db->bind(":affiliate_code", $affiliateCode);
        $this->db->execute();

        return $affiliateCode;
    }

    public function getAffiliateCodeForUser(int $userId): ?string
    {
        try {
            $this->db->query("SELECT affiliate_code FROM affiliate_codes WHERE user_id = :user_id AND status = 'active' LIMIT 1");
            $this->db->bind(":user_id", $userId);
            $row = $this->db->fetchOne();

            return $row ? (string)$row->affiliate_code : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setAffiliateCodeStatus(int $userId, string $status): void
    {
        $status = strtolower($status);
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $status = 'inactive';
        }

        $this->db->query("UPDATE affiliate_codes SET status = :status WHERE user_id = :user_id");
        $this->db->bind(":status", $status);
        $this->db->bind(":user_id", $userId);
        $this->db->execute();
    }

    private function generateAffiliateCode(int $userId): string
    {
        $baseCode = 'AFF' . str_pad((string)$userId, 6, '0', STR_PAD_LEFT);

        $this->db->query("SELECT id FROM affiliate_codes WHERE affiliate_code = :code");
        $this->db->bind(":code", $baseCode);
        $this->db->execute();
        $exists = $this->db->fetchAll();

        if (!empty($exists)) {
            $baseCode .= '_' . time();
        }

        return $baseCode;
    }

    public function processAffiliateClick(string $affiliateCode, ?string $utmSource = null, ?string $utmMedium = null, ?string $utmCampaign = null): bool
    {
        try {
            $affiliateCode = strtoupper($affiliateCode);

            $this->db->query("SELECT user_id FROM affiliate_codes WHERE affiliate_code = :code AND status = 'active'");
            $this->db->bind(":code", $affiliateCode);
            $this->db->execute();
            $affiliate = $this->db->fetchAll();

            if (empty($affiliate)) {
                return false;
            }

            $referrerId = (int)$affiliate[0]->user_id;
            if (!$this->profilesRepository->isApproved($referrerId)) {
                return false;
            }

            $this->db->query("UPDATE affiliate_codes SET clicks = clicks + 1 WHERE affiliate_code = :code");
            $this->db->bind(":code", $affiliateCode);
            $this->db->execute();

            $affiliateData = [
                'referrer_id' => $referrerId,
                'referral_code' => $affiliateCode,
                'utm_source' => $utmSource,
                'utm_medium' => $utmMedium,
                'utm_campaign' => $utmCampaign,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'cookie_id' => session_id(),
                'timestamp' => time(),
            ];

            $cookieDays = max(1, (int)($_ENV['AFFILIATE_COOKIE_DAYS'] ?? 30));
            setcookie('affiliate_data', base64_encode(json_encode($affiliateData)), time() + ($cookieDays * 24 * 60 * 60), '/');

            return true;
        } catch (\Exception $e) {
            error_log("Error processing affiliate click: " . $e->getMessage());
            return false;
        }
    }

    public function getAffiliateFromCookie(): ?array
    {
        if (!isset($_COOKIE['affiliate_data'])) {
            return null;
        }

        try {
            $data = json_decode(base64_decode($_COOKIE['affiliate_data']), true);

            $cookieDays = max(1, (int)($_ENV['AFFILIATE_COOKIE_DAYS'] ?? 30));
            if (!isset($data['timestamp']) || (time() - $data['timestamp']) > ($cookieDays * 24 * 60 * 60)) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function registerReferral(int $referredUserId, array $affiliateData): bool
    {
        try {
            $this->db->query("SELECT id FROM affiliate_referrals WHERE referrer_id = :referrer_id AND referred_id = :referred_id");
            $this->db->bind(":referrer_id", $affiliateData['referrer_id']);
            $this->db->bind(":referred_id", $referredUserId);
            $this->db->execute();
            $existing = $this->db->fetchAll();

            if (!empty($existing)) {
                return true;
            }

            if ((int)$affiliateData['referrer_id'] === $referredUserId) {
                return true;
            }

            if (!$this->profilesRepository->isApproved((int)$affiliateData['referrer_id'])) {
                return true;
            }

            $this->db->query("
                INSERT INTO affiliate_referrals
                (referrer_id, referred_id, referral_code, utm_source, utm_medium, utm_campaign, ip_address, user_agent, cookie_id, status, confirmed_at)
                VALUES
                (:referrer_id, :referred_id, :referral_code, :utm_source, :utm_medium, :utm_campaign, :ip_address, :user_agent, :cookie_id, 'confirmed', NOW())
            ");

            $this->db->bind(":referrer_id", $affiliateData['referrer_id']);
            $this->db->bind(":referred_id", $referredUserId);
            $this->db->bind(":referral_code", $affiliateData['referral_code']);
            $this->db->bind(":utm_source", $affiliateData['utm_source']);
            $this->db->bind(":utm_medium", $affiliateData['utm_medium']);
            $this->db->bind(":utm_campaign", $affiliateData['utm_campaign']);
            $this->db->bind(":ip_address", $affiliateData['ip_address']);
            $this->db->bind(":user_agent", $affiliateData['user_agent']);
            $this->db->bind(":cookie_id", $affiliateData['cookie_id']);
            $this->db->execute();

            $this->db->query("UPDATE affiliate_codes SET conversions = conversions + 1 WHERE affiliate_code = :code");
            $this->db->bind(":code", $affiliateData['referral_code']);
            $this->db->execute();

            setcookie('affiliate_data', '', time() - 3600, '/');

            return true;
        } catch (\Exception $e) {
            error_log("Error registering referral: " . $e->getMessage());
            return false;
        }
    }

    public function getAffiliateStats(int $userId): ?object
    {
        try {
            $this->db->query("SELECT affiliate_code, clicks, conversions FROM affiliate_codes WHERE user_id = :user_id");
            $this->db->bind(":user_id", $userId);
            $this->db->execute();
            $affiliateCode = $this->db->fetchAll();

            if (empty($affiliateCode)) {
                return null;
            }

            $code = $affiliateCode[0]->affiliate_code;

            $this->db->query("
                SELECT
                    COUNT(DISTINCT ar.referred_id) as total_referrals,
                    COUNT(DISTINCT CASE WHEN ar.status = 'confirmed' THEN ar.referred_id END) as confirmed_referrals,
                    COALESCE(SUM(CASE WHEN ac.status = 'pending' THEN ac.commission_amount END), 0) as pending_commissions,
                    COALESCE(SUM(CASE WHEN ac.status = 'approved' THEN ac.commission_amount END), 0) as approved_commissions,
                    COALESCE(SUM(CASE WHEN ac.status = 'paid' THEN ac.commission_amount END), 0) as paid_commissions,
                    COALESCE(SUM(ac.commission_amount), 0) as total_commissions_earned,
                    MAX(ar.created_at) as last_referral_date
                FROM affiliate_referrals ar
                LEFT JOIN affiliate_commissions ac ON ar.referred_id = ac.referred_id AND ac.referrer_id = :user_id
                WHERE ar.referral_code = :affiliate_code
            ");
            $this->db->bind(":user_id", $userId);
            $this->db->bind(":affiliate_code", $code);
            $this->db->execute();
            $stats = $this->db->fetchAll();

            if (!empty($stats)) {
                $result = $stats[0];
                $result->affiliate_code = $code;
                $result->clicks = $affiliateCode[0]->clicks;
                $result->conversions = $affiliateCode[0]->conversions;
                return $result;
            }

            return null;
        } catch (\Exception $e) {
            error_log("Error getting affiliate stats: " . $e->getMessage());
            return null;
        }
    }

    public function getReferrals(int $userId, int $limit = 50): array
    {
        try {
            $this->db->query("SELECT affiliate_code FROM affiliate_codes WHERE user_id = :user_id");
            $this->db->bind(":user_id", $userId);
            $this->db->execute();
            $affiliateCode = $this->db->fetchAll();

            if (empty($affiliateCode)) {
                return [];
            }

            $this->db->query("
                SELECT
                    ar.*,
                    u.name,
                    u.lastname,
                    u.email,
                    u.membership_type,
                    u.membership_due_date,
                    ar.created_at as user_registered_at,
                    COALESCE(SUM(ac.gross_amount), 0) as total_gross_generated,
                    COALESCE(SUM(ac.commission_amount), 0) as total_commissions_generated,
                    MAX(ac.created_at) as last_commission_at
                FROM affiliate_referrals ar
                JOIN users u ON ar.referred_id = u.id
                LEFT JOIN affiliate_commissions ac ON ar.referred_id = ac.referred_id AND ac.referrer_id = :user_id
                WHERE ar.referral_code = :affiliate_code
                GROUP BY ar.id, u.id
                ORDER BY ar.created_at DESC
                LIMIT :limit
            ");
            $this->db->bind(":user_id", $userId);
            $this->db->bind(":affiliate_code", $affiliateCode[0]->affiliate_code);
            $this->db->bind(":limit", $limit);
            $this->db->execute();

            return $this->db->fetchAll();
        } catch (\Exception $e) {
            error_log("Error getting referrals: " . $e->getMessage());
            return [];
        }
    }

    public function getCommissions(int $userId, int $limit = 50): array
    {
        try {
            $this->db->query("
                SELECT
                    ac.*,
                    u.name as referred_name,
                    u.email as referred_email
                FROM affiliate_commissions ac
                JOIN users u ON ac.referred_id = u.id
                WHERE ac.referrer_id = :user_id
                ORDER BY ac.created_at DESC
                LIMIT :limit
            ");
            $this->db->bind(":user_id", $userId);
            $this->db->bind(":limit", $limit);
            $this->db->execute();

            return $this->db->fetchAll();
        } catch (\Exception $e) {
            error_log("Error getting commissions: " . $e->getMessage());
            return [];
        }
    }

    public function createCommission(
        int $referredUserId,
        string $transactionType,
        float $grossAmount,
        string $transactionId,
        ?int $orderId = null,
        ?int $paymentId = null,
        ?string $moduleSlug = null,
        string $currency = 'USD',
        bool $isRenewal = false
    ): bool {
        try {
            if (!$this->programEnabled()) {
                return true;
            }

            if ($isRenewal && !$this->commissionOnRenewals()) {
                return true;
            }

            $moduleSlug = $this->normalizeModuleSlug($moduleSlug);
            if ($moduleSlug !== null && !in_array($moduleSlug, self::COMMISSIONABLE_MODULES, true)) {
                return true;
            }

            $this->db->query("SELECT referrer_id, id FROM affiliate_referrals WHERE referred_id = :referred_id AND status = 'confirmed'");
            $this->db->bind(":referred_id", $referredUserId);
            $this->db->execute();
            $referral = $this->db->fetchAll();

            if (empty($referral)) {
                return true;
            }

            $referrerId = (int)$referral[0]->referrer_id;
            $referralId = (int)$referral[0]->id;

            if (!$this->profilesRepository->isApproved($referrerId)) {
                return true;
            }

            if ($this->commissionExists($transactionId, $paymentId)) {
                return true;
            }

            $transactionType = $this->normalizeTransactionType($transactionType);
            $commissionRate = $this->profilesRepository->getCommissionRateForUser($referrerId);
            $commissionAmount = round($grossAmount * ($commissionRate / 100), $this->currencyDecimals($currency));
            $isAddon = $transactionType === 'ophyra_addon';

            try {
                $this->db->query("
                    INSERT INTO affiliate_commissions
                    (
                        referrer_id, referred_id, referral_id, transaction_type, product_name, module_slug,
                        transaction_id, order_id, payment_id, payment_source_table, payment_source_id,
                        gross_amount, payment_amount, payment_currency, commission_rate, commission_percentage_snapshot, commission_amount, commission_currency, currency, is_recurring, is_addon,
                        eligible_for_commission, commission_period_year, commission_period_month, status
                    )
                    VALUES
                    (
                        :referrer_id, :referred_id, :referral_id, :transaction_type, :product_name, :module_slug,
                        :transaction_id, :order_id, :payment_id, :payment_source_table, :payment_source_id,
                        :gross_amount, :payment_amount, :payment_currency, :commission_rate, :commission_percentage_snapshot, :commission_amount, :commission_currency, :currency, :is_recurring, :is_addon,
                        1, :commission_period_year, :commission_period_month, 'pending'
                    )
                ");
                $this->db->bind(":product_name", $this->getProductNameForModule($moduleSlug) ?? $this->getProductNameForTransaction($transactionType));
                $this->db->bind(":module_slug", $moduleSlug);
                $this->db->bind(":payment_source_table", $paymentId ? 'payments_all' : null);
                $this->db->bind(":payment_source_id", $paymentId);
                $this->db->bind(":payment_amount", $grossAmount);
                $this->db->bind(":payment_currency", strtoupper($currency));
                $this->db->bind(":commission_percentage_snapshot", $commissionRate);
                $this->db->bind(":commission_currency", strtoupper($currency));
                $this->db->bind(":currency", strtoupper($currency));
                $this->db->bind(":is_recurring", $isRenewal ? 1 : 0);
                $this->db->bind(":is_addon", $isAddon ? 1 : 0);
                $this->db->bind(":commission_period_year", (int)date('Y'));
                $this->db->bind(":commission_period_month", (int)date('n'));
                $this->bindCommissionValues($referrerId, $referredUserId, $referralId, $transactionType, $transactionId, $orderId, $paymentId, $grossAmount, $commissionRate, $commissionAmount);
                $this->db->execute();
            } catch (\Exception $e) {
                $this->db->query("
                    INSERT INTO affiliate_commissions
                    (referrer_id, referred_id, referral_id, transaction_type, transaction_id, order_id, payment_id, gross_amount, commission_rate, commission_amount, status)
                    VALUES
                    (:referrer_id, :referred_id, :referral_id, :transaction_type, :transaction_id, :order_id, :payment_id, :gross_amount, :commission_rate, :commission_amount, 'pending')
                ");
                $this->bindCommissionValues($referrerId, $referredUserId, $referralId, $transactionType, $transactionId, $orderId, $paymentId, $grossAmount, $commissionRate, $commissionAmount);
                $this->db->execute();
            }

            $this->updateReferralTotals($referralId, $grossAmount, $commissionAmount);

            return true;
        } catch (\Exception $e) {
            error_log("Error creating commission: " . $e->getMessage());
            return false;
        }
    }

    private function bindCommissionValues(
        int $referrerId,
        int $referredUserId,
        int $referralId,
        string $transactionType,
        string $transactionId,
        ?int $orderId,
        ?int $paymentId,
        float $grossAmount,
        float $commissionRate,
        float $commissionAmount
    ): void {
        $this->db->bind(":referrer_id", $referrerId);
        $this->db->bind(":referred_id", $referredUserId);
        $this->db->bind(":referral_id", $referralId);
        $this->db->bind(":transaction_type", $transactionType);
        $this->db->bind(":transaction_id", $transactionId);
        $this->db->bind(":order_id", $orderId);
        $this->db->bind(":payment_id", $paymentId);
        $this->db->bind(":gross_amount", $grossAmount);
        $this->db->bind(":commission_rate", $commissionRate);
        $this->db->bind(":commission_amount", $commissionAmount);
    }

    private function commissionExists(string $transactionId, ?int $paymentId = null): bool
    {
        $this->db->query("
            SELECT id
            FROM affiliate_commissions
            WHERE transaction_id = :transaction_id
               OR (:payment_id IS NOT NULL AND payment_id = :payment_id)
            LIMIT 1
        ");
        $this->db->bind(":transaction_id", $transactionId);
        $this->db->bind(":payment_id", $paymentId);
        $this->db->execute();

        return !empty($this->db->fetchAll());
    }

    private function programEnabled(): bool
    {
        return filter_var($_ENV['AFFILIATE_PROGRAM_ENABLED'] ?? true, FILTER_VALIDATE_BOOL);
    }

    private function commissionOnRenewals(): bool
    {
        return filter_var($_ENV['AFFILIATE_COMMISSION_ON_RENEWALS'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function normalizeModuleSlug(?string $moduleSlug): ?string
    {
        $moduleSlug = trim((string)$moduleSlug);
        if ($moduleSlug === '') {
            return null;
        }

        return match ($moduleSlug) {
            'services' => 'service_operations',
            'store_delivery_tracking' => 'store_logistics',
            'inventory_storage' => 'advanced_storage_qr_inventory',
            'tickets_rsvp' => 'ticket_sales_rsvp',
            default => $moduleSlug,
        };
    }

    private function currencyDecimals(string $currency): int
    {
        return in_array(strtoupper($currency), ['CLP', 'JPY', 'KRW'], true) ? 0 : 2;
    }

    private function normalizeTransactionType(string $transactionType): string
    {
        $normalized = match ($transactionType) {
            'membership_monthly' => 'ophyra_base',
            'addon', 'module_addon' => 'ophyra_addon',
            default => $transactionType,
        };

        $allowed = [
            'membership_monthly',
            'membership_annual',
            'listing_fee',
            'ophyra_base',
            'ophyra_addon',
            'ticket_sales',
            'event_registration',
            'order_payment',
            'other',
        ];

        return in_array($normalized, $allowed, true) ? $normalized : 'other';
    }

    private function getProductNameForTransaction(string $transactionType): string
    {
        return match ($transactionType) {
            'ophyra_base', 'membership_monthly' => 'Ophyra Base',
            'ophyra_addon' => 'Ophyra Add-on',
            'ticket_sales', 'event_registration' => 'Tickets / Event Registration',
            'listing_fee' => 'Legacy Listing',
            default => 'Ophyra Payment',
        };
    }

    private function getProductNameForModule(?string $moduleSlug): ?string
    {
        return match ($moduleSlug) {
            'service_operations' => 'Service Operations',
            'store_logistics' => 'Store + Logistics',
            'advanced_storage_qr_inventory' => 'Advanced Storage / QR Inventory',
            'ai_advisor' => 'AI Advisor',
            'ticket_sales_rsvp' => 'Ticket Sales + RSVP',
            default => null,
        };
    }

    private function updateReferralTotals(int $referralId, float $grossAmount, float $commissionAmount): void
    {
        try {
            $this->db->query("
                UPDATE affiliate_referrals
                SET first_paid_at = COALESCE(first_paid_at, NOW()),
                    last_paid_at = NOW(),
                    lifetime_gross_amount = COALESCE(lifetime_gross_amount, 0) + :gross_amount,
                    lifetime_commission_amount = COALESCE(lifetime_commission_amount, 0) + :commission_amount,
                    updated_at = NOW()
                WHERE id = :referral_id
            ");
            $this->db->bind(":gross_amount", $grossAmount);
            $this->db->bind(":commission_amount", $commissionAmount);
            $this->db->bind(":referral_id", $referralId);
            $this->db->execute();
        } catch (\Exception $e) {
            error_log("Error updating affiliate referral totals: " . $e->getMessage());
        }
    }
}
