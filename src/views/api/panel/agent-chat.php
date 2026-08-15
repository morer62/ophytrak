<?php

use App\Entity\User;
use App\Services\LoginService;
use App\Services\PlannerHubAgentService;
use App\Utils\JsonResponse;
use App\Utils\Request;
use App\Utils\RouterApi;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

LoginService::clearCache();
$user = LoginService::getSession();
if (!$user instanceof User) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'reply' => 'You must be logged in to use the assistant.',
    ]);
    exit;
}

// Recargar usuario desde BD para tener level e id_owner actualizados (p. ej. tras Change Profile)
$institutionId = $_SESSION['current_institution_id'] ?? null;
LoginService::reloadUserPermissions($institutionId ? (int) $institutionId : null);
$user = LoginService::getSession();

$body = (new Request())->getBody();
if (!is_array($body)) {
    $body = [];
}
$message = $body['message'] ?? $_POST['message'] ?? '';
$message = is_string($message) ? trim($message) : '';
$history = $body['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

try {
    $agent = new PlannerHubAgentService();
    $result = $agent->chat($user, $message, $history);
} catch (Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'reply' => 'Could not process your question. Please try again.',
    ]);
    exit;
}

$payload = [
    'success' => $result['success'],
    'reply' => $result['reply'] ?? '',
];
if (isset($result['usage']) && is_array($result['usage'])) {
    $payload['usage'] = $result['usage'];
}

header('Content-Type: application/json; charset=UTF-8');
http_response_code(200);
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
exit;
