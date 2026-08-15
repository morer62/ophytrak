<?php

require __DIR__ . '/../vendor/autoload.php';

if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();
}

$seo = new App\Services\OphyraSeoService();

header('Content-Type: application/xml; charset=UTF-8');
echo $seo->sitemapXml();
