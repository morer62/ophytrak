<?php

use App\Services\DailyAutomationService;
use App\Utils\JsonResponse;
use App\Utils\Request;
use App\Utils\RouterApi;

$router = new RouterApi();

$router->get(function () {
    return JsonResponse::createResponse([
        'success' => true,
        'message' => 'Daily cron endpoint is available. Use POST with X-Cron-Token to run it.',
        'runs_charges' => false,
        'supports_dry_run' => true,
    ]);
});

$router->post(function (Request $request) {
    $secret = trim((string)($_ENV['CRON_SECRET'] ?? ''));
    if ($secret === '') {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'CRON_SECRET is not configured.',
        ], 500);
    }

    $body = $request->getBody();
    if (!is_array($body)) {
        $body = [];
    }

    $headers = array_change_key_case($request->getHeaders() ?: [], CASE_LOWER);
    $token = (string)($headers['x-cron-token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? $_GET['token'] ?? $body['token'] ?? '');
    if ($token === '' || !hash_equals($secret, $token)) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Invalid cron token.',
        ], 403);
    }

    $dryRunValue = $_GET['dry_run'] ?? $_POST['dry_run'] ?? $body['dry_run'] ?? false;
    $dryRun = in_array(strtolower((string)$dryRunValue), ['1', 'true', 'yes', 'on'], true);

    $service = new DailyAutomationService(dirname(__DIR__, 6), false);
    $result = $service->run($dryRun, 'http');

    $status = 200;
    if (!$result['success'] && ($result['message'] ?? '') === 'Daily cron is already running.') {
        $status = 409;
    } elseif (!$result['success']) {
        $status = 500;
    }

    return JsonResponse::createResponse($result, $status);
});

$router->run();
