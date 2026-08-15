<?php

use App\Services\Level1CoreDashboardService;
use App\Services\LoginService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $dashboard = (new Level1CoreDashboardService())->build(LoginService::getSession());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'level1_dashboard' => $dashboard,
    ]);
});

$router->run();
