<?php

use App\Services\LoginService;
use App\Services\Level1CoreDashboardService;
use App\Services\Level1MembershipOperationsService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $dashboard = (new Level1CoreDashboardService())->build($user);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'level1_dashboard' => $dashboard,
        'membership_ops_alert' => (new Level1MembershipOperationsService())->buildHomeAlert(),
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
