<?php

namespace App\Repositories;

use PDOException;

class MarketplaceConnectorsRepository extends BaseRepository
{
    private const ENCRYPTION_METHOD = 'AES-256-CBC';

    public function __construct()
    {
        $this->table = 'marketplace_connectors';
        $this->db = new Connection();
    }

    public function isReady(): bool
    {
        return $this->tableExists($this->table);
    }

    public function hasConfiguredEncryptionKey(): bool
    {
        $key = trim((string)($_ENV['MARKETPLACE_CONNECTORS_ENCRYPTION_KEY'] ?? ''));
        $fallback = trim((string)($_ENV['PAYMENT_ENCRYPTION_KEY'] ?? $_ENV['VNV_SECRET_KEY'] ?? ''));

        return $key !== '' || $fallback !== '';
    }

    public function getByOwner(int $ownerId): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $this->db->query("
                SELECT * FROM {$this->table}
                WHERE id_owner = :owner_id
                ORDER BY FIELD(provider, 'shopee_br', 'mercadolibre', 'tiktok_shop'), provider
            ");
            $this->db->bind(':owner_id', $ownerId);

            return array_map([$this, 'withSafeDisplayFields'], $this->db->fetchAll());
        } catch (PDOException $e) {
            error_log('MarketplaceConnectorsRepository::getByOwner(): ' . $e->getMessage());
            return [];
        }
    }

    public function getByOwnerAndProvider(int $ownerId, string $provider): ?object
    {
        if (!$this->isReady()) {
            return null;
        }

        try {
            $this->db->query("
                SELECT * FROM {$this->table}
                WHERE id_owner = :owner_id AND provider = :provider
                LIMIT 1
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':provider', $provider);

            $connector = $this->db->fetchOne();
            return $connector ? $this->withSafeDisplayFields($connector) : null;
        } catch (PDOException $e) {
            error_log('MarketplaceConnectorsRepository::getByOwnerAndProvider(): ' . $e->getMessage());
            return null;
        }
    }

    public function getCredentialsForSync(int $ownerId, string $provider): ?object
    {
        $connector = $this->getRawByOwnerAndProvider($ownerId, $provider);

        if (!$connector) {
            return null;
        }

        $connector->access_token = $this->decrypt((string)($connector->access_token_encrypted ?? ''));
        $connector->refresh_token = $this->decrypt((string)($connector->refresh_token_encrypted ?? ''));

        return $connector;
    }

    public function upsert(int $ownerId, string $provider, array $data): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        $existing = $this->getRawByOwnerAndProvider($ownerId, $provider);
        $accessToken = trim((string)($data['access_token'] ?? ''));
        $refreshToken = trim((string)($data['refresh_token'] ?? ''));

        try {
            $this->db->query("
                INSERT INTO {$this->table}
                    (id_owner, provider, display_name, store_url, account_id, external_shop_id, shop_cipher, country_code, currency_code, shop_domain,
                     access_token_encrypted, refresh_token_encrypted, token_expires_at,
                     status, sync_status, public_store_url, created_at, updated_at)
                VALUES
                    (:owner_id, :provider, :display_name, :store_url, :account_id, :external_shop_id, :shop_cipher, :country_code, :currency_code, :shop_domain,
                     :access_token, :refresh_token, :token_expires_at,
                     :status, 'NEVER', :public_store_url, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    display_name = VALUES(display_name),
                    store_url = VALUES(store_url),
                    account_id = VALUES(account_id),
                    external_shop_id = VALUES(external_shop_id),
                    shop_cipher = VALUES(shop_cipher),
                    country_code = VALUES(country_code),
                    currency_code = VALUES(currency_code),
                    shop_domain = VALUES(shop_domain),
                    access_token_encrypted = IF(:access_token_plain = '', access_token_encrypted, VALUES(access_token_encrypted)),
                    refresh_token_encrypted = IF(:refresh_token_plain = '', refresh_token_encrypted, VALUES(refresh_token_encrypted)),
                    token_expires_at = VALUES(token_expires_at),
                    status = VALUES(status),
                    public_store_url = VALUES(public_store_url),
                    updated_at = NOW()
            ");

            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':provider', $provider);
            $this->db->bind(':display_name', trim((string)($data['display_name'] ?? '')));
            $this->db->bind(':store_url', trim((string)($data['store_url'] ?? '')) ?: null);
            $this->db->bind(':account_id', trim((string)($data['account_id'] ?? '')) ?: null);
            $this->db->bind(':external_shop_id', trim((string)($data['external_shop_id'] ?? $data['account_id'] ?? '')) ?: null);
            $this->db->bind(':shop_cipher', trim((string)($data['shop_cipher'] ?? '')) ?: null);
            $this->db->bind(':country_code', strtoupper(trim((string)($data['country_code'] ?? 'BR'))) ?: 'BR');
            $this->db->bind(':currency_code', strtoupper(trim((string)($data['currency_code'] ?? 'BRL'))) ?: 'BRL');
            $this->db->bind(':shop_domain', trim((string)($data['shop_domain'] ?? '')) ?: null);
            $this->db->bind(':access_token', $accessToken !== '' ? $this->encrypt($accessToken) : (string)($existing->access_token_encrypted ?? ''));
            $this->db->bind(':refresh_token', $refreshToken !== '' ? $this->encrypt($refreshToken) : (string)($existing->refresh_token_encrypted ?? ''));
            $this->db->bind(':access_token_plain', $accessToken);
            $this->db->bind(':refresh_token_plain', $refreshToken);
            $this->db->bind(':token_expires_at', trim((string)($data['token_expires_at'] ?? '')) ?: null);
            $this->db->bind(':status', !empty($data['is_active']) ? 'ACTIVE' : 'INACTIVE');
            $this->db->bind(':public_store_url', trim((string)($data['public_store_url'] ?? '')) ?: null);
            $this->db->execute();

            return true;
        } catch (PDOException $e) {
            error_log('MarketplaceConnectorsRepository::upsert(): ' . $e->getMessage());
            return false;
        }
    }

    public function markSync(int $ownerId, string $provider, string $status, ?string $error = null): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $this->db->query("
                UPDATE {$this->table}
                SET sync_status = :status,
                    last_sync_at = NOW(),
                    last_error = :error,
                    updated_at = NOW()
                WHERE id_owner = :owner_id AND provider = :provider
            ");
            $this->db->bind(':status', $status);
            $this->db->bind(':error', $error);
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':provider', $provider);
            $this->db->execute();

            return true;
        } catch (PDOException $e) {
            error_log('MarketplaceConnectorsRepository::markSync(): ' . $e->getMessage());
            return false;
        }
    }

    public function saveAuthorization(int $ownerId,string $provider,array $token): bool
    {
        $access=(string)($token['access_token']??''); if($access==='') return false;
        $refresh=(string)($token['refresh_token']??'');
        $expires=$token['expires_in']??$token['access_token_expire_in']??null;
        $expiresAt=is_numeric($expires)?date('Y-m-d H:i:s',((int)$expires>time()?(int)$expires:time()+(int)$expires)):null;
        try{$this->db->query("UPDATE {$this->table} SET access_token_encrypted=:access,refresh_token_encrypted=:refresh,token_expires_at=:expires,external_shop_id=COALESCE(NULLIF(:shop,''),external_shop_id),shop_cipher=COALESCE(NULLIF(:cipher,''),shop_cipher),status='ACTIVE',last_error=NULL,updated_at=NOW() WHERE id_owner=:owner AND provider=:provider");$this->db->bind(':access',$this->encrypt($access));$this->db->bind(':refresh',$refresh!==''?$this->encrypt($refresh):'');$this->db->bind(':expires',$expiresAt);$this->db->bind(':shop',(string)($token['shop_id']??$token['user_id']??$token['open_id']??''));$this->db->bind(':cipher',(string)($token['shop_cipher']??''));$this->db->bind(':owner',$ownerId);$this->db->bind(':provider',$provider);$this->db->execute();return$this->db->rowCount()===1;}catch(\Throwable $e){error_log('Marketplace authorization save: '.$e->getMessage());return false;}
    }

    private function getRawByOwnerAndProvider(int $ownerId, string $provider): ?object
    {
        if (!$this->isReady()) {
            return null;
        }

        try {
            $this->db->query("
                SELECT * FROM {$this->table}
                WHERE id_owner = :owner_id AND provider = :provider
                LIMIT 1
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':provider', $provider);

            $connector = $this->db->fetchOne();
            return $connector ?: null;
        } catch (PDOException $e) {
            error_log('MarketplaceConnectorsRepository::getRawByOwnerAndProvider(): ' . $e->getMessage());
            return null;
        }
    }

    private function withSafeDisplayFields(object $connector): object
    {
        $connector->has_access_token = !empty($connector->access_token_encrypted);
        $connector->has_refresh_token = !empty($connector->refresh_token_encrypted);
        $connector->access_token_mask = $connector->has_access_token ? 'Saved token' : 'No token';
        $connector->refresh_token_mask = $connector->has_refresh_token ? 'Saved token' : 'No token';
        unset($connector->access_token_encrypted, $connector->refresh_token_encrypted);

        return $connector;
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->query('SHOW TABLES LIKE :table');
            $this->db->bind(':table', $table);
            return (bool)$this->db->fetchOne();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function encrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }

        try {
            $key = $this->getEncryptionKey();
            $decoded = base64_decode($data, true);

            if ($decoded === false) {
                return '';
            }

            $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);
            $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);

            return $decrypted !== false ? $decrypted : '';
        } catch (\Throwable $e) {
            error_log('MarketplaceConnectorsRepository::decrypt(): ' . $e->getMessage());
            return '';
        }
    }

    private function getEncryptionKey(): string
    {
        $key = $_ENV['MARKETPLACE_CONNECTORS_ENCRYPTION_KEY']
            ?? $_ENV['PAYMENT_ENCRYPTION_KEY']
            ?? $_ENV['VNV_SECRET_KEY']
            ?? 'default-insecure-key-change-this';

        return hash('sha256', $key, true);
    }
}
