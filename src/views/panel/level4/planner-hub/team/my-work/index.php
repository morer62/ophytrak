<?php

use App\Repositories\OrdersTeamTasksRepository;
use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StoreOrdersRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\OphytrackDriverPayoutRepository;
use App\Repositories\StorePackagesRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\LoginService;
use App\Services\StoreDeliveryNotificationService;
use App\Services\StoreLogisticsWorkflowService;
use App\Services\TranslationService;
use App\Services\UserWorkspaceContextService;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $teamContext = (new UserWorkspaceContextService())->getTeamContext($user);
    $ownerId = (int)($teamContext['selectedOwnerId'] ?? $user->getOwner());
    $businessProfile = $ownerId > 0 ? (new InstitutionProfileRepository())->getByOwner($ownerId) : null;

    $serviceTaskRepo = new OrdersTeamTasksRepository();
    $serviceTasks = $ownerId > 0
        ? $serviceTaskRepo->getForUserAndOwnerDetailed((int)$user->getId(), $ownerId)
        : [];
    $serviceOrderIds = array_values(array_unique(array_filter(array_map(static fn($task) => (int)($task->id_order ?? 0), $serviceTasks))));
    $serviceTeamContacts = $ownerId > 0
        ? $serviceTaskRepo->getAssigneesByOrders($ownerId, $serviceOrderIds)
        : [];
    $storeTaskRepo = new StoreOrderTasksRepository();
    $storeTasks = $ownerId > 0
        ? $storeTaskRepo->getForAssignee($ownerId, (int)$user->getId())
        : [];
    $workTab = trim((string)($_GET['tab'] ?? 'active'));
    if (!in_array($workTab, ['active','incidents','closed'], true)) $workTab = 'active';
    $storeTaskCounts = ['active'=>0,'incidents'=>0,'closed'=>0];
    foreach ($storeTasks as $candidate) {
        $orderStatus = strtoupper((string)($candidate->order_status ?? ''));
        $taskStatus = strtoupper((string)($candidate->status ?? ''));
        $cancel = strtoupper((string)($candidate->cancellation_status ?? 'NONE'));
        $tab = ($cancel === 'REQUESTED' || in_array($orderStatus, ['DELIVERY_ATTEMPTED','RETURNED_TO_BUSINESS','RETURN_REQUESTED','RETURN_APPROVED'], true)) ? 'incidents' : ((in_array($orderStatus, ['DELIVERED','COMPLETED','CANCELLED','RETURNED','RETURN_REJECTED','CLOSED'], true) || in_array($taskStatus, ['COMPLETED','CANCELED'], true)) ? 'closed' : 'active');
        $candidate->lifecycle_tab = $tab;
        $storeTaskCounts[$tab]++;
    }
    $storeTasks = array_values(array_filter($storeTasks, static fn($task) => $task->lifecycle_tab === $workTab));
    $storeOrderIds = array_values(array_unique(array_map(static fn($task) => (int)$task->id_store_order, $storeTasks)));
    $storeTeamContacts = $ownerId > 0
        ? $storeTaskRepo->getAssigneesByOrders($ownerId, $storeOrderIds)
        : [];

    foreach ($storeTasks as $task) {
        $task->can_chat_with_client = (int)($task->allow_chat_with_client ?? 0) === 1
            && (int)$user->getAllowChatWithClients() === 1;
        $task->admin_contact_id = $ownerId;
        $task->order_team_contacts = array_values(array_filter(
            $storeTeamContacts[(int)$task->id_store_order] ?? [],
            static fn($member) => (int)$member->id !== (int)$user->getId()
        ));
    }

    foreach ($serviceTasks as $task) {
        $task->can_chat_with_client = (int)$user->getAllowChatWithClients() === 1 && (int)($task->id_client ?? 0) > 0;
        $task->admin_contact_id = $ownerId;
        $task->order_team_contacts = array_values(array_filter(
            $serviceTeamContacts[(int)($task->id_order ?? 0)] ?? [],
            static fn($member) => (int)$member->id !== (int)$user->getId()
        ));
    }

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'teamContext' => $teamContext,
        'serviceTasks' => $serviceTasks,
        'storeTasks' => $storeTasks,
        'businessProfile' => $businessProfile,
        'workTab' => $workTab,
        'storeTaskCounts' => $storeTaskCounts,
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $teamContext = (new UserWorkspaceContextService())->getTeamContext($user);
    $ownerId = (int)($teamContext['selectedOwnerId'] ?? $user->getOwner());
    $taskId = (int)($_POST['task_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));

    $tasksRepo = new StoreOrderTasksRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $ordersRepo = new StoreOrdersRepository();
    $locationRepo = new StoreDeliveryLocationLogsRepository();
    $logisticsService = new StoreLogisticsWorkflowService();
    $task = $tasksRepo->getOneForAssignee($taskId, $ownerId, (int)$user->getId());

    if (!$task) {
        MessageUtil::setMessage('Store task not found or not assigned to your workspace.');
        LocationUtils::reload();
    }

    $lat = trim((string)($_POST['location_lat'] ?? ''));
    $lng = trim((string)($_POST['location_long'] ?? ''));
    $locationMetadata = [
        'accuracy' => $_POST['location_accuracy'] ?? null,
        'platform' => trim((string)($_POST['location_platform'] ?? 'web_mobile')),
        'source' => trim((string)($_POST['location_source'] ?? 'browser_geolocation')),
        'permission_status' => trim((string)($_POST['location_permission_status'] ?? 'granted')),
        'device_id' => trim((string)($_POST['location_device_id'] ?? '')),
        'context' => trim((string)($_POST['location_context'] ?? 'store_delivery')),
    ];
    $needsLocation = (int)($task->requires_location ?? 0) === 1
        || in_array($action, ['out_for_delivery', 'arrived', 'delivered', 'update_location'], true);

    if ($needsLocation && (!is_numeric($lat) || !is_numeric($lng))) {
        MessageUtil::setMessage('You need to allow location access to start this task.');
        LocationUtils::reload();
    }

    $eventType = 'LOCATION_UPDATE';
    $ok = false;
    $notifyDeliveryStatus = null;

    if ($action === 'start') {
        if ((string)$task->task_type === StoreOrderTasksRepository::TYPE_PREPARATION) {
            $ok = $logisticsService->startPreparation($ownerId, (int)$task->id_store_order, $taskId);
        } else {
            $ok = $tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_IN_PROGRESS);
        }
        $eventType = 'TASK_START';
    } elseif ($action === 'out_for_delivery') {
        if (!$logisticsService->canStartDelivery($ownerId, (int)$task->id_store_order)) {
            MessageUtil::setMessage(TranslationService::trans('store_logistics.order_not_ready_delivery'));
            LocationUtils::reload();
        }
        if (!FileUtils::hasFile($_FILES, 'dispatch_proof')) {
            MessageUtil::setMessage('Scan the order QR or upload a clear package photo before marking this order as sent.');
            LocationUtils::reload();
        }
        $file = $_FILES['dispatch_proof'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array((string)($file['type'] ?? ''), $allowedTypes, true) || (int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
            MessageUtil::setMessage('Dispatch proof must be a JPG, PNG or WEBP image up to 5 MB.');
            LocationUtils::reload();
        }
        $photoUrl = FileUtils::saveFile($file, 'store-dispatch-proofs');
        $notes = trim((string)($_POST['notes'] ?? ''));
        $ok = $tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_IN_PROGRESS)
            && $ordersRepo->updateStatus((int)$task->id_store_order, StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY)
            && $workflowRepo->markDeliveryDispatch((int)$task->id_store_order, (int)$user->getId(), $photoUrl, $notes);
        $eventType = 'OUT_FOR_DELIVERY';
        if ($ok) {
            $notifyDeliveryStatus = 'out_for_delivery';
        }
    } elseif ($action === 'arrived') {
        $ok = true;
        $eventType = 'DELIVERY_ATTEMPTED';
    } elseif ($action === 'delivery_attempted') {
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($notes === '') {
            MessageUtil::setMessage(TranslationService::trans('store_logistics.attempt_notes_required'));
            LocationUtils::reload();
        }
        $ok = $logisticsService->markDeliveryAttempted($ownerId, (int)$task->id_store_order, $taskId, (int)$user->getId(), $notes);
        $eventType = 'RETURNED_TO_BUSINESS';
    } elseif ($action === 'returned_to_business') {
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($notes === '') {
            MessageUtil::setMessage(TranslationService::trans('store_logistics.attempt_notes_required'));
            LocationUtils::reload();
        }
        $ok = $logisticsService->markReturnedToBusiness($ownerId, (int)$task->id_store_order, $taskId, (int)$user->getId(), $notes);
        $eventType = 'ARRIVED';
    } elseif ($action === 'delivered') {
        if ((string)($task->order_status ?? '') !== StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY) {
            MessageUtil::setMessage(TranslationService::trans('store_logistics.start_delivery_before_delivered'));
            LocationUtils::reload();
        }
        $notes = trim((string)($_POST['notes'] ?? ''));
        $receiver = [
            'delivery_receiver_type' => trim((string)($_POST['receiver_type'] ?? '')),
            'delivery_receiver_name' => trim((string)($_POST['receiver_name'] ?? '')),
            'delivery_document_type' => trim((string)($_POST['document_type'] ?? '')),
            'delivery_document_number' => trim((string)($_POST['document_number'] ?? '')),
        ];
        if ($receiver['delivery_receiver_type'] === '' || $receiver['delivery_receiver_name'] === '' || $receiver['delivery_document_type'] === '' || $receiver['delivery_document_number'] === '') {
            MessageUtil::setMessage(TranslationService::trans('store_logistics.receiver_proof_required'));
            LocationUtils::reload();
        }
        if ((int)($task->allow_team_close_delivery ?? 0) !== 1) {
            $ok = $tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_WAITING_REVIEW, null, $notes);
            $eventType = 'ARRIVED';
        } else {
            $photoUrl = '';
            if (!FileUtils::hasFile($_FILES, 'delivery_proof')) {
                MessageUtil::setMessage('Delivery proof photo is required before marking this order delivered.');
                LocationUtils::reload();
            }
            if (FileUtils::hasFile($_FILES, 'delivery_proof')) {
                $file = $_FILES['delivery_proof'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array((string)($file['type'] ?? ''), $allowedTypes, true) || (int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
                    MessageUtil::setMessage('Delivery proof must be a JPG, PNG or WEBP image up to 5 MB.');
                    LocationUtils::reload();
                }
                $photoUrl = FileUtils::saveFile($file, 'store-delivery-proofs');
            }
            $ok = $tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_COMPLETED, (int)$user->getId(), $notes)
                && $ordersRepo->updateStatus((int)$task->id_store_order, StoreOrdersRepository::STATUS_DELIVERED);
            if ($ok) {
                $workflowRepo->markDeliveryProof((int)$task->id_store_order, (int)$user->getId(), $photoUrl, $notes, true, $receiver);
                $package=(new StorePackagesRepository())->ensurePrimary($ownerId,(int)$task->id_store_order,StoreOrdersRepository::STATUS_DELIVERED);
                if($package)(new OphytrackDriverPayoutRepository())->recordDelivered((int)$package->id,(int)$user->getId());
                $notifyDeliveryStatus = 'delivered';
            }
            $eventType = 'DELIVERED';
        }
    } elseif ($action === 'complete') {
        if ((int)($task->allow_assignee_complete ?? 0) !== 1) {
            MessageUtil::setMessage('This task requires admin review before completion.');
            LocationUtils::reload();
        }
        if ((string)$task->task_type === StoreOrderTasksRepository::TYPE_PREPARATION) {
            $ok = $logisticsService->completePreparation($ownerId, (int)$task->id_store_order, $taskId, (int)$user->getId(), trim((string)($_POST['notes'] ?? '')));
        } else {
            $ok = $tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_COMPLETED, (int)$user->getId(), trim((string)($_POST['notes'] ?? '')));
        }
    } elseif ($action === 'update_location') {
        $ok = true;
    }

    if ($ok && is_numeric($lat) && is_numeric($lng)) {
        $locationRepo->addLocation($ownerId, (int)$task->id_store_order, $taskId, (int)$user->getId(), $eventType, (float)$lat, (float)$lng, $locationMetadata);
        $workflowRepo->updateLatestLocation((int)$task->id_store_order, (int)$user->getId(), (float)$lat, (float)$lng);
    }

    if ($ok && $notifyDeliveryStatus !== null) {
        $driverName = trim((string)$user->getName() . ' ' . (string)$user->getLastname()) ?: TranslationService::trans('store_logistics.delivery_team');
        StoreDeliveryNotificationService::notify($ownerId, (int)$task->id_store_order, $notifyDeliveryStatus, $driverName);
    }

    if ($action === 'update_location' && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => $ok]);
        exit;
    }

    MessageUtil::setMessage($ok ? 'Work item updated.' : 'Unable to update this work item.');
    LocationUtils::reload();
});

$router->run();

function sendStoreDispatchNotification(int $ownerId, object $task, object $driver, string $photoUrl, string $notes, float $lat, float $lng): void
{
    sendStoreDeliveryEmail($ownerId, $task, $driver, $photoUrl, $notes, $lat, $lng, 'out_for_delivery');
}

function sendStoreDeliveredNotification(int $ownerId, object $task, object $driver, string $photoUrl, string $notes, float $lat, float $lng): void
{
    sendStoreDeliveryEmail($ownerId, $task, $driver, $photoUrl, $notes, $lat, $lng, 'delivered');
}

function sendStoreDeliveryEmail(int $ownerId, object $task, object $driver, string $photoUrl, string $notes, float $lat, float $lng, string $status): void
{
    try {
        $ordersRepo = new StoreOrdersRepository();
        $userRepo = new UserRepository();
        $order = $ordersRepo->getById((int)$task->id_store_order);
        if (!$order) {
            return;
        }

        $owner = $userRepo->getOneWithoutOwnership(['id' => $ownerId]);
        $client = !empty($order->id_user) ? $userRepo->getOneWithoutOwnership(['id' => (int)$order->id_user]) : null;
        $clientEmail = trim((string)($order->guest_email ?? ''));
        if ($clientEmail === '' && $client && !empty($client->email)) {
            $clientEmail = (string)$client->email;
        }
        $adminEmail = $owner && !empty($owner->email) ? (string)$owner->email : '';
        $publicUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/') . '/store/order-access?token=' . urlencode((string)$order->public_token);
        $mapsUrl = 'https://www.google.com/maps?q=' . rawurlencode((string)$lat . ',' . (string)$lng);
        $driverFirstName = method_exists($driver, 'getName') ? (string)$driver->getName() : '';
        $driverLastName = method_exists($driver, 'getLastname') ? (string)$driver->getLastname() : '';
        $driverName = trim($driverFirstName . ' ' . $driverLastName) ?: 'Delivery team';
        $isDelivered = $status === 'delivered';
        $safeNotes = $notes !== '' ? nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')) : ($isDelivered ? 'The order has been marked delivered.' : 'The order has been sent and tracking is now active.');
        $headline = $isDelivered ? 'Your order was delivered' : 'Your order is out for delivery';
        $adminHeadline = $isDelivered ? 'Store order #' . (int)$order->id . ' was delivered' : 'Store order #' . (int)$order->id . ' was sent';
        $body = '<h2>' . $headline . '</h2>'
            . '<p>Store order #' . (int)$order->id . ' was ' . ($isDelivered ? 'marked delivered' : 'marked as sent') . ' by ' . htmlspecialchars($driverName, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p><strong>Message:</strong><br>' . $safeNotes . '</p>'
            . '<p><a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '">View order, payment status and delivery tracking</a></p>'
            . '<p><a href="' . htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') . '">' . ($isDelivered ? 'View delivery location' : 'View dispatch location') . '</a></p>'
            . '<p><img src="' . htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Dispatch proof" style="max-width:520px;width:100%;border-radius:14px;border:1px solid #e5e7eb;"></p>';

        $email = new EmailService($ownerId);
        if ($clientEmail !== '') {
            $email->sendSimpleEmail($clientEmail, $headline . ' - order #' . (int)$order->id, $body, true);
        }
        if ($adminEmail !== '' && strtolower($adminEmail) !== strtolower($clientEmail)) {
            $email->sendSimpleEmail($adminEmail, $adminHeadline, $body, true);
        }
    } catch (\Throwable $e) {
        error_log('Store delivery notification failed: ' . $e->getMessage());
    }
}
