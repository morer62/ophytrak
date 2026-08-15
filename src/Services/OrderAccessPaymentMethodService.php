<?php

namespace App\Services;

use App\Repositories\ClientSavedPaymentMethodsRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Repositories\UserRepository;
use App\Services\Payment\PaymentProviderFactory;

class OrderAccessPaymentMethodService
{
    private ClientSavedPaymentMethodsRepository $methods;
    private ClientPaymentMethodService $clientPaymentMethods;

    public function __construct()
    {
        $this->methods = new ClientSavedPaymentMethodsRepository();
        $this->clientPaymentMethods = new ClientPaymentMethodService();
    }

    public function buildViewContext(object $order, int $businessId, ?object $activeProvider): array
    {
        $clientId = (int)($order->id_client ?? 0);
        $providerType = strtolower((string)($activeProvider->provider_type ?? ''));
        $sessionClient = $this->getAuthenticatedClientForOrder($order);
        $client = $clientId > 0 ? (new UserRepository())->getOneWithoutOwnership(['id' => $clientId]) : null;
        $clientName = $client
            ? trim(trim((string)($client->name ?? '')) . ' ' . trim((string)($client->lastname ?? '')))
            : '';
        $savedMethods = [];

        if ($sessionClient && $businessId > 0 && $providerType !== '') {
            $savedMethods = array_values(array_filter(
                $this->methods->getActiveForClient($businessId, $clientId),
                fn($method) => strtolower((string)($method->payment_provider ?? '')) === $providerType
            ));
        }

        return [
            'client_is_authenticated' => (bool)$sessionClient,
            'client_email' => trim((string)($client->email ?? '')),
            'client_name' => $clientName,
            'saved_payment_methods' => $savedMethods,
            'gateway_supports_saved_methods' => in_array($providerType, ['stripe', 'square'], true),
            'has_saved_payment_methods' => count($savedMethods) > 0,
        ];
    }

    public function chargeOrderAccessPayment(
        object $order,
        int $businessId,
        object $activeProvider,
        float $amount,
        ?string $cardToken,
        array $metadata,
        array $post,
        array $related = []
    ): object|false {
        $providerType = strtolower((string)($activeProvider->provider_type ?? ''));
        $provider = PaymentProviderFactory::create($activeProvider);
        $clientId = $this->resolveClientIdForPayment($order, $post);
        $savedMethodId = (int)($post['saved_payment_method_id'] ?? 0);
        $paymentTokenType = (string)($post['payment_token_type'] ?? 'new_card');

        if ($paymentTokenType === 'stored_card' && $savedMethodId > 0) {
            $sessionClient = $this->getAuthenticatedClientForOrder($order);
            if (!$sessionClient) {
                return false;
            }

            $savedMethod = $this->methods->getActiveByIdForBusiness($savedMethodId, $businessId);
            if (!$savedMethod || (int)($savedMethod->id_client ?? $savedMethod->user_id ?? 0) !== $clientId) {
                return false;
            }

            if (strtolower((string)$savedMethod->payment_provider) !== $providerType) {
                return false;
            }

            return $provider->chargeSavedPaymentMethod($savedMethod, $amount, $metadata);
        }

        if (!$cardToken) {
            return false;
        }

        $saveMethod = !empty($post['save_payment_method']);
        $autoConsent = !empty($post['auto_charge_consent']);

        if (($saveMethod || $autoConsent) && in_array($providerType, ['stripe', 'square'], true)) {
            $reusable = $this->createReusablePaymentMethodFromToken($activeProvider, $cardToken, $post, $order);
            if ($reusable) {
                $methodObject = (object)[
                    'provider_customer_id' => $reusable['provider_customer_id'] ?? null,
                    'provider_payment_method_id' => $reusable['provider_payment_method_id'] ?? null,
                    'provider_reference' => $reusable['provider_reference'] ?? null,
                ];

                $charge = $provider->chargeSavedPaymentMethod($methodObject, $amount, $metadata);
                if ($charge !== false) {
                    $this->clientPaymentMethods->recordFromSuccessfulPayment(array_merge($reusable, [
                        'id_user_business' => $businessId,
                        'id_client' => $clientId,
                        'user_id' => $clientId,
                        'payment_provider' => $providerType,
                        'method_type' => 'card',
                        'billing_name' => trim((string)($post['customer_name'] ?? '')),
                        'billing_email' => trim((string)($post['customer_email'] ?? '')),
                        'source' => $related['source'] ?? 'order_access',
                        'related_order_id' => $related['order_id'] ?? (int)($order->id ?? 0),
                        'related_payment_id' => $related['payment_id'] ?? null,
                        'save_payment_method' => $saveMethod ? 1 : 0,
                        'auto_charge_consent' => $autoConsent ? 1 : 0,
                        'metadata' => [
                            'payment_context' => $related['source'] ?? 'order_access',
                            'suborder_id' => $related['suborder_id'] ?? null,
                        ],
                    ]));
                    return $charge;
                }
            }
        }

        return $provider->chargeCustomer($cardToken, $amount, $metadata);
    }

    private function getAuthenticatedClientForOrder(object $order): ?object
    {
        $session = LoginService::getSession();
        if (!$session || (int)$session->getLevel() !== 5) {
            return null;
        }

        $clientId = (int)($order->id_client ?? 0);
        if ($clientId > 0 && (int)$session->getId() === $clientId) {
            return (object)[
                'id' => $session->getId(),
                'email' => $session->getEmail(),
            ];
        }

        return null;
    }

    private function resolveClientIdForPayment(object $order, array $post): int
    {
        $clientId = (int)($order->id_client ?? 0);
        if ($clientId > 0) {
            return $clientId;
        }

        $email = strtolower(trim((string)($post['customer_email'] ?? '')));
        if ($email === '') {
            return 0;
        }

        $client = (new UserRepository())->getOneWithoutOwnership(['email' => $email]);
        return $client ? (int)($client->id ?? 0) : 0;
    }

    private function createReusablePaymentMethodFromToken(object $activeProvider, string $token, array $post, object $order): ?array
    {
        $providerType = strtolower((string)($activeProvider->provider_type ?? ''));
        $email = trim((string)($post['customer_email'] ?? ''));
        $name = trim((string)($post['customer_name'] ?? ''));

        if ($name === '') {
            $client = (new UserRepository())->getOneWithoutOwnership(['id' => (int)($order->id_client ?? 0)]);
            $name = trim(trim((string)($client->name ?? '')) . ' ' . trim((string)($client->lastname ?? '')));
        }

        if ($providerType === 'stripe') {
            return $this->createStripeReusableMethod($activeProvider, $token, $email, $name, $post);
        }

        if ($providerType === 'square') {
            return $this->createSquareReusableMethod($activeProvider, $token, $email, $name, $post);
        }

        return null;
    }

    private function createStripeReusableMethod(object $provider, string $token, string $email, string $name, array $post): ?array
    {
        try {
            $client = new \Stripe\StripeClient(trim((string)$provider->api_key));
            $customer = $client->customers->create([
                'source' => $token,
                'email' => $email ?: null,
                'name' => $name ?: null,
            ]);

            $source = $customer->sources->data[0] ?? null;
            return [
                'provider_customer_id' => $customer->id,
                'provider_payment_method_id' => null,
                'provider_reference' => $source->id ?? $customer->id,
                'brand' => $source->brand ?? ($post['card_brand'] ?? null),
                'last4' => $source->last4 ?? ($post['card_last4'] ?? null),
                'exp_month' => $source->exp_month ?? ($post['card_exp_month'] ?? null),
                'exp_year' => $source->exp_year ?? ($post['card_exp_year'] ?? null),
            ];
        } catch (\Throwable $e) {
            error_log('[OrderAccessPaymentMethodService] Stripe reusable method failed: ' . $e->getMessage());
            return null;
        }
    }

    private function createSquareReusableMethod(object $provider, string $token, string $email, string $name, array $post): ?array
    {
        $accessToken = trim((string)($provider->api_key ?? ''));
        $environment = strtolower((string)($provider->environment ?? 'sandbox'));
        if ($accessToken === '') {
            return null;
        }

        $baseUrl = $environment === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        $nameParts = preg_split('/\s+/', trim($name), 2);
        $customer = $this->squareRequest($baseUrl . '/v2/customers', $accessToken, [
            'idempotency_key' => bin2hex(random_bytes(16)),
            'email_address' => $email ?: null,
            'given_name' => $nameParts[0] ?? 'Customer',
            'family_name' => $nameParts[1] ?? null,
        ]);

        $customerId = (string)($customer['customer']['id'] ?? '');
        if ($customerId === '') {
            return null;
        }

        $card = $this->squareRequest($baseUrl . '/v2/cards', $accessToken, [
            'idempotency_key' => bin2hex(random_bytes(16)),
            'source_id' => $token,
            'card' => [
                'customer_id' => $customerId,
            ],
        ]);

        $cardData = $card['card'] ?? [];
        $cardId = (string)($cardData['id'] ?? '');
        if ($cardId === '') {
            return null;
        }

        return [
            'provider_customer_id' => $customerId,
            'provider_payment_method_id' => $cardId,
            'provider_reference' => $cardId,
            'brand' => $cardData['card_brand'] ?? ($post['card_brand'] ?? null),
            'last4' => $cardData['last_4'] ?? ($post['card_last4'] ?? null),
            'exp_month' => $cardData['exp_month'] ?? ($post['card_exp_month'] ?? null),
            'exp_year' => $cardData['exp_year'] ?? ($post['card_exp_year'] ?? null),
        ];
    }

    private function squareRequest(string $url, string $accessToken, array $payload): ?array
    {
        $payload = array_filter($payload, fn($value) => $value !== null);
        if (isset($payload['card']) && is_array($payload['card'])) {
            $payload['card'] = array_filter($payload['card'], fn($value) => $value !== null);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Square-Version: 2024-12-18',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 45,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            error_log('[OrderAccessPaymentMethodService] Square reusable request failed: ' . ($error ?: (string)$response));
            return null;
        }

        $data = json_decode((string)$response, true);
        return is_array($data) ? $data : null;
    }
}
