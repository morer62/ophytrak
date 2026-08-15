<?php

use App\Services\OphyraLandingPageService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $landing = new OphyraLandingPageService();
    $page = $landing->getPage('industries', 'logistics-delivery', $appUrl);

    return TemplateResponse::render(dirname(__DIR__, 2) . '/ophyra-landing/index.twig', [
        'page' => $page,
        'seo' => $page['seo'],
        'schemaJson' => $page['schemaJson'],
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
