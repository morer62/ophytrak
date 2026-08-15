<?php

namespace App\Services;

use App\Repositories\Connection;
use App\Repositories\ModulesRepository;
use App\Repositories\UserModulesRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PDOException;

class AddonBillingService
{
    private Connection $db;
    private ModulesRepository $modulesRepository;
    private UserModulesRepository $userModulesRepository;
    private CurrencyPricingService $currencyPricingService;
    private OphyraPricingService $ophyraPricingService;

    public function __construct(
        ?ModulesRepository $modulesRepository = null,
        ?UserModulesRepository $userModulesRepository = null,
        ?CurrencyPricingService $currencyPricingService = null,
        ?OphyraPricingService $ophyraPricingService = null
    ) {
        $this->db = new Connection();
        $this->modulesRepository = $modulesRepository ?? new ModulesRepository();
        $this->userModulesRepository = $userModulesRepository ?? new UserModulesRepository();
        $this->currencyPricingService = $currencyPricingService ?? new CurrencyPricingService();
        $this->ophyraPricingService = $ophyraPricingService ?? new OphyraPricingService();
    }

    public function getAddonQuote(int $userId, string $moduleSlug, ?string $paymentCurrency = null): ?array
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);
        if (!ProductProfileService::allowsAddon($moduleSlug)) {
            return null;
        }
        $module = $this->getOfficialAddon($moduleSlug);
        $canonicalSlug = $this->modulesRepository->getCanonicalSlug($moduleSlug);

        if (!$module) {
            return null;
        }

        $paymentCurrency = $this->ophyraPricingService->normalizePaymentCurrency($paymentCurrency);
        $monthlyPrice = $this->ophyraPricingService->getModulePrice($canonicalSlug, $paymentCurrency);
        $baseMonthlyPrice = $this->ophyraPricingService->getModuleBasePriceUsd($canonicalSlug);

        if ($monthlyPrice === null || $baseMonthlyPrice === null) {
            return null;
        }

        $renewalAt = $this->getAddonRenewalDate($userId);
        $amountDue = $this->calculateAmountDueForPrice((float)$monthlyPrice, $userId);
        $baseAmountDue = $this->calculateAmountDueForPrice((float)$baseMonthlyPrice, $userId);
        if (in_array($paymentCurrency, ['CLP'], true)) {
            $amountDue = round($amountDue, 0);
        }

        $exchangeRate = $baseAmountDue > 0 ? round($amountDue / $baseAmountDue, 8) : 1.0;

        return [
            'slug' => $moduleSlug,
            'canonical_slug' => $canonicalSlug,
            'name' => $this->officialAddonLabel($moduleSlug, (string)($module->name ?? $moduleSlug)),
            'monthly_price' => (float)$monthlyPrice,
            'monthly_price_currency' => $paymentCurrency,
            'monthly_price_formatted' => $this->ophyraPricingService->format((float)$monthlyPrice, $paymentCurrency),
            'base_monthly_price' => (float)$baseMonthlyPrice,
            'amount_due' => $amountDue,
            'base_amount' => $baseAmountDue,
            'base_currency' => $this->ophyraPricingService->getBaseCurrency(),
            'display_amount' => $amountDue,
            'display_currency' => $paymentCurrency,
            'payment_amount' => $amountDue,
            'payment_currency' => $paymentCurrency,
            'exchange_rate' => $exchangeRate,
            'display_exchange_rate' => $exchangeRate,
            'exchange_rate_source' => 'env_fixed_price',
            'pricing_source' => 'env',
            'formatted_amount_due' => $this->ophyraPricingService->format($amountDue, $paymentCurrency),
            'is_prorated' => $this->shouldProrate($userId),
            'renewal_at' => $renewalAt,
            'billing_cycle' => $this->ophyraPricingService->billingCycle(),
            'payment_log_ready' => $this->supportsPaymentsAllAddonConcept(),
            'payment_log_note' => $this->supportsPaymentsAllAddonConcept()
                ? 'Add-on payments can be logged in payments_all.'
                : 'payments_all.concept must include OphyraAddon before self-service add-on checkout is enabled.',
        ];
    }

    public function getAddonQuotes(int $userId, ?string $paymentCurrency = null): array
    {
        $quotes = [];

        $slugs = ProductProfileService::isOphytrack()
            ? ProductProfileService::allowedAddonSlugs()
            : ModulesRepository::ADDON_SLUGS;
        foreach ($slugs as $moduleSlug) {
            $quote = $this->getAddonQuote($userId, $moduleSlug, $paymentCurrency);

            if ($quote) {
                $quotes[$moduleSlug] = $quote;
            }
        }

        return $quotes;
    }

    public function calculateAmountDue(string $moduleSlug, int $userId, ?DateTimeInterface $today = null): float
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);
        $monthlyPrice = $this->getAddonMonthlyPrice($moduleSlug);

        if (!$this->shouldProrate($userId)) {
            return $monthlyPrice;
        }

        $renewalDate = $this->getUserMembershipDueDate($userId);

        if (!$renewalDate) {
            return $monthlyPrice;
        }

        try {
            $startDate = $today
                ? DateTimeImmutable::createFromInterface($today)->setTime(0, 0)
                : new DateTimeImmutable('today');
            $endDate = (new DateTimeImmutable($renewalDate))->setTime(0, 0);

            if ($endDate <= $startDate) {
                return $monthlyPrice;
            }

            $daysRemaining = (int)$startDate->diff($endDate)->format('%a');
            $proratedAmount = ($monthlyPrice / 30) * max(1, $daysRemaining);

            return round(min($monthlyPrice, $proratedAmount), 2);
        } catch (Exception $e) {
            return $monthlyPrice;
        }
    }

    public function activateAddonAfterPayment(
        int $userId,
        string $moduleSlug,
        ?float $amountPaid = null,
        ?string $paymentReference = null,
        ?array $paymentSnapshot = null
    ): array {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);
        $quote = $this->getAddonQuote($userId, $moduleSlug, $paymentSnapshot['payment_currency'] ?? null);

        if (!$quote) {
            return [
                'success' => false,
                'message' => 'Invalid add-on module.',
            ];
        }

        $amountPaid = $amountPaid ?? (float)$quote['amount_due'];
        if (!$this->supportsPaymentsAllAddonConcept()) {
            return [
                'success' => false,
                'module_slug' => $moduleSlug,
                'message' => 'Add-on payment ledger is not ready. Run the Ophyra commercial launch SQL before enabling self-service add-ons.',
            ];
        }

        $paymentId = $this->logAddonPayment($userId, $moduleSlug, (float)$amountPaid, $quote['renewal_at'], $paymentReference, $paymentSnapshot ?: $quote);
        $activated = $this->userModulesRepository->activateModuleBySlug($userId, $moduleSlug, $quote['renewal_at']);

        if ($activated) {
            $this->markAddonPaidActivation($userId, $moduleSlug, $paymentId, $quote['renewal_at']);

            try {
                $affiliateService = new AffiliateService();
                $affiliateService->createCommission(
                    $userId,
                    'ophyra_addon',
                    (float)$amountPaid,
                    $paymentReference ?: 'addon_' . $moduleSlug . '_' . $userId . '_' . time(),
                    null,
                    $paymentId ?: null,
                    (string)$quote['canonical_slug'],
                    (string)$quote['payment_currency'],
                    false
                );
            } catch (Exception $e) {
                error_log('AddonBillingService::activateAddonAfterPayment() affiliate commission: ' . $e->getMessage());
            }
        }

        return [
            'success' => $activated,
            'module_slug' => $moduleSlug,
            'amount_paid' => $amountPaid,
            'payment_reference' => $paymentReference,
            'renewal_at' => $quote['renewal_at'],
            'payment_id' => $paymentId,
            'payment_log_pending' => false,
            'message' => $activated
                ? 'Add-on activated and payment logged.'
                : 'Add-on could not be activated.',
        ];
    }

    public function markAddonCheckoutStarted(int $userId, string $moduleSlug, ?string $paymentCurrency = null): array
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);
        $quote = $this->getAddonQuote($userId, $moduleSlug, $paymentCurrency);

        if (!$quote) {
            return [
                'success' => false,
                'message' => 'Invalid add-on module.',
            ];
        }

        if (!$this->supportsPaymentsAllAddonConcept()) {
            return [
                'success' => false,
                'message' => 'Add-on payment ledger is not ready.',
            ];
        }

        $this->userModulesRepository->upsertUserModuleBySlug(
            $userId,
            $moduleSlug,
            UserModulesRepository::STATUS_PENDING,
            $quote['renewal_at'],
            (float)$quote['monthly_price']
        );
        $this->setAddonBillingStatus($userId, $moduleSlug, 'checkout_started');

        return [
            'success' => true,
            'module_slug' => $moduleSlug,
            'quote' => $quote,
            'message' => 'Checkout started. Module remains locked until payment is confirmed.',
        ];
    }

    public function markAddonPaymentFailed(int $userId, string $moduleSlug, string $reason = 'payment_failed'): void
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);
        $this->userModulesRepository->setStatusBySlug($userId, $moduleSlug, UserModulesRepository::STATUS_INACTIVE);
        $this->setAddonBillingStatus($userId, $moduleSlug, $reason, true);
    }

    public function markAddonCheckoutCancelled(int $userId, string $moduleSlug): void
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);
        $this->userModulesRepository->setStatusBySlug($userId, $moduleSlug, UserModulesRepository::STATUS_INACTIVE);
        $this->setAddonBillingStatus($userId, $moduleSlug, 'cancelled_checkout');
    }

    public function getSuspiciousActiveAddonsWithoutConfirmedPayment(int $limit = 250): array
    {
        try {
            $this->db->query("
                SELECT um.id, um.id_user, um.module_slug, um.status, um.billing_status, um.started_at, um.renewal_at,
                       u.email, u.name, u.lastname
                FROM user_modules um
                INNER JOIN users u ON u.id = um.id_user
                LEFT JOIN payments_all pa
                    ON pa.user_id = um.id_user
                   AND pa.concept = 'OphyraAddon'
                   AND pa.module_slug = um.module_slug
                   AND pa.status = 'ACTIVE'
                   AND (
                        pa.billing_transaction_id IS NOT NULL
                        OR pa.stripe_event_id IS NOT NULL
                        OR pa.stripe_invoice_id IS NOT NULL
                        OR pa.stripe_payment_intent_id IS NOT NULL
                        OR pa.provider_type = 'manual'
                   )
                WHERE um.status = 'ACTIVE'
                  AND um.module_slug IN ('services','store_delivery_tracking','inventory_storage','ai_advisor','tickets_rsvp','marketplace_connectors')
                  AND pa.id IS NULL
                ORDER BY um.updated_at DESC, um.id DESC
                LIMIT " . max(1, min(500, $limit)) . "
            ");

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('AddonBillingService::getSuspiciousActiveAddonsWithoutConfirmedPayment(): ' . $e->getMessage());
            return [];
        }
    }

    public function getAddonRenewalDate(int $userId): ?string
    {
        if ($this->useSingleRenewalDate()) {
            return $this->getUserMembershipDueDate($userId) ?: (new DateTimeImmutable('+1 month'))->format('Y-m-d');
        }

        return (new DateTimeImmutable('+1 month'))->format('Y-m-d');
    }

    public function getUserMembershipDueDate(int $userId): ?string
    {
        try {
            $this->db->query('SELECT membership_due_date FROM users WHERE id = :id LIMIT 1');
            $this->db->bind(':id', $userId);

            $user = $this->db->fetchOne();
            return $user && $user->membership_due_date ? (string)$user->membership_due_date : null;
        } catch (PDOException $e) {
            error_log('AddonBillingService::getUserMembershipDueDate(): ' . $e->getMessage());
            return null;
        }
    }

    public function getAddonMonthlyPrice(string $moduleSlug): float
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);

        if (!in_array($moduleSlug, ModulesRepository::ADDON_SLUGS, true)
            || !ProductProfileService::allowsAddon($moduleSlug)) {
            return 0.0;
        }

        return (float)($this->modulesRepository->getOfficialPriceBySlug($moduleSlug) ?? 0);
    }

    private function calculateAmountDueForPrice(float $monthlyPrice, int $userId, ?DateTimeInterface $today = null): float
    {
        if (!$this->shouldProrate($userId)) {
            return $monthlyPrice;
        }

        $renewalDate = $this->getUserMembershipDueDate($userId);

        if (!$renewalDate) {
            return $monthlyPrice;
        }

        try {
            $startDate = $today
                ? DateTimeImmutable::createFromInterface($today)->setTime(0, 0)
                : new DateTimeImmutable('today');
            $endDate = (new DateTimeImmutable($renewalDate))->setTime(0, 0);

            if ($endDate <= $startDate) {
                return $monthlyPrice;
            }

            $daysRemaining = (int)$startDate->diff($endDate)->format('%a');
            $proratedAmount = ($monthlyPrice / 30) * max(1, $daysRemaining);

            return round(min($monthlyPrice, $proratedAmount), 2);
        } catch (Exception $e) {
            return $monthlyPrice;
        }
    }

    private function getOfficialAddon(string $moduleSlug): ?object
    {
        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);

        if (!in_array($moduleSlug, ModulesRepository::ADDON_SLUGS, true)
            || !ProductProfileService::allowsAddon($moduleSlug)) {
            return null;
        }

        $module = $this->modulesRepository->getBySlug($moduleSlug);

        if ($module) {
            return $module;
        }

        foreach ($this->fallbackAddons() as $fallbackModule) {
            if ($fallbackModule->slug === $moduleSlug) {
                return $fallbackModule;
            }
        }

        return null;
    }

    private function shouldProrate(int $userId): bool
    {
        return $this->envBool('OPHYRA_ADDONS_PRORATE')
            && $this->useSingleRenewalDate()
            && $this->getUserMembershipDueDate($userId) !== null;
    }

    private function useSingleRenewalDate(): bool
    {
        return $this->envBool('OPHYRA_USE_SINGLE_RENEWAL_DATE');
    }

    private function envBool(string $key): bool
    {
        $value = strtolower((string)($_ENV[$key] ?? 'false'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function fallbackAddons(): array
    {
        $labels = [
            'inventory_storage' => 'Warehouse',
            'services' => 'Service Operations',
            'store_delivery_tracking' => 'Store + Logistics',
            'ai_advisor' => 'AI Advisor',
            'tickets_rsvp' => 'Ticket Sales + RSVP',
            'marketplace_connectors' => 'Marketplace Connectors',
        ];

        return array_map(static function (string $slug) use ($labels): object {
            return (object)[
                'slug' => $slug,
                'name' => $labels[$slug] ?? $slug,
            ];
        }, ModulesRepository::ADDON_SLUGS);
    }

    private function supportsPaymentsAllAddonConcept(): bool
    {
        try {
            $this->db->query("SHOW COLUMNS FROM payments_all LIKE 'concept'");
            $column = $this->db->fetchOne();

            return $column && str_contains((string)$column->Type, "'OphyraAddon'");
        } catch (PDOException $e) {
            error_log('AddonBillingService::supportsPaymentsAllAddonConcept(): ' . $e->getMessage());
            return false;
        }
    }

    private function officialAddonLabel(string $slug, string $fallback): string
    {
        return [
            'services' => 'Service Operations',
            'store_delivery_tracking' => 'Store + Logistics',
            'inventory_storage' => 'Warehouse',
            'ai_advisor' => 'AI Advisor',
            'tickets_rsvp' => 'Ticket Sales + RSVP',
            'marketplace_connectors' => 'Marketplace Connectors',
        ][$slug] ?? $fallback;
    }

    private function logAddonPayment(
        int $userId,
        string $moduleSlug,
        float $amount,
        string $renewalAt,
        ?string $paymentReference = null,
        array $paymentSnapshot = []
    ): int {
        $this->db->query("
            INSERT INTO payments_all
            (user_id, concept, concept_id, id_membership_plan, payment_date, renewal, total, status, reference,
             base_amount, base_currency, display_amount, display_currency, payment_amount, payment_currency,
             exchange_rate, provider_type, payment_method, billing_transaction_id, module_slug)
            VALUES
            (:user_id, 'OphyraAddon', 0, NULL, CURDATE(), :renewal, :total, 'ACTIVE', :reference,
             :base_amount, :base_currency, :display_amount, :display_currency, :payment_amount, :payment_currency,
             :exchange_rate, :provider_type, :payment_method, :billing_transaction_id, :module_slug)
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':renewal', $renewalAt);
        $this->db->bind(':total', $amount);
        $this->db->bind(':reference', $paymentReference ?: 'Ophyra add-on ' . $moduleSlug);
        $this->db->bind(':base_amount', $paymentSnapshot['base_amount'] ?? $amount);
        $this->db->bind(':base_currency', $paymentSnapshot['base_currency'] ?? CurrencyPricingService::BASE_CURRENCY);
        $this->db->bind(':display_amount', $paymentSnapshot['display_amount'] ?? $amount);
        $this->db->bind(':display_currency', $paymentSnapshot['display_currency'] ?? CurrencyPricingService::BASE_CURRENCY);
        $this->db->bind(':payment_amount', $paymentSnapshot['payment_amount'] ?? $amount);
        $this->db->bind(':payment_currency', $paymentSnapshot['payment_currency'] ?? CurrencyPricingService::BASE_CURRENCY);
        $this->db->bind(':exchange_rate', $paymentSnapshot['exchange_rate'] ?? 1);
        $this->db->bind(':provider_type', $paymentSnapshot['provider_type'] ?? 'stripe');
        $this->db->bind(':payment_method', $paymentSnapshot['payment_method'] ?? 'saved_card');
        $this->db->bind(':billing_transaction_id', $paymentSnapshot['billing_transaction_id'] ?? null);
        $this->db->bind(':module_slug', $moduleSlug);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    private function setAddonBillingStatus(int $userId, string $moduleSlug, string $billingStatus, bool $failed = false): void
    {
        try {
            $this->db->query("
                UPDATE user_modules
                SET billing_status = :billing_status,
                    last_payment_failed_at = CASE WHEN :failed = 1 THEN NOW() ELSE last_payment_failed_at END,
                    updated_at = NOW()
                WHERE id_user = :user_id
                  AND module_slug = :module_slug
                LIMIT 1
            ");
            $this->db->bind(':billing_status', $billingStatus);
            $this->db->bind(':failed', $failed ? 1 : 0);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':module_slug', $moduleSlug);
            $this->db->execute();
        } catch (PDOException $e) {
            error_log('AddonBillingService::setAddonBillingStatus(): ' . $e->getMessage());
        }
    }

    private function markAddonPaidActivation(int $userId, string $moduleSlug, int $paymentId, string $renewalAt): void
    {
        $hasActivationSource = $this->hasUserModuleColumn('activation_source');
        $hasCurrentPeriodStart = $this->hasUserModuleColumn('current_period_start');
        $hasLastPaymentId = $this->hasUserModuleColumn('last_payment_id');
        $sets = [
            "billing_status = 'current'",
            'last_payment_failed_at = NULL',
            'updated_at = NOW()',
        ];

        if ($hasActivationSource) {
            $sets[] = "activation_source = 'paid'";
        }
        if ($hasCurrentPeriodStart) {
            $sets[] = 'current_period_start = CURDATE()';
        }
        if ($hasLastPaymentId) {
            $sets[] = 'last_payment_id = :payment_id';
        }

        try {
            $this->db->query("
                UPDATE user_modules
                SET " . implode(', ', $sets) . "
                WHERE id_user = :user_id
                  AND module_slug = :module_slug
                LIMIT 1
            ");
            if ($hasLastPaymentId) {
                $this->db->bind(':payment_id', $paymentId);
            }
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':module_slug', $moduleSlug);
            $this->db->execute();
        } catch (PDOException $e) {
            error_log('AddonBillingService::markAddonPaidActivation(): ' . $e->getMessage());
        }
    }

    private function hasUserModuleColumn(string $column): bool
    {
        try {
            $this->db->query("SHOW COLUMNS FROM user_modules LIKE :column");
            $this->db->bind(':column', $column);
            return (bool)$this->db->fetchOne();
        } catch (PDOException $e) {
            return false;
        }
    }
}
