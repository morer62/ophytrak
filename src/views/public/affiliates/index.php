<?php

use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\OphyraSeoService;

$router = new Router();

$router->get(function () {
    header('Cache-Control: private, max-age=300, stale-while-revalidate=86400');
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $seoService = new OphyraSeoService();
    $seo = $seoService->seoForRoute('affiliates', $appUrl);

    return TemplateResponse::render(__DIR__."/index.twig", [
        'seo' => $seo,
        'schemaJsonList' => $seoService->schemaJsonListForRoute('affiliates', $appUrl, $seo),
        'marketing_lite_assets' => true,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
