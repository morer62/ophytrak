<?php

namespace App\Utils;

use App\Entity\User;
use App\Services\LoginService;
use App\Services\OphyraSeoService;
use App\Services\TranslationService;
use App\Services\ProductProfileService;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Exception;

class TemplateResponse
{

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function render(string $templateLocation, array $data = []): string {

        $folders = explode(DIRECTORY_SEPARATOR."views".DIRECTORY_SEPARATOR, $templateLocation, 2);

        $templateParent = $folders[0] . DIRECTORY_SEPARATOR."views";
        $templateChild = $folders[1];

        // Specify the directory where your templates are located
        $loader = new FilesystemLoader($templateParent);

        // Initialize Twig environment
        $twig = new Environment($loader);

        $twig->addFunction(new TwigFunction('asset_for', [LocationUtils::class, 'assetFor']));
        $twig->addFunction(new TwigFunction('path', [LocationUtils::class, 'assetFor']));
        $twig->addFunction(new TwigFunction('trans', [TranslationService::class, 'trans']));
        $twig->addFunction(new TwigFunction('t', [TranslationService::class, 'trans']));
        $twig->addFunction(new TwigFunction('getLocaleName', [TranslationService::class, 'getLocaleName']));
        $twig->addFilter(new TwigFilter('trans', [TranslationService::class, 'trans']));
        $twig->addFunction(new TwigFunction('get_csrf', [CSRF::class, 'generateCSRF']));
        $twig->addFunction(new TwigFunction('contain_permission', [TwigUtils::class, 'hasPerm']));
        $twig->addFilter(new TwigFilter('truncate', [TwigUtils::class, 'truncate']));
        $twig->addFilter(new TwigFilter('html_to_text', [TwigUtils::class, 'htmlToText']));
        $twig->addFilter(new TwigFilter('json_decode', static function ($value) {
            if (is_array($value) || is_object($value)) {
                return $value;
            }
            $decoded = json_decode((string)$value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }));
        $twig->addFunction(new TwigFunction('getTreeRoutes', function () use ($templateChild) {
            return TwigUtils::getTreeRoutes($templateChild);
        }));
        // Incluir notificaciones globalmente para todas las pÃƒÆ’Ã‚Â¡ginas
        $notifications = [];
        $notifications_count = 0;
        $chatUnreadThreads = [];
        $chatUnreadCount = 0;
        $chatUnreadBaseRoute = 'panel/planner-hub/team/chat';
        try {
            // Solo cargar notificaciones si hay una sesiÃƒÆ’Ã‚Â³n activa
            $session = LoginService::getSession();
            if ($session && $session->getId()) {
                // Solo cargar notificaciones si no se han cargado antes
                if (!isset($GLOBALS['notifications_loaded'])) {
                    $GLOBALS['notifications_loaded'] = true;
                    
                    $notificationsRepo = new \App\Repositories\NotificationsRepository();
                    $userId = $session->getId();

                    // Obtener TODAS las notificaciones ordenadas por tiempo (mÃƒÆ’Ã‚Â¡s recientes primero)
                    $allNotifications = $notificationsRepo->getByUser($userId);
                    $notifications_count = $notificationsRepo->getUnreadCount($userId);

                    // Filtrar solo las no leÃƒÆ’Ã‚Â­das y tomar las primeras 5
                    $unreadNotifications = array_filter($allNotifications, function($notification) {
                        return $notification->leido == 0;
                    });

                    $notifications = array_slice($unreadNotifications, 0, 5);

                    // Establecer las variables globales para Twig
                    $GLOBALS['notifications'] = $notifications;
                    $GLOBALS['notifications_count'] = $notifications_count;
                } else {
                    // Usar las variables globales ya cargadas
                    $notifications = $GLOBALS['notifications'] ?? [];
                    $notifications_count = $GLOBALS['notifications_count'] ?? 0;
                }
            }
        } catch (Exception $e) {
            error_log("ERROR loading notifications in TemplateResponse: " . $e->getMessage());
            $notifications = [];
            $notifications_count = 0;
        }


        try {
            $session = LoginService::getSession();
            if ($session && $session->getId()) {
                $chatUnreadThreads = (new \App\Repositories\ChatThreadRepository())->getUnreadSummariesForUser((int)$session->getId());
                $chatUnreadCount = count($chatUnreadThreads);
                $chatUnreadBaseRoute = ((int)$session->getLevel() === 5) ? "panel/chat" : "panel/planner-hub/team/chat";
            }
        } catch (Exception $e) {
            error_log("ERROR loading chat unread summaries in TemplateResponse: " . $e->getMessage());
            $chatUnreadThreads = [];
            $chatUnreadCount = 0;
        }

        // Detectar y establecer idioma
        $currentLocale = TranslationService::detectLocale();

        $moduleBaseActive = false;
        $activeModuleSlugs = [];
        $activeAddonSlugs = [];

        try {
            $session = LoginService::getSession();
            if ($session && (int)$session->getLevel() === 2) {
                $moduleAccessService = new \App\Services\ModuleAccessService();
                $moduleBaseActive = $moduleAccessService->userHasActiveBaseMembership($session);
                $ownerId = (int)($session->getOwner() ?: $session->getId());
                $activeModuleSlugs = $moduleAccessService->getActiveModuleSlugs($ownerId);
                $activeAddonSlugs = array_values(array_intersect(
                    $activeModuleSlugs,
                    \App\Repositories\ModulesRepository::ADDON_SLUGS
                ));
            }
        } catch (Exception $e) {
            error_log("ERROR loading module access in TemplateResponse: " . $e->getMessage());
            $moduleBaseActive = false;
            $activeModuleSlugs = [];
            $activeAddonSlugs = [];
        }
        
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
        $seoService = new OphyraSeoService();
        $currentRoute = trim((string)($_GET['url'] ?? ''), '/');
        $seo = $data['seo'] ?? $seoService->seoForRoute($currentRoute, $appUrl);
        $schemaJsonList = $data['schemaJsonList'] ?? $seoService->schemaJsonListForRoute(
            $currentRoute,
            $appUrl,
            $seo,
            $data['schemaJson'] ?? null
        );

        if (ProductProfileService::isOphytrack()) {
            $brandProfile = ProductProfileService::profile();
            $routeName = stripos((string)($seo['title'] ?? ''), 'OPHYTRACK') !== false
                ? (string)$seo['title']
                : ($currentRoute === 'signup' ? 'Create your OPHYTRACK account' : ($currentRoute === 'login' ? 'Sign in to OPHYTRACK' : 'OPHYTRACK logistics platform'));
            $description = TranslationService::trans('ophytrack_public.meta_description');
            $canonical = $appUrl . ($currentRoute === '' ? '/' : '/' . $currentRoute);
            $seo = array_merge($seo, [
                'title' => $routeName,
                'description' => $description,
                'og_title' => $routeName,
                'og_description' => $description,
                'twitter_title' => $routeName,
                'twitter_description' => $description,
                'author' => 'OPHYTRACK',
                'canonical' => $canonical,
            ]);
            $schemaJsonList = [[
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => 'OPHYTRACK',
                'applicationCategory' => 'BusinessApplication',
                'applicationSubCategory' => 'Logistics and delivery management',
                'operatingSystem' => 'Web',
                'url' => $appUrl . '/',
                'description' => $description,
                'featureList' => ['Store orders', 'Package custody', 'Warehouse operations', 'Carrier teams', 'Delivery tracking', 'Proof of delivery', 'Marketplace synchronization'],
                'image' => $appUrl . '/' . $brandProfile['hero_logo'],
            ], [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $routeName,
                'url' => $canonical,
                'description' => $description,
                'isPartOf' => ['@type' => 'WebSite', 'name' => 'OPHYTRACK', 'url' => $appUrl . '/'],
            ]];
        }

        $viewData = [
            "user" => LoginService::getSession(),
            "alertMessage" => MessageUtil::getMessage(),
            "env" => $_ENV,
            "app_url" => $appUrl,
            "seo" => $seo,
            "schemaJsonList" => $schemaJsonList,
            "current_location" => TwigUtils::getCurrentLocation($templateChild),
            "notifications" => $notifications,
            "notifications_count" => $notifications_count,
            "chat_unread_threads" => $chatUnreadThreads,
            "chat_unread_count" => $chatUnreadCount,
            "chat_unread_base_route" => $chatUnreadBaseRoute,
            "isMobileApp" => PlatformDetector::isMobileApp(),
            "isWeb" => PlatformDetector::isWeb(),
            "locale" => $currentLocale,
            "supported_locales" => TranslationService::getSupportedLocales(),
            "TranslationService" => TranslationService::class,
            "module_base_active" => $moduleBaseActive,
            "active_module_slugs" => $activeModuleSlugs,
            "active_addon_slugs" => $activeAddonSlugs,
            "product_mode" => ProductProfileService::mode(),
            "brand" => ProductProfileService::profile(),
            ...$data
        ];
        $viewData["seo"] = $seo;
        $viewData["schemaJsonList"] = $schemaJsonList;

        return $twig->render($templateChild, $viewData);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public static function renderAndDisplay(string $templateLocation, array $data = []): void {
        echo self::render($templateLocation, $data);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function renderInTemplates(string $templateName, array $data = []): string {
        $templatesFolder  = __DIR__. DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "views";

        $loader = new FilesystemLoader($templatesFolder);

        // Initialize Twig environment
        $twig = new Environment($loader);

        $twig->addFunction(new TwigFunction('asset_for', [LocationUtils::class, 'assetFor']));
        $twig->addFunction(new TwigFunction('path', [LocationUtils::class, 'assetFor']));
        $twig->addFunction(new TwigFunction('trans', [TranslationService::class, 'trans']));
        $twig->addFunction(new TwigFunction('t', [TranslationService::class, 'trans']));
        $twig->addFunction(new TwigFunction('getLocaleName', [TranslationService::class, 'getLocaleName']));
        $twig->addFilter(new TwigFilter('trans', [TranslationService::class, 'trans']));

        // Incluir notificaciones globalmente para todas las pÃƒÆ’Ã‚Â¡ginas
        $notifications = [];
        $notifications_count = 0;
        
        try {
            // Solo cargar notificaciones si hay una sesiÃƒÆ’Ã‚Â³n activa
            $session = LoginService::getSession();
            if ($session && $session->getId()) {
                // Solo cargar notificaciones si no se han cargado antes
                if (!isset($GLOBALS['notifications_loaded'])) {
                    $GLOBALS['notifications_loaded'] = true;
                    
                    $notificationsRepo = new \App\Repositories\NotificationsRepository();
                    $userId = $session->getId();

                    // Obtener TODAS las notificaciones ordenadas por tiempo (mÃƒÆ’Ã‚Â¡s recientes primero)
                    $allNotifications = $notificationsRepo->getByUser($userId);
                    $notifications_count = $notificationsRepo->getUnreadCount($userId);

                    // Filtrar solo las no leÃƒÆ’Ã‚Â­das y tomar las primeras 5
                    $unreadNotifications = array_filter($allNotifications, function($notification) {
                        return $notification->leido == 0;
                    });

                    $notifications = array_slice($unreadNotifications, 0, 5);

                    // Establecer las variables globales para Twig
                    $GLOBALS['notifications'] = $notifications;
                    $GLOBALS['notifications_count'] = $notifications_count;
                } else {
                    // Usar las variables globales ya cargadas
                    $notifications = $GLOBALS['notifications'] ?? [];
                    $notifications_count = $GLOBALS['notifications_count'] ?? 0;
                }
            }
        } catch (Exception $e) {
            error_log("ERROR loading notifications in TemplateResponse renderInTemplates: " . $e->getMessage());
            $notifications = [];
            $notifications_count = 0;
        }


        try {
            $session = LoginService::getSession();
            if ($session && $session->getId()) {
                $chatUnreadThreads = (new \App\Repositories\ChatThreadRepository())->getUnreadSummariesForUser((int)$session->getId());
                $chatUnreadCount = count($chatUnreadThreads);
                $chatUnreadBaseRoute = ((int)$session->getLevel() === 5) ? "panel/chat" : "panel/planner-hub/team/chat";
            }
        } catch (Exception $e) {
            error_log("ERROR loading chat unread summaries in TemplateResponse: " . $e->getMessage());
            $chatUnreadThreads = [];
            $chatUnreadCount = 0;
        }

        $currentLocale = TranslationService::detectLocale();

        return $twig->render("templates".DIRECTORY_SEPARATOR.$templateName, [
            "user" => LoginService::getSession(),
            "alertMessage" => MessageUtil::getMessage(),
            "env" => $_ENV,
            "notifications" => $notifications,
            "notifications_count" => $notifications_count,
            "chat_unread_threads" => $chatUnreadThreads,
            "chat_unread_count" => $chatUnreadCount,
            "chat_unread_base_route" => $chatUnreadBaseRoute,
            "isMobileApp" => PlatformDetector::isMobileApp(),
            "isWeb" => PlatformDetector::isWeb(),
            "locale" => $currentLocale,
            "supported_locales" => TranslationService::getSupportedLocales(),
            "TranslationService" => TranslationService::class,
            "product_mode" => ProductProfileService::mode(),
            "brand" => ProductProfileService::profile(),
            ...$data
        ]);
    }

}
