<?php

use App\Repositories\Connection;
use App\Services\LoginService;
use App\Utils\JsonResponse;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    $token = trim((string)($_GET['token'] ?? ''));
    $orderId = (int)($_GET['order_id'] ?? 0);
    $db = new Connection();
    $userId = 0;
    $userEmail = '';

    try {
        $session = LoginService::getSession();
        $userId = $session ? (int)$session->getId() : 0;
        $userEmail = ($session && method_exists($session, 'getEmail')) ? strtolower(trim((string)$session->getEmail())) : '';
    } catch (Throwable $e) {
        $userId = 0;
        $userEmail = '';
    }

    if ($token !== '') {
        $db->query("
            SELECT *
            FROM store_orders
            WHERE public_token = :token
            LIMIT 1
        ");
        $db->bind(':token', $token);
    } elseif ($orderId > 0 && $userId > 0) {
        $db->query("
            SELECT *
            FROM store_orders
            WHERE id = :order
              AND (id_user = :user OR (:email <> '' AND LOWER(guest_email) = :email))
            LIMIT 1
        ");
        $db->bind(':order', $orderId);
        $db->bind(':user', $userId);
        $db->bind(':email', $userEmail);
    } else {
        return JsonResponse::createResponse(['message' => 'A public token or authenticated order_id is required.'], 400);
    }

    $order = $db->fetchOne();
    if (!$order) {
        return JsonResponse::createResponse(['message' => 'Store order was not found or is not available.'], 404);
    }

    $db->query("
        SELECT l.event_type, l.latitude, l.longitude, l.accuracy, l.platform, l.source,
               l.permission_status, l.context, l.recorded_at, u.name, u.lastname
        FROM store_delivery_location_logs l
        LEFT JOIN users u ON u.id = l.id_user
        WHERE l.id_owner = :owner AND l.id_store_order = :order
        ORDER BY l.recorded_at DESC, l.id DESC
        LIMIT 50
    ");
    $db->bind(':owner', (int)$order->id_owner);
    $db->bind(':order', (int)$order->id);
    $timeline = $db->fetchAll();
    $latest = $timeline[0] ?? null;

    $db->query("
        SELECT delivery_photo_url, delivery_notes, delivery_lat, delivery_lng,
               delivery_location_at, delivered_at, completed_at
        FROM store_order_workflow
        WHERE id_store_order = :order
        LIMIT 1
    ");
    $db->bind(':order', (int)$order->id);
    $workflow = $db->fetchOne() ?: null;

    return JsonResponse::createResponse([
        'order' => [
            'id' => (int)$order->id,
            'status' => (string)$order->status,
            'payment_status' => (string)($order->payment_status ?? ''),
            'fulfillment_status' => (string)($order->fulfillment_status ?? ''),
            'customer_name' => (string)($order->guest_name ?? ''),
            'customer_email' => (string)($order->guest_email ?? ''),
            'shipping_city' => (string)($order->shipping_city ?? ''),
            'shipping_state' => (string)($order->shipping_state ?? ''),
            'shipping_zip' => (string)($order->shipping_zip ?? ''),
            'return_notes' => $order->return_notes ?? null,
            'return_admin_message' => $order->return_admin_message ?? null,
            'return_requested_at' => $order->return_requested_at ?? null,
            'return_decision_at' => $order->return_decision_at ?? null,
            'delivered_at' => $workflow->delivered_at ?? null,
        ],
        'tracking' => [
            'latest' => $latest,
            'timeline' => $timeline,
        ],
        'proof' => [
            'photo_url' => $workflow->delivery_photo_url ?? null,
            'notes' => $workflow->delivery_notes ?? null,
            'delivered_at' => $workflow->delivered_at ?? null,
            'completed_at' => $workflow->completed_at ?? null,
        ],
    ]);
});

$router->run();
