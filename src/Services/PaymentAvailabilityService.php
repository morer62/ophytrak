<?php

namespace App\Services;

use App\Repositories\Connection;
use App\Repositories\PaymentProvidersRepository;

class PaymentAvailabilityService
{
    private const PROVIDER_CURRENCIES = [
        'stripe' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'MXN', 'BRL', 'CHF', 'CLP', 'COP', 'PEN', 'CRC', 'DOP'],
        'paypal' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'MXN', 'BRL', 'CHF', 'CLP', 'COP', 'PEN', 'CRC'],
        'square' => ['USD', 'CAD', 'AUD', 'JPY', 'GBP', 'EUR'],
    ];

    private const SQUARE_COUNTRIES_BY_CURRENCY = [
        'USD' => ['US'],
        'CAD' => ['CA'],
        'AUD' => ['AU'],
        'JPY' => ['JP'],
        'GBP' => ['GB'],
        'EUR' => ['IE', 'FR', 'ES'],
    ];

    private CurrencyPricingService $pricing;

    public function __construct(?CurrencyPricingService $pricing = null)
    {
        $this->pricing = $pricing ?? new CurrencyPricingService();
    }

    public function evaluate(int $ownerId, float $baseAmountUsd, array $context = []): array
    {
        $providers = (new PaymentProvidersRepository())->getActiveByOwner($ownerId);
        $country = $this->normalizeCountry((string)($context['country'] ?? 'US'));
        $siteKey = trim((string)($context['site_key'] ?? ''));
        $displayCurrency = $this->pricing->resolveDisplayCurrency($context);

        $available = [];
        $unavailable = [];
        $primaryProvider = null;
        $paymentCurrency = CurrencyPricingService::BASE_CURRENCY;

        foreach ($providers as $provider) {
            $providerType = strtolower((string)($provider->provider_type ?? ''));
            if (!in_array($providerType, ['stripe', 'square', 'paypal'], true)) {
                continue;
            }

            $providerCurrency = $this->pricing->normalizeCurrency((string)($provider->currency ?? 'USD'));
            $siteMatches = $this->matchesSite($provider, $siteKey);
            $currencyAllowed = $this->providerSupportsCurrency($providerType, $providerCurrency);
            $countryAllowed = $this->providerSupportsCountry($providerType, $country, $providerCurrency);
            $dbAllowed = $this->isAllowedByDatabaseRules($providerType, $country, $providerCurrency, $ownerId, $siteKey);

            $entry = [
                'method' => $providerType,
                'provider_id' => (int)($provider->id ?? 0),
                'provider_name' => (string)($provider->provider_name ?? ucfirst($providerType)),
                'currency' => $providerCurrency,
                'country' => $country,
                'site_key' => (string)($provider->site_key ?? ''),
            ];

            if ($siteMatches && $currencyAllowed && $countryAllowed && $dbAllowed) {
                $available[] = $entry + ['requires_manual_review' => false];
                if ($primaryProvider === null) {
                    $primaryProvider = $provider;
                    $paymentCurrency = $providerCurrency;
                }
                continue;
            }

            $reasons = [];
            if (!$siteMatches) {
                $reasons[] = 'site_key_mismatch';
            }
            if (!$currencyAllowed) {
                $reasons[] = 'currency_not_supported';
            }
            if (!$countryAllowed) {
                $reasons[] = 'country_not_supported';
            }
            if (!$dbAllowed) {
                $reasons[] = 'disabled_by_rule';
            }

            $unavailable[] = $entry + ['reasons' => $reasons];
        }

        $snapshot = $this->pricing->createSnapshot($baseAmountUsd, $displayCurrency, $paymentCurrency);
        $bankTransfer = [
            'method' => 'bank_transfer',
            'provider_id' => null,
            'provider_name' => 'Bank transfer',
            'currency' => $snapshot['display_currency'],
            'country' => $country,
            'site_key' => $siteKey,
            'requires_manual_review' => true,
        ];
        $available[] = $bankTransfer;

        return [
            'base_currency' => $snapshot['base_currency'],
            'base_amount' => $snapshot['base_amount'],
            'display_currency' => $snapshot['display_currency'],
            'display_amount' => $snapshot['display_amount'],
            'payment_currency' => $snapshot['payment_currency'],
            'payment_amount' => $snapshot['payment_amount'],
            'exchange_rate' => $snapshot['exchange_rate'],
            'display_exchange_rate' => $snapshot['display_exchange_rate'],
            'exchange_rate_source' => $snapshot['exchange_rate_source'],
            'exchange_rate_at' => $snapshot['exchange_rate_at'],
            'country' => $country,
            'available_methods' => $available,
            'unavailable_methods' => $unavailable,
            'primary_provider' => $primaryProvider,
            'primary_provider_type' => $primaryProvider ? strtolower((string)$primaryProvider->provider_type) : '',
            'bank_transfer_available' => true,
        ];
    }

    public function providerSupportsCurrency(string $providerType, string $currency): bool
    {
        $providerType = strtolower($providerType);
        $currency = $this->pricing->normalizeCurrency($currency);
        return in_array($currency, self::PROVIDER_CURRENCIES[$providerType] ?? [], true);
    }

    private function providerSupportsCountry(string $providerType, string $country, string $currency): bool
    {
        if ($providerType !== 'square') {
            return true;
        }

        return in_array($country, self::SQUARE_COUNTRIES_BY_CURRENCY[$currency] ?? [], true);
    }

    private function matchesSite(object $provider, string $siteKey): bool
    {
        $providerSite = trim((string)($provider->site_key ?? ''));
        return $siteKey === '' || $providerSite === '' || hash_equals($providerSite, $siteKey);
    }

    private function isAllowedByDatabaseRules(string $providerType, string $country, string $currency, int $ownerId, string $siteKey): bool
    {
        try {
            $db = new Connection();
            $db->query("
                SELECT is_enabled
                FROM payment_provider_availability_rules
                WHERE provider_type = :provider_type
                  AND (country_code = :country_code OR country_code = '*')
                  AND (currency = :currency OR currency = '*')
                  AND (id_user_business IS NULL OR id_user_business = :owner_id)
                  AND (site_key IS NULL OR site_key = '' OR site_key = :site_key)
                ORDER BY
                  CASE WHEN country_code = :country_code_exact THEN 0 ELSE 1 END,
                  CASE WHEN currency = :currency_exact THEN 0 ELSE 1 END,
                  id DESC
                LIMIT 1
            ");
            $db->bind(':provider_type', $providerType);
            $db->bind(':country_code', $country);
            $db->bind(':currency', $currency);
            $db->bind(':owner_id', $ownerId);
            $db->bind(':site_key', $siteKey);
            $db->bind(':country_code_exact', $country);
            $db->bind(':currency_exact', $currency);
            $row = $db->fetchOne();
            return !$row || (int)($row->is_enabled ?? 0) === 1;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function normalizeCountry(string $country): string
    {
        $country = strtoupper(trim($country));
        $aliases = [
            'USA' => 'US',
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'UK' => 'GB',
            'UNITED KINGDOM' => 'GB',
            'CANADA' => 'CA',
            'AUSTRALIA' => 'AU',
            'JAPAN' => 'JP',
            'FRANCE' => 'FR',
            'SPAIN' => 'ES',
            'IRELAND' => 'IE',
            'MEXICO' => 'MX',
            'BRAZIL' => 'BR',
            'CHILE' => 'CL',
            'COLOMBIA' => 'CO',
            'PERU' => 'PE',
            'COSTA RICA' => 'CR',
            'DOMINICAN REPUBLIC' => 'DO',
        ];

        if (isset($aliases[$country])) {
            return $aliases[$country];
        }

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : 'US';
    }
}
