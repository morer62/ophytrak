<?php

use App\Utils\TemplateResponse;
use App\Services\OphyraSeoService;

$appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
$seoService = new OphyraSeoService();
$seo = $seoService->seoForRoute('terms-and-conditions', $appUrl);
echo TemplateResponse::render(__DIR__ . '/../terms_and_conditions/index.twig', [
    'seo' => $seo,
    'schemaJsonList' => $seoService->schemaJsonListForRoute('terms-and-conditions', $appUrl, $seo),
]);
