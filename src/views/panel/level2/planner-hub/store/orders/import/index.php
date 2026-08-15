<?php

use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Services\StoreManualOrderService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');

$router = new Router();

$router->get(function () {
    $service = new StoreManualOrderService();
    if (isset($_GET['template'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="store-orders-template.csv"');
        echo $service->csvTemplate();
        exit;
    }
    return TemplateResponse::render(__DIR__ . '/index.twig');
});

$router->post(function () {
    $session = LoginService::getSession();
    $result = (new StoreManualOrderService())->importCsv((int)$session->getOwner(), $_FILES['orders_csv'] ?? [], (int)$session->getId());
    MessageUtil::setMessage((string)$result['message'], $result['success'] ? 'Success' : 'Error', $result['success'] ? 'success' : 'danger');
    LocationUtils::redirectInternal('panel/planner-hub/store/orders/home');
});

$router->run();
