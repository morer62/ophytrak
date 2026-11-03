<?php

namespace App\Services;

use App\Repositories\AutopayRetryQueueRepository;
use App\Repositories\AutopaySettingRepository;
use App\Repositories\AutopayTransactionRepository;
use App\Repositories\Connection;
use App\Repositories\UserCardsRepository;
use App\Repositories\UserModulesRepository;
use App\Repositories\UserRepository;
use DateTime;
use DateTimeImmutable;
use Throwable;

class AutopayService
{
    private AutopaySettingRepository $settingRepo;
    private AutopayTransactionRepository $transactionRepo;
    private AutopayRetryQueueRepository $retryRepo;
    private UserRepository $userRepo;
    private UserCardsRepository $cardRepo;
    private UserModulesRepository $userModulesRepo;
    private StripeService $stripe;
    private AddonBillingService $addonBilling;
    private OphyraPricingService $ophyraPricingService;
    private CurrencyPricingService $currencyPricingService;

    public function __construct()
    {
        $this->settingRepo = new AutopaySettingRepository();
        $this->transactionRepo = new AutopayTransactionRepository();
        $this->retryRepo = new AutopayRetryQueueRepository();
        $this->userRepo = new UserRepository();
        $this->cardRepo = new UserCardsRepository();
        $this->userModulesRepo = new UserModulesRepository();
        $this->stripe = new StripeService();
        $this->addonBilling = new AddonBillingService();
        $this->ophyraPricingService = new OphyraPricingService();
        $this->currencyPricingService = new CurrencyPricingService();
    }

    private function getPlanAmount(string $planType): float
    {
        return $this->ophyraPricingService->baseProfilePrice();
    }

    private function getPlanDays(string $planType): int
    {
        return 30;
    }

    /**
     * Renew a due base membership and every due paid add-on in one saved-card
     * charge. The retry flag prevents a retry attempt from creating a second
     * retry record when it fails again.
     */
    public function processRenewal(
        int $userId,
        int $autopaySettingId,
        string $planType,
        bool $enqueueRetry = true
    ): array {
        $card = $this->cardRepo->getOne([
            'id_user' => $userId,
            'main_card' => 'yes',
        ]);
        if (!$card) {
            return ['success' => false, 'error' => 'No payment method found'];
        }

        $user = $this->userRepo->getOne(['id' => $userId]);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $today = new DateTimeImmutable('today');
        $previousDueDate = $user->membership_due_date ?? null;
        $baseAmount = $this->getPlanAmount($planType);
        $baseIsDue = false;
        if ($baseAmount > 0 && $previousDueDate) {
            try {
                $baseIsDue = (new DateTimeImmutable((string)$previousDueDate))->setTime(0, 0) <= $today;
            } catch (Throwable $e) {
                $baseIsDue = true;
            }
        }

        $paymentCurrency = ProductProfileService::billingCurrency()
            ?: $this->ophyraPricingService->getDefaultCurrency();
        $totalAmount = 0.0;
        $baseSnapshot = null;
        $newDueDate = $previousDueDate;
        $cycleParts = [];

        if ($baseIsDue) {
            $baseSnapshot = $this->currencyPricingService->createSnapshot(
                $baseAmount,
                $paymentCurrency,
                $paymentCurrency
            );
            $totalAmount += (float)$baseSnapshot['payment_amount'];
            $baseDueDate = new DateTimeImmutable((string)$previousDueDate);
            if ($baseDueDate < $today) {
                $baseDueDate = $today;
            }
            $newDueDate = $baseDueDate
                ->modify('+' . $this->getPlanDays($planType) . ' days')
                ->format('Y-m-d');
            $cycleParts[] = 'base:' . $previousDueDate;
        }

        $addonRenewals = [];
        foreach ($this->userModulesRepo->getDuePaidRenewals($userId, $today) as $module) {
            $moduleSlug = (string)$module->module_slug;
            $quote = $this->addonBilling->getAddonQuote($userId, $moduleSlug, $paymentCurrency);
            $amount = $quote ? (float)$quote['monthly_price'] : (float)($module->price ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $renewalAt = AddonBillingService::resolveRenewalDate((string)$module->renewal_at, $today);
            $snapshot = $quote ?: [
                'base_amount' => $amount,
                'base_currency' => $paymentCurrency,
                'display_amount' => $amount,
                'display_currency' => $paymentCurrency,
                'payment_amount' => $amount,
                'payment_currency' => $paymentCurrency,
                'exchange_rate' => 1,
            ];
            if ($quote) {
                $snapshot['base_amount'] = (float)$quote['base_monthly_price'];
            }
            $snapshot['display_amount'] = $amount;
            $snapshot['payment_amount'] = $amount;
            $snapshot['display_currency'] = $paymentCurrency;
            $snapshot['payment_currency'] = $paymentCurrency;

            $addonRenewals[] = [
                'slug' => $moduleSlug,
                'amount' => $amount,
                'renewal_at' => $renewalAt,
                'previous_renewal_at' => (string)$module->renewal_at,
                'snapshot' => $snapshot,
            ];
            $totalAmount += $amount;
            $cycleParts[] = $moduleSlug . ':' . (string)$module->renewal_at;
            $newDueDate = $renewalAt;
        }

        if ($totalAmount <= 0) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'No paid renewal is due.',
            ];
        }

        $transactionId = $this->transactionRepo->logTransaction([
            'user_id' => $userId,
            'autopay_setting_id' => $autopaySettingId,
            'amount' => $totalAmount,
            'plan_type' => $planType,
            'status' => 'pending',
            'previous_due_date' => $previousDueDate,
        ]);

        $logFile = __DIR__ . '/../../.logs/autopay_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[DEBUG] Transaction logged. ID: {$transactionId}" . PHP_EOL, FILE_APPEND);

        try {
            sort($cycleParts);
            $idempotencyKey = 'autopay-' . hash(
                'sha256',
                $userId . '|' . $planType . '|' . implode('|', $cycleParts)
            );
            file_put_contents(
                $logFile,
                "[DEBUG] Attempting to charge user {$userId} amount {$totalAmount} {$paymentCurrency}" . PHP_EOL,
                FILE_APPEND
            );
            $chargeResult = $this->stripe->createOffSessionChargeV1(
                (string)$card->token,
                $totalAmount,
                strtolower($paymentCurrency),
                [
                    'user_id' => (string)$userId,
                    'autopay_transaction_id' => (string)$transactionId,
                    'renewal_type' => 'membership_and_modules',
                    'module_slugs' => implode(',', array_column($addonRenewals, 'slug')),
                ],
                $idempotencyKey
            );
            file_put_contents(
                $logFile,
                '[DEBUG] Charge result: ' . ($chargeResult ?: 'false') . PHP_EOL,
                FILE_APPEND
            );

            if (!$chargeResult) {
                throw new \RuntimeException('Payment failed');
            }

            if ($baseIsDue && $baseSnapshot) {
                $this->userRepo->updateMembershipAndRegisterPayment(
                    $userId,
                    (string)$newDueDate,
                    (float)$baseSnapshot['payment_amount'],
                    'Stripe autopay charge ' . $chargeResult,
                    'autopay_' . $transactionId . '_' . $chargeResult . '_membership',
                    $baseSnapshot + [
                        'provider_type' => 'stripe',
                        'payment_method' => 'autopay_saved_card',
                    ]
                );
            }

            $renewalWarnings = [];
            foreach ($addonRenewals as $renewal) {
                try {
                    $billingTransactionId = 'stripe_charge:' . $chargeResult . ':addon:' . $renewal['slug'];
                    $result = $this->addonBilling->renewAddonAfterAutopay(
                        $userId,
                        $renewal['slug'],
                        $renewal['amount'],
                        $renewal['renewal_at'],
                        'Stripe add-on autopay charge ' . $chargeResult,
                        $renewal['snapshot'] + [
                            'provider_type' => 'stripe',
                            'payment_method' => 'autopay_saved_card',
                            'billing_transaction_id' => $billingTransactionId,
                            'stripe_payment_intent_id' => $chargeResult,
                        ]
                    );
                    if (empty($result['success'])) {
                        $this->restorePaidModuleAccess($userId, $renewal);
                        $renewalWarnings[] = $renewal['slug'];
                    }
                } catch (Throwable $renewalError) {
                    $this->restorePaidModuleAccess($userId, $renewal);
                    $renewalWarnings[] = $renewal['slug'];
                    error_log('AutopayService::processRenewal() add-on ledger: ' . $renewalError->getMessage());
                }
            }

            $this->markBillingCurrent($userId);
            $this->transactionRepo->update([
                'status' => 'success',
                'new_due_date' => $newDueDate,
                'stripe_charge_id' => $chargeResult,
                'error_message' => $renewalWarnings
                    ? 'Payment succeeded; add-on ledger review: ' . implode(',', $renewalWarnings)
                    : null,
            ], ['id' => $transactionId]);

            NotificationService::sendToUsers(
                [$userId],
                'Renovação mensal confirmada',
                'Sua assinatura foi renovada automaticamente. Valor cobrado: '
                    . $this->ophyraPricingService->format($totalAmount, $paymentCurrency)
            );

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'new_due_date' => $newDueDate,
                'renewed_addons' => array_column($addonRenewals, 'slug'),
                'amount' => $totalAmount,
                'currency' => $paymentCurrency,
            ];
        } catch (Throwable $e) {
            $this->transactionRepo->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ], ['id' => $transactionId]);
            $this->markBillingPastDue($userId);
            foreach ($addonRenewals as $renewal) {
                $this->addonBilling->markAddonRenewalFailed($userId, $renewal['slug']);
            }

            if ($enqueueRetry) {
                $nextRetryDate = (new DateTime())->modify('+1 day')->format('Y-m-d');
                $this->retryRepo->addToQueue($transactionId, $userId, $nextRetryDate);
            }

            NotificationService::sendToUsers(
                [$userId],
                'Falha na renovação automática',
                'Não foi possível processar sua renovação. Atualize o método de pagamento; tentaremos novamente.'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ];
        }
    }

    public function processAllRenewals(): array
    {
        $usersDue = $this->settingRepo->getUsersDueForRenewal($this->getPlanAmount('monthly') > 0);
        $logFile = __DIR__ . '/../../.logs/autopay_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, '[DEBUG] Found ' . count($usersDue) . ' users due for renewal.' . PHP_EOL, FILE_APPEND);

        $results = ['processed' => 0, 'successful' => 0, 'failed' => 0];
        foreach ($usersDue as $userData) {
            $result = $this->processRenewal(
                (int)$userData->user_id,
                (int)$userData->autopay_setting_id,
                (string)$userData->plan_type
            );
            $results['processed']++;
            $results[$result['success'] ? 'successful' : 'failed']++;
        }

        return $results;
    }

    public function processRetries(): array
    {
        $retries = $this->retryRepo->getPendingRetries();
        $results = ['processed' => 0, 'successful' => 0, 'failed' => 0, 'abandoned' => 0];

        foreach ($retries as $retry) {
            if ($this->retryRepo->hasReachedMaxRetries((int)$retry->id)) {
                $this->retryRepo->updateRetryStatus((int)$retry->id, 'abandoned');
                $this->settingRepo->setAutopayStatus((int)$retry->user_id, false);
                NotificationService::sendToUsers(
                    [(int)$retry->user_id],
                    'Renovação automática desativada',
                    'Não foi possível processar o pagamento após várias tentativas. Atualize o método de pagamento e ative novamente.'
                );
                $results['abandoned']++;
                continue;
            }

            $setting = $this->settingRepo->getByUserId((int)$retry->user_id);
            if (!$setting) {
                continue;
            }

            $result = $this->processRenewal(
                (int)$retry->user_id,
                (int)$setting->getId(),
                $setting->getPlanType(),
                false
            );
            $results['processed']++;

            if ($result['success']) {
                $this->retryRepo->updateRetryStatus((int)$retry->id, 'completed');
                $results['successful']++;
            } else {
                $nextRetryDate = (new DateTime())->modify('+1 day')->format('Y-m-d');
                $this->retryRepo->incrementRetry((int)$retry->id, $nextRetryDate);
                $results['failed']++;
            }
        }

        return $results;
    }

    private function restorePaidModuleAccess(int $userId, array $renewal): void
    {
        $this->userModulesRepo->activateModuleBySlug($userId, $renewal['slug'], $renewal['renewal_at']);
        $this->userModulesRepo->setBillingStatusByCompatibleSlugs($userId, [$renewal['slug']], 'current');
    }

    private function markBillingCurrent(int $userId): void
    {
        try {
            $db = new Connection();
            $db->query("
                UPDATE users
                SET billing_status = 'current',
                    billing_failure_count = 0,
                    billing_status_updated_at = NOW()
                WHERE id = :user_id
            ");
            $db->bind(':user_id', $userId);
            $db->execute();
        } catch (Throwable $e) {
        }
    }

    private function markBillingPastDue(int $userId): void
    {
        try {
            $db = new Connection();
            $db->query("
                UPDATE users
                SET billing_status = 'past_due',
                    billing_failure_count = COALESCE(billing_failure_count, 0) + 1,
                    billing_status_updated_at = NOW()
                WHERE id = :user_id
            ");
            $db->bind(':user_id', $userId);
            $db->execute();
        } catch (Throwable $e) {
        }
    }
}
