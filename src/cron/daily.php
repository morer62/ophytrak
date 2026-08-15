<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

use App\Services\DailyAutomationService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This cron must be run from CLI.\n";
    exit(1);
}

$service = new DailyAutomationService($root, true);
$result = $service->run(false, 'cli');

exit($result['success'] ? 0 : 1);
