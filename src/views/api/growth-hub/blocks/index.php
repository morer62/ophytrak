<?php

use App\Services\GrowthHubService;
use App\Utils\JsonResponse;

$service = new GrowthHubService();
$siteKey = trim((string)($_GET['site_key'] ?? ''));
$contentId = isset($_GET['id_content']) ? (int)$_GET['id_content'] : 0;

if ($siteKey === '' || $contentId <= 0) {
    JsonResponse::createResponse(['error' => 'site_key and id_content are required'], 400)->handle();
    return;
}

$payload = $service->blocksPayload($siteKey, $contentId);
JsonResponse::createResponse($payload ?: ['error' => 'Content not found'], $payload ? 200 : 404)->handle();
