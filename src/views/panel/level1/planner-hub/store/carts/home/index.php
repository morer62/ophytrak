<?php

use App\Repositories\StoreCartsRepository;
use App\Repositories\StoreCartItemsRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $cartsRepo = new StoreCartsRepository();
    $itemsRepo = new StoreCartItemsRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);

    $carts = $ownerId > 0 ? $cartsRepo->getAbandonedByOwner($ownerId, 200) : [];

    foreach ($carts as &$cart) {
        $cart->items = $itemsRepo->getDetailedByCart((int)$cart->id);
    }
    unset($cart);

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'carts' => $carts,
        'operation_context' => $operationContext,
    ]);
});

$router->run();
