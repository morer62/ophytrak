<?php

use App\Services\ModuleGuardService;


use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\LoginService;

ModuleGuardService::requireModule('ai_advisor');


$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    if (!$user) {
        \App\Utils\LocationUtils::redirectInternal("login");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
