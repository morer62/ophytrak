<?php

use App\Utils\TemplateResponse;
use App\Services\OphyraSeoService;

$appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
$seoService = new OphyraSeoService();
$seo = $seoService->seoForRoute('data-processing-notice', $appUrl);
echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'seo' => $seo,
    'schemaJsonList' => $seoService->schemaJsonListForRoute('data-processing-notice', $appUrl, $seo),
]);
