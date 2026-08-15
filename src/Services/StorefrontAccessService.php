<?php

namespace App\Services;

use App\Repositories\Connection;

class StorefrontAccessService
{
    public static function ownerCanUseStore(int $ownerId): bool
    {
        return self::ownerCanUseModule($ownerId, 'store_delivery_tracking');
    }

    public static function ownerCanUseModule(int $ownerId, string $moduleSlug): bool
    {
        if ($ownerId <= 0) {
            return false;
        }

        $db = new Connection();
        $db->query('SELECT level FROM users WHERE id = :id LIMIT 1');
        $db->bind(':id', $ownerId);
        $owner = $db->fetchOne();

        if (!$owner) {
            return false;
        }

        if ((int)$owner->level === 1) {
            return true;
        }

        return (new ModuleAccessService())->userHasModule($ownerId, $moduleSlug);
    }
}
