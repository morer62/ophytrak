<?php

namespace App\Services;

use App\Repositories\Connection;
use App\Repositories\ModulesRepository;
use App\Repositories\UserModulesRepository;
use DateTimeImmutable;
use Exception;
use PDOException;

class ModuleAccessService
{
    public const SERVICE_OPERATION_FEATURES = [
        'crm',
        'clients',
        'team',
        'basic_payroll',
        'orders',
        'contracts',
        'basic_chat',
        'payments',
        'roles',
        'reports',
    ];

    public const STORE_LOGISTICS_FEATURES = [
        'store',
        'products',
        'store_orders',
        'fulfillment',
        'delivery_tracking',
        'basic_inventory',
        'customers',
    ];

    private Connection $db;
    private ModulesRepository $modulesRepository;
    private UserModulesRepository $userModulesRepository;

    public function __construct(
        ?ModulesRepository $modulesRepository = null,
        ?UserModulesRepository $userModulesRepository = null
    ) {
        $this->db = new Connection();
        $this->modulesRepository = $modulesRepository ?? new ModulesRepository();
        $this->userModulesRepository = $userModulesRepository ?? new UserModulesRepository();
    }

    public function userHasActiveBaseMembership(object|array|int|null $user): bool
    {
        if (is_int($user)) {
            // A user may be operating through an authorized alternate workspace
            // view (for example, a Level 5 account in its Level 2 Business Admin
            // view). Preserve that effective session level instead of replacing it
            // with the account's original database level during module checks.
            $sessionUser = LoginService::getSession();
            $user = $sessionUser && (int)$sessionUser->getId() === $user
                ? $sessionUser
                : $this->getUserById($user);
        }

        if (!$user) {
            return false;
        }

        $membershipType = strtoupper((string)$this->value($user, 'membership_type', ''));
        $dueDate = $this->value($user, 'membership_due_date');

        if ($membershipType === 'CANCELED' || $membershipType === 'EXPIRED') {
            return false;
        }

        if ($this->freeStarterBaseEnabled()) {
            $level = (int)$this->value($user, 'level', 0);
            $isActive = (int)$this->value($user, 'is_active', 1);

            return $isActive === 1 && in_array($level, [1, 2], true);
        }

        if (!$dueDate) {
            return false;
        }

        try {
            $today = new DateTimeImmutable('today');
            $membershipDueDate = new DateTimeImmutable((string)$dueDate);
            return $membershipDueDate >= $today;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getActiveModuleSlugs(int $userId): array
    {
        if (!$this->userHasActiveBaseMembership($userId)) {
            return [];
        }

        $baseSlugs = $this->getBaseModuleSlugs();
        $addonSlugs = array_map(
            static fn($module) => $module->slug,
            $this->userModulesRepository->getActiveByUserId($userId)
        );

        foreach ($baseSlugs as $baseSlug) {
            $baseSlugs[] = $this->modulesRepository->getCanonicalSlug((string)$baseSlug);
        }

        foreach ($addonSlugs as $addonSlug) {
            $addonSlugs[] = $this->modulesRepository->getCanonicalSlug((string)$addonSlug);
        }

        return array_values(array_unique(array_merge($baseSlugs, $addonSlugs)));
    }

    public function userHasModule(int $userId, string $moduleSlug): bool
    {
        if (!$this->userHasActiveBaseMembership($userId)) {
            return false;
        }

        $moduleSlug = $this->modulesRepository->normalizeSlug($moduleSlug);

        if (in_array($moduleSlug, $this->getBaseModuleSlugs(), true)) {
            return true;
        }

        $compatibleSlugs = $this->modulesRepository->getCompatibleSlugs($moduleSlug);
        if (empty(array_intersect($compatibleSlugs, ModulesRepository::ADDON_SLUGS))) {
            return false;
        }

        $module = null;
        foreach ($compatibleSlugs as $compatibleSlug) {
            $module = $this->userModulesRepository->getByUserAndSlug($userId, $compatibleSlug);
            if ($module) {
                break;
            }
        }

        if (!$module || strtoupper((string)$module->status) !== UserModulesRepository::STATUS_ACTIVE) {
            return false;
        }

        if (!empty($module->renewal_at)) {
            try {
                return new DateTimeImmutable((string)$module->renewal_at) >= new DateTimeImmutable('today');
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    public function getBaseModules(): array
    {
        $modules = $this->modulesRepository->getBaseModules();

        return $this->withFallbackModules($modules, ModulesRepository::BASE_SLUGS);
    }

    public function getAvailableAddons(?string $currency = null): array
    {
        $addons = $this->modulesRepository->getAddons();
        $pricing = new OphyraPricingService();
        $currency = $pricing->normalizePaymentCurrency($currency);

        $allowedSlugs = ProductProfileService::isOphytrack()
            ? ProductProfileService::allowedAddonSlugs()
            : ModulesRepository::ADDON_SLUGS;
        $addons = $this->withFallbackModules($addons, $allowedSlugs);
        $addons = array_values(array_filter($addons, static function (object $addon): bool {
            return ProductProfileService::allowsAddon((string)($addon->slug ?? ''));
        }));

        foreach ($addons as $addon) {
            $canonical = $this->modulesRepository->getCanonicalSlug((string)$addon->slug);
            $price = $pricing->getModulePrice($canonical, $currency);
            $addon->name = $this->officialAddonLabel((string)$addon->slug, (string)($addon->name ?? ''));
            $addon->description = $this->officialAddonDescription((string)$addon->slug, (string)($addon->description ?? ''));
            $addon->monthly_price = $price;
            $addon->monthly_price_currency = $currency;
            $addon->monthly_price_formatted = $price === null ? null : $pricing->format((float)$price, $currency);
        }

        return $addons;
    }

    public function getUserAddonModules(int $userId): array
    {
        return $this->userModulesRepository->getByUserId($userId);
    }

    public function canAccessRouteModule(int $userId, string $moduleSlug): bool
    {
        return $this->userHasModule($userId, $moduleSlug);
    }

    public function canAccessAnyRouteModule(int $userId, array $moduleSlugs): bool
    {
        foreach ($moduleSlugs as $moduleSlug) {
            if ($this->canAccessRouteModule($userId, (string)$moduleSlug)) {
                return true;
            }
        }

        return false;
    }

    public function getLockedModuleCatalog(int $userId, ?string $currency = null): array
    {
        $activeSlugs = $this->getActiveModuleSlugs($userId);
        $pricing = new OphyraPricingService();
        $currency = $pricing->normalizePaymentCurrency($currency);
        $addons = $this->getAvailableAddons($currency);
        $catalog = [];

        foreach ($addons as $addon) {
            $canonical = $this->modulesRepository->getCanonicalSlug((string)$addon->slug);
            $legacy = $this->modulesRepository->normalizeSlug((string)$addon->slug);
            $isActive = in_array($canonical, $activeSlugs, true) || in_array($legacy, $activeSlugs, true);

            $catalog[$legacy] = [
                'slug' => $legacy,
                'canonical_slug' => $canonical,
                'name' => (string)$addon->name,
                'description' => (string)($addon->description ?? ''),
                'monthly_price' => $addon->monthly_price,
                'monthly_price_currency' => $currency,
                'monthly_price_formatted' => $addon->monthly_price_formatted ?? null,
                'is_active' => $isActive,
                'unlocks' => $this->moduleUnlocks($legacy),
            ];
        }

        if (!ProductProfileService::isOphytrack()) {
        $catalog['custom_domain_seo_page_builder'] = [
            'slug' => 'custom_domain_seo_page_builder',
            'canonical_slug' => 'custom_domain_seo_page_builder',
            'name' => 'Custom Domain + SEO Page Builder',
            'description' => 'Public profile domain, SEO controls and page-builder tools.',
            'monthly_price' => null,
            'monthly_price_currency' => $currency,
            'monthly_price_formatted' => null,
            'is_active' => false,
            'is_coming_soon' => true,
            'unlocks' => ['Custom domain', 'SEO page builder', 'Public profile pages'],
        ];
        }

        return $catalog;
    }

    public function getBaseMonthlyPrice(): float
    {
        if ($this->freeStarterBaseEnabled()) {
            return 0.0;
        }

        return $this->modulesRepository->getBaseMonthlyPrice();
    }

    private function getBaseModuleSlugs(): array
    {
        return ModulesRepository::BASE_SLUGS;
    }

    private function moduleUnlocks(string $moduleSlug): array
    {
        return match ($this->modulesRepository->normalizeSlug($moduleSlug)) {
            'services' => ['CRM', 'Clients', 'Orders', 'Contracts', 'Team', 'Payroll basics', 'Team Chat', 'Service reports'],
            'store_delivery_tracking' => ['Store', 'Products', 'Store orders', 'Fulfillment', 'Delivery tracking', 'Customers', 'Store reports'],
            'inventory_storage' => ['Advanced storage', 'QR inventory', 'Containers', 'Items', 'Equipment tracking'],
            'ai_advisor' => ['AI Advisor', 'Operational recommendations', 'Business insights'],
            'tickets_rsvp' => ['Ticket sales', 'RSVP', 'Event registration', 'Guest lists'],
            'marketplace_connectors' => ['Mercado Libre', 'TikTok Business', 'Shopify', 'Manual sync', 'External status mapping', 'Public tracking links'],
            default => [],
        };
    }

    private function getUserById(int $userId): ?object
    {
        try {
            $this->db->query('SELECT id, level, is_active, membership_type, membership_due_date FROM users WHERE id = :id LIMIT 1');
            $this->db->bind(':id', $userId);

            $user = $this->db->fetchOne();
            return $user ?: null;
        } catch (PDOException $e) {
            error_log('ModuleAccessService::getUserById(): ' . $e->getMessage());
            return null;
        }
    }

    private function value(object|array $data, string $key, mixed $default = null): mixed
    {
        if (is_array($data)) {
            return $data[$key] ?? $default;
        }

        $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (method_exists($data, $getter)) {
            return $data->{$getter}();
        }

        return $data->{$key} ?? $default;
    }

    private function freeStarterBaseEnabled(): bool
    {
        $value = strtolower((string)($_ENV['OPHYRA_FREE_STARTER_BASE'] ?? 'true'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function fallbackModules(array $slugs): array
    {
        $labels = [
            'crm' => 'CRM',
            'clients' => 'Clients',
            'team' => 'Team',
            'basic_payroll' => 'Basic Payroll',
            'orders' => 'Orders',
            'contracts' => 'Contracts / E-signature',
            'basic_chat' => 'Basic Chat',
            'business_profile' => 'Business Profile',
            'inventory_storage' => 'Warehouse',
            'services' => 'Service Operations',
            'store_delivery_tracking' => 'Store + Logistics',
            'ai_advisor' => 'AI Advisor',
            'tickets_rsvp' => 'Ticket Sales + RSVP',
            'marketplace_connectors' => 'Marketplace Connectors',
        ];

        return array_map(static function (string $slug) use ($labels): object {
            return (object)[
                'id' => null,
                'slug' => $slug,
                'name' => $labels[$slug] ?? $slug,
                'description' => self::fallbackDescription($slug),
            ];
        }, $slugs);
    }

    private function withFallbackModules(array $modules, array $officialSlugs): array
    {
        $foundSlugs = array_map(static fn($module) => $module->slug ?? null, $modules);
        $missingSlugs = array_values(array_diff($officialSlugs, $foundSlugs));

        if (empty($missingSlugs)) {
            return $modules;
        }

        return array_merge($modules, $this->fallbackModules($missingSlugs));
    }

    private static function fallbackDescription(string $slug): string
    {
        return [
            'services' => 'Core module for clients, service orders, contracts, team execution, communications and reports.',
            'store_delivery_tracking' => 'Core module for products, online orders, fulfillment, delivery status, tracking and basic inventory.',
            'inventory_storage' => 'Optional advanced physical operations module for containers, QR labels, locations and equipment tracking.',
            'ai_advisor' => 'Optional AI module for ideas, summaries, recommendations, content support and operational guidance.',
            'tickets_rsvp' => 'Optional event module for ticket sales, registrations, RSVP and attendee workflows.',
            'marketplace_connectors' => 'Optional marketplace connector module for Mercado Libre, TikTok Business, Shopify, manual sync and public tracking links.',
        ][$slug] ?? 'Available as part of Ophyra Base.';
    }

    private function officialAddonLabel(string $slug, string $fallback): string
    {
        return [
            'services' => 'Service Operations',
            'store_delivery_tracking' => 'Store + Logistics',
            'inventory_storage' => 'Warehouse',
            'ai_advisor' => 'AI Advisor',
            'tickets_rsvp' => 'Ticket Sales + RSVP',
            'marketplace_connectors' => 'Marketplace Connectors',
        ][$slug] ?? ($fallback !== '' ? $fallback : $slug);
    }

    private function officialAddonDescription(string $slug, string $fallback): string
    {
        $description = self::fallbackDescription($slug);
        return $description !== 'Available as part of Ophyra Base.' ? $description : $fallback;
    }
}
