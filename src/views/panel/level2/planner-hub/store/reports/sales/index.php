<?php

use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Services\StoreSalesReportService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');

$router = new Router();

$router->get(function () {
    $session = LoginService::getSession();
    $ownerId = (int)$session->getOwner();

    try {
        $report = (new StoreSalesReportService())->build($ownerId, $_GET);
    } catch (\Throwable $exception) {
        error_log('Store sales report build failed: ' . $exception->getMessage());
        $report = [
            'label' => 'selected range',
            'filters' => [
                'preset' => $_GET['preset'] ?? 'this_month',
                'date_from' => date('Y-m-01'),
                'date_to' => date('Y-m-t'),
                'payment_status' => '',
                'status' => '',
                'product_id' => 0,
                'email' => '',
                'search' => '',
            ],
            'stats' => [
                'total_orders' => 0,
                'paid_orders' => 0,
                'pending_orders' => 0,
                'return_requested' => 0,
                'returned_orders' => 0,
                'gross_sales' => 0,
                'returned_amount' => 0,
                'net_sales' => 0,
                'average_order' => 0,
            ],
            'orders' => [],
            'products' => [],
            'productSummary' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 1,
                'total_records' => 0,
                'limit' => 25,
            ],
        ];
    }

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'report' => $report,
        'back_url' => '/panel/planner-hub/store/orders/home',
        'history_url' => '/panel/planner-hub/store/orders/history',
    ]);
});

$router->run();

