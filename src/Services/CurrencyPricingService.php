<?php

namespace App\Services;

use App\Repositories\Connection;

class CurrencyPricingService
{
    public const BASE_CURRENCY = 'USD';

    private const DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
    ];

    private const SUPPORTED_DISPLAY_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'MXN', 'BRL', 'CHF', 'CLP', 'COP', 'PEN', 'CRC', 'DOP'
    ];

    public function normalizeCurrency(?string $currency, string $fallback = self::BASE_CURRENCY): string
    {
        $currency = strtoupper(trim((string)$currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return $fallback;
        }

        return $currency;
    }

    public function supportedDisplayCurrencies(): array
    {
        return self::SUPPORTED_DISPLAY_CURRENCIES;
    }

    public function resolveDisplayCurrency(array $context = []): string
    {
        $candidate = $context['display_currency']
            ?? $context['preferred_currency']
            ?? $context['currency']
            ?? null;

        $currency = $this->normalizeCurrency((string)$candidate);
        return in_array($currency, self::SUPPORTED_DISPLAY_CURRENCIES, true)
            ? $currency
            : self::BASE_CURRENCY;
    }

    public function createSnapshot(float $baseAmountUsd, ?string $displayCurrency = null, ?string $paymentCurrency = null): array
    {
        $baseCurrency = self::BASE_CURRENCY;
        $displayCurrency = $this->normalizeCurrency($displayCurrency, $baseCurrency);
        $paymentCurrency = $this->normalizeCurrency($paymentCurrency ?: $displayCurrency, $baseCurrency);

        $displayRate = $this->getCachedRate($baseCurrency, $displayCurrency);
        if ($displayRate === null) {
            $displayCurrency = $baseCurrency;
            $displayRate = 1.0;
        }

        $paymentRate = $this->getCachedRate($baseCurrency, $paymentCurrency);
        if ($paymentRate === null) {
            $paymentCurrency = $baseCurrency;
            $paymentRate = 1.0;
        }

        return [
            'base_amount' => $this->roundAmount($baseAmountUsd, $baseCurrency),
            'base_currency' => $baseCurrency,
            'display_amount' => $this->roundAmount($baseAmountUsd * $displayRate, $displayCurrency),
            'display_currency' => $displayCurrency,
            'payment_amount' => $this->roundAmount($baseAmountUsd * $paymentRate, $paymentCurrency),
            'payment_currency' => $paymentCurrency,
            'exchange_rate' => $paymentRate,
            'display_exchange_rate' => $displayRate,
            'exchange_rate_source' => $paymentRate === 1.0 && $paymentCurrency === $baseCurrency ? 'base_currency' : 'exchange_rates',
            'exchange_rate_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function format(float $amount, string $currency): string
    {
        $currency = $this->normalizeCurrency($currency);
        $decimals = in_array($currency, self::DECIMAL_CURRENCIES, true) ? 0 : 2;
        return $currency . ' ' . number_format($amount, $decimals, '.', ',');
    }

    private function getCachedRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $baseCurrency = $this->normalizeCurrency($baseCurrency);
        $targetCurrency = $this->normalizeCurrency($targetCurrency);

        if ($baseCurrency === $targetCurrency) {
            return 1.0;
        }

        try {
            $db = new Connection();
            $db->query("
                SELECT rate
                FROM exchange_rates
                WHERE base_currency = :base_currency
                  AND target_currency = :target_currency
                  AND is_active = 1
                ORDER BY rate_date DESC, updated_at DESC, id DESC
                LIMIT 1
            ");
            $db->bind(':base_currency', $baseCurrency);
            $db->bind(':target_currency', $targetCurrency);
            $row = $db->fetchOne();
            $rate = $row ? (float)($row->rate ?? 0) : 0.0;
            return $rate > 0 ? $rate : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function roundAmount(float $amount, string $currency): float
    {
        $decimals = in_array($this->normalizeCurrency($currency), self::DECIMAL_CURRENCIES, true) ? 0 : 2;
        return round($amount, $decimals);
    }
}
