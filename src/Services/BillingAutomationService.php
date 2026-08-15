<?php

namespace App\Services;

use App\Repositories\Connection;
use App\Repositories\ModulesRepository;
use App\Repositories\UserModulesRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use Exception;
use PDOException;
use Stripe\Webhook;
use UnexpectedValueException;

class BillingAutomationService
{
    private Connection $db;
    private ModulesRepository $modulesRepository;
    private UserModulesRepository $userModulesRepository;
    private CurrencyPricingService $currencyPricingService;
    private OphyraPricingService $ophyraPricingService;

    private const SUPPORTED_EVENTS = [
        'invoice.paid',
        'invoice.payment_failed',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'checkout.session.completed',
    ];

    public function __construct()
    {
        $this->db = new Connection();
        $this->modulesRepository = new ModulesRepository();
        $this->userModulesRepository = new UserModulesRepository();
        $this->currencyPricingService = new CurrencyPricingService();
        $this->ophyraPricingService = new OphyraPricingService();
    }

    public function handleStripeWebhook(string $payload, ?string $signatureHeader): array
    {
        $parsed = json_decode($payload, true);
        $eventId = (string)($parsed['id'] ?? ('unsigned_' . hash('sha256', $payload)));
        $eventType = (string)($parsed['type'] ?? 'unknown');
        $object = $parsed['data']['object'] ?? [];
        $ids = $this->extractStripeIds($object);

        $hardDisabled = $this->envBool('STRIPE_WEBHOOK_HARD_DISABLE');
        $logOnly = $this->envBool('STRIPE_WEBHOOK_LOG_ONLY');

        $log = $this->findEventLog($eventId);
        if (!$log) {
            $logId = $this->createEventLog($eventId, $eventType, $ids, $payload);
            $log = $this->findEventLogById($logId);
        } else {
            $this->appendEventNote((int)$log->id, 'Duplicate delivery received at ' . date('Y-m-d H:i:s'));
            return ['success' => true, 'status' => 'duplicate', 'message' => 'Event already received. Manual review/replay is required for pending or failed events.'];
        }

        if ($hardDisabled) {
            $this->markEvent((int)$log->id, 'ignored', 'Webhook hard disabled by STRIPE_WEBHOOK_HARD_DISABLE.');
            return ['success' => true, 'status' => 'ignored', 'message' => 'Webhook hard disabled.'];
        }

        $secret = trim((string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));
        if ($secret === '') {
            $this->markEvent((int)$log->id, 'pending_review', 'Missing STRIPE_WEBHOOK_SECRET. Event logged but not processed.');
            return ['success' => true, 'status' => 'pending_review', 'message' => 'Webhook secret missing.'];
        }

        try {
            Webhook::constructEvent($payload, (string)$signatureHeader, $secret);
        } catch (UnexpectedValueException $e) {
            $this->markEvent((int)$log->id, 'failed', 'Invalid Stripe payload: ' . $e->getMessage());
            return ['success' => false, 'status' => 'failed', 'message' => 'Invalid payload.', 'http_status' => 400];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->markEvent((int)$log->id, 'failed', 'Invalid Stripe signature: ' . $e->getMessage());
            return ['success' => false, 'status' => 'failed', 'message' => 'Invalid signature.', 'http_status' => 400];
        }

        if ($logOnly) {
            $this->markEvent((int)$log->id, 'ignored', 'Webhook log-only mode enabled by STRIPE_WEBHOOK_LOG_ONLY.');
            return ['success' => true, 'status' => 'ignored', 'message' => 'Log-only mode.'];
        }

        if (!in_array($eventType, self::SUPPORTED_EVENTS, true)) {
            $this->markEvent((int)$log->id, 'ignored', 'Unsupported Stripe event type.');
            return ['success' => true, 'status' => 'ignored', 'message' => 'Unsupported event.'];
        }

        try {
            $result = match ($eventType) {
                'invoice.paid' => $this->processInvoicePaid($parsed, (int)$log->id),
                'invoice.payment_failed' => $this->processInvoicePaymentFailed($parsed, (int)$log->id),
                'checkout.session.completed' => $this->processCheckoutSessionCompleted($parsed, (int)$log->id),
                'customer.subscription.updated' => $this->processSubscriptionUpdated($parsed, (int)$log->id),
                'customer.subscription.deleted' => $this->processSubscriptionDeleted($parsed, (int)$log->id),
                default => ['status' => 'ignored', 'message' => 'Unsupported event.'],
            };

            $this->markEvent((int)$log->id, $result['status'], $result['message'] ?? null, $result['user_id'] ?? null, $result['id_owner'] ?? null);

            return ['success' => true, 'status' => $result['status'], 'message' => $result['message'] ?? 'Processed.'];
        } catch (Exception $e) {
            $this->markEvent((int)$log->id, 'failed', $e->getMessage());
            return ['success' => true, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    public function getRecentEvents(int $limit = 100, string $status = '', string $search = ''): array
    {
        $where = '1=1';
        $params = [];

        if ($status !== '') {
            $where .= ' AND sbe.status = :status';
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where .= ' AND (sbe.stripe_event_id LIKE :search OR sbe.event_type LIKE :search OR sbe.stripe_customer_id LIKE :search OR u.email LIKE :search OR ip.company_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $this->db->query("
            SELECT sbe.*, u.email, u.name, u.lastname, ip.company_name
            FROM stripe_billing_events sbe
            LEFT JOIN users u ON u.id = sbe.user_id
            LEFT JOIN institution_profile ip ON ip.id_owner = sbe.id_owner
            WHERE {$where}
            ORDER BY sbe.created_at DESC, sbe.id DESC
            LIMIT " . max(1, min(250, $limit)) . "
        ");

        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }

        return $this->db->fetchAll();
    }

    public function getEventStats(): array
    {
        $this->db->query("
            SELECT status, COUNT(*) total
            FROM stripe_billing_events
            GROUP BY status
        ");
        $rows = $this->db->fetchAll();
        $stats = [
            'received' => 0,
            'processed' => 0,
            'failed' => 0,
            'ignored' => 0,
            'pending_review' => 0,
        ];

        foreach ($rows as $row) {
            $stats[(string)$row->status] = (int)$row->total;
        }

        return $stats;
    }

    private function processInvoicePaid(array $event, int $eventLogId): array
    {
        $invoice = $event['data']['object'] ?? [];
        $user = $this->resolveUserForStripeObject($invoice);

        if (!$user) {
            return ['status' => 'pending_review', 'message' => 'Could not identify Ophyra user for paid invoice.'];
        }

        $components = $this->extractBillingComponents($invoice);
        if (!$components['base'] && empty($components['addons'])) {
            return [
                'status' => 'pending_review',
                'message' => 'Paid invoice has no Ophyra Base or add-on metadata.',
                'user_id' => (int)$user->id,
                'id_owner' => (int)($user->id_owner ?? $user->id),
            ];
        }

        $renewalAt = $this->resolveRenewalDate($invoice);
        $stripeInvoiceId = (string)($invoice['id'] ?? '');
        $stripePaymentIntentId = $this->stringValue($invoice['payment_intent'] ?? null);
        $chargedCurrency = strtoupper((string)($invoice['currency'] ?? OphyraPricingService::BASE_CURRENCY));
        $eventId = (string)($event['id'] ?? '');
        $processed = [];

        if ($components['base']) {
            $paymentId = $this->recordMembershipPayment(
                (int)$user->id,
                $renewalAt,
                (float)$components['base_amount'],
                'Stripe invoice ' . $stripeInvoiceId . ' Ophyra Base',
                'stripe_invoice:' . $stripeInvoiceId . ':base',
                $eventId,
                $stripeInvoiceId,
                $stripePaymentIntentId,
                $this->snapshotFromCharged((float)$components['base_amount'], (float)$components['base_amount'], $chargedCurrency)
            );
            $processed[] = 'base:' . $paymentId;
        }

        foreach ($components['addons'] as $addon) {
            $paymentId = $this->recordAddonPayment(
                (int)$user->id,
                (string)$addon['module_slug'],
                $renewalAt,
                (float)$addon['amount'],
                'Stripe invoice ' . $stripeInvoiceId . ' add-on ' . $addon['module_slug'],
                'stripe_invoice:' . $stripeInvoiceId . ':addon:' . $addon['module_slug'],
                $eventId,
                $stripeInvoiceId,
                $stripePaymentIntentId,
                $this->snapshotFromCharged($this->officialAddonPrice((string)$addon['module_slug']), (float)$addon['amount'], $chargedCurrency)
            );
            $processed[] = 'addon:' . $addon['module_slug'] . ':' . $paymentId;
        }

        $this->markBillingCurrent((int)$user->id);

        return [
            'status' => 'processed',
            'message' => 'Processed paid invoice: ' . implode(', ', $processed),
            'user_id' => (int)$user->id,
            'id_owner' => (int)($user->id_owner ?? $user->id),
        ];
    }

    private function processInvoicePaymentFailed(array $event, int $eventLogId): array
    {
        $invoice = $event['data']['object'] ?? [];
        $user = $this->resolveUserForStripeObject($invoice);

        if (!$user) {
            return ['status' => 'pending_review', 'message' => 'Could not identify Ophyra user for failed invoice.'];
        }

        $components = $this->extractBillingComponents($invoice);
        $moduleSlugs = array_map(static fn($addon) => (string)$addon['module_slug'], $components['addons']);

        $this->markBillingPastDue(
            (int)$user->id,
            $moduleSlugs,
            'Stripe invoice payment failed: ' . (string)($invoice['id'] ?? '')
        );

        return [
            'status' => 'processed',
            'message' => 'Payment failure recorded. Account/add-ons marked at risk without deleting data.',
            'user_id' => (int)$user->id,
            'id_owner' => (int)($user->id_owner ?? $user->id),
        ];
    }

    private function processCheckoutSessionCompleted(array $event, int $eventLogId): array
    {
        $session = $event['data']['object'] ?? [];

        if (($session['payment_status'] ?? '') !== 'paid') {
            return ['status' => 'ignored', 'message' => 'Checkout session completed without paid status.'];
        }

        $user = $this->resolveUserForStripeObject($session);
        if (!$user) {
            return ['status' => 'pending_review', 'message' => 'Could not identify Ophyra user for checkout session.'];
        }

        $components = $this->extractBillingComponents($session);
        if (!$components['base'] && empty($components['addons'])) {
            return [
                'status' => 'pending_review',
                'message' => 'Checkout session has no Ophyra Base or add-on metadata.',
                'user_id' => (int)$user->id,
                'id_owner' => (int)($user->id_owner ?? $user->id),
            ];
        }

        $renewalAt = $this->datePlusOneMonth();
        $sessionId = (string)($session['id'] ?? '');
        $paymentIntentId = $this->stringValue($session['payment_intent'] ?? null);
        $chargedCurrency = strtoupper((string)($session['currency'] ?? OphyraPricingService::BASE_CURRENCY));
        $eventId = (string)($event['id'] ?? '');
        $processed = [];

        if ($components['base']) {
            $amount = (float)($components['base_amount'] ?: $this->modulesRepository->getBaseMonthlyPrice());
            $paymentId = $this->recordMembershipPayment(
                (int)$user->id,
                $renewalAt,
                $amount,
                'Stripe checkout ' . $sessionId . ' Ophyra Base',
                'stripe_checkout:' . $sessionId . ':base',
                $eventId,
                null,
                $paymentIntentId,
                $this->snapshotFromCharged($this->modulesRepository->getBaseMonthlyPrice(), $amount, $chargedCurrency)
            );
            $processed[] = 'base:' . $paymentId;
        }

        foreach ($components['addons'] as $addon) {
            $paymentId = $this->recordAddonPayment(
                (int)$user->id,
                (string)$addon['module_slug'],
                $renewalAt,
                (float)$addon['amount'],
                'Stripe checkout ' . $sessionId . ' add-on ' . $addon['module_slug'],
                'stripe_checkout:' . $sessionId . ':addon:' . $addon['module_slug'],
                $eventId,
                null,
                $paymentIntentId,
                $this->snapshotFromCharged($this->officialAddonPrice((string)$addon['module_slug']), (float)$addon['amount'], $chargedCurrency)
            );
            $processed[] = 'addon:' . $addon['module_slug'] . ':' . $paymentId;
        }

        $this->markBillingCurrent((int)$user->id);

        return [
            'status' => 'processed',
            'message' => 'Processed checkout session: ' . implode(', ', $processed),
            'user_id' => (int)$user->id,
            'id_owner' => (int)($user->id_owner ?? $user->id),
        ];
    }

    private function processSubscriptionUpdated(array $event, int $eventLogId): array
    {
        $subscription = $event['data']['object'] ?? [];
        $user = $this->resolveUserForStripeObject($subscription);

        if (!$user) {
            return ['status' => 'pending_review', 'message' => 'Could not identify Ophyra user for subscription update.'];
        }

        $status = (string)($subscription['status'] ?? '');
        if (in_array($status, ['active', 'trialing'], true)) {
            $this->markBillingCurrent((int)$user->id);
            $message = 'Subscription status marked current.';
        } elseif (in_array($status, ['past_due', 'unpaid', 'incomplete', 'incomplete_expired'], true)) {
            $this->markBillingPastDue((int)$user->id, [], 'Stripe subscription status: ' . $status);
            $message = 'Subscription status marked at risk: ' . $status;
        } else {
            $message = 'Subscription status observed: ' . ($status ?: 'unknown');
        }

        return [
            'status' => 'processed',
            'message' => $message,
            'user_id' => (int)$user->id,
            'id_owner' => (int)($user->id_owner ?? $user->id),
        ];
    }

    private function processSubscriptionDeleted(array $event, int $eventLogId): array
    {
        $subscription = $event['data']['object'] ?? [];
        $user = $this->resolveUserForStripeObject($subscription);

        if (!$user) {
            return ['status' => 'pending_review', 'message' => 'Could not identify Ophyra user for deleted subscription.'];
        }

        $this->db->query("
            UPDATE users
            SET billing_status = 'subscription_canceled',
                billing_status_updated_at = NOW()
            WHERE id = :user_id
            LIMIT 1
        ");
        $this->db->bind(':user_id', (int)$user->id);
        $this->db->execute();

        $this->db->query("
            UPDATE user_modules
            SET billing_status = 'at_risk',
                updated_at = NOW()
            WHERE id_user = :user_id
              AND status = 'ACTIVE'
        ");
        $this->db->bind(':user_id', (int)$user->id);
        $this->db->execute();

        return [
            'status' => 'processed',
            'message' => 'Subscription deleted. Account marked canceled/at risk without deleting data.',
            'user_id' => (int)$user->id,
            'id_owner' => (int)($user->id_owner ?? $user->id),
        ];
    }

    private function recordMembershipPayment(
        int $userId,
        string $renewalAt,
        float $amount,
        string $reference,
        string $billingTransactionId,
        ?string $stripeEventId,
        ?string $stripeInvoiceId,
        ?string $stripePaymentIntentId,
        array $paymentSnapshot = []
    ): int {
        $existing = $this->findPaymentByBillingTransaction($billingTransactionId);
        if ($existing) {
            $this->updateMembershipRenewal($userId, $renewalAt);
            return (int)$existing->id;
        }

        return (new UserRepository())->updateMembershipAndRegisterPayment(
            $userId,
            $renewalAt,
            $amount,
            $reference,
            $billingTransactionId,
            [
                'stripe_event_id' => $stripeEventId,
                'stripe_invoice_id' => $stripeInvoiceId,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
            ] + $paymentSnapshot
        );
    }

    private function recordAddonPayment(
        int $userId,
        string $moduleSlug,
        string $renewalAt,
        float $amount,
        string $reference,
        string $billingTransactionId,
        ?string $stripeEventId,
        ?string $stripeInvoiceId,
        ?string $stripePaymentIntentId,
        array $paymentSnapshot = []
    ): int {
        $existing = $this->findPaymentByBillingTransaction($billingTransactionId);
        if ($existing) {
            $this->renewAddon($userId, $moduleSlug, $renewalAt);
            return (int)$existing->id;
        }

        $this->db->query("
            INSERT INTO payments_all
            (user_id, concept, concept_id, id_membership_plan, payment_date, renewal, total, status, reference,
             base_amount, base_currency, display_amount, display_currency, payment_amount, payment_currency,
             exchange_rate, provider_type, payment_method, billing_transaction_id, stripe_event_id,
             stripe_invoice_id, stripe_payment_intent_id, module_slug)
            VALUES
            (:user_id, 'OphyraAddon', 0, NULL, CURDATE(), :renewal, :total, 'ACTIVE', :reference,
             :base_amount, :base_currency, :display_amount, :display_currency, :payment_amount, :payment_currency,
             :exchange_rate, :provider_type, :payment_method, :billing_transaction_id, :stripe_event_id,
             :stripe_invoice_id, :stripe_payment_intent_id, :module_slug)
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':renewal', $renewalAt);
        $this->db->bind(':total', $amount);
        $this->db->bind(':reference', $reference);
        $this->db->bind(':base_amount', $paymentSnapshot['base_amount'] ?? $amount);
        $this->db->bind(':base_currency', $paymentSnapshot['base_currency'] ?? OphyraPricingService::BASE_CURRENCY);
        $this->db->bind(':display_amount', $paymentSnapshot['display_amount'] ?? $amount);
        $this->db->bind(':display_currency', $paymentSnapshot['display_currency'] ?? OphyraPricingService::BASE_CURRENCY);
        $this->db->bind(':payment_amount', $paymentSnapshot['payment_amount'] ?? $amount);
        $this->db->bind(':payment_currency', $paymentSnapshot['payment_currency'] ?? OphyraPricingService::BASE_CURRENCY);
        $this->db->bind(':exchange_rate', $paymentSnapshot['exchange_rate'] ?? 1);
        $this->db->bind(':provider_type', $paymentSnapshot['provider_type'] ?? 'stripe');
        $this->db->bind(':payment_method', $paymentSnapshot['payment_method'] ?? 'stripe_webhook');
        $this->db->bind(':billing_transaction_id', $billingTransactionId);
        $this->db->bind(':stripe_event_id', $stripeEventId);
        $this->db->bind(':stripe_invoice_id', $stripeInvoiceId);
        $this->db->bind(':stripe_payment_intent_id', $stripePaymentIntentId);
        $this->db->bind(':module_slug', $moduleSlug);
        $this->db->execute();
        $paymentId = (int)$this->db->lastId();

        $this->renewAddon($userId, $moduleSlug, $renewalAt);
        $this->createAffiliateCommission($userId, 'ophyra_addon', $amount, $billingTransactionId, $paymentId);

        return $paymentId;
    }

    private function updateMembershipRenewal(int $userId, string $renewalAt): void
    {
        $this->db->query("
            UPDATE users
            SET membership_due_date = :renewal,
                membership_type = 'PAID',
                billing_status = 'current',
                billing_status_updated_at = NOW(),
                billing_failure_count = 0
            WHERE id = :user_id
            LIMIT 1
        ");
        $this->db->bind(':renewal', $renewalAt);
        $this->db->bind(':user_id', $userId);
        $this->db->execute();
    }

    private function renewAddon(int $userId, string $moduleSlug, string $renewalAt): void
    {
        $this->userModulesRepository->activateModuleBySlug($userId, $moduleSlug, $renewalAt);

        $this->db->query("
            UPDATE user_modules
            SET billing_status = 'current',
                last_payment_failed_at = NULL,
                updated_at = NOW()
            WHERE id_user = :user_id
              AND module_slug = :module_slug
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':module_slug', $moduleSlug);
        $this->db->execute();
    }

    private function markBillingCurrent(int $userId): void
    {
        $this->db->query("
            UPDATE users
            SET billing_status = 'current',
                billing_status_updated_at = NOW(),
                billing_failure_count = 0
            WHERE id = :user_id
            LIMIT 1
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->execute();
    }

    private function markBillingPastDue(int $userId, array $moduleSlugs, string $reason): void
    {
        $this->db->query("
            UPDATE users
            SET billing_status = 'past_due',
                billing_status_updated_at = NOW(),
                billing_failure_count = billing_failure_count + 1
            WHERE id = :user_id
            LIMIT 1
        ");
        $this->db->bind(':user_id', $userId);
        $this->db->execute();

        if (empty($moduleSlugs)) {
            $this->db->query("
                UPDATE user_modules
                SET billing_status = 'at_risk',
                    last_payment_failed_at = NOW(),
                    updated_at = NOW()
                WHERE id_user = :user_id
                  AND status = 'ACTIVE'
            ");
            $this->db->bind(':user_id', $userId);
            $this->db->execute();
        } else {
            foreach ($moduleSlugs as $moduleSlug) {
                $this->db->query("
                    UPDATE user_modules
                    SET billing_status = 'at_risk',
                        last_payment_failed_at = NOW(),
                        updated_at = NOW()
                    WHERE id_user = :user_id
                      AND module_slug = :module_slug
                ");
                $this->db->bind(':user_id', $userId);
                $this->db->bind(':module_slug', $moduleSlug);
                $this->db->execute();
            }
        }

        $this->addAdminAudit(0, $userId, 'billing_payment_failed', $reason);
    }

    private function extractBillingComponents(array $object): array
    {
        $metadata = $this->metadataFromObject($object);
        $components = [
            'base' => $this->truthy($metadata['ophyra_base'] ?? $metadata['includes_base'] ?? null)
                || (($metadata['plan'] ?? '') === 'ophyra_base'),
            'base_amount' => 0.0,
            'addons' => [],
        ];

        $lines = $object['lines']['data'] ?? [];
        foreach ($lines as $line) {
            $lineMetadata = $this->metadataFromObject($line);
            $amount = isset($line['amount']) ? ((float)$line['amount'] / 100) : 0.0;
            $moduleSlug = $lineMetadata['module_slug'] ?? $lineMetadata['ophyra_module_slug'] ?? null;

            if ($moduleSlug && in_array($moduleSlug, ModulesRepository::ADDON_SLUGS, true)) {
                $components['addons'][$moduleSlug] = [
                    'module_slug' => $moduleSlug,
                    'amount' => $amount > 0 ? $amount : $this->officialAddonPrice($moduleSlug),
                ];
                continue;
            }

            if (
                $this->truthy($lineMetadata['ophyra_base'] ?? $lineMetadata['includes_base'] ?? null)
                || (($lineMetadata['plan'] ?? '') === 'ophyra_base')
            ) {
                $components['base'] = true;
                $components['base_amount'] += $amount;
            }
        }

        if (!empty($metadata['module_slug']) && in_array($metadata['module_slug'], ModulesRepository::ADDON_SLUGS, true)) {
            $slug = (string)$metadata['module_slug'];
            $components['addons'][$slug] = [
                'module_slug' => $slug,
                'amount' => $this->amountFromObject($object) ?: $this->officialAddonPrice($slug),
            ];
        }

        if (!empty($metadata['module_slugs'])) {
            foreach (array_filter(array_map('trim', explode(',', (string)$metadata['module_slugs']))) as $slug) {
                if (in_array($slug, ModulesRepository::ADDON_SLUGS, true)) {
                    $components['addons'][$slug] = [
                        'module_slug' => $slug,
                        'amount' => $this->officialAddonPrice($slug),
                    ];
                }
            }
        }

        if ($components['base'] && $components['base_amount'] <= 0) {
            $components['base_amount'] = $this->modulesRepository->getBaseMonthlyPrice();
        }

        $components['addons'] = array_values($components['addons']);
        return $components;
    }

    private function resolveUserForStripeObject(array $object): ?object
    {
        $metadata = $this->metadataFromObject($object);
        $userId = (int)($metadata['user_id'] ?? $metadata['id_user'] ?? $metadata['ophyra_user_id'] ?? 0);

        if ($userId > 0) {
            return $this->getUserById($userId);
        }

        $customerId = $this->stringValue($object['customer'] ?? null);
        if ($customerId !== '') {
            $this->db->query("
                SELECT u.*
                FROM user_cards uc
                INNER JOIN users u ON u.id = uc.id_user
                WHERE uc.token = :customer_id
                ORDER BY uc.main_card DESC, uc.id DESC
                LIMIT 1
            ");
            $this->db->bind(':customer_id', $customerId);
            $user = $this->db->fetchOne();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function getUserById(int $userId): ?object
    {
        $this->db->query("SELECT * FROM users WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $userId);
        $user = $this->db->fetchOne();
        return $user ?: null;
    }

    private function metadataFromObject(array $object): array
    {
        $metadata = $object['metadata'] ?? [];

        if (!empty($object['subscription_details']['metadata']) && is_array($object['subscription_details']['metadata'])) {
            $metadata = array_merge($metadata, $object['subscription_details']['metadata']);
        }

        if (!empty($object['price']['metadata']) && is_array($object['price']['metadata'])) {
            $metadata = array_merge($metadata, $object['price']['metadata']);
        }

        if (!empty($object['plan']['metadata']) && is_array($object['plan']['metadata'])) {
            $metadata = array_merge($metadata, $object['plan']['metadata']);
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function resolveRenewalDate(array $object): string
    {
        $timestamps = [];
        foreach (($object['lines']['data'] ?? []) as $line) {
            if (!empty($line['period']['end'])) {
                $timestamps[] = (int)$line['period']['end'];
            }
        }

        foreach (['period_end', 'current_period_end'] as $key) {
            if (!empty($object[$key])) {
                $timestamps[] = (int)$object[$key];
            }
        }

        if (!empty($timestamps)) {
            return date('Y-m-d', max($timestamps));
        }

        return $this->datePlusOneMonth();
    }

    private function datePlusOneMonth(): string
    {
        return (new DateTimeImmutable('+1 month'))->format('Y-m-d');
    }

    private function officialAddonPrice(string $moduleSlug): float
    {
        return (float)($this->modulesRepository->getOfficialPriceBySlug($moduleSlug) ?? 0);
    }

    private function amountFromObject(array $object): float
    {
        foreach (['amount_paid', 'amount_total', 'amount_received', 'amount'] as $key) {
            if (isset($object[$key])) {
                return round(((float)$object[$key]) / 100, 2);
            }
        }

        return 0.0;
    }

    private function snapshotFromCharged(float $baseAmountUsd, float $chargedAmount, string $chargedCurrency): array
    {
        $chargedCurrency = $this->currencyPricingService->normalizeCurrency($chargedCurrency, OphyraPricingService::BASE_CURRENCY);
        $baseAmountUsd = round(max(0, $baseAmountUsd), 2);
        $chargedAmount = round(max(0, $chargedAmount), 2);
        $exchangeRate = $baseAmountUsd > 0 ? round($chargedAmount / $baseAmountUsd, 6) : 1.0;

        return [
            'base_amount' => $baseAmountUsd,
            'base_currency' => OphyraPricingService::BASE_CURRENCY,
            'display_amount' => $chargedAmount,
            'display_currency' => $chargedCurrency,
            'payment_amount' => $chargedAmount,
            'payment_currency' => $chargedCurrency,
            'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1,
            'provider_type' => 'stripe',
            'payment_method' => 'stripe_webhook',
        ];
    }

    private function findPaymentByBillingTransaction(string $billingTransactionId): ?object
    {
        $this->db->query("SELECT id FROM payments_all WHERE billing_transaction_id = :id LIMIT 1");
        $this->db->bind(':id', $billingTransactionId);
        $payment = $this->db->fetchOne();
        return $payment ?: null;
    }

    private function createAffiliateCommission(int $userId, string $transactionType, float $amount, string $transactionId, int $paymentId): void
    {
        try {
            (new AffiliateService())->createCommission($userId, $transactionType, $amount, $transactionId, null, $paymentId);
        } catch (Exception $e) {
            $this->addAdminAudit(0, $userId, 'affiliate_commission_pending_review', $e->getMessage(), [
                'transaction_type' => $transactionType,
                'transaction_id' => $transactionId,
                'payment_id' => $paymentId,
            ]);
        }
    }

    private function addAdminAudit(int $adminId, int $userId, string $actionType, ?string $note = null, array $metadata = []): void
    {
        try {
            $this->db->query("
                INSERT INTO admin_account_actions
                (id_admin, id_user, action_type, note, metadata_json, created_at)
                VALUES
                (:admin_id, :user_id, :action_type, :note, :metadata_json, NOW())
            ");
            $this->db->bind(':admin_id', $adminId);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':action_type', $actionType);
            $this->db->bind(':note', $note);
            $this->db->bind(':metadata_json', $metadata ? json_encode($metadata) : null);
            $this->db->execute();
        } catch (PDOException $e) {
            error_log('BillingAutomationService::addAdminAudit(): ' . $e->getMessage());
        }
    }

    private function extractStripeIds(array $object): array
    {
        return [
            'stripe_customer_id' => $this->stringValue($object['customer'] ?? null),
            'stripe_subscription_id' => $this->stringValue($object['subscription'] ?? $object['id'] ?? null),
            'stripe_invoice_id' => (($object['object'] ?? '') === 'invoice') ? $this->stringValue($object['id'] ?? null) : $this->stringValue($object['invoice'] ?? null),
            'stripe_payment_intent_id' => $this->stringValue($object['payment_intent'] ?? null),
            'stripe_checkout_session_id' => (($object['object'] ?? '') === 'checkout.session') ? $this->stringValue($object['id'] ?? null) : null,
        ];
    }

    private function createEventLog(string $eventId, string $eventType, array $ids, string $payload): int
    {
        $this->db->query("
            INSERT INTO stripe_billing_events
            (stripe_event_id, event_type, stripe_customer_id, stripe_subscription_id, stripe_invoice_id,
             stripe_payment_intent_id, stripe_checkout_session_id, raw_payload, status, created_at, updated_at)
            VALUES
            (:event_id, :event_type, :customer_id, :subscription_id, :invoice_id,
             :payment_intent_id, :checkout_session_id, :raw_payload, 'received', NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $this->db->bind(':event_id', $eventId);
        $this->db->bind(':event_type', $eventType);
        $this->db->bind(':customer_id', $ids['stripe_customer_id'] ?? null);
        $this->db->bind(':subscription_id', $ids['stripe_subscription_id'] ?? null);
        $this->db->bind(':invoice_id', $ids['stripe_invoice_id'] ?? null);
        $this->db->bind(':payment_intent_id', $ids['stripe_payment_intent_id'] ?? null);
        $this->db->bind(':checkout_session_id', $ids['stripe_checkout_session_id'] ?? null);
        $this->db->bind(':raw_payload', $payload);
        $this->db->execute();

        $lastId = (int)$this->db->lastId();
        if ($lastId > 0) {
            return $lastId;
        }

        $existing = $this->findEventLog($eventId);
        return $existing ? (int)$existing->id : 0;
    }

    private function findEventLog(string $eventId): ?object
    {
        $this->db->query("SELECT * FROM stripe_billing_events WHERE stripe_event_id = :event_id LIMIT 1");
        $this->db->bind(':event_id', $eventId);
        $event = $this->db->fetchOne();
        return $event ?: null;
    }

    private function findEventLogById(int $id): ?object
    {
        $this->db->query("SELECT * FROM stripe_billing_events WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $id);
        $event = $this->db->fetchOne();
        return $event ?: null;
    }

    private function markEvent(int $eventLogId, string $status, ?string $message = null, ?int $userId = null, ?int $ownerId = null): void
    {
        $isProblemStatus = in_array($status, ['failed', 'pending_review'], true) ? 1 : 0;
        $isFinalStatus = in_array($status, ['processed', 'failed', 'ignored', 'pending_review'], true) ? 1 : 0;

        $this->db->query("
            UPDATE stripe_billing_events
            SET status = :status,
                error_message = CASE WHEN :is_problem_status = 1 THEN :message ELSE error_message END,
                processing_notes = CASE WHEN :is_problem_status = 0 THEN :message ELSE processing_notes END,
                user_id = COALESCE(:user_id, user_id),
                id_owner = COALESCE(:owner_id, id_owner),
                processed_at = CASE WHEN :is_final_status = 1 THEN NOW() ELSE processed_at END,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $this->db->bind(':status', $status);
        $this->db->bind(':is_problem_status', $isProblemStatus);
        $this->db->bind(':is_final_status', $isFinalStatus);
        $this->db->bind(':message', $message);
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':id', $eventLogId);
        $this->db->execute();
    }

    private function appendEventNote(int $eventLogId, string $note): void
    {
        $this->db->query("
            UPDATE stripe_billing_events
            SET processing_notes = CONCAT(COALESCE(processing_notes, ''), CASE WHEN processing_notes IS NULL OR processing_notes = '' THEN '' ELSE '\n' END, :note),
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $this->db->bind(':note', $note);
        $this->db->bind(':id', $eventLogId);
        $this->db->execute();
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_array($value)) {
            return isset($value['id']) ? (string)$value['id'] : null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on', 'base', 'ophyra_base'], true);
    }

    private function envBool(string $key): bool
    {
        return in_array(strtolower((string)($_ENV[$key] ?? 'false')), ['1', 'true', 'yes', 'on'], true);
    }
}
