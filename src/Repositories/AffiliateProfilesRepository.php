<?php

namespace App\Repositories;

use PDOException;

class AffiliateProfilesRepository extends BaseRepository
{
    private const ENCRYPTION_METHOD = 'AES-256-CBC';

    protected array $fields = [
        'id_user',
        'application_status',
        'commission_rate',
        'legal_name',
        'business_name',
        'business_type',
        'tax_country',
        'tax_reference',
        'tax_id_last4',
        'contact_email',
        'contact_phone',
        'address_line1',
        'city',
        'state',
        'zip',
        'country',
        'payout_method',
        'paypal_email',
        'account_holder_name',
        'bank_name',
        'routing_number',
        'routing_number_encrypted',
        'account_number_last4',
        'account_number_encrypted',
        'account_type',
        'payout_notes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_at',
        'updated_at',
    ];

    public function __construct()
    {
        $this->table = 'affiliate_profiles';
        $this->db = new Connection();
    }

    public function getByUserId(int $userId): ?object
    {
        try {
            $this->db->query("SELECT * FROM {$this->table} WHERE id_user = :id_user LIMIT 1");
            $this->db->bind(':id_user', $userId);
            $profile = $this->db->fetchOne();

            return $profile ? $this->maskSensitiveProfile($profile) : null;
        } catch (PDOException $e) {
            error_log('AffiliateProfilesRepository::getByUserId(): ' . $e->getMessage());
            return null;
        }
    }

    public function ensureForUser(int $userId, ?string $email = null, string $status = 'PENDING'): ?object
    {
        $profile = $this->getByUserId($userId);
        if ($profile) {
            return $profile;
        }

        try {
            $this->db->query("
                INSERT INTO {$this->table}
                (id_user, application_status, commission_rate, contact_email, created_at, updated_at)
                VALUES (:id_user, :application_status, 30.00, :contact_email, NOW(), NOW())
            ");
            $this->db->bind(':id_user', $userId);
            $this->db->bind(':application_status', $status);
            $this->db->bind(':contact_email', $email);
            $this->db->execute();

            return $this->getByUserId($userId);
        } catch (PDOException $e) {
            error_log('AffiliateProfilesRepository::ensureForUser(): ' . $e->getMessage());
            return null;
        }
    }

    public function upsertProfile(int $userId, array $data): bool
    {
        $allowed = array_flip($this->fields);
        unset($allowed['id_user'], $allowed['created_at'], $allowed['updated_at']);

        $data = array_intersect_key($data, $allowed);
        $data['updated_at'] = date('Y-m-d H:i:s');

        $routingNumber = preg_replace('/\D+/', '', (string)($data['routing_number'] ?? ''));
        if ($routingNumber !== '') {
            $data['routing_number'] = $this->encrypt($routingNumber);
            if ($this->hasColumn('routing_number_encrypted')) {
                $data['routing_number_encrypted'] = $data['routing_number'];
            }
        } else {
            unset($data['routing_number'], $data['routing_number_encrypted']);
        }

        $accountNumber = preg_replace('/\D+/', '', (string)($data['account_number_encrypted'] ?? ''));
        if ($accountNumber !== '') {
            $data['account_number_last4'] = substr($accountNumber, -4);
            if ($this->hasColumn('account_number_encrypted')) {
                $data['account_number_encrypted'] = $this->encrypt($accountNumber);
            } else {
                unset($data['account_number_encrypted']);
            }
        } else {
            unset($data['account_number_encrypted']);
        }

        if (!$this->hasColumn('routing_number_encrypted')) {
            unset($data['routing_number_encrypted']);
        }
        if (!$this->hasColumn('account_number_encrypted')) {
            unset($data['account_number_encrypted']);
        }

        if (!$this->getByUserId($userId)) {
            $this->ensureForUser($userId, $data['contact_email'] ?? null);
        }

        return $this->update($data, ['id_user' => $userId]);
    }

    public function updateApplicationStatus(
        int $userId,
        string $status,
        ?int $reviewedBy = null,
        ?float $commissionRate = null,
        ?string $rejectionReason = null
    ): bool {
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'], true)) {
            return false;
        }

        $data = [
            'application_status' => $status,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $rejectionReason,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($commissionRate !== null && in_array((int)$commissionRate, [30, 40, 50], true)) {
            $data['commission_rate'] = $commissionRate;
        }

        if (!$this->getByUserId($userId)) {
            $this->ensureForUser($userId);
        }

        return $this->update($data, ['id_user' => $userId]);
    }

    public function markApplied(int $userId, array $data): bool
    {
        $profile = $this->ensureForUser($userId, $data['contact_email'] ?? null, 'PENDING');
        if (!$profile) {
            return false;
        }

        $data['application_status'] = 'PENDING';
        return $this->upsertProfile($userId, $data);
    }

    public function getCommissionRateForUser(int $userId): float
    {
        $profile = $this->getByUserId($userId);
        $rate = $profile ? (float)$profile->commission_rate : 30.00;

        return in_array((int)$rate, [30, 40, 50], true) ? $rate : 30.00;
    }

    public function isApproved(int $userId): bool
    {
        $profile = $this->getByUserId($userId);

        return $profile && $profile->application_status === 'APPROVED';
    }

    public function getAllWithUsers(?string $status = null): array
    {
        try {
            $sql = "
                SELECT ap.*, u.name, u.lastname, u.email, u.phone, u.level
                FROM {$this->table} ap
                JOIN users u ON u.id = ap.id_user
            ";

            if ($status) {
                $sql .= " WHERE ap.application_status = :status";
            }

            $sql .= " ORDER BY ap.updated_at DESC, ap.created_at DESC";

            $this->db->query($sql);
            if ($status) {
                $this->db->bind(':status', $status);
            }

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('AffiliateProfilesRepository::getAllWithUsers(): ' . $e->getMessage());
            return [];
        }
    }

    public function getAllForFilter(): array
    {
        try {
            $this->db->query("
                SELECT DISTINCT u.id, u.name, u.lastname, u.email, u.level
                FROM {$this->table} ap
                JOIN users u ON u.id = ap.id_user
                ORDER BY u.name ASC, u.email ASC
            ");

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('AffiliateProfilesRepository::getAllForFilter(): ' . $e->getMessage());
            return [];
        }
    }

    private function maskSensitiveProfile(object $profile): object
    {
        $routingRaw = (string)($profile->routing_number ?? '');
        $routing = $this->decrypt($routingRaw);
        if ($routing === '' && preg_match('/^\d{4,}$/', $routingRaw)) {
            $routing = $routingRaw;
        }

        if ($routing !== '') {
            $profile->routing_number_masked = '****' . substr($routing, -4);
        } else {
            $profile->routing_number_masked = '';
        }

        $last4 = (string)($profile->account_number_last4 ?? '');
        $profile->account_number_masked = $last4 !== '' ? '****' . substr($last4, -4) : '';

        unset($profile->routing_number, $profile->routing_number_encrypted, $profile->account_number_encrypted);
        return $profile;
    }

    private function encrypt(string $data): string
    {
        $data = trim($data);
        if ($data === '') {
            return '';
        }

        $key = $this->encryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);

        return $encrypted === false ? '' : base64_encode($iv . $encrypted);
    }

    private function decrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }

        try {
            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                return '';
            }

            $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);
            $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $this->encryptionKey(), 0, $iv);

            return $decrypted !== false ? $decrypted : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function encryptionKey(): string
    {
        $key = $_ENV['AFFILIATE_PAYOUT_ENCRYPTION_KEY']
            ?? $_ENV['PAYMENT_ENCRYPTION_KEY']
            ?? $_ENV['VNV_SECRET_KEY']
            ?? 'default-insecure-key-change-this';

        return hash('sha256', (string)$key, true);
    }

    private function hasColumn(string $column): bool
    {
        try {
            $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE :column");
            $this->db->bind(':column', $column);
            return (bool)$this->db->fetchOne();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
