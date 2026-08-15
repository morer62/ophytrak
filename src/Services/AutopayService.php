<?php

namespace App\Services;

use App\Repositories\AutopaySettingRepository;
use App\Repositories\AutopayTransactionRepository;
use App\Repositories\AutopayRetryQueueRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserCardsRepository;
use App\Repositories\Connection;
use DateTime;

class AutopayService
{
    private AutopaySettingRepository $settingRepo;
    private AutopayTransactionRepository $transactionRepo;
    private AutopayRetryQueueRepository $retryRepo;
    private UserRepository $userRepo;
    private UserCardsRepository $cardRepo;
    private StripeService $stripe;
    private OphyraPricingService $ophyraPricingService;
    private CurrencyPricingService $currencyPricingService;

    public function __construct()
    {
        $this->settingRepo = new AutopaySettingRepository();
        $this->transactionRepo = new AutopayTransactionRepository();
        $this->retryRepo = new AutopayRetryQueueRepository();
        $this->userRepo = new UserRepository();
        $this->cardRepo = new UserCardsRepository();
        $this->stripe = new StripeService();
        $this->ophyraPricingService = new OphyraPricingService();
        $this->currencyPricingService = new CurrencyPricingService();
    }

    /**
     * Get amount for a plan type
     */
    private function getPlanAmount(string $planType): float
    {
        return $this->ophyraPricingService->baseProfilePrice();
    }

    /**
     * Get days to add for a plan type
     */
    private function getPlanDays(string $planType): int
    {
        return 30;
    }

    /**
     * Process autopay renewal for a single user
     */
    public function processRenewal(int $userId, int $autopaySettingId, string $planType): array
    {
        // Get user's main card
        $card = $this->cardRepo->getOne([
            'id_user' => $userId,
            'main_card' => 'yes'
        ]);

        if (!$card) {
            return [
                'success' => false,
                'error' => 'No payment method found'
            ];
        }

        // Get current membership due date
        $user = $this->userRepo->getOne(['id' => $userId]);
        $previousDueDate = $user->membership_due_date ?? date('Y-m-d');
        $baseDueDate = new DateTime($previousDueDate);
        $today = new DateTime();

        if ($baseDueDate < $today) {
            $baseDueDate = $today;
        }
        
        // Calculate amount and new due date
        $amount = $this->getPlanAmount($planType);
        $daysToAdd = $this->getPlanDays($planType);
        $newDueDate = $baseDueDate->modify("+{$daysToAdd} days")->format('Y-m-d');

        // Log transaction attempt
        $transactionId = $this->transactionRepo->logTransaction([
            'user_id' => $userId,
            'autopay_setting_id' => $autopaySettingId,
            'amount' => $amount,
            'plan_type' => $planType,
            'status' => 'pending',
            'previous_due_date' => $previousDueDate
        ]);

        $logFile = __DIR__ . '/../../.logs/autopay_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[DEBUG] Transaction logged. ID: $transactionId" . PHP_EOL, FILE_APPEND);

        try {
            // Attempt to charge
            file_put_contents($logFile, "[DEBUG] Attempting to charge user $userId amount $amount" . PHP_EOL, FILE_APPEND);
            $snapshot = $this->currencyPricingService->createSnapshot($amount, OphyraPricingService::BASE_CURRENCY, OphyraPricingService::BASE_CURRENCY);
            $chargeResult = $this->stripe->createChargeV1($card->token, (float)$snapshot['payment_amount'], strtolower((string)$snapshot['payment_currency']));
            file_put_contents($logFile, "[DEBUG] Charge result: " . ($chargeResult ? $chargeResult : 'false') . PHP_EOL, FILE_APPEND);

            if ($chargeResult) {
                // Success - update membership through the audited payment path.
                file_put_contents($logFile, "[DEBUG] Updating user $userId membership to $newDueDate and logging payments_all." . PHP_EOL, FILE_APPEND);
                $this->userRepo->updateMembershipAndRegisterPayment(
                    $userId,
                    $newDueDate,
                    (float)$snapshot['payment_amount'],
                    'Stripe autopay charge ' . $chargeResult,
                    'autopay_' . $transactionId . '_' . $chargeResult,
                    $snapshot + [
                        'provider_type' => 'stripe',
                        'payment_method' => 'autopay_saved_card',
                    ]
                );
                $this->markBillingCurrent($userId);
                file_put_contents($logFile, "[DEBUG] User updated and payment logged." . PHP_EOL, FILE_APPEND);

                // Update transaction with actual Stripe charge ID
                $this->transactionRepo->update([
                    'status' => 'success',
                    'new_due_date' => $newDueDate,
                    'stripe_charge_id' => $chargeResult // chargeResult now contains the charge ID
                ], ['id' => $transactionId]);
                file_put_contents($logFile, "[DEBUG] Transaction updated with charge ID: $chargeResult" . PHP_EOL, FILE_APPEND);

                // Send success notification
                NotificationService::sendToUsers(
                    [$userId],
                    '✅ Membership Renewed',
                    "Your membership has been automatically renewed until {$newDueDate}. Amount charged: \${$amount}"
                );

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'new_due_date' => $newDueDate
                ];
            } else {
                throw new \Exception('Payment failed');
            }
        } catch (\Exception $e) {
            // Failed - update transaction and add to retry queue
            $this->transactionRepo->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ], ['id' => $transactionId]);

            $this->markBillingPastDue($userId);

            // Add to retry queue (retry tomorrow)
            $nextRetryDate = (new DateTime())->modify('+1 day')->format('Y-m-d');
            $this->retryRepo->addToQueue($transactionId, $userId, $nextRetryDate);

            // Send failure notification
            NotificationService::sendToUsers(
                [$userId],
                '⚠️ Membership Renewal Failed',
                "We couldn't process your automatic renewal. Please update your payment method. We'll retry tomorrow."
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ];
        }
    }

    /**
     * Process all pending renewals
     */
    public function processAllRenewals(): array
    {
        $usersDue = $this->settingRepo->getUsersDueForRenewal();
        
        // Debug logging
        $logFile = __DIR__ . '/../../.logs/autopay_' . date('Y-m-d') . '.log';
        $msg = "Found " . count($usersDue) . " users due for renewal.";
        file_put_contents($logFile, "[DEBUG] $msg" . PHP_EOL, FILE_APPEND);

        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        ];

        foreach ($usersDue as $userData) {
            $msg = "Processing user ID: " . $userData->user_id;
            file_put_contents($logFile, "[DEBUG] $msg" . PHP_EOL, FILE_APPEND);

            $result = $this->processRenewal(
                $userData->user_id,
                $userData->id,
                $userData->plan_type
            );

            $results['processed']++;
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Process retry queue
     */
    public function processRetries(): array
    {
        $retries = $this->retryRepo->getPendingRetries();
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'abandoned' => 0
        ];

        foreach ($retries as $retry) {
            // Check if max retries reached
            if ($this->retryRepo->hasReachedMaxRetries($retry->id)) {
                $this->retryRepo->updateRetryStatus($retry->id, 'abandoned');
                $this->settingRepo->setAutopayStatus($retry->user_id, false);
                
                NotificationService::sendToUsers(
                    [$retry->user_id],
                    '❌ Auto-Renewal Disabled',
                    'We were unable to process your payment after multiple attempts. Auto-renewal has been disabled. Please update your payment method and re-enable it.'
                );
                
                $results['abandoned']++;
                continue;
            }

            // Get autopay setting
            $setting = $this->settingRepo->getByUserId($retry->user_id);
            if (!$setting) {
                continue;
            }

            // Attempt renewal
            $result = $this->processRenewal(
                $retry->user_id,
                $setting->getId(),
                $setting->getPlanType()
            );

            $results['processed']++;

            if ($result['success']) {
                $this->retryRepo->updateRetryStatus($retry->id, 'completed');
                $results['successful']++;
            } else {
                // Increment retry
                $nextRetryDate = (new DateTime())->modify('+1 day')->format('Y-m-d');
                $this->retryRepo->incrementRetry($retry->id, $nextRetryDate);
                $results['failed']++;
            }
        }

        return $results;
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
        }
    }
}
