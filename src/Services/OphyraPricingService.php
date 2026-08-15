<?php

namespace App\Services;

class OphyraPricingService
{
    public const BASE_CURRENCY = 'USD';
    public const BILLING_CYCLE_MONTHLY = 'monthly';
    private const DEFAULT_SUPPORTED_CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'MXN', 'BRL', 'CLP', 'COP'];

    private const PRICE_KEYS = [
        'base_profile' => ['OPHYRA_BASE_PROFILE_PRICE', 'OPHYRA_BASE_MONTHLY'],
        'service_operations' => ['SERVICE_OPERATIONS_PRICE', 'OPHYRA_MODULE_SERVICE_OPERATIONS_MONTHLY'],
        'store_logistics' => ['STORE_LOGISTICS_PRICE', 'OPHYRA_MODULE_STORE_DELIVERY_TRACKING_MONTHLY'],
        'advanced_storage_qr_inventory' => [
            'ADVANCED_STORAGE_QR_INVENTORY_PRICE',
            'OPHYRA_MODULE_ADVANCED_STORAGE_QR_INVENTORY_MONTHLY',
            'OPHYRA_MODULE_INVENTORY_STORAGE_MONTHLY',
        ],
        'ai_advisor' => ['AI_ADVISOR_PRICE', 'OPHYRA_MODULE_AI_ADVISOR_MONTHLY'],
        'ticket_sales_rsvp' => ['TICKET_SALES_RSVP_PRICE', 'OPHYRA_MODULE_TICKETS_RSVP_MONTHLY'],
        'marketplace_connectors' => ['MARKETPLACE_CONNECTORS_PRICE', 'OPHYRA_MODULE_MARKETPLACE_CONNECTORS_MONTHLY'],
    ];

    private const PRICE_PREFIXES = [
        'base_profile' => 'OPHYRA_BASE_PROFILE_PRICE',
        'service_operations' => 'SERVICE_OPERATIONS_PRICE',
        'store_logistics' => 'STORE_LOGISTICS_PRICE',
        'advanced_storage_qr_inventory' => 'ADVANCED_STORAGE_QR_INVENTORY_PRICE',
        'ai_advisor' => 'AI_ADVISOR_PRICE',
        'ticket_sales_rsvp' => 'TICKET_SALES_RSVP_PRICE',
        'marketplace_connectors' => 'MARKETPLACE_CONNECTORS_PRICE',
    ];

    private const DEFAULTS = [
        'base_profile' => 0.00,
        'service_operations' => 24.00,
        'store_logistics' => 34.00,
        'advanced_storage_qr_inventory' => 15.00,
        'ai_advisor' => 9.00,
        'ticket_sales_rsvp' => 15.00,
        'marketplace_connectors' => 12.00,
    ];

    private const ALIASES = [
        'business_profile' => 'base_profile',
        'services' => 'service_operations',
        'store_delivery_tracking' => 'store_logistics',
        'inventory_storage' => 'advanced_storage_qr_inventory',
        'tickets_rsvp' => 'ticket_sales_rsvp',
    ];

    private const LEGACY_BY_CANONICAL = [
        'base_profile' => 'business_profile',
        'service_operations' => 'services',
        'store_logistics' => 'store_delivery_tracking',
        'advanced_storage_qr_inventory' => 'inventory_storage',
        'ai_advisor' => 'ai_advisor',
        'ticket_sales_rsvp' => 'tickets_rsvp',
        'marketplace_connectors' => 'marketplace_connectors',
    ];

    public function canonicalModuleSlugs(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public function addonCanonicalSlugs(): array
    {
        return array_values(array_filter(
            $this->canonicalModuleSlugs(),
            static fn(string $slug): bool => $slug !== 'base_profile'
        ));
    }

    public function slugAliases(): array
    {
        return self::ALIASES;
    }

    public function baseCurrency(): string
    {
        $currency = strtoupper(trim((string)$this->env('OPHYRA_BILLING_BASE_CURRENCY', self::BASE_CURRENCY)));
        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : self::BASE_CURRENCY;
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency();
    }

    public function getDefaultCurrency(): string
    {
        $currency = strtoupper(trim((string)$this->env('OPHYRA_DEFAULT_BILLING_CURRENCY', $this->baseCurrency())));
        return $this->isCurrencySupported($currency) ? $currency : $this->baseCurrency();
    }

    public function getSupportedCurrencies(): array
    {
        $configured = trim((string)$this->env(
            'OPHYRA_SUPPORTED_BILLING_CURRENCIES',
            (string)$this->env('OPHYRA_ALLOWED_PAYMENT_CURRENCIES', implode(',', self::DEFAULT_SUPPORTED_CURRENCIES))
        ));

        $currencies = array_values(array_unique(array_filter(array_map(
            static fn(string $currency): string => strtoupper(trim($currency)),
            explode(',', $configured)
        ), static fn(string $currency): bool => preg_match('/^[A-Z]{3}$/', $currency) === 1)));

        $currencies = array_values(array_diff($currencies, ['JPY', 'CHF']));
        if (!in_array($this->baseCurrency(), $currencies, true)) {
            array_unshift($currencies, $this->baseCurrency());
        }

        return array_values(array_unique($currencies ?: self::DEFAULT_SUPPORTED_CURRENCIES));
    }

    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper(trim($currency)), $this->getSupportedCurrencies(), true);
    }

    public function isModuleComingSoon(string $slug): bool
    {
        return $this->canonicalSlug($slug) === 'custom_domain_seo_page_builder'
            || $this->customDomainStatus() === 'coming_soon' && $this->canonicalSlug($slug) === 'custom_domain_seo_page_builder';
    }

    public function billingCycle(): string
    {
        $cycle = strtolower(trim((string)$this->env('OPHYRA_BILLING_CYCLE', self::BILLING_CYCLE_MONTHLY)));
        return $cycle !== '' ? $cycle : self::BILLING_CYCLE_MONTHLY;
    }

    public function freeStarterBaseEnabled(): bool
    {
        return $this->envBool('OPHYRA_FREE_STARTER_BASE', true);
    }

    public function customDomainStatus(): string
    {
        return strtolower(trim((string)$this->env(
            'OPHYRA_MODULE_CUSTOM_DOMAIN_SEO_PAGE_BUILDER_STATUS',
            (string)$this->env('CUSTOM_DOMAIN_SEO_PAGE_BUILDER_STATUS', 'coming_soon')
        ))) ?: 'coming_soon';
    }

    public function canonicalSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        return self::ALIASES[$slug] ?? $slug;
    }

    public function legacySlug(string $slug): string
    {
        $canonical = $this->canonicalSlug($slug);
        return self::LEGACY_BY_CANONICAL[$canonical] ?? $canonical;
    }

    public function compatibleSlugs(string $slug): array
    {
        $canonical = $this->canonicalSlug($slug);
        $legacy = $this->legacySlug($canonical);
        return array_values(array_unique([$slug, $canonical, $legacy]));
    }

    public function modulePrice(string $slug): ?float
    {
        return $this->getModuleBasePriceUsd($slug);
    }

    public function getModuleBasePriceUsd(string $slug): ?float
    {
        return $this->getModulePrice($slug, $this->baseCurrency());
    }

    public function getModulePrice(string $slug, ?string $currency = null): ?float
    {
        $canonical = $this->canonicalSlug($slug);
        $currency = strtoupper(trim((string)($currency ?: $this->getDefaultCurrency())));

        if ($this->isModuleComingSoon($canonical)) {
            return null;
        }

        if ($canonical === 'base_profile' && $this->freeStarterBaseEnabled()) {
            return 0.0;
        }

        if (!isset(self::PRICE_PREFIXES[$canonical]) || !$this->isCurrencySupported($currency)) {
            return null;
        }

        $currencyKey = self::PRICE_PREFIXES[$canonical] . '_' . $currency;
        $currencyValue = $this->env($currencyKey);
        if ($currencyValue !== null && trim((string)$currencyValue) !== '') {
            $amount = (float)$currencyValue;
            return $amount >= 0 ? $amount : null;
        }

        if ($currency !== $this->baseCurrency()) {
            error_log("OphyraPricingService: missing {$currencyKey}. Checkout for {$canonical}/{$currency} is disabled.");
            return null;
        }

        foreach (self::PRICE_KEYS[$canonical] ?? [] as $key) {
            $value = $this->env($key);
            if ($value === null || trim((string)$value) === '') {
                continue;
            }

            $amount = (float)$value;
            if ($amount < 0) {
                error_log("OphyraPricingService: {$key} cannot be negative. Falling back to configured default.");
                return self::DEFAULTS[$canonical];
            }

            return $amount;
        }

        error_log('OphyraPricingService: missing price env for ' . $canonical . '. Using configured fallback.');
        return self::DEFAULTS[$canonical];
    }

    public function baseProfilePrice(): float
    {
        return $this->freeStarterBaseEnabled() ? 0.0 : (float)$this->modulePrice('base_profile');
    }

    public function checkoutAllowed(string $slug): bool
    {
        return $this->checkoutAllowedForCurrency($slug, $this->getDefaultCurrency());
    }

    public function checkoutAllowedForCurrency(string $slug, ?string $currency): bool
    {
        $price = $this->getModulePrice($slug, $currency);
        return $price !== null && $price >= 0 && $this->canonicalSlug($slug) !== 'custom_domain_seo_page_builder';
    }

    public function officialAddonPrices(): array
    {
        $prices = [];

        foreach ($this->addonCanonicalSlugs() as $slug) {
            $amount = (float)$this->modulePrice($slug);
            $prices[$slug] = $amount;
            $prices[$this->legacySlug($slug)] = $amount;
        }

        return $prices;
    }

    public function publicPricing(?string $currency = null): array
    {
        $currency = $this->normalizePaymentCurrency($currency);
        $modules = [];

        foreach (array_keys(self::PRICE_KEYS) as $slug) {
            $amount = $this->getModulePrice($slug, $currency);
            $modules[$slug] = [
                'amount' => $amount,
                'currency' => $currency,
                'label' => $amount === null ? 'Coming Soon' : $this->format($amount, $currency),
                'monthly_label' => $amount === null ? 'Coming Soon' : $this->formatMonthly($amount, $currency),
                'legacy_slug' => $this->legacySlug($slug),
            ];
        }

        return [
            'base_currency' => $this->baseCurrency(),
            'default_currency' => $currency,
            'supported_currencies' => $this->getSupportedCurrencies(),
            'billing_cycle' => $this->billingCycle(),
            'free_starter_base' => $this->freeStarterBaseEnabled(),
            'custom_domain_status' => $this->customDomainStatus(),
            'modules' => $modules,
        ];
    }

    public function allowedOphyraPaymentCurrencies(): array
    {
        return $this->getSupportedCurrencies();
    }

    public function normalizePaymentCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string)$currency));
        return $this->isCurrencySupported($currency) ? $currency : $this->getDefaultCurrency();
    }

    public function formatMonthly(float $amount, ?string $currency = null): string
    {
        return $this->format($amount, $currency ?: $this->baseCurrency()) . '/month';
    }

    public function formatUsd(float $amount): string
    {
        return $this->format($amount, $this->baseCurrency());
    }

    public function format(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        $decimals = in_array($currency, ['CLP'], true) ? 0 : 2;
        return $currency . ' ' . number_format($amount, $decimals, '.', ',');
    }

    public function suggestedCurrency(array|object|null $context = null): string
    {
        $country = '';
        if (is_array($context)) {
            $country = (string)($context['country'] ?? $context['billing_country'] ?? '');
        } elseif (is_object($context)) {
            $country = (string)($context->country ?? $context->billing_country ?? '');
        }

        $country = strtoupper(trim($country));
        $map = [
            'US' => 'USD', 'USA' => 'USD',
            'GB' => 'GBP', 'UK' => 'GBP',
            'CA' => 'CAD',
            'AU' => 'AUD',
            'MX' => 'MXN',
            'BR' => 'BRL',
            'CL' => 'CLP',
            'CO' => 'COP',
            'AT' => 'EUR', 'BE' => 'EUR', 'CY' => 'EUR', 'DE' => 'EUR', 'EE' => 'EUR', 'ES' => 'EUR',
            'FI' => 'EUR', 'FR' => 'EUR', 'GR' => 'EUR', 'IE' => 'EUR', 'IT' => 'EUR', 'LT' => 'EUR',
            'LU' => 'EUR', 'LV' => 'EUR', 'MT' => 'EUR', 'NL' => 'EUR', 'PT' => 'EUR', 'SI' => 'EUR', 'SK' => 'EUR',
        ];

        return $this->normalizePaymentCurrency($map[$country] ?? $this->getDefaultCurrency());
    }

    private function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value !== false && $value !== '' ? $value : $default;
    }

    private function envBool(string $key, bool $default = false): bool
    {
        $value = $this->env($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
