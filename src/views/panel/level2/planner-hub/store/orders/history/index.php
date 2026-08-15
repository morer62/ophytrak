<?php

use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\StoreOrderItemsRepository;
use App\Repositories\StoreOrdersRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StorePaymentsRepository;
use App\Repositories\StoreProductsRepository;
use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');

$router = new Router();

$router->get(function () {
    $session = LoginService::getSession();
    $ownerId = (int)$session->getOwner();
    $today = new DateTimeImmutable('now');
    $from = trim((string)($_GET['from'] ?? $today->modify('-60 days')->format('Y-m-d')));
    $to = trim((string)($_GET['to'] ?? $today->format('Y-m-d')));
    $fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $from) ?: $today->modify('-60 days');
    $toDate = DateTimeImmutable::createFromFormat('Y-m-d', $to) ?: $today;
    $ordersRepo = new StoreOrdersRepository();
    $itemsRepo = new StoreOrderItemsRepository();
    $paymentsRepo = new StorePaymentsRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $locationRepo = new StoreDeliveryLocationLogsRepository();
    $orders = $ordersRepo->getHistoryByOwner($ownerId, $fromDate->setTime(0,0)->format('Y-m-d H:i:s'), $toDate->setTime(23,59,59)->format('Y-m-d H:i:s'), 500);
    foreach ($orders as &$order) {
        $order->items = $itemsRepo->getByOrder((int)$order->id);
        $order->latest_payment = $paymentsRepo->getLatestByOrder((int)$order->id, $ownerId);
        $order->workflow = $workflowRepo->getByOrder((int)$order->id);
        $order->latest_location = $locationRepo->getLatestByOrder((int)$order->id);
    }
    unset($order);
    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'orders' => $orders,
        'filters' => ['from' => $fromDate->format('Y-m-d'), 'to' => $toDate->format('Y-m-d')],
    ]);
});

$router->post(function () {
    $session = LoginService::getSession();
    $ownerId = (int)$session->getOwner();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = strtoupper(trim((string)($_POST['status'] ?? '')));
    $ordersRepo = new StoreOrdersRepository();
    $order = $ordersRepo->getById($orderId);
    if (!$order || (int)$order->id_owner !== $ownerId || !in_array($status, [StoreOrdersRepository::STATUS_COMPLETED, StoreOrdersRepository::STATUS_RETURN_REQUESTED, StoreOrdersRepository::STATUS_RETURN_APPROVED, StoreOrdersRepository::STATUS_RETURN_REJECTED, StoreOrdersRepository::STATUS_RETURNED, StoreOrdersRepository::STATUS_REDELIVERY_SCHEDULED, StoreOrdersRepository::STATUS_CLOSED, StoreOrdersRepository::STATUS_CANCELLED], true)) {
        MessageUtil::setMessage('Unable to update historical Store order.');
        LocationUtils::reload();
    }
    $adminMessage = trim((string)($_POST['return_admin_message'] ?? ''));
    $ok = in_array($status, [StoreOrdersRepository::STATUS_RETURN_APPROVED, StoreOrdersRepository::STATUS_RETURN_REJECTED, StoreOrdersRepository::STATUS_RETURNED, StoreOrdersRepository::STATUS_REDELIVERY_SCHEDULED, StoreOrdersRepository::STATUS_CLOSED], true)
        ? $ordersRepo->reviewReturn($orderId, $status, $adminMessage)
        : $ordersRepo->updateStatus($orderId, $status);
    if ($ok && $status === StoreOrdersRepository::STATUS_RETURNED) {
        $stockRestored = (new StoreProductsRepository())->restoreStockForReturnedOrder($ownerId, $orderId, (int)$session->getId());
        $ok = $stockRestored && $ordersRepo->markAsRefunded($orderId);
    }
    MessageUtil::setMessage($ok ? 'Historical Store order updated.' : 'Unable to update historical Store order.');
    LocationUtils::reload();
});

$router->run();
