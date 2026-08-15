<?php

use App\Services\LoginService;
use App\Services\ModuleAccessService;
use App\Services\OphyraPricingService;
use App\Services\UserCurrencyPreferenceService;
use App\Repositories\InstitutionProfileRepository;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $ownerId = (int)($user->getOwner() ?: $user->getId());
    $moduleAccess = new ModuleAccessService();
    $pricing = new OphyraPricingService();
    $currencyPreference = new UserCurrencyPreferenceService($pricing);
    $preferredCurrency = $currencyPreference->resolveCurrency($ownerId, $_GET['payment_currency'] ?? null);
    $profileRepo = new InstitutionProfileRepository();
    $profile = $profileRepo->getByOwner($ownerId);
    $activeModuleSlugs = $moduleAccess->getActiveModuleSlugs($ownerId);
    $lockedCatalog = $moduleAccess->getLockedModuleCatalog($ownerId, $preferredCurrency);

    $modules = [
        [
            'slug' => 'business_profile',
            'title_key' => 'planner_hub_module_center.business_profile_title',
            'summary_key' => 'planner_hub_module_center.business_profile_summary',
            'icon' => 'briefcase',
            'route' => 'panel/planner-hub/institution-profile',
            'status' => 'ACTIVE',
            'is_base' => true,
            'features' => ['Public profile', 'Business details', 'Contact info'],
        ],
        [
            'slug' => 'services',
            'title_key' => 'planner_hub_module_center.service_operations_title',
            'summary_key' => 'planner_hub_module_center.service_operations_summary',
            'icon' => 'calendar',
            'route' => 'panel/planner-hub/management',
            'status' => in_array('services', $activeModuleSlugs, true) || in_array('service_operations', $activeModuleSlugs, true) ? 'ACTIVE' : 'LOCKED',
            'features' => ['CRM', 'Orders', 'Contracts', 'Team'],
        ],
        [
            'slug' => 'store_delivery_tracking',
            'title_key' => 'planner_hub_module_center.store_logistics_title',
            'summary_key' => 'planner_hub_module_center.store_logistics_summary',
            'icon' => 'shopping-bag',
            'route' => 'panel/planner-hub/store/products/home',
            'status' => 'COMING_SOON',
            'features' => ['Products', 'Store orders', 'Delivery', 'Customers'],
        ],
        [
            'slug' => 'inventory_storage',
            'title_key' => 'planner_hub_module_center.inventory_title',
            'summary_key' => 'planner_hub_module_center.inventory_summary',
            'icon' => 'archive',
            'route' => 'panel/planner-hub/management/storage',
            'status' => in_array('inventory_storage', $activeModuleSlugs, true) || in_array('advanced_storage_qr_inventory', $activeModuleSlugs, true) ? 'ACTIVE' : 'LOCKED',
            'features' => ['QR labels', 'Containers', 'Locations'],
        ],
        [
            'slug' => 'ai_advisor',
            'title_key' => 'planner_hub_module_center.ai_advisor_title',
            'summary_key' => 'planner_hub_module_center.ai_advisor_summary',
            'icon' => 'cpu',
            'route' => 'panel/planner-hub/management/chatia',
            'status' => in_array('ai_advisor', $activeModuleSlugs, true) ? 'ACTIVE' : 'LOCKED',
            'features' => ['Insights', 'Summaries', 'Actions'],
        ],
        [
            'slug' => 'tickets_rsvp',
            'title_key' => 'planner_hub_module_center.tickets_title',
            'summary_key' => 'planner_hub_module_center.tickets_summary',
            'icon' => 'ticket',
            'route' => 'panel/event-invitations',
            'status' => in_array('tickets_rsvp', $activeModuleSlugs, true) || in_array('ticket_sales_rsvp', $activeModuleSlugs, true) ? 'ACTIVE' : 'LOCKED',
            'features' => ['Tickets', 'RSVP', 'Guests'],
        ],
        [
            'slug' => 'marketplace_connectors',
            'title_key' => 'planner_hub_module_center.marketplace_title',
            'summary_key' => 'planner_hub_module_center.marketplace_summary',
            'icon' => 'share-2',
            'route' => 'panel/planner-hub/marketplace-connectors',
            'status' => in_array('marketplace_connectors', $activeModuleSlugs, true) ? 'ACTIVE' : 'LOCKED',
            'features' => ['Tokens', 'Manual sync', 'Mappings'],
        ],
    ];
    $activeModules = array_values(array_filter($modules, static fn(array $module): bool => $module['status'] === 'ACTIVE'));
    $lockedModules = array_values(array_filter($modules, static fn(array $module): bool => $module['status'] !== 'ACTIVE'));

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "profile" => $profile,
        "activeModuleSlugs" => $activeModuleSlugs,
        "lockedCatalog" => $lockedCatalog,
        "preferredCurrency" => $preferredCurrency,
        "modules" => $modules,
        "activeModules" => $activeModules,
        "lockedModules" => $lockedModules,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
