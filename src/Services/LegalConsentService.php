<?php

namespace App\Services;

use App\Repositories\Connection;
use PDOException;

class LegalConsentService
{
    private const DOCUMENT_VERSIONS = [
        'terms_conditions' => '2026-06-06',
        'privacy_policy' => '2026-06-06',
        'cookie_policy' => '2026-06-06',
        'data_processing_notice' => '2026-06-06',
    ];

    private Connection $db;

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function activeVersion(string $documentType): string
    {
        return self::DOCUMENT_VERSIONS[$documentType] ?? '2026-06-06';
    }

    public function recordSignupConsents(int $userId, string $email, ?string $countryCode, ?string $currencyCode): void
    {
        foreach (['terms_conditions', 'privacy_policy'] as $documentType) {
            $this->recordConsent($documentType, [
                'id_user' => $userId,
                'email' => $email,
                'country_code' => $countryCode,
                'currency_code' => $currencyCode,
                'source' => 'signup',
            ]);
        }
    }

    public function recordConsent(string $documentType, array $context): void
    {
        try {
            $this->db->query("
                INSERT INTO user_legal_consents
                    (id_user, email, document_type, document_version, accepted_at, ip_address, country_code, currency_code, user_agent, source, created_at, updated_at)
                VALUES
                    (:id_user, :email, :document_type, :document_version, NOW(), :ip_address, :country_code, :currency_code, :user_agent, :source, NOW(), NOW())
            ");
            $this->db->bind(':id_user', $context['id_user'] ?? null);
            $this->db->bind(':email', $context['email'] ?? null);
            $this->db->bind(':document_type', $documentType);
            $this->db->bind(':document_version', $this->activeVersion($documentType));
            $this->db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            $this->db->bind(':country_code', $context['country_code'] ?? null);
            $this->db->bind(':currency_code', $context['currency_code'] ?? null);
            $this->db->bind(':user_agent', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
            $this->db->bind(':source', $context['source'] ?? 'manual_update');
            $this->db->execute();
        } catch (PDOException $e) {
            error_log('LegalConsentService::recordConsent(): ' . $e->getMessage());
        }
    }
}
