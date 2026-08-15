<?php

use App\Services\GrowthHubService;
use App\Utils\JsonResponse;

$siteKey = trim((string)($_GET['site_key'] ?? ''));
$configuredToken = trim((string)($_ENV['GROWTH_HUB_MONITORING_TOKEN'] ?? ''));
$providedToken = trim((string)($_GET['token'] ?? ($_SERVER['HTTP_X_GROWTH_HUB_TOKEN'] ?? '')));

if ($siteKey === '') {
    JsonResponse::createResponse(['error' => 'site_key is required'], 400)->handle();
    return;
}

if ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
    JsonResponse::createResponse(['error' => 'Invalid monitoring token'], 403)->handle();
    return;
}

$payload = (new GrowthHubService())->monitoringPayload($siteKey);

JsonResponse::createResponse($payload ?: ['error' => 'Growth Hub site not found'], $payload ? 200 : 404)->handle();
