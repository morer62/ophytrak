<?php

use App\Services\GrowthHubService;
use App\Services\LoginService;
use App\Utils\JsonResponse;

$user = LoginService::getSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::createResponse(['error' => 'Method not allowed'], 405)->handle();
    return;
}

if (!$user || (int)$user->getLevel() !== 1) {
    JsonResponse::createResponse(['error' => 'Only level 1 admins can generate Growth Hub ideas.'], 403)->handle();
    return;
}

try {
    $service = new GrowthHubService();
    JsonResponse::createResponse($service->generateTitleIdeas($user, $_POST))->handle();
} catch (Throwable $e) {
    error_log('GrowthHub title idea API failed: ' . $e->getMessage());
    JsonResponse::createResponse(['error' => $e->getMessage()], 422)->handle();
}
