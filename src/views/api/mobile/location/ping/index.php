<?php

use App\Repositories\Connection;
use App\Services\ApiAuthService;
use App\Utils\Cors;
use App\Utils\JsonResponse;
use App\Utils\Router;

Cors::handle();

$router = new Router();

function mobileLocationPayload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function mobileLocationColumnExists(Connection $db, string $table, string $column): bool
{
    $db->query("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ");
    $db->bind(':table', $table);
    $db->bind(':column', $column);
    $row = $db->fetchOne();
    return (int)($row->total ?? 0) > 0;
}

function mobileLocationInsert(Connection $db, string $table, array $data): bool
{
    $columns = [];
    $params = [];
    foreach ($data as $column => $value) {
        if (mobileLocationColumnExists($db, $table, $column)) {
            $columns[] = $column;
            $params[":{$column}"] = $value;
        }
    }

    if (!$columns) {
        return false;
    }

    $db->query("
        INSERT INTO {$table} (" . implode(',', $columns) . ")
        VALUES (" . implode(',', array_keys($params)) . ")
    ");
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    return (bool)$db->execute();
}

function mobileLocationUpdateOpenPayrollLog(Connection $db, int $userId, ?int $ownerId, float $lat, float $lng, ?float $accuracy, string $source, string $permission): bool
{
    if (!mobileLocationColumnExists($db, 'payroll_time_logs', 'location_lat')) {
        return false;
    }

    $fields = [
        'location_lat = :lat',
        'location_long = :lng',
    ];
    if (mobileLocationColumnExists($db, 'payroll_time_logs', 'location_accuracy')) {
        $fields[] = 'location_accuracy = :accuracy';
    }
    if (mobileLocationColumnExists($db, 'payroll_time_logs', 'location_source')) {
        $fields[] = 'location_source = :source';
    }
    if (mobileLocationColumnExists($db, 'payroll_time_logs', 'location_permission_status')) {
        $fields[] = 'location_permission_status = :permission';
    }

    $ownerSql = $ownerId ? ' AND (id_owner = :owner OR id_owner IS NULL)' : '';
    $db->query("
        UPDATE payroll_time_logs
        SET " . implode(', ', $fields) . "
        WHERE id_user = :user
          AND end_time IS NULL
          {$ownerSql}
        ORDER BY start_time DESC, id DESC
        LIMIT 1
    ");
    $db->bind(':lat', $lat);
    $db->bind(':lng', $lng);
    if (in_array('location_accuracy = :accuracy', $fields, true)) {
        $db->bind(':accuracy', $accuracy);
    }
    if (in_array('location_source = :source', $fields, true)) {
        $db->bind(':source', $source);
    }
    if (in_array('location_permission_status = :permission', $fields, true)) {
        $db->bind(':permission', $permission);
    }
    $db->bind(':user', $userId);
    if ($ownerId) {
        $db->bind(':owner', $ownerId);
    }
    return (bool)$db->execute();
}

$router->post(function () {
    $user = ApiAuthService::getAuthenticatedUser();
    $payload = mobileLocationPayload();

    $userId = (int)($payload['user_id'] ?? ($user ? $user->getId() : 0));
    $ownerId = (int)($payload['id_owner'] ?? ($user ? ($user->getOwner() ?: $user->getId()) : 0));
    $orderId = (int)($payload['id_store_order'] ?? 0);
    $taskId = (int)($payload['id_store_order_task'] ?? 0);
    $context = trim((string)($payload['context'] ?? 'location_update'));
    $eventType = strtoupper(trim((string)($payload['event_type'] ?? 'LOCATION_UPDATE')));
    $lat = isset($payload['latitude']) ? (float)$payload['latitude'] : null;
    $lng = isset($payload['longitude']) ? (float)$payload['longitude'] : null;
    $accuracy = isset($payload['accuracy']) && $payload['accuracy'] !== '' ? (float)$payload['accuracy'] : null;
    $platform = trim((string)($payload['platform'] ?? 'mobile_webview'));
    $source = trim((string)($payload['source'] ?? 'mobile_webview'));
    $permission = trim((string)($payload['permission_status'] ?? 'unknown'));
    $deviceId = trim((string)($payload['device_id'] ?? ''));

    if ($userId <= 0 || $lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Invalid location payload.',
        ], 422);
    }

    $allowedEvents = ['TASK_START', 'LOCATION_UPDATE', 'OUT_FOR_DELIVERY', 'ARRIVED', 'DELIVERED', 'CLOCK_IN', 'CLOCK_OUT'];
    if (!in_array($eventType, $allowedEvents, true)) {
        $eventType = 'LOCATION_UPDATE';
    }

    $db = new Connection();
    $stored = false;
    $payrollUpdated = false;

    if ($orderId > 0 && $ownerId > 0) {
        $stored = mobileLocationInsert($db, 'store_delivery_location_logs', [
            'id_owner' => $ownerId,
            'id_store_order' => $orderId,
            'id_store_order_task' => $taskId > 0 ? $taskId : null,
            'id_user' => $userId,
            'event_type' => $eventType,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'platform' => $platform,
            'source' => $source,
            'permission_status' => $permission,
            'device_id' => $deviceId !== '' ? $deviceId : null,
            'context' => $context,
            'recorded_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $db->query("
            UPDATE store_order_workflow
            SET delivery_lat = :lat,
                delivery_lng = :lng,
                delivery_location_at = NOW(),
                updated_at = NOW()
            WHERE id_store_order = :order
              AND id_owner = :owner
            LIMIT 1
        ");
        $db->bind(':lat', $lat);
        $db->bind(':lng', $lng);
        $db->bind(':order', $orderId);
        $db->bind(':owner', $ownerId);
        $db->execute();
    }

    if (in_array($context, ['payroll_clock', 'clock_in', 'clock_out'], true) || in_array($eventType, ['CLOCK_IN', 'CLOCK_OUT'], true)) {
        $payrollUpdated = mobileLocationUpdateOpenPayrollLog($db, $userId, $ownerId > 0 ? $ownerId : null, $lat, $lng, $accuracy, $source, $permission);
    }

    return JsonResponse::createResponse([
        'success' => $stored || $payrollUpdated,
        'stored_delivery_location' => $stored,
        'updated_payroll_location' => $payrollUpdated,
        'context' => $context,
        'event_type' => $eventType,
    ]);
});

$router->run();
