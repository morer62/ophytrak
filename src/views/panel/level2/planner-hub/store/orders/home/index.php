<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreOrdersRepository;
use App\Repositories\StoreOrderItemsRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StorePaymentsRepository;
use App\Repositories\StoreUserRolesRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\StorePackagesRepository;
use App\Repositories\CarrierPackageRepository;
use App\Repositories\CarrierRelationshipRepository;
use App\Services\LoginService;
use App\Services\ProductProfileService;
use App\Services\StoreDeliveryNotificationService;
use App\Services\StoreLogisticsWorkflowService;
use App\Services\StoreManualOrderService;
use App\Services\StoreOrderLifecycleService;
use App\Utils\LocationUtils;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $ordersRepo = new StoreOrdersRepository();
    $itemsRepo = new StoreOrderItemsRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $paymentsRepo = new StorePaymentsRepository();
    $storeRolesRepo = new StoreUserRolesRepository();
    $tasksRepo = new StoreOrderTasksRepository();
    $locationRepo = new StoreDeliveryLocationLogsRepository();
    $packagesRepo = new StorePackagesRepository();
    $carrierRepo = new CarrierPackageRepository();
    $carrierRelationshipRepo = new CarrierRelationshipRepository();
    $session = LoginService::getSession();

    $ownerId = (int)$session->getOwner();
    $isCarrierOrganization = $carrierRepo->isCarrier($ownerId);
    $profile = $ownerId > 0 ? (new InstitutionProfileRepository())->getByOwner($ownerId) : null;

    $weekStartInput = trim($_GET['week_start'] ?? '');
    $weekEndInput = trim($_GET['week_end'] ?? '');
    $paymentStatus = trim($_GET['payment_status'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $email = trim($_GET['email'] ?? '');
    $packageQuery = trim((string)($_GET['package'] ?? ''));
    $packageSearch = null;
    $packageSearchAttempted = $packageQuery !== '';
    if ($packageSearchAttempted) {
        try { $packageSearch = $packagesRepo->search($ownerId, $packageQuery); } catch (Throwable $e) { error_log('Package search failed: ' . $e->getMessage()); }
    }
    $lifecycleTab = trim((string)($_GET['tab'] ?? StoreOrderLifecycleService::TAB_ACTIVE));
    if (!in_array($lifecycleTab, [StoreOrderLifecycleService::TAB_ACTIVE, StoreOrderLifecycleService::TAB_INCIDENTS, StoreOrderLifecycleService::TAB_CLOSED], true)) {
        $lifecycleTab = StoreOrderLifecycleService::TAB_ACTIVE;
    }

    $today = new DateTimeImmutable('now');
    $weekStart = $weekStartInput !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $weekStartInput) : null;
    $weekEnd = $weekEndInput !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $weekEndInput) : null;
    if ($weekStartInput !== '' || $weekEndInput !== '') {
        $weekStart = $weekStart ?: $today->modify('-90 days');
        $weekEnd = $weekEnd ?: $today;
        $orders = $ordersRepo->getByOwnerAndDateRange($ownerId, $weekStart->setTime(0, 0)->format('Y-m-d H:i:s'), $weekEnd->setTime(23, 59, 59)->format('Y-m-d H:i:s'), 300);
    } else {
        $orders = $ordersRepo->getAllByOwner($ownerId, 300);
    }

    if ($paymentStatus !== '') {
        $orders = array_values(array_filter($orders, function ($o) use ($paymentStatus) {
            return strtoupper((string)$o->payment_status) === strtoupper($paymentStatus);


        }));
    }
    if ($status !== '') {
        $orders = array_values(array_filter($orders, function ($o) use ($status) {
            return strtoupper((string)$o->status) === strtoupper($status);


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
    $associatedCarriers = $carrierRelationshipRepo->getAssociated($ownerId);
    $associatedCarriersById = [];
    foreach ($associatedCarriers as $carrier) $associatedCarriersById[(int)$carrier->carrier_owner_id] = (string)$carrier->company_name;

    $lifecycleCounts = ['active' => 0, 'incidents' => 0, 'closed' => 0];
    foreach ($orders as $candidate) {
        $candidateTab = StoreOrderLifecycleService::tabFor($candidate);
        $lifecycleCounts[$candidateTab]++;
    }
    $orders = array_values(array_filter($orders, static fn($o) => StoreOrderLifecycleService::tabFor($o) === $lifecycleTab));
    $orderIds = array_map(fn($o) => (int)$o->id, $orders);
    $workflowMap = $workflowRepo->getMapByOrders($orderIds);
    $latestLocationMap = $locationRepo->getLatestMapByOrders($orderIds, $ownerId);
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
        $order->paid_total = $paymentsRepo->getPaidTotalByOrder((int)$order->id, $ownerId);
        $order->remaining_balance = max(0.0, (float)$order->total - $order->paid_total);
        $order->latest_location = $latestLocationMap[(int)$order->id] ?? null;
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
        $order->primary_package = $packagesRepo->ensurePrimary($ownerId, (int)$order->id, (string)$order->status);
        $order->external_carrier_owner_id = ((string)($order->primary_package->logistics_mode ?? '') === 'EXTERNAL_CARRIER') ? (int)($order->primary_package->current_custodian_owner_id ?? 0) : 0;
        $order->external_carrier_name = $order->external_carrier_owner_id > 0 ? ($associatedCarriersById[$order->external_carrier_owner_id] ?? '') : '';
        $order->assignment_required = in_array(strtoupper((string)$order->status), [
            StoreOrdersRepository::STATUS_IN_PREPARATION,
            StoreOrdersRepository::STATUS_READY_FOR_DELIVERY,
            StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY,
        ], true) && ($order->kitchen_user_id <= 0 || ($order->delivery_user_id <= 0 && $order->external_carrier_owner_id <= 0));
    }
    unset($order);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "orders" => $orders,
        "deliveryUsers" => $deliveryUsers,
        "teamUsers" => $teamUsers,
        "managerUserId" => (int)$session->getId(),
        "managerUserName" => trim($session->getName() . ' ' . $session->getLastname()),
        "assignmentRequiredOrderId" => (int)($_GET['assign_order'] ?? 0),
        "lifecycleTab" => $lifecycleTab,
        "lifecycleCounts" => $lifecycleCounts,
        "businessProfile" => $profile,
        "packageQuery" => $packageQuery,
        "packageSearch" => $packageSearch,
        "packageSearchAttempted" => $packageSearchAttempted,
        "carrierCustodyRequests" => $carrierRepo->pendingForSeller($ownerId),
        "isCarrierOrganization" => $isCarrierOrganization,
        "carrierPackages" => $isCarrierOrganization ? $carrierRepo->getForCarrier($ownerId) : [],
        "carrierOrganizations" => $associatedCarriers,
        "filters" => [
            "week_start" => $weekStartInput,
            "week_end" => $weekEndInput,
            "payment_status" => $paymentStatus,
            "status" => $status,
            "email" => $email
        ],
        "public_store_url" => $ownerId > 0 ? ('/store/home?owner=' . $ownerId) : '',
        "public_profile_url" => $profile && !empty($profile->slug) ? ('/business-profile?slug=' . urlencode((string)$profile->slug)) : ''
    ]);
});

$router->post(function () {
    $ordersRepo = new StoreOrdersRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $paymentsRepo = new StorePaymentsRepository();
    $tasksRepo = new StoreOrderTasksRepository();
    $storeRolesRepo = new StoreUserRolesRepository();
    $session = LoginService::getSession();

    $action = $_POST['action'] ?? '';
    if ($action === 'carrier_custody_decision') {
        $requestId=(int)($_POST['request_id']??0);$decision=strtoupper(trim((string)($_POST['decision']??'')));
        [$ok,$message]=(new CarrierPackageRepository())->decideRequest($requestId,(int)$session->getOwner(),(int)$session->getId(),$decision==='APPROVE');
        MessageUtil::setMessage($message);LocationUtils::reload();
    }
    if ($action === 'carrier_assign_employee') {
        [$ok,$message]=(new CarrierPackageRepository())->assignEmployee((int)($_POST['package_id']??0),(int)$session->getOwner(),(int)($_POST['employee_id']??0),(int)$session->getId(),trim((string)($_POST['carrier_role']??'')));MessageUtil::setMessage($message);LocationUtils::reload();
    }
    if ($action === 'seller_assign_carrier') {
        [$ok,$message]=(new CarrierPackageRepository())->sellerAssignCarrier((int)($_POST['package_id']??0),(int)$session->getOwner(),(int)($_POST['carrier_owner_id']??0),(int)$session->getId());MessageUtil::setMessage($message);LocationUtils::reload();
    }
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($orderId <= 0) {
        MessageUtil::setMessage('Invalid order id.');
        LocationUtils::reload();
    }
    $order = $ordersRepo->getById($orderId);
    $sessionOwnerId = (int)$session->getOwner();
    if (!$order || (int)($order->id_owner ?? 0) !== $sessionOwnerId) {
        MessageUtil::setMessage('Order not found in this workspace.');
        LocationUtils::reload();
    }

    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        if ($newStatus === '') {
            MessageUtil::setMessage('Select a valid status.');
            LocationUtils::reload();
        }

        $workflow = $workflowRepo->getByOrder($orderId);
        $prepUserId = (int)($workflow->kitchen_user_id ?? 0);
        $deliveryUserId = (int)($workflow->delivery_user_id ?? 0);
        $requiresPrep = in_array($newStatus, [StoreOrdersRepository::STATUS_IN_PREPARATION, StoreOrdersRepository::STATUS_READY_FOR_DELIVERY], true);
        $requiresDelivery = $newStatus === StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY;
        $hasExternalCarrier = (new CarrierPackageRepository())->hasExternalCarrierForOrder($sessionOwnerId, $orderId);
        if (($requiresPrep && $prepUserId <= 0) || ($requiresDelivery && $deliveryUserId <= 0 && !$hasExternalCarrier)) {
            MessageUtil::setMessage('Assign the responsible team member before changing this order status.', 'Assignment required', 'warning');
            LocationUtils::redirectInternal('panel/planner-hub/store/orders/home?assign_order=' . $orderId);
        }
        $ok = $ordersRepo->updateStatus($orderId, $newStatus);
        MessageUtil::setMessage($ok ? 'Order status updated.' : 'Failed to update order status.');
        LocationUtils::reload();
    }

    if ($action === 'resolve_cancellation') {
        $decision = strtoupper(trim((string)($_POST['decision'] ?? '')));
        $message = trim((string)($_POST['decision_notes'] ?? ''));
        if (strtoupper((string)($order->cancellation_status ?? 'NONE')) !== 'REQUESTED') {
            MessageUtil::setMessage('This order does not have a pending cancellation request.');
            LocationUtils::reload();
        }
        $nextStatus = $decision === 'REFUNDED'
            ? StoreOrdersRepository::STATUS_CANCELLED
            : ($decision === 'RESEND'
                ? StoreOrdersRepository::STATUS_IN_PREPARATION
                : (string)($order->cancellation_previous_status ?? StoreOrdersRepository::STATUS_IN_PREPARATION));
        $ok = $ordersRepo->resolveCancellation($orderId, $decision, $message, $nextStatus);
        if ($ok && $decision === 'REFUNDED') $ok = $ordersRepo->markAsRefunded($orderId);
        if ($ok) {
            try { StoreDeliveryNotificationService::notify($sessionOwnerId, $orderId, 'cancellation_' . strtolower($decision), trim($session->getName() . ' ' . $session->getLastname())); } catch (Throwable $e) { error_log('Cancellation notification failed: ' . $e->getMessage()); }
        }
        MessageUtil::setMessage($ok ? 'Cancellation request resolved.' : 'Unable to resolve the cancellation request.');
        LocationUtils::redirectInternal('panel/planner-hub/store/orders/home?tab=' . ($decision === 'REFUNDED' ? 'closed' : 'active'));
    }

    if ($action === 'review_return') {
        $decision = strtoupper(trim((string)($_POST['decision'] ?? '')));
        $message = trim((string)($_POST['decision_notes'] ?? ''));
        $map = ['APPROVE' => StoreOrdersRepository::STATUS_RETURN_APPROVED, 'REJECT' => StoreOrdersRepository::STATUS_RETURN_REJECTED, 'RECEIVED_REFUND' => StoreOrdersRepository::STATUS_RETURNED, 'RECEIVED_RESEND' => StoreOrdersRepository::STATUS_IN_PREPARATION];
        if (!isset($map[$decision])) { MessageUtil::setMessage('Select a valid return decision.'); LocationUtils::reload(); }
        $target = $map[$decision];
        $ok = $decision === 'RECEIVED_RESEND' ? $ordersRepo->updateStatus($orderId, $target) : $ordersRepo->reviewReturn($orderId, $target, $message);
        if ($ok && $decision === 'RECEIVED_REFUND') $ok = (new \App\Repositories\StoreProductsRepository())->restoreStockForReturnedOrder($sessionOwnerId, $orderId, (int)$session->getId()) && $ordersRepo->markAsRefunded($orderId);
        MessageUtil::setMessage($ok ? 'Return workflow updated.' : 'Unable to update the return workflow.');
        LocationUtils::redirectInternal('panel/planner-hub/store/orders/home?tab=' . (in_array($decision, ['REJECT','RECEIVED_REFUND'], true) ? 'closed' : 'active'));
    }

    if ($action === 'manual_logistics_update') {
        $isAsync = (string)($_POST['async'] ?? '') === '1'
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $requestId = 'manual-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $respondManual = static function (bool $success, string $message, array $data = [], ?array $debug = null) use ($isAsync, $requestId): void {
            if (!$isAsync) {
                MessageUtil::setMessage($message);
                LocationUtils::reload();
            }
            if (ob_get_level() > 0 && ob_get_length() !== false) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($success ? 200 : 422);
            echo json_encode(['success'=>$success,'message'=>$message,'request_id'=>$requestId,'data'=>$data,'debug'=>$debug], JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
            exit;
        };
        $newStatus = strtoupper(trim((string)($_POST['status'] ?? '')));
        $notes = trim((string)($_POST['manual_logistics_notes'] ?? ''));
        $allowedStatuses = [
            StoreOrdersRepository::STATUS_IN_PREPARATION,
            StoreOrdersRepository::STATUS_READY_FOR_DELIVERY,
            StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY,
            StoreOrdersRepository::STATUS_DELIVERY_ATTEMPTED,
            StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS,
            StoreOrdersRepository::STATUS_REDELIVERY_SCHEDULED,
            StoreOrdersRepository::STATUS_DELIVERED,
        ];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $respondManual(false, 'Select a valid manual logistics status.');
        }

        $photoUrl = '';
        if (\App\Utils\FileUtils::hasFile($_FILES, 'manual_delivery_photo')) {
            $file = $_FILES['manual_delivery_photo'];
            if (!in_array((string)($file['type'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)
                || (int)($file['size'] ?? 0) > 8 * 1024 * 1024) {
                $respondManual(false, 'Delivery photo must be JPG, PNG or WEBP up to 8 MB.');
            }
            $photoUrl = \App\Utils\FileUtils::saveFile($file, 'store-delivery-proofs');
        }
        if (in_array($newStatus, [StoreOrdersRepository::STATUS_DELIVERED, StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS], true) && $photoUrl === '') {
            $respondManual(false, 'A photo is required when an order is delivered or returned to the business.');
        }

        $ownerId = (int)$order->id_owner;
        $workflow = $workflowRepo->getByOrder($orderId);
        if (!$workflow) {
            $workflowRepo->upsertOperations($ownerId, $orderId, null, null, false, true);
        }

        try {
            $ok = $ordersRepo->updateStatus($orderId, $newStatus);
            if ($ok && $newStatus === StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY) {
                $ok = $workflowRepo->markDeliveryDispatch($orderId, (int)$session->getId(), null, $notes);
                if ($ok) { try { StoreDeliveryNotificationService::notify($ownerId, $orderId, 'out_for_delivery', 'Admin manual update'); } catch (\Throwable $notificationError) { error_log($requestId . ' notification warning: ' . $notificationError->getMessage()); } }
            } elseif ($ok && $newStatus === StoreOrdersRepository::STATUS_DELIVERED) {
                $ok = $workflowRepo->markDeliveryProof($orderId, (int)$session->getId(), $photoUrl, $notes, true);
                if ($ok) { try { StoreDeliveryNotificationService::notify($ownerId, $orderId, 'delivered', 'Admin manual update'); } catch (\Throwable $notificationError) { error_log($requestId . ' notification warning: ' . $notificationError->getMessage()); } }
            } elseif ($ok && in_array($newStatus, [StoreOrdersRepository::STATUS_DELIVERY_ATTEMPTED, StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS, StoreOrdersRepository::STATUS_REDELIVERY_SCHEDULED], true)) {
                $ok = $workflowRepo->markManualStatusEvidence($orderId, (int)$session->getId(), $photoUrl ?: null, $notes);
            }
            $respondManual($ok, $ok ? 'Manual logistics status updated. No live GPS location was generated.' : 'Unable to update manual logistics status.', ['order_id'=>$orderId,'status'=>$newStatus,'photo_url'=>$photoUrl]);
        } catch (\Throwable $e) {
            error_log($requestId . ' Manual logistics update failed: ' . $e->getMessage());
            $respondManual(false, 'Unable to update manual logistics status.', [], ['exception'=>get_class($e),'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
        }
    }

    if ($action === 'update_payment_status') {
        $newPayment = trim($_POST['payment_status'] ?? '');
        $ok = false;
        if ($newPayment === StoreOrdersRepository::PAYMENT_PAID) {
            $manualMethod = trim((string)($_POST['manual_payment_method'] ?? 'other'));
            $manualReference = trim((string)($_POST['manual_payment_reference'] ?? ''));
            $manualNotes = trim((string)($_POST['manual_payment_notes'] ?? ''));
            $manualAmount = round((float)($_POST['manual_payment_amount'] ?? 0), 2);
            $alreadyPaid = $paymentsRepo->getPaidTotalByOrder($orderId, (int)$order->id_owner);
            $remainingBefore = max(0.0, round((float)$order->total - $alreadyPaid, 2));
            $proofUrl = '';
            $allowedManualMethods = ['cash', 'bank_transfer', 'zelle', 'other'];
            if (!in_array($manualMethod, $allowedManualMethods, true) || $manualReference === '' || $manualAmount <= 0 || $manualAmount > $remainingBefore + 0.01) {
                MessageUtil::setMessage('A valid manual payment method and reference are required.');
                LocationUtils::reload();
            }
            if (\App\Utils\FileUtils::hasFile($_FILES, 'manual_payment_proof')) {
                $file = $_FILES['manual_payment_proof'];
                if (!in_array((string)($file['type'] ?? ''), ['image/jpeg','image/png','image/webp','application/pdf'], true) || (int)($file['size'] ?? 0) > 5*1024*1024) {
                    MessageUtil::setMessage('Payment proof must be a JPG, PNG, WEBP or PDF up to 5 MB.'); LocationUtils::reload();
                }
                $proofUrl = \App\Utils\FileUtils::saveFile($file, 'store-manual-payments');
            }
            $willBePaid = ($alreadyPaid + $manualAmount) >= ((float)$order->total - 0.01);
            $paymentRecorded = $paymentsRepo->addCompatible([
                'id_owner' => (int)$order->id_owner,
                'id_store_order' => $orderId,
                'id_user' => !empty($order->id_user) ? (int)$order->id_user : null,
                'payment_method' => $manualMethod,
                'payment_type' => $willBePaid ? StorePaymentsRepository::TYPE_FULL : StorePaymentsRepository::TYPE_PARTIAL,
                'external_reference' => $manualReference,
                'amount' => $manualAmount,
                'currency' => ProductProfileService::operationalCurrency(),
                'status' => StorePaymentsRepository::STATUS_PAID,
                'payer_name' => $order->guest_name ?? null,
                'payer_email' => $order->guest_email ?? null,
                'payment_source' => 'admin_order_payment_update',
                'proof_url' => $proofUrl,
                'notes' => $manualNotes,
                'created_by' => (int)$session->getId(),
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
            $ok = $paymentRecorded && (!$willBePaid || $ordersRepo->markAsPaid($orderId));
            if ($ok && in_array((string)$order->status, [
                StoreOrdersRepository::STATUS_NEW,
                StoreOrdersRepository::STATUS_CONFIRMED,
                StoreOrdersRepository::STATUS_PROCESSING,
            ], true)) {
                $workflow = $workflowRepo->getByOrder($orderId);
                if (!empty($workflow->kitchen_user_id)) {
                    $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_IN_PREPARATION);
                } else {
                    MessageUtil::setMessage('Payment was recorded. Assign a preparation responsible before starting preparation.', 'Assignment required', 'warning');
                    LocationUtils::redirectInternal('panel/planner-hub/store/orders/home?assign_order=' . $orderId);
                }
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
        $isAsync = (string)($_POST['async'] ?? '') === '1'
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $requestId = 'assign-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $respondAssignment = static function (bool $success, string $message, array $data = [], ?array $debug = null) use ($isAsync, $requestId): void {
            if (!$isAsync) {
                MessageUtil::setMessage($message);
                LocationUtils::reload();
            }
            if (ob_get_level() > 0 && ob_get_length() !== false) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($success ? 200 : 422);
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'request_id' => $requestId,
                'data' => $data,
                'debug' => $debug,
            ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            exit;
        };
        $deliveryMode = (string)($_POST['delivery_assignment_type'] ?? 'own_team');
        $deliveryUserId = isset($_POST['delivery_user_id']) && $_POST['delivery_user_id'] !== ''
            ? (int)$_POST['delivery_user_id']
            : null;
        $kitchenUserId = isset($_POST['kitchen_user_id']) && $_POST['kitchen_user_id'] !== ''
            ? (int)$_POST['kitchen_user_id']
            : null;
        $ownerId = (int)($order->id_owner ?? 0);
        if ($ownerId <= 0) {
            $respondAssignment(false, 'Invalid order owner.');
        }
        $allowClose = isset($_POST['allow_team_close_delivery']);
        $allowChat = isset($_POST['allow_chat_with_client']);
        $actorId = (int)$session->getId();
        $canAssignUser = static fn(?int $userId): bool => !$userId || $userId === $actorId || $storeRolesRepo->userBelongsToOwner($ownerId, $userId);
        if (!in_array($deliveryMode, ['own_team','carrier'], true)) $respondAssignment(false, 'Select a valid delivery method.');
        if (!$canAssignUser($deliveryUserId) || !$canAssignUser($kitchenUserId)) {
            $respondAssignment(false, 'Select team members from this workspace.');
        }
        try {
            $carrierOwnerId = (int)($_POST['carrier_owner_id'] ?? 0);
            $carrierRelationships = new CarrierRelationshipRepository();
            if ($deliveryMode === 'carrier' && !$carrierRelationships->isAssociated($ownerId, $carrierOwnerId)) {
                $respondAssignment(false, 'Select a shipping company associated with this business.');
            }
            if ($deliveryMode === 'carrier') $deliveryUserId = null;
            $ok = (new StoreLogisticsWorkflowService())->assignOperations($ownerId, $orderId, $kitchenUserId, $deliveryUserId, $allowClose, $allowChat, $actorId);
            $package = (new StorePackagesRepository())->ensurePrimary($ownerId, $orderId, (string)$order->status);
            if ($ok && $deliveryMode === 'carrier') {
                [$ok] = (new CarrierPackageRepository())->sellerAssignCarrier((int)$package->id, $ownerId, $carrierOwnerId, $actorId);
            } elseif ($ok) {
                $ok = (new CarrierPackageRepository())->sellerUseOwnTeam((int)$package->id, $ownerId, $actorId);
            }
            $respondAssignment($ok, $ok ? 'Store responsibilities updated.' : 'Failed to update Store responsibilities.', [
                'order_id' => $orderId,
                'preparation_user_id' => $kitchenUserId,
                'delivery_user_id' => $deliveryUserId,
                'delivery_assignment_type' => $deliveryMode,
                'carrier_owner_id' => $deliveryMode === 'carrier' ? $carrierOwnerId : null,
            ]);
        } catch (\Throwable $e) {
            error_log($requestId . ' Async Store assignment failed: ' . $e->getMessage());
            $respondAssignment(false, 'The responsibilities could not be saved.', [], [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
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


