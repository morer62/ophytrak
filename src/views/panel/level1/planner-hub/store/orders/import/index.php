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
    $service = new StoreManualOrderService();
    if (isset($_GET['template'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="store-orders-template.csv"');
        echo $service->csvTemplate();
        exit;
    }
    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'operation_context' => (new CentralOperationsContextService())->getContext(),
    ]);
});

$router->post(function () {
    $session = LoginService::getSession();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $result = (new StoreManualOrderService())->importCsv((int)($operationContext['owner_id'] ?? 0), $_FILES['orders_csv'] ?? [], (int)$session->getId());
    MessageUtil::setMessage((string)$result['message'], $result['success'] ? 'Success' : 'Error', $result['success'] ? 'success' : 'danger');
    LocationUtils::redirectInternal('panel/planner-hub/store/orders/home?' . ($operationContext['query'] ?? 'operation=vnv_events'));
});

$router->run();
