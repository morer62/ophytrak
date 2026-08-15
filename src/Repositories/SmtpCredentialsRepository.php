<?php

namespace App\Repositories;

use PDOException;

class SmtpCredentialsRepository extends BaseRepository
{
    protected array $fields = [
        'id_owner',
        'provider_name',
        'provider_type',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'from_email',
        'from_name',
        'reply_to_email',
        'is_active',
        'is_verified',
        'is_default',
        'daily_limit',
        'emails_sent_today',
        'last_reset_date',
        'last_used_at',
        'last_error',
        'created_at',
        'updated_at'
    ];

    private const ENCRYPTION_METHOD = 'AES-256-CBC';
    private const SENSITIVE_FIELDS = ['smtp_password'];

    public function __construct()
    {
        $this->table = "smtp_credentials";
        $this->db = new Connection();
    }

    /**
     * Get encryption key from environment
     */
    private function getEncryptionKey(): string
    {
        $key = $_ENV['SMTP_ENCRYPTION_KEY'] ?? $_ENV['PAYMENT_ENCRYPTION_KEY'] ?? $_ENV['VNV_SECRET_KEY'] ?? 'default-key';
        
        if ($key === 'default-key') {
            error_log("WARNING: No encryption key set. Using insecure default.");
        }
        
        return hash('sha256', $key, true);
    }

    /**
     * Encrypt sensitive data
     */
    private function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    private function decrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($data);
            
            $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);
            
            return $decrypted !== false ? $decrypted : '';
        } catch (\Exception $e) {
            error_log("SMTP Decryption error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Encrypt credentials before saving
     */
    private function encryptCredentials(array $data): array
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->encrypt($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Decrypt credentials after fetching
     */
    private function decryptCredentials(object $smtp): object
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($smtp->$field) && !empty($smtp->$field)) {
                $smtp->$field = $this->decrypt($smtp->$field);
            }
        }
        return $smtp;
    }

    /**
     * Override add() to encrypt credentials
     */
    public function add(array $data): bool
    {
        $data = $this->encryptCredentials($data);
        return parent::add($data);
    }

    /**
     * Override update() to encrypt credentials
     */
    public function update(array $data, array $criteriaVals): bool
    {
        $data = $this->encryptCredentials($data);
        return parent::update($data, $criteriaVals);
    }

    /**
     * Get default SMTP for an owner (decrypted)
     */
    public function getDefaultByOwner(int $ownerId): ?object
    {
        try {
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id AND `is_default` = 1
                LIMIT 1
            ");
            $this->db->bind(':owner_id', $ownerId);
            
            $smtp = $this->db->fetchOne();
            
            return $smtp ? $this->decryptCredentials($smtp) : null;
        } catch (PDOException $e) {
            error_log("Error in getDefaultByOwner: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all active SMTP configs for an owner
     */
    public function getActiveByOwner(int $ownerId): array
    {
        try {
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id AND `is_active` = 1
                ORDER BY `is_default` DESC, `provider_name`
            ");
            $this->db->bind(':owner_id', $ownerId);
            
            $smtpList = $this->db->fetchAll();
            
            return array_map(fn($smtp) => $this->decryptCredentials($smtp), $smtpList);
        } catch (PDOException $e) {
            error_log("Error in getActiveByOwner: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get SMTP by ID (with owner verification)
     */
    public function getById(int $id, int $ownerId): ?object
    {
        try {
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id` = :id AND `id_owner` = :owner_id
                LIMIT 1
            ");
            $this->db->bind(':id', $id);
            $this->db->bind(':owner_id', $ownerId);
            
            $smtp = $this->db->fetchOne();
            
            return $smtp ? $this->decryptCredentials($smtp) : null;
        } catch (PDOException $e) {
            error_log("Error in getById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all SMTP configs for owner (with pagination)
     */
    public function getAllByOwner(int $ownerId, int $page = 1, int $limit = 20): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id
                ORDER BY `is_default` DESC, `is_active` DESC, `provider_name`
                LIMIT :limit OFFSET :offset
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':limit', $limit, \PDO::PARAM_INT);
            $this->db->bind(':offset', $offset, \PDO::PARAM_INT);
            
            $smtpList = $this->db->fetchAll();
            $smtpList = array_map(fn($smtp) => $this->decryptCredentials($smtp), $smtpList);
            
            // Get total count
            $this->db->query("SELECT COUNT(*) as total FROM `{$this->table}` WHERE `id_owner` = :owner_id");
            $this->db->bind(':owner_id', $ownerId);
            $totalResult = $this->db->fetchOne();
            
            return [
                'data' => $smtpList,
                'current_page' => $page,
                'limit' => $limit,
                'total' => (int)$totalResult->total,
                'last_page' => ceil($totalResult->total / $limit)
            ];
        } catch (PDOException $e) {
            error_log("Error in getAllByOwner: " . $e->getMessage());
            return ['data' => [], 'current_page' => $page, 'limit' => $limit, 'total' => 0, 'last_page' => 0];
        }
    }

    /**
     * Set SMTP as default (removes default from others)
     */
    public function setAsDefault(int $smtpId, int $ownerId): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Remove default from all
            $this->db->query("UPDATE `{$this->table}` SET `is_default` = 0 WHERE `id_owner` = :owner_id");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->execute();
            
            // Set new default
            $this->db->query("UPDATE `{$this->table}` SET `is_default` = 1, `updated_at` = NOW() WHERE `id` = :id AND `id_owner` = :owner_id");
            $this->db->bind(':id', $smtpId);
            $this->db->bind(':owner_id', $ownerId);
            $this->db->execute();
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error in setAsDefault: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activate SMTP
     */
    public function activate(int $smtpId, int $ownerId): bool
    {
        return $this->update(['is_active' => 1], ['id' => $smtpId, 'id_owner' => $ownerId]);
    }

    /**
     * Deactivate SMTP
     */
    public function deactivate(int $smtpId, int $ownerId): bool
    {
        return $this->update(['is_active' => 0], ['id' => $smtpId, 'id_owner' => $ownerId]);
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(int $smtpId, int $ownerId): bool
    {
        return $this->update(['is_verified' => 1], ['id' => $smtpId, 'id_owner' => $ownerId]);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(int $smtpId): bool
    {
        try {
            $this->db->query("UPDATE `{$this->table}` SET `last_used_at` = NOW() WHERE `id` = :id");
            $this->db->bind(':id', $smtpId);
            $this->db->execute();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Update last error
     */
    public function updateLastError(int $smtpId, string $error): bool
    {
        return $this->update(['last_error' => $error], ['id' => $smtpId]);
    }

    /**
     * Increment daily email counter
     */
    public function incrementEmailCount(int $smtpId): bool
    {
        try {
            $this->db->query("
                UPDATE `{$this->table}` 
                SET `emails_sent_today` = `emails_sent_today` + 1,
                    `last_used_at` = NOW()
                WHERE `id` = :id
            ");
            $this->db->bind(':id', $smtpId);
            $this->db->execute();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Reset daily counter (should run daily via cron)
     */
    public function resetDailyCounters(): bool
    {
        try {
            $this->db->query("
                UPDATE `{$this->table}` 
                SET `emails_sent_today` = 0, `last_reset_date` = CURDATE()
                WHERE `last_reset_date` < CURDATE() OR `last_reset_date` IS NULL
            ");
            $this->db->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error resetting daily counters: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if provider name exists
     */
    public function providerNameExists(int $ownerId, string $providerName, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `id_owner` = :owner_id AND `provider_name` = :name";
            
            if ($excludeId !== null) {
                $query .= " AND `id` != :exclude_id";
            }
            
            $this->db->query($query);
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':name', $providerName);
            
            if ($excludeId !== null) {
                $this->db->bind(':exclude_id', $excludeId);
            }
            
            $result = $this->db->fetchOne();
            return $result->count > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Delete SMTP configuration
     */
    public function deleteSmtp(int $smtpId, int $ownerId): bool
    {
        return $this->delete(['id' => $smtpId, 'id_owner' => $ownerId]);
    }
}
