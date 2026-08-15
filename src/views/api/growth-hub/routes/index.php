<?php

use App\Services\GrowthHubService;
use App\Utils\JsonResponse;

$service = new GrowthHubService();
$siteKey = trim((string)($_GET['site_key'] ?? ''));

if ($siteKey === '') {
    JsonResponse::createResponse(['error' => 'site_key is required'], 400)->handle();
    return;
}

$content = $service->publicContentPayload($siteKey);
$routes = array_values(array_filter(array_map(static fn(array $item): ?array => empty($item['route']) ? null : [
    'id_content' => $item['id'],
    'content_type' => $item['content_type'],
    'title' => $item['title'],
    'slug' => $item['slug'],
    'route' => $item['route'],
    'canonical_url' => $item['canonical_url'],
], $content)));

JsonResponse::createResponse([
    'site_key' => $siteKey,
    'routes' => $routes,
])->handle();
