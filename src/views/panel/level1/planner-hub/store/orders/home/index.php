<?php

use App\Repositories\StoreOrdersRepository;
use App\Repositories\StoreOrderItemsRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StorePaymentsRepository;
use App\Repositories\StoreUserRolesRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Services\CentralOperationsContextService;
use App\Services\LoginService;
use App\Services\StoreDeliveryNotificationService;
use App\Services\StoreLogisticsWorkflowService;
use App\Services\StoreManualOrderService;
use App\Utils\LocationUtils;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;

$router = new Router();

$router->get(function () {
    $ordersRepo = new StoreOrdersRepository();
    $itemsRepo = new StoreOrderItemsRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $paymentsRepo = new StorePaymentsRepository();
    $storeRolesRepo = new StoreUserRolesRepository();
    $tasksRepo = new StoreOrderTasksRepository();
    $locationRepo = new StoreDeliveryLocationLogsRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $profile = $ownerId > 0 ? (new InstitutionProfileRepository())->getByOwner($ownerId) : null;

    $weekStartInput = trim($_GET['week_start'] ?? '');
    $weekEndInput = trim($_GET['week_end'] ?? '');
    $paymentStatus = trim($_GET['payment_status'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $email = trim($_GET['email'] ?? '');

    $today = new DateTimeImmutable('now');
    $weekStart = $weekStartInput !== ''
        ? DateTimeImmutable::createFromFormat('Y-m-d', $weekStartInput)
        : $today->modify('monday this week');
    $weekEnd = $weekEndInput !== ''
        ? DateTimeImmutable::createFromFormat('Y-m-d', $weekEndInput)
        : $today->modify('sunday this week');

    if (!$weekStart) {
        $weekStart = $today->modify('monday this week');
    }
    if (!$weekEnd) {
        $weekEnd = $today->modify('sunday this week');
    }

    $orders = $ordersRepo->getByOwnerAndDateRange(
        $ownerId,
        $weekStart->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
        $weekEnd->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
        300
    );

    if ($paymentStatus !== '') {
        $orders = array_values(array_filter($orders, function ($o) use ($paymentStatus) {
            return strtoupper((string)$o->payment_status) === strtoupper($paymentStatus);
        }));
    }
    if ($status !== '') {
        $orders = array_values(array_filter($orders, function ($o) use ($status) {
            return strtoupper((string)$o->status) === strtoupper($status);
        }));
    } else {
        $historyStatuses = [
            StoreOrdersRepository::STATUS_DELIVERED,
            StoreOrdersRepository::STATUS_COMPLETED,
            StoreOrdersRepository::STATUS_RETURNED,
            StoreOrdersRepository::STATUS_CLOSED,
            StoreOrdersRepository::STATUS_CANCELLED,
        ];
        $orders = array_values(array_filter($orders, function ($o) use ($historyStatuses) {
            return !in_array(strtoupper((string)$o->status), $historyStatuses, true);
        }));
    }
    if ($email !== '') {
        $orders = array_values(array_filter($orders, function ($o) use ($email) {
            return stripos((string)($o->guest_email ?? ''), $email) !== false;
        }));
    }

    $deliveryUsers = $storeRolesRepo->getUsersByOwnerAndRole($ownerId, 'delivery');
    $teamUsers = $storeRolesRepo->getUsersByOwner($ownerId);
    if (!$deliveryUsers) {
        $deliveryUsers = $teamUsers;
    }
    $orderIds = array_map(fn($o) => (int)$o->id, $orders);
    $workflowMap = $workflowRepo->getMapByOrders($orderIds);
    $teamUsersById = [];
    foreach ($teamUsers as $u) {
        $teamUsersById[(int)$u->id_user] = trim(($u->name ?? '') . ' ' . ($u->lastname ?? '')) ?: ($u->email ?? ('User #' . (int)$u->id_user));
    }

    foreach ($orders as &$order) {
        $shippingParts = array_filter([
            trim((string)($order->shipping_address_1 ?? '')),
            trim((string)($order->shipping_address_2 ?? '')),
            trim((string)($order->shipping_city ?? '')),
            trim((string)(
                trim((string)($order->shipping_state ?? '')) .
                (((string)($order->shipping_zip ?? '') !== '') ? (' ' . trim((string)$order->shipping_zip)) : '')
            )),
            trim((string)($order->shipping_country ?? ''))
        ], function ($v) {
            return $v !== '';
        });
        $order->shipping_address_display = $shippingParts
            ? implode(', ', $shippingParts)
            : ((string)($order->city ?? '') !== '' ? (string)$order->city : '-');

        $items = $itemsRepo->getByOrder((int)$order->id);
        $order->items_summary = [];
        $order->items_meals_total = 0;
        $modalItems = [];

        foreach ($items as $idx => $item) {
            $order->items_meals_total += (int)($item->quantity ?? 0);
            if ($idx < 3) {
                $order->items_summary[] = sprintf(
                    '%s x %d',
                    $item->product_name_snapshot ?? ('#' . $item->id_product),
                    (int)($item->quantity ?? 0)
                );
            }

            $modalItems[] = [
                'name' => $item->product_name_snapshot ?? ('#' . $item->id_product),
                'quantity' => (int)($item->quantity ?? 0),
                'unit_price' => (float)($item->unit_price ?? 0),
                'line_total' => (float)($item->line_total ?? 0),
            ];
        }
        if (count($items) > 3) {
            $order->items_summary[] = '+' . (count($items) - 3) . ' more';
        }

        $order->items_modal = $modalItems;
        $order->items_modal_json = json_encode($modalItems);
        $wf = $workflowMap[(int)$order->id] ?? null;
        $order->delivery_user_id = $wf ? (int)($wf->delivery_user_id ?? 0) : 0;
        $order->kitchen_user_id = $wf ? (int)($wf->kitchen_user_id ?? 0) : 0;
        $order->allow_team_close_delivery = $wf ? (int)($wf->allow_team_close_delivery ?? 0) : 0;
        $order->allow_chat_with_client = $wf ? (int)($wf->allow_chat_with_client ?? 0) : 0;
        $order->delivery_photo_url = $wf ? (string)($wf->delivery_photo_url ?? '') : '';
        $order->store_tasks = $tasksRepo->getByOrder($ownerId, (int)$order->id);
        $order->latest_payment = $paymentsRepo->getLatestByOrder((int)$order->id, $ownerId);
        $order->latest_location = $locationRepo->getLatestByOrder((int)$order->id);
        $order->preparation_assignee_name = $order->kitchen_user_id > 0
            ? ($teamUsersById[$order->kitchen_user_id] ?? '')
            : '';
        $order->delivery_assignee_name = $order->delivery_user_id > 0
            ? ($teamUsersById[$order->delivery_user_id] ?? '')
            : '';
        $order->delivery_tracking_active = in_array(strtoupper((string)$order->status), [
            StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY,
            StoreOrdersRepository::STATUS_DELIVERY_ATTEMPTED,
            StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS,
            StoreOrdersRepository::STATUS_DELIVERED,
        ], true);
    }
    unset($order);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "orders" => $orders,
        "deliveryUsers" => $deliveryUsers,
        "teamUsers" => $teamUsers,
        "businessProfile" => $profile,
        "filters" => [
            "week_start" => $weekStart->format('Y-m-d'),
            "week_end" => $weekEnd->format('Y-m-d'),
            "payment_status" => $paymentStatus,
            "status" => $status,
            "email" => $email
        ],
        "operation_context" => $operationContext
        ,"public_store_url" => $ownerId > 0 ? ('/store/home?owner=' . $ownerId) : ''
        ,"public_profile_url" => $profile && !empty($profile->slug) ? ('/business-profile?slug=' . urlencode((string)$profile->slug)) : ''
    ]);
});

$router->post(function () {
    $ordersRepo = new StoreOrdersRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $tasksRepo = new StoreOrderTasksRepository();
    $storeRolesRepo = new StoreUserRolesRepository();
    $session = LoginService::getSession();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);

    $action = $_POST['action'] ?? '';
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($orderId <= 0) {
        MessageUtil::setMessage('Invalid order id.');
        LocationUtils::reload();
    }
    $order = $ordersRepo->getById($orderId);
    if (!$order || (int)($order->id_owner ?? 0) !== $ownerId) {
        MessageUtil::setMessage('Order not found in this workspace.');
        LocationUtils::reload();
    }

    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        if ($newStatus === '') {
            MessageUtil::setMessage('Select a valid status.');
            LocationUtils::reload();
        }

        $ok = $ordersRepo->updateStatus($orderId, $newStatus);
        MessageUtil::setMessage($ok ? 'Order status updated.' : 'Failed to update order status.');
        LocationUtils::reload();
    }

    if ($action === 'update_payment_status') {
        $newPayment = trim($_POST['payment_status'] ?? '');
        $ok = false;
        if ($newPayment === StoreOrdersRepository::PAYMENT_PAID) {
            $ok = $ordersRepo->markAsPaid($orderId);
            if ($ok) {
                $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_IN_PREPARATION);
            }
        } elseif ($newPayment === StoreOrdersRepository::PAYMENT_FAILED) {
            $ok = $ordersRepo->markAsFailed($orderId);
            if ($ok) {
                $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_CANCELLED);
            }
        } elseif ($newPayment === StoreOrdersRepository::PAYMENT_REFUNDED) {
            $ok = $ordersRepo->markAsRefunded($orderId);
            if ($ok) {
                $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_RETURNED);
            }
        }
        MessageUtil::setMessage($ok ? 'Payment status updated.' : 'Failed to update payment status.');
        LocationUtils::reload();
    }

    if ($action === 'resend_payment_link') {
        $result = (new StoreManualOrderService())->resendPaymentLink((int)$order->id_owner, $orderId);
        MessageUtil::setMessage((string)$result['message'], $result['success'] ? 'Success' : 'Error', $result['success'] ? 'success' : 'danger');
        LocationUtils::reload();
    }

    if ($action === 'assign_delivery') {
        $deliveryUserId = isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] !== ''
            ? (int)$_POST['delivery_user_id']
            : null;
        $kitchenUserId = isset($_POST['kitchen_user_id']) && $_POST['kitchen_user_id'] !== ''
            ? (int)$_POST['kitchen_user_id']
            : null;
        $ownerId = (int)($order->id_owner ?? 0);
        if ($ownerId <= 0) {
            MessageUtil::setMessage('Invalid order owner.');
            LocationUtils::reload();
        }
        $allowClose = isset($_POST['allow_team_close_delivery']);
        $allowChat = isset($_POST['allow_chat_with_client']);
        if (($deliveryUserId && !$storeRolesRepo->userBelongsToOwner($ownerId, $deliveryUserId)) || ($kitchenUserId && !$storeRolesRepo->userBelongsToOwner($ownerId, $kitchenUserId))) {
            MessageUtil::setMessage('Select team members from this workspace.');
            LocationUtils::reload();
        }
        $ok = (new StoreLogisticsWorkflowService())->assignOperations($ownerId, $orderId, $kitchenUserId, $deliveryUserId, $allowClose, $allowChat);
        MessageUtil::setMessage($ok ? 'Store operations updated.' : 'Failed to update Store operations.');
        LocationUtils::reload();
    }

    if ($action === 'create_task') {
        $ownerId = (int)($order->id_owner ?? 0);
        $type = strtoupper(trim((string)($_POST['task_type'] ?? 'OTHER')));
        $title = trim((string)($_POST['title'] ?? ''));
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        $userId = isset($_POST['id_user']) && $_POST['id_user'] !== '' ? (int)$_POST['id_user'] : null;
        if ($userId && !$storeRolesRepo->userBelongsToOwner($ownerId, $userId)) {
            MessageUtil::setMessage('Select a team member from this workspace.');
            LocationUtils::reload();
        }
        $requiresLocation = isset($_POST['requires_location']);
        $allowComplete = isset($_POST['allow_assignee_complete']);
        $ok = $tasksRepo->createTask($ownerId, $orderId, $userId, $type, $title, $instructions, $requiresLocation, $allowComplete);
        MessageUtil::setMessage($ok ? 'Store work item created.' : 'Unable to create Store work item.');
        LocationUtils::reload();
    }

    if ($action === 'complete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $task = $tasksRepo->getOneForOwner($taskId, (int)$order->id_owner);
        $ok = $task && (int)$task->id_store_order === $orderId
            && $tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_COMPLETED, (int)$session->getId());
        if ($ok && $task->task_type === StoreOrderTasksRepository::TYPE_DELIVERY) {
            $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_DELIVERED);
            StoreDeliveryNotificationService::notify((int)$order->id_owner, $orderId, 'delivered', 'Admin');
        }
        MessageUtil::setMessage($ok ? 'Store work item approved.' : 'Unable to approve Store work item.');
        LocationUtils::reload();
    }

    MessageUtil::setMessage('Invalid action.');
    LocationUtils::reload();
});

$router->run();


