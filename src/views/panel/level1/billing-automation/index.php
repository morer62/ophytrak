<?php

use App\Services\BillingAutomationService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $status = trim((string)($_GET['status'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));
    $allowedStatuses = ['received', 'processed', 'failed', 'ignored', 'pending_review', 'duplicate'];

    if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    $billingAutomation = new BillingAutomationService();

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'status' => $status,
        'search' => $search,
        'statuses' => $allowedStatuses,
        'stats' => $billingAutomation->getEventStats(),
        'events' => $billingAutomation->getRecentEvents(150, $status, $search),
        'log_only' => in_array(strtolower((string)($_ENV['STRIPE_WEBHOOK_LOG_ONLY'] ?? 'false')), ['1', 'true', 'yes', 'on'], true),
        'hard_disabled' => in_array(strtolower((string)($_ENV['STRIPE_WEBHOOK_HARD_DISABLE'] ?? 'false')), ['1', 'true', 'yes', 'on'], true),
        'has_secret' => trim((string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '')) !== '',
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
