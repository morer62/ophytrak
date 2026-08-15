<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreCategoriesRepository;
use App\Services\LoginService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $repo = new StoreCategoriesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "categories" => $repo->getActive($ownerId),
        "publicStoreUrl" => "store/home?owner={$ownerId}"
    ]);
});

$router->run();
