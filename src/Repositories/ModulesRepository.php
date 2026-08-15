<?php

namespace App\Repositories;

use App\Services\OphyraPricingService;
use PDOException;

class ModulesRepository extends BaseRepository
{
    public const BASE_SLUGS = [
        'business_profile',
    ];

    public const ADDON_SLUGS = [
        'services',
        'store_delivery_tracking',
        'inventory_storage',
        'ai_advisor',
        'tickets_rsvp',
        'marketplace_connectors',
    ];

    public const COMING_SOON_SLUGS = [
        'custom_domain_seo_page_builder',
    ];

    public const CANONICAL_SLUGS = [
        'base_profile',
        'service_operations',
        'store_logistics',
        'advanced_storage_qr_inventory',
        'ai_advisor',
        'ticket_sales_rsvp',
        'marketplace_connectors',
        'custom_domain_seo_page_builder',
    ];

    public const LEGACY_SLUG_ALIASES = [
        'base_profile' => 'business_profile',
        'service_operations' => 'services',
        'store_logistics' => 'store_delivery_tracking',
        'advanced_storage_qr_inventory' => 'inventory_storage',
        'ticket_sales_rsvp' => 'tickets_rsvp',
    ];

    public const CANONICAL_TO_LEGACY_ALIASES = [
        'business_profile' => 'base_profile',
        'services' => 'service_operations',
        'store_delivery_tracking' => 'store_logistics',
        'inventory_storage' => 'advanced_storage_qr_inventory',
        'tickets_rsvp' => 'ticket_sales_rsvp',
    ];

    public function __construct()
    {
        $this->table = 'modules';
        $this->db = new Connection();
    }

    public function getAllActive(): array
    {
        try {
            $where = $this->activeWhereClause();
            $sql = "SELECT * FROM {$this->table}";

            if ($where !== '') {
                $sql .= " WHERE {$where}";
            }

            $sql .= " ORDER BY " . $this->orderByClause();

            $this->db->query($sql);
            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('ModulesRepository::getAllActive(): ' . $e->getMessage());
            return [];
        }
    }

    public function getBySlug(string $slug): ?object
    {
        try {
            $where = 'slug = :slug';
            $activeWhere = $this->activeWhereClause();

            if ($activeWhere !== '') {
                $where .= " AND {$activeWhere}";
            }

            $this->db->query("SELECT * FROM {$this->table} WHERE {$where} LIMIT 1");
            $this->db->bind(':slug', $slug);

            $module = $this->db->fetchOne();
            return $module ?: null;
        } catch (PDOException $e) {
            error_log('ModulesRepository::getBySlug(): ' . $e->getMessage());
            return null;
        }
    }

    public function getBaseModules(): array
    {
        return $this->getBySlugs(self::BASE_SLUGS);
    }

    public function getAddons(): array
    {
        $slugs = \App\Services\ProductProfileService::isOphytrack()
            ? \App\Services\ProductProfileService::allowedAddonSlugs()
            : self::ADDON_SLUGS;
        return $this->getBySlugs($slugs);
    }

    public function getOfficialAddonPrices(): array
    {
        return (new OphyraPricingService())->officialAddonPrices();
    }

    public function getOfficialPriceBySlug(string $slug): ?float
    {
        $pricing = new OphyraPricingService();
        $price = $pricing->modulePrice($slug);

        if ($price !== null) {
            return $price;
        }

        $module = $this->getBySlug($slug);
        return $module && $module->monthly_price !== null ? (float)$module->monthly_price : null;
    }

    public function getBaseMonthlyPrice(): float
    {
        return (new OphyraPricingService())->baseProfilePrice();
    }

    public function normalizeSlug(string $slug): string
    {
        return (new OphyraPricingService())->legacySlug($slug);
    }

    public function getCanonicalSlug(string $slug): string
    {
        return (new OphyraPricingService())->canonicalSlug($slug);
    }

    public function getCompatibleSlugs(string $slug): array
    {
        return (new OphyraPricingService())->compatibleSlugs($slug);
    }

    public function getBySlugs(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        try {
            $placeholders = [];

            foreach ($slugs as $index => $slug) {
                $placeholders[] = ':slug_' . $index;
            }

            $where = 'slug IN (' . implode(', ', $placeholders) . ')';
            $activeWhere = $this->activeWhereClause();

            if ($activeWhere !== '') {
                $where .= " AND {$activeWhere}";
            }

            $this->db->query("SELECT * FROM {$this->table} WHERE {$where} ORDER BY " . $this->orderByClause());

            foreach ($slugs as $index => $slug) {
                $this->db->bind(':slug_' . $index, $slug);
            }

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('ModulesRepository::getBySlugs(): ' . $e->getMessage());
            return [];
        }
    }

    private function activeWhereClause(): string
    {
        if ($this->hasColumn('is_active')) {
            return 'is_active = 1';
        }

        if ($this->hasColumn('status')) {
            return "status = 'ACTIVE'";
        }

        return '';
    }

    private function orderByClause(): string
    {
        if ($this->hasColumn('sort_order')) {
            return 'sort_order ASC, id ASC';
        }

        if ($this->hasColumn('name')) {
            return 'name ASC';
        }

        return 'id ASC';
    }

    private function hasColumn(string $column): bool
    {
        try {
            $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE :column");
            $this->db->bind(':column', $column);
            return (bool)$this->db->fetchOne();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function envFloat(array $keys, float $default): float
    {
        foreach ($keys as $key) {
            if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
                return (float)$_ENV[$key];
            }
        }

        return $default;
    }
}
