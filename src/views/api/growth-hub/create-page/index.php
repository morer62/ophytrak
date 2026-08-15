<?php

use App\Services\GrowthHubService;
use App\Services\LoginService;
use App\Utils\JsonResponse;
use App\Utils\LocationUtils;

$user = LoginService::getSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::createResponse(['error' => 'Method not allowed'], 405)->handle();
    return;
}

if (!$user || (int)$user->getLevel() !== 1) {
    JsonResponse::createResponse(['error' => 'Only level 1 admins can create Growth Hub pages.'], 403)->handle();
    return;
}

try {
    $service = new GrowthHubService();
    $siteKey = trim((string)($_POST['site_key'] ?? 'vnvevents'));
    $contentId = $service->createPlannedDraft($user, $_POST);

    JsonResponse::createResponse([
        'id_content' => $contentId,
        'preview_url' => LocationUtils::pathFor('panel/growth-hub?site_key=' . urlencode($siteKey) . '&step=3&preview_content_id=' . $contentId . '#article-preview'),
    ])->handle();
} catch (Throwable $e) {
    error_log('GrowthHub create page API failed: ' . $e->getMessage());
    JsonResponse::createResponse(['error' => $e->getMessage()], 422)->handle();
}
