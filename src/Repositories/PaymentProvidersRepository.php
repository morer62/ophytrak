<?php

namespace App\Repositories;

use PDOException;

class PaymentProvidersRepository extends BaseRepository
{
    protected array $fields = [
        'id_owner',
        'provider_type',
        'provider_name',
        'api_key',
        'api_secret',
        'public_key',
        'webhook_secret',
        'environment',
        'currency',
        'merchant_email',
        'location_id',
        'is_active',
        'is_verified',
        'is_default',
        'last_used_at',
        'created_at',
        'updated_at'
    ];

    // Encryption settings - Uses environment variable for security
    private const ENCRYPTION_METHOD = 'AES-256-CBC';
    private const SENSITIVE_FIELDS = ['api_key', 'api_secret', 'webhook_secret'];

    public function __construct()
    {
        $this->table = "payment_providers_credentials";
        $this->db = new Connection();
    }

    /**
     * Get encryption key from environment
     * Falls back to a default key if not set (NOT RECOMMENDED for production)
     */
    private function getEncryptionKey(): string
    {
        $key = $_ENV['PAYMENT_ENCRYPTION_KEY'] ?? null;
        
        if (!$key) {
            // Generate a warning in logs
            error_log("WARNING: PAYMENT_ENCRYPTION_KEY not set in .env. Using fallback key (insecure).");
            // Use VNV_SECRET_KEY as fallback
            $key = $_ENV['VNV_SECRET_KEY'] ?? 'default-insecure-key-change-this';
        }
        
        // Ensure key is exactly 32 bytes for AES-256
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
        
        // Combine IV and encrypted data, then base64 encode
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
            error_log("Decryption error: " . $e->getMessage());
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
    private function decryptCredentials(object $provider): object
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($provider->$field) && !empty($provider->$field)) {
                $provider->$field = $this->decrypt($provider->$field);
            }
        }
        return $provider;
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
     * Get all active providers for an owner (decrypted)
     */
    public function getActiveByOwner(int $ownerId): array
    {
        try {
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id AND `is_active` = 1
                ORDER BY `is_default` DESC, `provider_type`, `provider_name`
            ");
            $this->db->bind(':owner_id', $ownerId);
            
            $providers = $this->db->fetchAll();
            
            // Decrypt all providers
            return array_map(function($provider) {
                return $this->decryptCredentials($provider);
            }, $providers);
        } catch (PDOException $e) {
            error_log("Error in getActiveByOwner: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get default provider for an owner (decrypted)
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
            
            $provider = $this->db->fetchOne();
            
            return $provider ? $this->decryptCredentials($provider) : null;
        } catch (PDOException $e) {
            error_log("Error in getDefaultByOwner: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the active payment provider for an owner (default or first active).
     * Used by order-access and other flows that need a single provider.
     */
    public function getActiveProviderForOwner(int $ownerId): ?object
    {
        $provider = $this->getDefaultByOwner($ownerId);
        if ($provider && $provider->is_active) {
            return $provider;
        }
        $active = $this->getActiveByOwner($ownerId);
        return $active[0] ?? null;
    }

    /**
     * Devuelve el id_owner a usar para cobros (order-access).
     * Si la orden fue creada por un usuario nivel 2 que tiene proveedor configurado, usa ese;
     * si no, usa order.id_owner (nivel 1 / institución).
     */
    public function getPaymentOwnerIdForOrder(object $order): int
    {
        $ownerId = (int)($order->id_owner ?? 0);
        if (empty($order->id_user)) {
            return $ownerId;
        }
        $userRepo = new UserRepository();
        $creator = $userRepo->getOne(['id' => $order->id_user]);
        if (!$creator || (int)$creator->level !== 2) {
            return $ownerId;
        }
        $provider = $this->getActiveProviderForOwner((int)$order->id_user);
        if ($provider) {
            return (int)$order->id_user;
        }
        return $ownerId;
    }

    /**
     * Get all providers of a specific type for an owner
     */
    public function getByType(int $ownerId, string $type): array
    {
        try {
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id AND `provider_type` = :type
                ORDER BY `is_default` DESC, `provider_name`
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':type', $type);
            
            $providers = $this->db->fetchAll();
            
            return array_map(function($provider) {
                return $this->decryptCredentials($provider);
            }, $providers);
        } catch (PDOException $e) {
            error_log("Error in getByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get provider by ID (decrypted)
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
            
            $provider = $this->db->fetchOne();
            
            return $provider ? $this->decryptCredentials($provider) : null;
        } catch (PDOException $e) {
            error_log("Error in getById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a provider as default (removes default from others)
     */
    public function setAsDefault(int $credentialId, int $ownerId): bool
    {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Remove default flag from all providers of this owner
            $this->db->query("
                UPDATE `{$this->table}` 
                SET `is_default` = 0, `updated_at` = NOW()
                WHERE `id_owner` = :owner_id
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->execute();
            
            // Set new default
            $this->db->query("
                UPDATE `{$this->table}` 
                SET `is_default` = 1, `updated_at` = NOW()
                WHERE `id` = :id AND `id_owner` = :owner_id
            ");
            $this->db->bind(':id', $credentialId);
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
     * Activate a provider
     */
    public function activate(int $credentialId, int $ownerId): bool
    {
        return $this->update([
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $credentialId,
            'id_owner' => $ownerId
        ]);
    }

    /**
     * Deactivate a provider
     */
    public function deactivate(int $credentialId, int $ownerId): bool
    {
        return $this->update([
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $credentialId,
            'id_owner' => $ownerId
        ]);
    }

    /**
     * Deactivate all providers for an owner (used when activating a new one)
     */
    public function deactivateAllByOwner(int $ownerId): bool
    {
        try {
            $this->db->query("
                UPDATE `{$this->table}` 
                SET `is_active` = 0, `updated_at` = NOW()
                WHERE `id_owner` = :owner_id
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error in deactivateAllByOwner: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark provider as verified
     */
    public function markAsVerified(int $credentialId, int $ownerId): bool
    {
        return $this->update([
            'is_verified' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $credentialId,
            'id_owner' => $ownerId
        ]);
    }

    /**
     * Mark provider as unverified
     */
    public function markAsUnverified(int $credentialId, int $ownerId): bool
    {
        return $this->update([
            'is_verified' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $credentialId,
            'id_owner' => $ownerId
        ]);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(int $credentialId): bool
    {
        try {
            $this->db->query("
                UPDATE `{$this->table}` 
                SET `last_used_at` = NOW(), `updated_at` = NOW()
                WHERE `id` = :id
            ");
            $this->db->bind(':id', $credentialId);
            $this->db->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error in updateLastUsed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a provider (with owner verification)
     */
    public function deleteProvider(int $credentialId, int $ownerId): bool
    {
        return $this->delete([
            'id' => $credentialId,
            'id_owner' => $ownerId
        ]);
    }

    /**
     * Get all providers for an owner (with pagination, for admin panel)
     */
    public function getAllByOwner(int $ownerId, int $page = 1, int $limit = 20): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // Get paginated data
            $this->db->query("
                SELECT * FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id
                ORDER BY `is_default` DESC, `is_active` DESC, `provider_type`, `provider_name`
                LIMIT :limit OFFSET :offset
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':limit', $limit, \PDO::PARAM_INT);
            $this->db->bind(':offset', $offset, \PDO::PARAM_INT);
            
            $providers = $this->db->fetchAll();
            
            // Decrypt sensitive fields
            $providers = array_map(function($provider) {
                return $this->decryptCredentials($provider);
            }, $providers);
            
            // Get total count
            $this->db->query("
                SELECT COUNT(*) as total FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id
            ");
            $this->db->bind(':owner_id', $ownerId);
            $totalResult = $this->db->fetchOne();
            
            return [
                'data' => $providers,
                'current_page' => $page,
                'limit' => $limit,
                'total' => (int)$totalResult->total,
                'last_page' => ceil($totalResult->total / $limit)
            ];
        } catch (PDOException $e) {
            error_log("Error in getAllByOwner: " . $e->getMessage());
            return [
                'data' => [],
                'current_page' => $page,
                'limit' => $limit,
                'total' => 0,
                'last_page' => 0
            ];
        }
    }

    /**
     * Check if provider name already exists for owner
     */
    public function providerNameExists(int $ownerId, string $providerType, string $providerName, ?int $excludeId = null): bool
    {
        try {
            $query = "
                SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `id_owner` = :owner_id 
                AND `provider_type` = :type 
                AND `provider_name` = :name
            ";
            
            if ($excludeId !== null) {
                $query .= " AND `id` != :exclude_id";
            }
            
            $this->db->query($query);
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':type', $providerType);
            $this->db->bind(':name', $providerName);
            
            if ($excludeId !== null) {
                $this->db->bind(':exclude_id', $excludeId);
            }
            
            $result = $this->db->fetchOne();
            return $result->count > 0;
        } catch (PDOException $e) {
            error_log("Error in providerNameExists: " . $e->getMessage());
            return false;
        }
    }
}
