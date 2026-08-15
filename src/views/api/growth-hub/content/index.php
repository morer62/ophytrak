<?php

use App\Services\GrowthHubService;
use App\Utils\JsonResponse;

$service = new GrowthHubService();
$siteKey = trim((string)($_GET['site_key'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$slug = trim((string)($_GET['slug'] ?? ''));
$route = trim((string)($_GET['route'] ?? ''));

if ($siteKey === '') {
    JsonResponse::createResponse(['error' => 'site_key is required'], 400)->handle();
    return;
}

if ($slug !== '') {
    $content = $service->publicContentBySlug($siteKey, $slug);
    JsonResponse::createResponse($content ?: ['error' => 'Content not found'], $content ? 200 : 404)->handle();
    return;
}

if ($route !== '') {
    $content = $service->publicContentByRoute($siteKey, $route);
    JsonResponse::createResponse($content ?: ['error' => 'Content not found'], $content ? 200 : 404)->handle();
    return;
}

JsonResponse::createResponse([
    'site_key' => $siteKey,
    'content' => $service->publicContentPayload($siteKey, $type ?: null),
])->handle();
