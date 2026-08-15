<?php

namespace App;

use App\Core\FindViewTrait;
use App\Core\GetViewTrait;
use App\Core\IncludeViewTrait;
use App\Entity\User;
use App\Services\ConfigService;
use App\Services\LoginService;
use App\Services\ValidationSessionService;
use App\Utils\ErrorLogging;
use App\Utils\LocationUtils;
use App\Utils\PlatformDetector;
use App\Repositories\InstitutionProfileRepository;
use Closure;
use Exception;

ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class Kernel
{
    private static string $protectedFolderViews = "panel";
    private static string $publicFolderViews = "public";
    private static string $apiFolderViews = "api";

    private static string $protectedUrlPrefix = "panel";
    private static string $apiUrlPrefix = "api";

    private static string $homeIndex = "planner-hub";
    private static string $notFoundIndex = "404.php";
    private static string $errorIndex = "505.php";
    private static string $notFoundApi = "404.php";
    private static array $urlViews = [];

    private static array $VERIFYING_SESSION_MIDDLEWARES = [];


    use GetViewTrait, IncludeViewTrait, FindViewTrait;

    public static function getHomeIndex(): string
    {
        return self::$homeIndex;
    }

    private function handleAffiliateRoute($affiliateCode): void
    {
        try {
            $affiliateService = new \App\Services\AffiliateService();
            $utmSource = $_GET['utm_source'] ?? null;
            $utmMedium = $_GET['utm_medium'] ?? null;
            $utmCampaign = $_GET['utm_campaign'] ?? null;
            $success = $affiliateService->processAffiliateClick($affiliateCode, $utmSource, $utmMedium, $utmCampaign);
            if (!$success) {
                LocationUtils::redirectInternal("/");
                return;
            }
            $redirectTo = $_GET['redirect'] ?? '/';
            
            if (!isset($_SESSION['user'])) {
                $redirectTo = '/signup?from_affiliate=1';
            }
            
            LocationUtils::redirectInternal($redirectTo);
            
        } catch (\Exception $e) {
            LocationUtils::redirectInternal("/");
        }
    }


    private function getNotFoundView(): string
    {
        return LocationUtils::getRootLocation() . "/src/views/public/" . self::$notFoundIndex;
    }

    public function __construct() {

        $timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';
        date_default_timezone_set($timezone);
        ConfigService::init();
        ErrorLogging::init();

        self::$urlViews = $this->getUrlViews();

        self::$VERIFYING_SESSION_MIDDLEWARES = [
            fn () => ValidationSessionService::verifyPhoneConfirmation(self::$urlViews),
            fn () => ValidationSessionService::verifyInstitutionProfile(self::$urlViews),
            fn () => ValidationSessionService::verifyMembership(self::$urlViews),
            fn () => ValidationSessionService::verifyUserPermissions(self::$urlViews),
        ];
    }

    private function getUrlViews(): array
    {
        if (isset($_GET["url"])) {
            $url = rtrim($_GET["url"], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            return explode('/', strtolower($url));
        }

        return [];
    }

    public function run(): void
    {
        try {
            $urlViews = $this->getUrlViews();

            if (count($urlViews) > 1 && $urlViews[0] === self::$protectedUrlPrefix && $urlViews[1] === "tokenapi") {
                $token = $urlViews[2] ?? null;
                $internalRoute = array_slice($urlViews, 3);

                if ($token && count($internalRoute)) {
                    $user = LoginService::validateToken($token);

                    if ($user instanceof User) {
                        LoginService::setSession($user);
                        $_SESSION['IS_MOBILE_APP'] = true;
                        $redirectTo = implode("/", $internalRoute);
                        LocationUtils::redirectInternal($redirectTo);
                    }
                }

                LocationUtils::redirectInternal("login");
            }

            if (PlatformDetector::isMobileApp()) {
                if (!empty($urlViews) && $urlViews[0] === 'signup') {
                    LocationUtils::redirectInternal("login");
                    return;
                }
                if (empty($urlViews)) {
                    if (!LoginService::getSession()) {
                        LocationUtils::redirectInternal("login");
                        return;
                    }
                    LocationUtils::redirectInternal("panel/home");
                    return;
                }
            }

            if (empty($urlViews)) {
                $this->includeViewAndExit($this->getPublicView([self::$homeIndex]));
            }

            $publicPath = implode('/', $urlViews);
            if (\App\Services\ProductProfileService::isDisabledPublicRoute($publicPath)) {
                LocationUtils::redirectInternal('planner-hub');
                return;
            }

            if ($urlViews[0] == self::$protectedFolderViews) {
                $user = LoginService::getSession();
                if ($user instanceof User) {
                    if (PlatformDetector::isMobileApp()) {
                        $mobileOwnerId = (int)($_ENV['MOBILE_OWNER_ID'] ?? 0);
                        if ($mobileOwnerId > 0 && $user->getOwner() != $mobileOwnerId) {
                            LoginService::logout();
                            \App\Utils\MessageUtil::setMessage("Access restricted to this company.");
                            LocationUtils::redirectInternal("login");
                            return;
                        }
                        $path = implode("/", $urlViews);
                        $blockedMobile = ['venues', 'venue-categories', 'vendors', 'service', 'membership', 'subscriptions-manager'];
                        $firstSegment = $urlViews[1] ?? '';
                        if (in_array($firstSegment, $blockedMobile) || str_starts_with($path, 'panel/venues') || str_starts_with($path, 'panel/vendors') || str_starts_with($path, 'panel/membership') || str_starts_with($path, 'panel/subscriptions')) {
                            LocationUtils::redirectInternal("panel/planner-hub");
                            return;
                        }
                    }

                    LoginService::verifyMany([
                        fn () => LoginService::verifyPhoneConfirmation($urlViews),
                    ]);

                    $path = implode("/", $urlViews);

                    if (\App\Services\ProductProfileService::isDisabledRoute($path)) {
                        \App\Utils\MessageUtil::setMessage('This feature belongs to Ophyra Services and is not available in OPHYTRACK.');
                        LocationUtils::redirectInternal('panel/home');
                        return;
                    }

                    if (!PlatformDetector::isMobileApp()) {
                        if (str_contains($path, "planner-hub") && in_array($user->getLevel(), [2, 3])) {
                            $isBusinessProfileRoute = str_contains($path, "institution-profile");
                            $freeStarterBase = (new \App\Services\ModuleAccessService())->userHasActiveBaseMembership($user);
                            if (!$freeStarterBase && !$user->hasActiveMembership() && !$isBusinessProfileRoute) {
                                LocationUtils::redirectInternal("panel/membership/pay");
                            }

                            if (!str_contains($path, "institution-profile")) {
                                $institutionRepo = new InstitutionProfileRepository();
                                $institutionProfile = $institutionRepo->getByOwner($user->getOwner());

                                if (!$institutionProfile) {
                                    \App\Utils\MessageUtil::setMessage(\App\Services\TranslationService::trans('business_profile_builder.alerts.complete_before_continue'));
                                    LocationUtils::redirectInternal("panel/planner-hub/institution-profile");
                                }

                                if ((int)$user->getLevel() === 2 && !$institutionRepo->isProfileComplete($institutionProfile)) {
                                    $missing = implode(", ", \App\Services\BusinessProfileBuilderService::translatedMissingItems($institutionRepo->getMissingProfileItems($institutionProfile)));
                                    \App\Utils\MessageUtil::setMessage(\App\Services\TranslationService::trans('business_profile_builder.alerts.complete_workspace_missing', ['missing' => $missing]));
                                    LocationUtils::redirectInternal("panel/planner-hub/institution-profile");
                                }
                            }
                        }

                        if (
                            str_contains($path, "panel/planner-hub/store") &&
                            (int)$user->getLevel() === 2
                        ) {
                            \App\Services\ModuleGuardService::requireModule('store_delivery_tracking', 'panel/planner-hub/no-access');
                        }
                    }

                    if ((int)$user->getLevel() === 2) {
                        $path = implode("/", $urlViews);
                        $isAllowedFreeRoute = $path === 'panel/home'
                            || $path === 'panel/onboarding'
                            || str_starts_with($path, 'panel/billing')
                            || str_starts_with($path, 'panel/cards')
                            || str_starts_with($path, 'panel/settings')
                            || str_starts_with($path, 'panel/membership')
                            || str_starts_with($path, 'panel/notifications')
                            || str_starts_with($path, 'panel/afiliate-hub')
                            || str_starts_with($path, 'panel/planner-hub/no-access')
                            || str_starts_with($path, 'panel/planner-hub/institution-profile')
                            || str_starts_with($path, 'panel/planner-hub/settings');

                        if (!$isAllowedFreeRoute) {
                            if ($path === 'panel/planner-hub/management') {
                                \App\Services\ModuleGuardService::requireAnyModule(['services', 'store_delivery_tracking'], 'panel/planner-hub/no-access', 'operating_core');
                            } elseif (str_starts_with($path, 'panel/planner-hub/management/orders/contracts')) {
                                \App\Services\ModuleGuardService::requireModule('services', 'panel/planner-hub/no-access');
                            } elseif (str_starts_with($path, 'panel/planner-hub/management/orders')) {
                                \App\Services\ModuleGuardService::requireModule('services', 'panel/planner-hub/no-access');
                            } elseif (str_starts_with($path, 'panel/planner-hub/management/crm')
                                || str_starts_with($path, 'panel/planner-hub/management/users')
                                || str_starts_with($path, 'panel/planner-hub/management/payroll')
                                || str_starts_with($path, 'panel/planner-hub/management/payments')
                                || str_starts_with($path, 'panel/planner-hub/management/roles')
                                || str_starts_with($path, 'panel/planner-hub/team/chat')
                                || str_starts_with($path, 'panel/planner-hub/team/orders')
                                || str_starts_with($path, 'panel/planner-hub/team/payroll')
                            ) {
                                \App\Services\ModuleGuardService::requireAnyModule(['services', 'store_delivery_tracking'], 'panel/planner-hub/no-access', 'service_operations');
                            } elseif (str_starts_with($path, 'panel/planner-hub/management/storage')
                                || str_starts_with($path, 'panel/planner-hub/team/storage')
                            ) {
                                \App\Services\ModuleGuardService::requireModule('inventory_storage', 'panel/planner-hub/no-access');
                            } elseif (str_starts_with($path, 'panel/planner-hub/management/chatia')) {
                                \App\Services\ModuleGuardService::requireModule('ai_advisor', 'panel/planner-hub/no-access');
                            } elseif (str_starts_with($path, 'panel/event-invitations')
                                || str_starts_with($path, 'panel/events')
                            ) {
                                \App\Services\ModuleGuardService::requireModule('tickets_rsvp', 'panel/planner-hub/no-access');
                            } elseif (str_starts_with($path, 'panel/planner-hub/marketplace-connectors')) {
                                \App\Services\ModuleGuardService::requireModule('marketplace_connectors', 'panel/planner-hub/no-access');
                            } elseif ($path === 'panel/planner-hub' || str_starts_with($path, 'panel/planner-hub/management')) {
                                \App\Services\ModuleGuardService::requireAnyModule(['services', 'store_delivery_tracking', 'inventory_storage', 'ai_advisor', 'tickets_rsvp', 'marketplace_connectors'], 'panel/planner-hub/no-access', 'paid_modules');
                            }
                        }
                    }

                    if (
                        (int)$user->getLevel() !== 1
                        && str_starts_with(implode("/", $urlViews), 'panel/planner-hub/management/commissions')
                    ) {
                        \App\Utils\MessageUtil::setMessage("Affiliate administration is restricted to platform admins.");
                        LocationUtils::redirectInternal("panel/afiliate-hub");
                    }

                    if ($user->getLevel() === 4) {
                        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
                        if (!$currentInstitutionId && in_array('planner-hub', $urlViews, true)) {
                            $teamContext = (new \App\Services\UserWorkspaceContextService())->getTeamContext($user);
                            $currentInstitutionId = $teamContext['selectedInstitutionId'] ?? null;
                        }

                        if ($currentInstitutionId) {
                            LoginService::reloadUserPermissions((int)$currentInstitutionId);
                            $user = LoginService::getSession();
                        }
                    }

                    if (
                        $user->getLevel() === 4 &&
                        isset($urlViews[1], $urlViews[2], $urlViews[3]) &&
                        $urlViews[1] === 'planner-hub' &&
                        $urlViews[2] === 'management'
                    ) {
                        $module = $urlViews[3];
                        if (!$user->hasPermissionForModule($module)) {
                            \App\Utils\MessageUtil::setMessage("This area requires admin approval for the selected company.");
                            LocationUtils::redirectInternal("panel/planner-hub/no-access?module={$module}");
                        }
                    }

                    if (count($urlViews) === 1) {
                        LocationUtils::redirectInternal("panel/home");
                    }

                    $this->includeAdminViewAndExit($this->getPrivateView($urlViews, $user));
                } else {
                    LocationUtils::redirectInternal("login");
                }
            }

            if ($urlViews[0] == self::$apiUrlPrefix) {
                $this->includeViewAndExit($this->getApiViews($urlViews));
            }

            if (count($urlViews) >= 2 && $urlViews[0] === 'r') {
                $this->handleAffiliateRoute($urlViews[1]);
            }

            $this->includeViewAndExit($this->getPublicView($urlViews));
        } catch (Exception $exception) {
            if ($_ENV["APP_ENV"] == "debug") {
                throw $exception;
            }

            $this->includeViewAndExit($this->getPublicView([self::$errorIndex]));
        }
    }
}
