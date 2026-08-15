<?php

use App\Repositories\StoreProductsRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repo = new StoreProductsRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "products" => $ownerId > 0 ? $repo->getScopedByOwner($ownerId) : [],
        "operation_context" => $operationContext,
        "publicStoreUrl" => "store/home?owner={$ownerId}"
    ]);
});

$router->run();
