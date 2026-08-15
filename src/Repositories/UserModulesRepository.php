<?php

namespace App\Repositories;

use PDOException;

class UserModulesRepository extends BaseRepository
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CANCELED = 'CANCELED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public function __construct()
    {
        $this->table = 'user_modules';
        $this->db = new Connection();
    }

    public function getActiveByUserId(int $userId): array
    {
        try {
            $this->db->query("
                SELECT um.*, m.slug, m.name
                FROM {$this->table} um
                INNER JOIN modules m ON m.slug = um.module_slug
                WHERE um.id_user = :user_id
                AND um.status = :status
                AND (um.renewal_at IS NULL OR um.renewal_at >= CURDATE())
                ORDER BY m.name ASC
            ");
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':status', self::STATUS_ACTIVE);

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('UserModulesRepository::getActiveByUserId(): ' . $e->getMessage());
            return [];
        }
    }

    public function getByUserId(int $userId): array
    {
        try {
            $this->db->query("
                SELECT um.*, m.slug, m.name
                FROM {$this->table} um
                INNER JOIN modules m ON m.slug = um.module_slug
                WHERE um.id_user = :user_id
                ORDER BY m.name ASC
            ");
            $this->db->bind(':user_id', $userId);

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('UserModulesRepository::getByUserId(): ' . $e->getMessage());
            return [];
        }
    }

    public function getByUserAndSlug(int $userId, string $moduleSlug): ?object
    {
        try {
            $this->db->query("
                SELECT um.*, m.slug, m.name
                FROM {$this->table} um
                INNER JOIN modules m ON m.slug = um.module_slug
                WHERE um.id_user = :user_id
                AND m.slug = :module_slug
                LIMIT 1
            ");
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':module_slug', $moduleSlug);

            $module = $this->db->fetchOne();
            return $module ?: null;
        } catch (PDOException $e) {
            error_log('UserModulesRepository::getByUserAndSlug(): ' . $e->getMessage());
            return null;
        }
    }

    public function activateModule(int $userId, int $moduleId, ?string $renewalAt = null): bool
    {
        $moduleSlug = $this->getModuleSlugById($moduleId);
        return $moduleSlug ? $this->activateModuleBySlug($userId, $moduleSlug, $renewalAt) : false;
    }

    public function deactivateModule(int $userId, int $moduleId): bool
    {
        $moduleSlug = $this->getModuleSlugById($moduleId);
        return $moduleSlug ? $this->setStatusBySlug($userId, $moduleSlug, self::STATUS_INACTIVE) : false;
    }

    public function setStatus(int $userId, int $moduleId, string $status, ?string $renewalAt = null): bool
    {
        $moduleSlug = $this->getModuleSlugById($moduleId);
        return $moduleSlug ? $this->setStatusBySlug($userId, $moduleSlug, $status, $renewalAt) : false;
    }

    public function upsertUserModule(int $userId, int $moduleId, string $status, ?string $renewalAt = null): bool
    {
        $moduleSlug = $this->getModuleSlugById($moduleId);
        return $moduleSlug ? $this->upsertUserModuleBySlug($userId, $moduleSlug, $status, $renewalAt) : false;
    }

    public function activateModuleBySlug(int $userId, string $moduleSlug, ?string $renewalAt = null): bool
    {
        return $this->upsertUserModuleBySlug($userId, $moduleSlug, self::STATUS_ACTIVE, $renewalAt);
    }

    public function activateIncludedCarrierLogistics(int $ownerId): bool
    {
        try {
            $this->db->query("INSERT INTO user_modules (id_user,module_slug,status,billing_status,activation_source,activation_reason,is_included_in_base,price,started_at,renewal_at,created_at,updated_at) VALUES (:owner,'store_delivery_tracking','ACTIVE','not_required','carrier_included','Logistics is permanently included for verified carrier organizations.',1,0,CURDATE(),NULL,NOW(),NOW()) ON DUPLICATE KEY UPDATE status='ACTIVE',billing_status='not_required',activation_source='carrier_included',activation_reason='Logistics is permanently included for verified carrier organizations.',is_included_in_base=1,price=0,renewal_at=NULL,canceled_at=NULL,updated_at=NOW()");
            $this->db->bind(':owner',$ownerId); return (bool)$this->db->execute();
        } catch (PDOException $e) { error_log('Carrier entitlement failed: '.$e->getMessage()); return false; }
    }

    public function deactivateModuleBySlug(int $userId, string $moduleSlug): bool
    {
        return $this->setStatusBySlug($userId, $moduleSlug, self::STATUS_INACTIVE);
    }

    public function setStatusBySlug(int $userId, string $moduleSlug, string $status, ?string $renewalAt = null): bool
    {
        return $this->upsertUserModuleBySlug($userId, $moduleSlug, $status, $renewalAt);
    }

    public function upsertUserModuleBySlug(int $userId, string $moduleSlug, string $status, ?string $renewalAt = null, ?float $priceOverride = null): bool
    {
        try {
            $existing = $this->getByUserAndSlug($userId, $moduleSlug);

            if ($existing) {
                $this->db->query("
                    UPDATE {$this->table}
                    SET status = :status,
                        price = :price,
                        renewal_at = :renewal_at,
                        updated_at = NOW()
                    WHERE id_user = :user_id
                    AND module_slug = :module_slug
                ");
            } else {
                $this->db->query("
                    INSERT INTO {$this->table}
                    (id_user, module_slug, status, is_included_in_base, price, started_at, renewal_at, created_at, updated_at)
                    VALUES
                    (:user_id, :module_slug, :status, 0, :price, NOW(), :renewal_at, NOW(), NOW())
                ");
            }

            $this->db->bind(':user_id', $userId);
            $this->db->bind(':module_slug', $moduleSlug);
            $this->db->bind(':status', $status);
            $this->db->bind(':renewal_at', $renewalAt);
            $this->db->bind(':price', $priceOverride ?? $this->getMonthlyPriceBySlug($moduleSlug));
            $this->db->execute();

            return true;
        } catch (PDOException $e) {
            error_log('UserModulesRepository::upsertUserModule(): ' . $e->getMessage());
            return false;
        }
    }

    public function expirePastDueModules(): bool
    {
        try {
            $this->db->query("
                UPDATE {$this->table}
                SET status = :expired_status,
                    updated_at = NOW()
                WHERE status = :active_status
                AND renewal_at IS NOT NULL
                AND renewal_at < CURDATE()
            ");
            $this->db->bind(':expired_status', self::STATUS_EXPIRED);
            $this->db->bind(':active_status', self::STATUS_ACTIVE);
            $this->db->execute();

            return true;
        } catch (PDOException $e) {
            error_log('UserModulesRepository::expirePastDueModules(): ' . $e->getMessage());
            return false;
        }
    }

    private function getModuleSlugById(int $moduleId): ?string
    {
        $this->db->query("
            SELECT slug
            FROM modules
            WHERE id = :module_id
            LIMIT 1
        ");
        $this->db->bind(':module_id', $moduleId);

        $module = $this->db->fetchOne();
        return $module ? $module->slug : null;
    }

    private function getMonthlyPriceBySlug(string $moduleSlug): ?float
    {
        $modulesRepository = new ModulesRepository();
        $officialPrice = $modulesRepository->getOfficialPriceBySlug($moduleSlug);

        if ($officialPrice !== null) {
            return $officialPrice;
        }

        $this->db->query("
            SELECT monthly_price
            FROM modules
            WHERE slug = :module_slug
            LIMIT 1
        ");
        $this->db->bind(':module_slug', $moduleSlug);

        $module = $this->db->fetchOne();
        return $module && $module->monthly_price !== null ? (float)$module->monthly_price : null;
    }
    public function setBillingStatusByCompatibleSlugs(int $userId, array $moduleSlugs, string $billingStatus): bool
    {
        $moduleSlugs = array_values(array_unique(array_filter(array_map('strval', $moduleSlugs))));
        if ($moduleSlugs === []) {
            return false;
        }

        try {
            $placeholders = [];

            foreach ($moduleSlugs as $index => $slug) {
                $placeholders[] = ':slug_' . $index;
            }

            $this->db->query('
                UPDATE user_modules
                SET billing_status = :billing_status,
                    updated_at = NOW()
                WHERE id_user = :user_id
                AND module_slug IN (' . implode(',', $placeholders) . ')
            ');
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':billing_status', $billingStatus);

            foreach ($moduleSlugs as $index => $slug) {
                $this->db->bind(':slug_' . $index, $slug);
            }

            $this->db->execute();
            return true;
        } catch (PDOException $e) {
            error_log('UserModulesRepository::setBillingStatusByCompatibleSlugs(): ' . $e->getMessage());
            return false;
        }
    }
}
