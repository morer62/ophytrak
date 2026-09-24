<?php

use App\Services\LoginService;
use App\Services\AddonBillingService;
use App\Services\BusinessEvaluatorService;
use App\Services\BusinessProfileBuilderService;
use App\Services\BusinessOperationsReportService;
use App\Services\ModuleAccessService;
use App\Services\OphyraPricingService;
use App\Services\UserCurrencyPreferenceService;
use App\Utils\BillingDateUtils;
use App\Repositories\ModulesRepository;
use App\Repositories\UserRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\UserInstitutionsRepository;
use App\Repositories\CarrierPackageRepository;
use App\Repositories\CarrierRelationshipRepository;
use App\Repositories\StoreUserRolesRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->post(function () {
    $user = LoginService::getSession();
    $ownerId = (int)($user->getOwner() ?: $user->getId());
    $currencyService = new UserCurrencyPreferenceService();
    $action = trim((string)($_POST['action'] ?? ''));
    $newLevel = (int)($_POST['level'] ?? 0);
    if ($action === 'carrier_custody_decision') {
        [$ok,$message]=(new CarrierPackageRepository())->decideRequest((int)($_POST['request_id']??0),$ownerId,(int)$user->getId(),strtoupper((string)($_POST['decision']??''))==='APPROVE');MessageUtil::setMessage($message);LocationUtils::redirectInternal('panel/home');return;
    }

    if ($newLevel === 4) {
        $institutionRepo = new InstitutionProfileRepository();
        $userInstitutionsRepo = new UserInstitutionsRepository();
        $businessProfile = $institutionRepo->getByOwner($ownerId);

        if ($businessProfile && !empty($businessProfile->id)) {
            $institutionId = (int) $businessProfile->id;
            if (!$userInstitutionsRepo->exists((int) $user->getId(), $institutionId)) {
                $userInstitutionsRepo->addUserToInstitution((int) $user->getId(), $institutionId);
            }

            $_SESSION['current_institution_id'] = $institutionId;
            $_SESSION['current_institution_role'] = 'owner';
        }

        (new UserRepository())->updateData((int)$user->getId(), [
            'level' => 4,
            'id_owner' => $ownerId,
        ]);
        LoginService::reloadUserPermissions(isset($institutionId) ? $institutionId : null);
        LocationUtils::redirectInternal('panel/home');
        return;
    }

    if ($newLevel === 5) {
        (new UserRepository())->updateData((int)$user->getId(), [
            'level' => $newLevel,
        ]);
        LoginService::reloadUserPermissions();
        LocationUtils::redirectInternal('panel/home');
        return;
    }

    if ($action === 'update_preferred_currency') {
        $currency = $currencyService->updatePreferredCurrency($ownerId, $_POST['preferred_currency'] ?? null);
        MessageUtil::setMessage("Billing currency updated to {$currency}.");
    }

    LocationUtils::redirectInternal('panel/home');
});

$router->get(function () {
    $user = LoginService::getSession();

    $moduleAccessService = new ModuleAccessService();
    $addonBillingService = new AddonBillingService();
    $pricingService = new OphyraPricingService();
    $currencyService = new UserCurrencyPreferenceService($pricingService);
    $businessEvaluatorService = new BusinessEvaluatorService();
    $businessProfileBuilderService = new BusinessProfileBuilderService();
    $operationsReportService = new BusinessOperationsReportService();
    $ownerId = (int)($user->getOwner() ?: $user->getId());
    $carrierRepository = new CarrierPackageRepository();
    $isCarrierOrganization = $carrierRepository->isCarrier($ownerId);
    $preferredCurrency = $currencyService->resolveCurrency($ownerId, $_GET['payment_currency'] ?? null);
    $requiresCurrencySetup = $currencyService->requiresSetup($ownerId);
    $reportPreset = trim((string)($_GET['report_preset'] ?? 'this_month'));
    $reportFrom = trim((string)($_GET['report_from'] ?? ''));
    $reportTo = trim((string)($_GET['report_to'] ?? ''));
    $baseMembershipActive = $moduleAccessService->userHasActiveBaseMembership($user);
    $userAddonModules = $moduleAccessService->getUserAddonModules($ownerId);
    $activeModuleSlugs = $moduleAccessService->getActiveModuleSlugs($ownerId);
    $activeAddonSlugs = array_values(array_intersect($activeModuleSlugs, ModulesRepository::ADDON_SLUGS));
    $workspaceRenewals = [
        'services' => null,
        'store_logistics' => null,
    ];
    foreach ($userAddonModules as $module) {
        $moduleSlug = (string)($module->slug ?? $module->module_slug ?? '');
        $renewalAt = $module->renewal_at ?? null;
        if (!$renewalAt || strtoupper((string)($module->status ?? '')) !== 'ACTIVE') {
            continue;
        }
        if (in_array($moduleSlug, ['services', 'service_operations'], true)) {
            $workspaceRenewals['services'] = [
                'raw' => $renewalAt,
                'label' => BillingDateUtils::format((string) $renewalAt),
            ];
        }
        if (in_array($moduleSlug, ['store_delivery_tracking', 'store_logistics'], true)) {
            $workspaceRenewals['store_logistics'] = [
                'raw' => $renewalAt,
                'label' => BillingDateUtils::format((string) $renewalAt),
            ];
        }
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "isFreeTrial" => false,
        "trialDaysRemaining" => 0,
        "trialExpirationDate" => null,
        "baseMembershipActive" => $baseMembershipActive,
        "baseModules" => $moduleAccessService->getBaseModules(),
        "availableAddons" => $moduleAccessService->getAvailableAddons($preferredCurrency),
        "moduleCatalog" => $moduleAccessService->getLockedModuleCatalog($ownerId, $preferredCurrency),
        "addonQuotes" => $addonBillingService->getAddonQuotes($ownerId, $preferredCurrency),
        "userAddonModules" => $userAddonModules,
        "workspaceRenewals" => $workspaceRenewals,
        "activeModuleSlugs" => $activeModuleSlugs,
        "activeAddonSlugs" => $activeAddonSlugs,
        "activeAddonCount" => count($activeAddonSlugs),
        "ophyraBasePrice" => $moduleAccessService->getBaseMonthlyPrice(),
        "preferredCurrency" => $preferredCurrency,
        "supportedBillingCurrencies" => $currencyService->supportedCurrencies(),
        "requiresCurrencySetup" => $requiresCurrencySetup,
        "billingCycle" => $_ENV['OPHYRA_BILLING_CYCLE'] ?? 'monthly',
        "businessInsights" => $businessEvaluatorService->getDashboardInsights($user->getId()),
        "businessProfile" => $businessProfileBuilderService->getBuilderData($user->getOwner()),
        "operationsReport" => $operationsReportService->build($user->getId(), $reportPreset, $reportFrom ?: null, $reportTo ?: null),
        "carrierCustodyRequests" => (new CarrierPackageRepository())->pendingForSeller($ownerId),
        "isCarrierOrganization" => $isCarrierOrganization,
        "carrierContactEmail" => $user->getEmail(),
        "carrierRelationships" => $isCarrierOrganization ? (new CarrierRelationshipRepository())->getSellersForCarrier($ownerId) : [],
        "carrierPackages" => $isCarrierOrganization ? $carrierRepository->getForCarrier($ownerId) : [],
        "carrierTeam" => $isCarrierOrganization ? (new StoreUserRolesRepository())->getUsersByOwner($ownerId) : [],
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}

