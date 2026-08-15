<?php

namespace App\Services;

use App\Repositories\AuthorizedManualChargeLogsRepository;
use App\Repositories\ClientAutoChargeConsentsRepository;
use App\Repositories\ClientSavedPaymentMethodsRepository;
use App\Repositories\Connection;
use App\Repositories\OrdersPaymentsRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Repositories\UserRepository;
use App\Services\Payment\PaymentProviderFactory;

class AuthorizedManualChargeService
{
    private Connection $db;
    private OrdersRepository $orders;
    private ClientSavedPaymentMethodsRepository $methods;
    private ClientAutoChargeConsentsRepository $consents;
    private AuthorizedManualChargeLogsRepository $logs;

    public function __construct()
    {
        $this->db = new Connection();
        $this->orders = new OrdersRepository();
        $this->methods = new ClientSavedPaymentMethodsRepository();
        $this->consents = new ClientAutoChargeConsentsRepository();
        $this->logs = new AuthorizedManualChargeLogsRepository();
    }

    public function getOrderEligibility(int $orderId, object $admin): array
    {
        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['eligible' => false, 'reason' => 'Order not found.'];
        }

        $permission = $this->canAdminAccessOrder($admin, $order);
        if (!$permission['allowed']) {
            return ['eligible' => false, 'reason' => $permission['reason'], 'order' => $order];
        }

        $businessId = (int)($order->id_owner ?? 0);
        $clientId = (int)($order->id_client ?? 0);
        $balance = $this->calculateOrderBalance($orderId);
        $methods = $clientId > 0 ? $this->methods->getActiveForClient($businessId, $clientId) : [];

        $eligibleMethods = [];
        foreach ($methods as $method) {
            $consent = $this->consents->getActiveForMethod($businessId, $clientId, (int)$method->id);
            if ($consent) {
                $method->active_consent = $consent;
                $eligibleMethods[] = $method;
            }
        }

        return [
            'eligible' => $balance > 0 && !empty($eligibleMethods),
            'reason' => $balance <= 0 ? 'No pending balance.' : (empty($eligibleMethods) ? 'No saved method with active consent.' : null),
            'order' => $order,
            'balance' => $balance,
            'methods' => $eligibleMethods,
        ];
    }

    public function chargeOrderBalance(int $orderId, int $methodId, float $amount, object $admin): array
    {
        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }

        $permission = $this->canAdminAccessOrder($admin, $order);
        if (!$permission['allowed']) {
            return ['success' => false, 'message' => $permission['reason']];
        }

        $businessId = (int)$order->id_owner;
        $clientId = (int)$order->id_client;
        $balance = $this->calculateOrderBalance($orderId);
        if ($amount <= 0 || $balance <= 0 || $amount > $balance) {
            return ['success' => false, 'message' => 'Invalid charge amount.'];
        }

        $method = $this->methods->getActiveByIdForBusiness($methodId, $businessId);
        if (!$method || (int)($method->id_client ?? $method->user_id ?? 0) !== $clientId) {
            return ['success' => false, 'message' => 'Saved payment method is not valid for this order.'];
        }

        $consent = $this->consents->getActiveForMethod($businessId, $clientId, (int)$method->id);
        if (!$consent) {
            return ['success' => false, 'message' => 'Active auto-charge consent was not found.'];
        }

        $providerRepo = new PaymentProvidersRepository();
        $credentials = $providerRepo->getActiveProviderForOwner($businessId);
        if (!$credentials || strtolower((string)$credentials->provider_type) !== strtolower((string)$method->payment_provider)) {
            $typed = $providerRepo->getByType($businessId, (string)$method->payment_provider);
            $credentials = $typed[0] ?? null;
        }
        if (!$credentials) {
            return ['success' => false, 'message' => 'Payment provider is not configured for this business.'];
        }

        $provider = PaymentProviderFactory::create($credentials);
        if (!$provider->supportsChargingSavedPaymentMethods()) {
            return ['success' => false, 'message' => 'This provider does not support saved payment method charges.'];
        }

        $idempotencyKey = 'order_' . $orderId . '_method_' . $methodId . '_amount_' . number_format($amount, 2, '.', '') . '_' . date('YmdHi');
        $existing = $this->logs->findByIdempotencyKey($idempotencyKey);
        if ($existing && ($existing->status ?? '') === 'SUCCESS') {
            return ['success' => false, 'message' => 'This charge was already processed.'];
        }

        $logId = $this->createLog($order, $method, $consent, $amount, $admin, $idempotencyKey, 'PENDING');

        $charge = $provider->chargeSavedPaymentMethod($method, $amount, [
            'description' => 'Authorized manual charge for order #' . $orderId,
            'order_id' => $orderId,
            'customer_email' => $this->clientEmail($clientId),
            'idempotency_key' => $idempotencyKey,
        ]);

        if (!$charge || empty($charge->paid)) {
            $this->logs->update([
                'status' => 'FAILED',
                'failure_reason' => 'Provider charge failed.',
                'provider_response_json' => $this->safeJson($charge),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $logId]);
            return ['success' => false, 'message' => 'The charge could not be processed.'];
        }

        $paymentRepo = new OrdersPaymentsRepository();
        $paymentRepo->add([
            'id_order' => $orderId,
            'id_suborder' => null,
            'is_suborder' => 0,
            'amount' => $amount,
            'method' => $method->payment_provider,
            'stripe_charge_id' => $charge->id ?? null,
            'payment_concept' => 'Authorized manual balance charge',
            'paid_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $newBalance = max(0, $this->calculateOrderBalance($orderId));
        if ($newBalance <= 0.01) {
            $this->orders->update(['payment_status' => 'paid_full', 'status_workflow' => 'INVOICE_PAID'], ['id' => $orderId]);
        } else {
            $this->orders->update(['status_workflow' => 'INVOICE_PARTIAL'], ['id' => $orderId]);
        }

        $emailSent = $this->sendSuccessEmail($order, $amount);

        $this->logs->update([
            'status' => 'SUCCESS',
            'provider_transaction_id' => $charge->id ?? null,
            'provider_response_json' => $this->safeJson($charge),
            'charged_at' => date('Y-m-d H:i:s'),
            'email_sent_at' => $emailSent ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $logId]);

        return ['success' => true, 'message' => 'Charge processed successfully.'];
    }

    private function loadOrder(int $orderId): ?object
    {
        $order = $this->orders->getByIdWithoutOwnershipCheck($orderId);
        return $order ? (object)$order : null;
    }

    private function canAdminAccessOrder(object $admin, object $order): array
    {
        $level = (int)$admin->getLevel();
        if (!in_array($level, [1, 2], true)) {
            return ['allowed' => false, 'reason' => 'Only Level 1 and Level 2 can run this charge.'];
        }
        if ($level === 2 && (int)$admin->getOwner() !== (int)$order->id_owner && (int)$admin->getId() !== (int)$order->id_owner) {
            return ['allowed' => false, 'reason' => 'This order belongs to another business.'];
        }
        return ['allowed' => true, 'reason' => null];
    }

    private function calculateOrderBalance(int $orderId): float
    {
        $total = $this->orders->calculateTotal($orderId);
        $this->db->query("
            SELECT COALESCE(SUM(amount - COALESCE(refunded_amount, 0)), 0) AS paid
            FROM (
                SELECT amount, refunded_amount FROM orders_payments WHERE id_order = :order1
                UNION ALL
                SELECT amount, refunded_amount FROM orders_advances WHERE id_order = :order2 AND is_suborder = 0
            ) p
        ");
        $this->db->bind(':order1', $orderId);
        $this->db->bind(':order2', $orderId);
        $row = $this->db->fetchOne();
        return round(max(0, $total - (float)($row->paid ?? 0)), 2);
    }

    private function createLog(object $order, object $method, object $consent, float $amount, object $admin, string $key, string $status): int
    {
        $this->logs->add([
            'id_user_business' => (int)$order->id_owner,
            'order_id' => (int)$order->id,
            'client_id' => (int)$order->id_client,
            'user_id' => (int)$order->id_client,
            'saved_payment_method_id' => (int)$method->id,
            'consent_id' => (int)$consent->id,
            'payment_provider' => $method->payment_provider,
            'amount' => $amount,
            'currency' => $method->currency ?? 'USD',
            'status' => $status,
            'idempotency_key' => $key,
            'charged_by_user_id' => (int)$admin->getId(),
            'charged_by_level' => (string)$admin->getLevel(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->logs->getLastId();
    }

    private function clientEmail(int $clientId): string
    {
        $client = (new UserRepository())->getOneWithoutOwnership(['id' => $clientId]);
        return $client->email ?? '';
    }

    private function sendSuccessEmail(object $order, float $amount): bool
    {
        $client = (new UserRepository())->getOneWithoutOwnership(['id' => (int)$order->id_client]);
        if (!$client || empty($client->email)) {
            return false;
        }

        $businessName = 'Ophyra';
        try {
            $this->db->query('SELECT company_name FROM institution_profile WHERE id_owner = :owner LIMIT 1');
            $this->db->bind(':owner', (int)$order->id_owner);
            $profile = $this->db->fetchOne();
            $businessName = $profile->company_name ?? $businessName;
        } catch (\Throwable $e) {
        }

        $name = trim(($client->name ?? '') . ' ' . ($client->lastname ?? '')) ?: 'there';
        $subject = 'Your remaining payment was processed successfully';
        $body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Your remaining payment for order #' . (int)$order->id . ' has been processed successfully.</p>'
            . '<p>Your service is now ready to move forward.</p>'
            . '<p>Thank you,<br>' . htmlspecialchars($businessName) . '</p>';

        return (new EmailService((int)$order->id_owner))->sendSimpleEmail((string)$client->email, $subject, $body);
    }

    private function safeJson($value): ?string
    {
        if (!$value) {
            return null;
        }
        return json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
