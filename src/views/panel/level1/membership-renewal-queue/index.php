<?php

use App\Repositories\AdminAccountActionsRepository;
use App\Services\Level1MembershipOperationsService;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->post(function () {
    $admin = LoginService::getSession();
    $action = $_POST['action'] ?? '';
    if ($action === 'confirm_batch_preview') {
        if (($_POST['understand'] ?? '') !== '1' || strtoupper(trim((string)($_POST['confirm_word'] ?? ''))) !== 'CHARGE') {
            MessageUtil::setMessage('Batch was not confirmed. No customer was charged.', 'Error', 'error');
            LocationUtils::reload();
        }
        (new AdminAccountActionsRepository())->add((int)$admin->getId(), (int)$admin->getId(), 'membership_batch_preview_confirmed', 'Batch requires queue/job execution before charging.', [
            'selected_customers' => $_POST['customer_ids'] ?? [],
            'safety' => 'No charges executed in this request. Use queued worker/idempotency before production batch charging.',
        ]);
        MessageUtil::setMessage('Batch preview confirmed and recorded. No cards were charged from this request without a queue/lock worker.');
        LocationUtils::reload();
    }
});

$router->get(function () {
    $service = new Level1MembershipOperationsService();
    $filters = [
        'search' => $_GET['search'] ?? '',
        'filter' => $_GET['filter'] ?? 'due_or_expired',
        'page' => $_GET['page'] ?? 1,
        'per_page' => $_GET['per_page'] ?? 25,
    ];
    $queue = $service->getRenewalQueue($filters);
    $preview = isset($_GET['preview']) ? $service->buildBatchPreview($filters) : null;
    return TemplateResponse::render(__DIR__ . '/index.twig', ['queue' => $queue, 'filters' => $filters, 'preview' => $preview]);
});

try { $router->run(); } catch (Exception $e) { echo $e->getMessage(); }
