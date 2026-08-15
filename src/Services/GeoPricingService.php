<?php

namespace App\Services;

class GeoPricingService
{
    private OphyraPricingService $pricing;

    private const COUNTRY_TO_CURRENCY = [
        'US' => 'USD',
        'CA' => 'CAD',
        'MX' => 'MXN',
        'CO' => 'COP',
        'CL' => 'CLP',
        'BR' => 'BRL',
        'GB' => 'GBP',
        'UK' => 'GBP',
    ];

    private const EURO_COUNTRIES = [
        'AT', 'BE', 'HR', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV',
        'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES',
    ];

    private const EEA_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
        'SI', 'ES', 'SE', 'IS', 'LI', 'NO',
    ];

    public function __construct(?OphyraPricingService $pricing = null)
    {
        $this->pricing = $pricing ?? new OphyraPricingService();
    }

    public function publicContext(array $server = null): array
    {
        $server ??= $_SERVER;
        $country = $this->detectCountry($server);
        $requestedCurrency = $this->normalizeCurrency($server['REQUEST_PUBLIC_CURRENCY'] ?? null);
        $cookieCurrency = $this->normalizeCurrency($_COOKIE['ophyra_public_currency'] ?? null);
        $currency = $requestedCurrency ?: ($cookieCurrency ?: $this->resolveCurrencyForCountry($country));

        return [
            'country_code' => $country,
            'currency_code' => $currency,
            'source' => $requestedCurrency ? 'manual_public_selector' : ($cookieCurrency ? 'public_currency_cookie' : ($country ? 'request_country' : 'default')),
            'is_european_region' => $this->isEuropeanRegion($country),
        ];
    }

    public function detectCountry(array $server = null): ?string
    {
        $server ??= $_SERVER;
        $candidates = [
            $server['HTTP_CF_IPCOUNTRY'] ?? null,
            $server['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ?? null,
            $server['HTTP_X_VERCEL_IP_COUNTRY'] ?? null,
            $server['HTTP_X_APPENGINE_COUNTRY'] ?? null,
            $server['HTTP_X_COUNTRY_CODE'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $country = $this->normalizeCountry($candidate);
            if ($country) {
                return $country;
            }
        }

        return $this->countryFromAcceptLanguage($server['HTTP_ACCEPT_LANGUAGE'] ?? '');
    }

    public function resolveCurrencyForCountry(?string $countryCode): string
    {
        $countryCode = $this->normalizeCountry($countryCode);
        if ($countryCode === null) {
            return 'USD';
        }

        $currency = self::COUNTRY_TO_CURRENCY[$countryCode] ?? null;
        if ($currency === null && in_array($countryCode, self::EURO_COUNTRIES, true)) {
            $currency = 'EUR';
        }

        if ($currency !== null && $this->pricing->isCurrencySupported($currency)) {
            return $currency;
        }

        return 'USD';
    }

    public function isEuropeanRegion(?string $countryCode): bool
    {
        $countryCode = $this->normalizeCountry($countryCode);
        return $countryCode !== null && (in_array($countryCode, self::EEA_COUNTRIES, true) || $countryCode === 'GB');
    }

    private function normalizeCountry(?string $countryCode): ?string
    {
        $countryCode = strtoupper(trim((string)$countryCode));
        if ($countryCode === '' || $countryCode === 'XX' || $countryCode === 'T1') {
            return null;
        }

        if ($countryCode === 'UK') {
            return 'GB';
        }

        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1 ? $countryCode : null;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        $currency = strtoupper(trim((string)$currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return null;
        }

        return $this->pricing->isCurrencySupported($currency) ? $currency : null;
    }

    private function countryFromAcceptLanguage(string $acceptLanguage): ?string
    {
        if ($acceptLanguage === '') {
            return null;
        }

        foreach (explode(',', $acceptLanguage) as $part) {
            if (preg_match('/[-_]([A-Za-z]{2})(?:;|$)/', trim($part), $matches) === 1) {
                return $this->normalizeCountry($matches[1]);
            }
        }

        return null;
    }
}
