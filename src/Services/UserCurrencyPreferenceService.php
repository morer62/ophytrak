<?php

namespace App\Services;

use App\Repositories\Connection;
use PDOException;

class UserCurrencyPreferenceService
{
    private Connection $db;
    private OphyraPricingService $pricing;

    public function __construct(?OphyraPricingService $pricing = null)
    {
        $this->db = new Connection();
        $this->pricing = $pricing ?? new OphyraPricingService();
    }

    public function getPreferredCurrency(int $ownerId): ?string
    {
        if ($currency = ProductProfileService::billingCurrency()) {
            return $currency;
        }

        try {
            $this->db->query('SELECT preferred_currency FROM users WHERE id = :id LIMIT 1');
            $this->db->bind(':id', $ownerId);
            $row = $this->db->fetchOne();
            $currency = strtoupper(trim((string)($row->preferred_currency ?? '')));

            return $this->pricing->isCurrencySupported($currency) ? $currency : null;
        } catch (PDOException $e) {
            error_log('UserCurrencyPreferenceService::getPreferredCurrency(): ' . $e->getMessage());
            return null;
        }
    }

    public function resolveCurrency(int $ownerId, ?string $candidate = null): string
    {
        if ($currency = ProductProfileService::billingCurrency()) {
            return $currency;
        }

        $candidate = strtoupper(trim((string)$candidate));
        if ($this->pricing->isCurrencySupported($candidate)) {
            return $candidate;
        }

        return $this->getPreferredCurrency($ownerId) ?: $this->pricing->getDefaultCurrency();
    }

    public function updatePreferredCurrency(int $ownerId, ?string $currency): string
    {
        $currency = $this->pricing->normalizePaymentCurrency($currency);

        $this->db->query('UPDATE users SET preferred_currency = :currency WHERE id = :id');
        $this->db->bind(':currency', $currency);
        $this->db->bind(':id', $ownerId);
        $this->db->execute();

        return $currency;
    }

    public function supportedCurrencies(): array
    {
        return $this->pricing->allowedOphyraPaymentCurrencies();
    }

    public function requiresSetup(int $ownerId): bool
    {
        return $this->getPreferredCurrency($ownerId) === null;
    }
}
