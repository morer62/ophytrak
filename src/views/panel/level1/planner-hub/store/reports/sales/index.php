<?php

use App\Services\CentralOperationsContextService;
use App\Services\StoreSalesReportService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $context = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($context['owner_id'] ?? 0);
    try {
        $report = $ownerId > 0 ? (new StoreSalesReportService())->build($ownerId, $_GET) : [
            'label' => 'No operation selected',
            'filters' => [],
            'stats' => [],
            'orders' => [],
            'products' => [],
            'productSummary' => [],
            'pagination' => ['current_page' => 1, 'total_pages' => 1, 'total_records' => 0, 'limit' => 25],
        ];
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
            'stats' => [],
            'orders' => [],
            'products' => [],
            'productSummary' => [],
            'pagination' => ['current_page' => 1, 'total_pages' => 1, 'total_records' => 0, 'limit' => 25],
        ];
    }

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'report' => $report,
        'operation_context' => $context,
        'back_url' => '/panel/planner-hub/store/orders/home?' . ($context['query'] ?? 'operation=vnv_events'),
        'history_url' => '/panel/planner-hub/store/orders/history?' . ($context['query'] ?? 'operation=vnv_events'),
    ]);
});

$router->run();

