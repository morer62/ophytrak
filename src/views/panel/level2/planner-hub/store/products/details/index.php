<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreProductsRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $repo = new StoreProductsRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid product.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home");
    }

    $product = $repo->getFullProductDetails($id, $ownerId);

    if (!$product) {
        MessageUtil::setMessage("Product not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "product" => $product,
        "publicStoreUrl" => "store/home?owner={$ownerId}"
    ]);
});

$router->run();
