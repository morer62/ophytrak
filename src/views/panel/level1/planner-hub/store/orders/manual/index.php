<?php

use App\Services\CentralOperationsContextService;
use App\Services\LoginService;
use App\Services\StoreManualOrderService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $service = new StoreManualOrderService();

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'products' => $ownerId > 0 ? $service->getProductsForOwner($ownerId) : [],
        'clients' => $service->getClients($ownerId),
        'online_payment_available' => $ownerId > 0 && $service->hasConnectedOnlinePaymentProvider($ownerId),
        'saved_card_payment_available' => $ownerId > 0 && $service->hasConnectedSavedCardProvider($ownerId),
        'operation_context' => $operationContext,
    ]);
});

$router->post(function () {
    $session = LoginService::getSession();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $result = (new StoreManualOrderService())->createFromRequest((int)($operationContext['owner_id'] ?? 0), $_POST, $_FILES, (int)$session->getId());
    MessageUtil::setMessage((string)$result['message'], $result['success'] ? 'Success' : 'Error', $result['success'] ? 'success' : 'danger');
    LocationUtils::redirectInternal('panel/planner-hub/store/orders/home?' . ($operationContext['query'] ?? 'operation=vnv_events'));
});

$router->run();
