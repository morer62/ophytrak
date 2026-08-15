<?php

use App\Repositories\InstitutionProfileRepository;
use App\Repositories\ModulesRepository;
use App\Services\AddonBillingService;
use App\Services\BusinessProfileBuilderService;
use App\Services\LoginService;
use App\Services\ModuleAccessService;
use App\Services\OphyraPricingService;
use App\Services\ProductProfileService;
use App\Services\UserCurrencyPreferenceService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal('login');
    }

    $ownerId = (int)($user->getOwner() ?: $user->getId());
    $profileBuilder = new BusinessProfileBuilderService();
    $moduleAccessService = new ModuleAccessService();
    $pricingService = new OphyraPricingService();
    $currencyPreferenceService = new UserCurrencyPreferenceService($pricingService);
    $preferredCurrency = $currencyPreferenceService->resolveCurrency($ownerId, $_GET['payment_currency'] ?? null);
    $addonBillingService = new AddonBillingService(null, null, null, $pricingService);

    $profileData = $profileBuilder->getBuilderData($ownerId);
    $activeModuleSlugs = $moduleAccessService->getActiveModuleSlugs($ownerId);
    $activeAddonSlugs = array_values(array_intersect($activeModuleSlugs, ModulesRepository::ADDON_SLUGS));
    $recommendedAddons = recommendOphyraAddons($profileData['profile'] ?? null);
    $profileComplete = (bool)($profileData['profileComplete'] ?? false);
    $businessTypeSet = !empty($profileData['profile']->business_nature ?? null)
        && !empty($profileData['profile']->business_operation_type ?? null);
    $hasPaymentMethod = false;

    $completedSteps = 1; // account created
    $completedSteps += $profileComplete ? 1 : 0;
    $completedSteps += $businessTypeSet ? 1 : 0;
    $completedSteps += !empty($activeAddonSlugs) ? 1 : 0;
    $progress = (int)round(($completedSteps / 4) * 100);

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'profileData' => $profileData,
        'baseModules' => $moduleAccessService->getBaseModules(),
        'availableAddons' => $moduleAccessService->getAvailableAddons($preferredCurrency),
        'moduleCatalog' => $moduleAccessService->getLockedModuleCatalog($ownerId, $preferredCurrency),
        'activeModuleSlugs' => $activeModuleSlugs,
        'addonQuotes' => $addonBillingService->getAddonQuotes($ownerId, $preferredCurrency),
        'preferredCurrency' => $preferredCurrency,
        'activeAddonSlugs' => $activeAddonSlugs,
        'recommendedAddons' => $recommendedAddons,
        'progress' => $progress,
        'profileComplete' => $profileComplete,
        'businessTypeSet' => $businessTypeSet,
        'hasPaymentMethod' => $hasPaymentMethod,
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal('login');
    }

    $profileRepo = new InstitutionProfileRepository();
    $profile = $profileRepo->getByOwner((int)$user->getOwner());

    if (!$profile) {
        MessageUtil::setMessage('Business profile was not found. Please complete your profile first.');
        LocationUtils::redirectInternal('panel/onboarding');
    }

    $profileRepo->upsertBasicBusinessProfile(
        (int)$user->getOwner(),
        (string)($profile->company_name ?? ''),
        trim((string)($_POST['business_nature'] ?? $profile->business_nature ?? '')) ?: null,
        trim((string)($_POST['business_operation_type'] ?? $profile->business_operation_type ?? '')) ?: null,
        (string)($profile->phone ?? ''),
        (string)($profile->email ?? '')
    );

    MessageUtil::setMessage('Business type saved. Your module recommendations were updated.');
    LocationUtils::redirectInternal('panel/onboarding');
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}

function recommendOphyraAddons(?object $profile): array
{
    if (ProductProfileService::isOphytrack()) {
        return ['store_delivery_tracking', 'inventory_storage', 'marketplace_connectors'];
    }
    $nature = strtolower((string)($profile->business_nature ?? ''));
    $operation = strtolower((string)($profile->business_operation_type ?? ''));
    $signals = $nature . ' ' . $operation;

    $recommendations = [];

    if (str_contains($signals, 'event') || str_contains($signals, 'venue') || str_contains($signals, 'hospitality')) {
        $recommendations = ['inventory_storage', 'tickets_rsvp', 'services'];
    } elseif (str_contains($signals, 'logistics') || str_contains($signals, 'delivery') || str_contains($signals, 'fulfillment') || str_contains($signals, 'store')) {
        $recommendations = ['services', 'inventory_storage'];
    } elseif (str_contains($signals, 'commerce') || str_contains($signals, 'local_store') || str_contains($signals, 'meal') || str_contains($signals, 'catering')) {
        $recommendations = ['services', 'inventory_storage'];
    } elseif (str_contains($signals, 'agency') || str_contains($signals, 'consulting') || str_contains($signals, 'studio')) {
        $recommendations = ['services', 'ai_advisor'];
    } else {
        $recommendations = ['services', 'inventory_storage'];
    }

    return array_values(array_unique(array_intersect($recommendations, ModulesRepository::ADDON_SLUGS)));
}
