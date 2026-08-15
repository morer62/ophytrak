<?php

use App\Entity\User;
use App\Services\LoginService;
use App\Services\TokenUsageService;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$user = LoginService::getSession();
if (!$user instanceof User) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Debes iniciar sesión.',
    ]);
    exit;
}

$usage = TokenUsageService::getUsage($user->getId());
$payload = [
    'success' => true,
    'usage' => [
        'used' => $usage['used'],
        'limit' => $usage['limit'],
        'period' => $usage['period'],
        'remaining' => max(0, $usage['limit'] - $usage['used']),
    ],
];

header('Content-Type: application/json; charset=UTF-8');
http_response_code(200);
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
exit;
