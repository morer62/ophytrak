<?php

use App\Services\Level1MembershipOperationsService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();
$router->get(function () {
    $service = new Level1MembershipOperationsService();
    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'summary' => $service->getRenewalSummary(),
        'reports' => $service->getReports(),
        'alert' => $service->buildHomeAlert(),
    ]);
});
try { $router->run(); } catch (Exception $e) { echo $e->getMessage(); }
