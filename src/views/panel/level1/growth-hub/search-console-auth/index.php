<?php

use App\Services\LoginService;
use App\Services\SearchConsoleImportService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    if (!$user || (int)$user->getLevel() !== 1) {
        MessageUtil::setMessage('Only level 1 admins can connect Search Console.');
        LocationUtils::redirectInternal('panel/home');
    }

    $siteKey = trim((string)($_GET['site_key'] ?? ($_ENV['SEO_AGENT_DEFAULT_SITE_KEY'] ?? 'vnvevents')));

    try {
        $service = new SearchConsoleImportService();
        LocationUtils::redirectTo($service->authorizationUrl($siteKey));
    } catch (Throwable $e) {
        error_log('Search Console OAuth start failed: ' . $e->getMessage());
        MessageUtil::setMessage('Search Console connection failed: ' . $e->getMessage(), 'Connection failed', 'error');
        LocationUtils::redirectInternal('panel/growth-hub?site_key=' . urlencode($siteKey));
    }
});

$router->run();
