<?php

use App\Services\GrowthHubService;

$siteKey = trim((string)($_GET['site_key'] ?? ''));

if ($siteKey === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'site_key is required';
    return;
}

$xml = (new GrowthHubService())->publicSitemapXml($siteKey);

if (!$xml) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Growth Hub site not found';
    return;
}

header('Content-Type: application/xml; charset=utf-8');
echo $xml;
