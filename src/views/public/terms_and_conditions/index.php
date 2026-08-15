<?php

use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\OphyraSeoService;

$router = new Router();

$router->get(function () {
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $seoService = new OphyraSeoService();
    $seo = $seoService->seoForRoute('terms_and_conditions', $appUrl);

    return TemplateResponse::render(__DIR__."/index.twig", [
        'seo' => $seo,
        'schemaJsonList' => $seoService->schemaJsonListForRoute('terms_and_conditions', $appUrl, $seo),
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
