<?php

use App\Repositories\StoreProductsRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repo = new StoreProductsRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid product.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home");
    }

    $product = $ownerId > 0 ? $repo->getFullProductDetails($id, $ownerId) : null;

    if (!$product) {
        MessageUtil::setMessage("Product not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "product" => $product,
        "operation_context" => $operationContext,
        "publicStoreUrl" => "store/home?owner={$ownerId}"
    ]);
});

$router->run();
