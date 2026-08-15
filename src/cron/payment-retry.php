<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

use App\Services\AutopayService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This cron must be run from CLI.\n";
    exit(1);
}

$results = (new AutopayService())->processRetries();

echo '[' . date('Y-m-d H:i:s') . '] Retries processed=' . (int)$results['processed']
    . ', successful=' . (int)$results['successful']
    . ', failed=' . (int)$results['failed']
    . ', abandoned=' . (int)$results['abandoned'] . PHP_EOL;

exit(0);
