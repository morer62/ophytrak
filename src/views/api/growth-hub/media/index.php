<?php

use App\Services\GrowthHubService;
use App\Utils\JsonResponse;

$service = new GrowthHubService();
$siteKey = trim((string)($_GET['site_key'] ?? ''));
$contentId = isset($_GET['id_content']) ? (int)$_GET['id_content'] : null;

if ($siteKey === '') {
    JsonResponse::createResponse(['error' => 'site_key is required'], 400)->handle();
    return;
}

JsonResponse::createResponse([
    'site_key' => $siteKey,
    'media' => $service->mediaPayload($siteKey, $contentId ?: null),
])->handle();
