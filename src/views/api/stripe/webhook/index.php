<?php

use App\Services\BillingAutomationService;
use App\Utils\JsonResponse;
use App\Utils\Request;
use App\Utils\RouterApi;

$router = new RouterApi();

$router->post(function (Request $request) {
    $payload = file_get_contents('php://input') ?: '';
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;

    $result = (new BillingAutomationService())->handleStripeWebhook($payload, $signature);

    return JsonResponse::createResponse([
        'received' => true,
        'status' => $result['status'] ?? 'unknown',
        'message' => $result['message'] ?? null,
    ], (int)($result['http_status'] ?? 200));
});

try {
    $router->run();
} catch (Throwable $e) {
    JsonResponse::createResponse([
        'received' => false,
        'status' => 'failed',
        'message' => 'Webhook endpoint error.',
    ], 500)->handle();
}
