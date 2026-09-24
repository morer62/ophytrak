<?php


namespace App\Services;

use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeService
{
    private const SAVED_METHOD_SEPARATOR = '|';
    private string $stripeBaseUrl;
    private string $apiKey;
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
    ];


    public function __construct()
    {
        $this->stripeBaseUrl = $_ENV["STRIPE_BASE"] ?? "";
        $this->apiKey = $_ENV["STRIPE_KEY"] ?? "";
    }

    public function chargeUserToken(string $token, float $amount, string $currency = "usd"): bool
    {
        try {
            $client = new StripeClient($this->apiKey);
            $charge = $client->charges->create([
                "amount" => $this->toMinorUnits($amount, $currency),
                "currency" => strtolower($currency),
                "customer" => $token // Este es el token guardado en stripe_token
            ]);
            return $charge->paid && !$charge->refunded;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createCustomerWithCard(string $token, string $email): ?string
    {
        try {
            $client = new StripeClient($this->apiKey);

            $customer = $client->customers->create([
                'source' => $token,
                'email' => $email
            ]);

            return $customer->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function createCardSetupIntent(string $email, array $metadata = []): ?array
    {
        try {
            $client = new StripeClient($this->apiKey);
            $customer = $client->customers->create([
                'email' => $email,
                'metadata' => $metadata,
            ]);
            $intent = $client->setupIntents->create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => $metadata,
            ]);

            return [
                'id' => (string)$intent->id,
                'client_secret' => (string)$intent->client_secret,
            ];
        } catch (\Throwable $e) {
            error_log('StripeService::createCardSetupIntent(): ' . $e->getMessage());
            return null;
        }
    }

    public function verifiedCardFromSetupIntent(string $setupIntentId): ?array
    {
        if (!str_starts_with($setupIntentId, 'seti_')) return null;

        try {
            $client = new StripeClient($this->apiKey);
            $intent = $client->setupIntents->retrieve($setupIntentId, []);
            if ((string)$intent->status !== 'succeeded' || empty($intent->customer) || empty($intent->payment_method)) {
                return null;
            }

            $paymentMethodId = is_string($intent->payment_method)
                ? $intent->payment_method
                : (string)$intent->payment_method->id;
            $customerId = is_string($intent->customer) ? $intent->customer : (string)$intent->customer->id;
            $paymentMethod = $client->paymentMethods->retrieve($paymentMethodId, []);
            if ((string)$paymentMethod->customer !== $customerId || (string)$paymentMethod->type !== 'card' || !$paymentMethod->card) {
                return null;
            }

            return [
                'reference' => $paymentMethodId . self::SAVED_METHOD_SEPARATOR . $customerId,
                'user_id' => (string)($intent->metadata->user_id ?? ''),
                'brand' => (string)$paymentMethod->card->brand,
                'last4' => (string)$paymentMethod->card->last4,
                'exp' => (int)$paymentMethod->card->exp_month . '/' . (int)$paymentMethod->card->exp_year,
                'billing_zip' => substr((string)($paymentMethod->billing_details->address->postal_code ?? ''), 0, 12),
            ];
        } catch (\Throwable $e) {
            error_log('StripeService::verifiedCardFromSetupIntent(): ' . $e->getMessage());
            return null;
        }
    }

    private function parseSavedPaymentMethodReference(string $reference): ?array
    {
        $parts = explode(self::SAVED_METHOD_SEPARATOR, $reference, 2);
        if (count($parts) !== 2 || !str_starts_with($parts[0], 'pm_') || !str_starts_with($parts[1], 'cus_')) {
            return null;
        }

        return ['payment_method' => $parts[0], 'customer' => $parts[1]];
    }

    public function createCustomerWithCardOnConnectedAccount($cardToken, $email, $name, $accountId)
{
    try {
        // DEBUG LOG INICIAL
        error_log("⚙️ [DEBUG] Intentando crear cliente con:");
        error_log("Token: $cardToken");
        error_log("Email: $email");
        error_log("Name: $name");
        error_log("Account ID: $accountId");

        \Stripe\Stripe::setApiKey($_ENV["STRIPE_KEY"]);

        $customer = \Stripe\Customer::create([
            'email' => $email,
            'name' => $name,
            'source' => $cardToken,
        ], [
            'stripe_account' => $accountId
        ]);

        error_log("✅ Cliente creado correctamente: " . $customer->id);

        return $customer->id;
    } catch (\Throwable $e) {
        // MOSTRAR ERROR DIRECTO EN PANTALLA
        echo "<pre style='color:red; background:#fee; padding:20px;'>";
        echo "❌ Stripe Exception: " . $e->getMessage() . "\n";
        echo "Archivo: " . $e->getFile() . "\n";
        echo "Línea: " . $e->getLine() . "\n";
        echo "</pre>";

        // LOG DETALLADO EN error_log
        error_log("❌ Stripe Exception: " . $e->getMessage());
        error_log("Archivo: " . $e->getFile());
        error_log("Línea: " . $e->getLine());

        return null;
    }
}


    public function chargeCardToConnectedAccount($paymentMethodId, $amount, $connectedAccountId)
    {
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_KEY']);

        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => round($amount * 100),
                'currency' => 'usd',
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'off_session' => true,
                'transfer_data' => [
                    'destination' => $connectedAccountId
                ],
            ]);
            return $intent;
        } catch (\Exception $e) {
            return null;
        }
    }


 

    public function chargeTokenToConnectedAccount($token, $amount, $accountId)
    {
        try {
            \Stripe\Stripe::setApiKey($_ENV["STRIPE_KEY"]);

            return \Stripe\Charge::create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'source' => $token,
                'description' => 'Client payment',
            ], [
                'stripe_account' => $accountId
            ]);
        } catch (\Throwable $e) {
            error_log("Charge failed: " . $e->getMessage());
            return null;
        }
    }





    public function createCharge(string $token, float $amount, string $currency = "usd"): string
    {
        $url = "$this->stripeBaseUrl/charges"; // Example API
        $data = [
            "amount" => $amount,
            "currency" => $currency,
            "source" => $token
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Encode data for form submission
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey); // Authentication
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded"
        ]);

        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Output response
        if ($httpCode == 200) {
            echo "Payment Successful: " . $response;
        } else {
            echo "Error Processing Payment: " . $response;
        }

        return $response;
    }

    /**
     * @throws ApiErrorException
     */
    public function createChargeV1(string $token, float $amount, string $currency = "usd", array $metadata = []): bool|string
    {
        $client = new StripeClient($this->apiKey);

        $savedMethod = $this->parseSavedPaymentMethodReference($token);
        if ($savedMethod) {
            $intent = $client->paymentIntents->create([
                'amount' => $this->toMinorUnits($amount, $currency),
                'currency' => strtolower($currency),
                'customer' => $savedMethod['customer'],
                'payment_method' => $savedMethod['payment_method'],
                'payment_method_types' => ['card'],
                'confirm' => true,
                'off_session' => false,
                'metadata' => $metadata,
            ]);

            return (string)$intent->status === 'succeeded' ? (string)$intent->id : false;
        }

        $charge = $client->charges->create([
            "amount" => $this->toMinorUnits($amount, $currency),
            "currency" => strtolower($currency),
            "customer" => $token,
            "metadata" => $metadata,
        ]);

        // Return charge ID if successful, false otherwise
        return ($charge->paid && !$charge->refunded) ? $charge->id : false;
    }

    


    public function createExpressAccount(string $email): ?string
    {
        try {
            $client = new StripeClient($this->apiKey);

            $account = $client->accounts->create([
                'type' => 'express',
                'email' => $email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ]
            ]);

            return $account->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function generateAccountLink(string $accountId, string $refreshUrl, string $returnUrl): ?string
    {
        try {
            $client = new StripeClient($this->apiKey);

            $link = $client->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return $link->url;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function chargeCustomerOnConnectedAccount($customerId, $amount, $accountId)
    {
        try {
            $charge = \Stripe\Charge::create([
                'amount' => intval($amount * 100),
                'currency' => 'usd',
                'customer' => $customerId,
            ], [
                'stripe_account' => $accountId
            ]);

            return $charge;
        } catch (\Throwable $e) {
            return null;
        }
    }



    public function chargeUserTokenToConnectedAccount(string $customerId, float $amount, string $connectedAccountId)
    {
        try {
            \Stripe\Stripe::setApiKey($_ENV["STRIPE_KEY"]);

            $charge = \Stripe\Charge::create([
                'amount' => intval($amount * 100), // en centavos
                'currency' => 'usd',
                'customer' => $customerId,
                'description' => 'Order payment via ophyra.com',
                'transfer_data' => [
                    'destination' => $connectedAccountId,
                ],
            ], [
                'stripe_account' => $connectedAccountId
            ]);

            return $charge;
        } catch (\Exception $e) {
             
            return null;
        }
    }



    public function getAccountBalance(string $accountId): ?array
    {
        try {
            $client = new \Stripe\StripeClient($this->apiKey);

            $balance = $client->balance->retrieve([], [
                'stripe_account' => $accountId
            ]);

            return [
                'available' => $balance->available[0]->amount / 100,
                'pending' => $balance->pending[0]->amount / 100
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function toMinorUnits(float $amount, string $currency): int
    {
        $currency = strtoupper(trim($currency));
        $multiplier = in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 1 : 100;
        return (int)round($amount * $multiplier);
    }


}
