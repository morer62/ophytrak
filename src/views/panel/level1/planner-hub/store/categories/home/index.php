<?php

use App\Repositories\StoreCategoriesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repo = new StoreCategoriesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "categories" => $ownerId > 0 ? $repo->getActive($ownerId) : [],
        "operation_context" => $operationContext,
        "publicStoreUrl" => "store/home?owner={$ownerId}"
    ]);
});

$router->run();
