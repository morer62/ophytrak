<?php

use App\Repositories\StoreCouponsRepository;
use App\Repositories\StoreOrderItemsRepository;
use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\StoreOrdersRepository;
use App\Repositories\StoreSubscriptionItemsRepository;
use App\Repositories\StoreSubscriptionsRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Services\LoginService;
use App\Services\StoreOrderLifecycleService;
use App\Services\StoreDeliveryNotificationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

/**
 * Weekly renewal amount matches store-subscription-renewals cron logic,
 * including any linked SUBSCRIPTION coupon still valid.
 */
function gourmet_panel_subscription_next_charge_preview(
    object $sub,
    StoreSubscriptionItemsRepository $subItemsRepo,
    StoreCouponsRepository $couponsRepo
): ?array {
    $meals = (int)($sub->meals_count ?? 0);
    if ($meals <= 0) {
        $items = $subItemsRepo->getBySubscription((int)$sub->id);
        foreach ($items ?: [] as $it) {
            $meals += (int)($it->quantity ?? 0);
        }
    }

    $ppm = round((float)($sub->price_per_meal ?? 0), 2);
    $subtotal = round($ppm * max(0, $meals), 2);
    if ($subtotal <= 0) {
        return null;
    }

    $discount = 0.0;
    $subCouponId = (int)($sub->id_coupon ?? 0);
    if ($subCouponId > 0) {
        $coupon = $couponsRepo->getOne(['id' => $subCouponId]);
        if ($coupon) {
            $now = date('Y-m-d H:i:s');
            $valid = strtoupper((string)($coupon->status ?? '')) === StoreCouponsRepository::STATUS_ACTIVE;
            if ($valid && !empty($coupon->starts_at) && (string)$coupon->starts_at > $now) {
                $valid = false;
            }
            if ($valid && !empty($coupon->expires_at) && (string)$coupon->expires_at < $now) {
                $valid = false;
            }
            if ($valid) {
                $dType = strtoupper((string)($coupon->discount_type ?? 'PERCENT'));
                $dVal = (float)($coupon->discount_value ?? 0);
                if ($dType === StoreCouponsRepository::TYPE_PERCENT) {
                    $discount = round(($subtotal * $dVal) / 100, 2);
                } else {
                    $discount = round(min($dVal, $subtotal), 2);
                }
            }
        }
    }

    $total = round(max(0, $subtotal - $discount), 2);
    $nextDate = $sub->next_charge_date ?? null;
    $nextDateStr = $nextDate !== null && $nextDate !== '' ? (string)$nextDate : null;

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'next_charge_date' => $nextDateStr,
    ];
}

$router->get(function () {
    $session = LoginService::getSession();
    $repo = new StoreOrdersRepository();
    $itemsRepo = new StoreOrderItemsRepository();
    $subsRepo = new StoreSubscriptionsRepository();
    $subItemsRepo = new StoreSubscriptionItemsRepository();
    $couponsRepo = new StoreCouponsRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $locationRepo = new StoreDeliveryLocationLogsRepository();
    $tasksRepo = new StoreOrderTasksRepository();

    $userId = (int)$session->getId();
    $email = method_exists($session, 'getEmail') ? trim((string)$session->getEmail()) : '';

    $byUser = $repo->getAllByClientWithCompany($userId, $email, 150);
    $byEmail = [];
    $seen = [];
    $orders = [];
    foreach (array_merge($byUser ?: [], $byEmail ?: []) as $o) {
        $id = (int)($o->id ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $orders[] = $o;
    }
    usort($orders, function ($a, $b) {
        $ta = strtotime((string)($a->created_at ?? '')) ?: 0;
        $tb = strtotime((string)($b->created_at ?? '')) ?: 0;
        return $tb <=> $ta;
    });
    $lifecycleTab = trim((string)($_GET['tab'] ?? StoreOrderLifecycleService::TAB_ACTIVE));
    if (!in_array($lifecycleTab, [StoreOrderLifecycleService::TAB_ACTIVE, StoreOrderLifecycleService::TAB_INCIDENTS, StoreOrderLifecycleService::TAB_CLOSED], true)) $lifecycleTab = StoreOrderLifecycleService::TAB_ACTIVE;
    $lifecycleCounts = ['active'=>0,'incidents'=>0,'closed'=>0];
    foreach ($orders as $candidate) $lifecycleCounts[StoreOrderLifecycleService::tabFor($candidate)]++;
    $orders = array_values(array_filter($orders, static fn($o) => StoreOrderLifecycleService::tabFor($o) === $lifecycleTab));

    $activeSub = $subsRepo->getActiveByUser($userId);
    if (!$activeSub && $email !== '') {
        $activeSub = $subsRepo->getActiveByEmail($email);
    }

    $subscriptionNextCharge = null;
    if ($activeSub) {
        $subscriptionNextCharge = gourmet_panel_subscription_next_charge_preview($activeSub, $subItemsRepo, $couponsRepo);
    }

    $orderIds = array_values(array_unique(array_map(static fn($order) => (int)$order->id, $orders)));
    $teamContactsByOrder = [];
    if (!empty($orderIds)) {
        $contactsByOwner = [];
        foreach ($orders as $order) {
            $ownerId = (int)($order->id_owner ?? 0);
            if ($ownerId > 0) {
                $contactsByOwner[$ownerId][] = (int)$order->id;
            }
        }
        $teamContactsByOrder = [];
        foreach ($contactsByOwner as $ownerId => $ownerOrderIds) {
            $teamContactsByOrder += $tasksRepo->getAssigneesByOrders((int)$ownerId, $ownerOrderIds);
        }
    }

    $latestLocationMap = $locationRepo->getLatestMapByOrders($orderIds);

    foreach ($orders as &$order) {
        $order->can_cancel = $repo->supportsCancellationWorkflow() && StoreOrderLifecycleService::canClientCancel($order);
        $shippingParts = array_filter([
            trim((string)($order->shipping_address_1 ?? '')),
            trim((string)($order->shipping_city ?? '')),
            trim((string)(
                trim((string)($order->shipping_state ?? '')) .
                (((string)($order->shipping_zip ?? '') !== '') ? (' ' . trim((string)$order->shipping_zip)) : '')
            ))
        ], static function ($v) {
            return $v !== '';
        });
        $order->shipping_address_display = $shippingParts
            ? implode(', ', $shippingParts)
            : ((string)($order->city ?? '') !== '' ? (string)$order->city : '-');

        $items = $itemsRepo->getByOrder((int)$order->id);
        $modalItems = [];
        foreach ($items ?: [] as $item) {
            $modalItems[] = [
                'name' => $item->product_name_snapshot ?? ('#' . $item->id_product),
                'quantity' => (int)($item->quantity ?? 0),
                'unit_price' => (float)($item->unit_price ?? 0),
                'line_total' => (float)($item->line_total ?? 0),
            ];
        }
        $order->items_modal_json = json_encode($modalItems);
        $order->workflow = $workflowRepo->getByOrder((int)$order->id);
        $order->latest_location = $latestLocationMap[(int)$order->id] ?? null;
        $order->institution = (object)[
            'company_name' => $order->company_name ?? null,
            'logo_path' => $order->company_logo_path ?? null,
            'email' => $order->company_email ?? null,
            'phone' => $order->company_phone ?? null,
            'address_line1' => $order->company_address_line1 ?? null,
            'city' => $order->company_city ?? null,
            'state' => $order->company_state ?? null,
            'zip' => $order->company_zip ?? null,
            'country' => $order->company_country ?? null,
        ];
        $teamContacts = [];
        if ((int)($order->workflow->allow_chat_with_client ?? 0) === 1) {
            foreach ($teamContactsByOrder[(int)$order->id] ?? [] as $member) {
                if ((int)($member->level ?? 0) !== 4 || (int)($member->allow_chat_with_clients ?? 0) !== 1) {
                    continue;
                }
                $teamContacts[] = [
                    'id' => (int)$member->id,
                    'name' => trim((string)(($member->name ?? '') . ' ' . ($member->lastname ?? ''))) ?: ($member->email ?? 'Team member'),
                    'email' => $member->email ?? '',
                ];
            }
        }
        $order->team_contacts_json = json_encode($teamContacts);
    }
    unset($order);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'orders' => $orders,
        'subscription_next_charge' => $subscriptionNextCharge,
        'lifecycleTab' => $lifecycleTab,
        'lifecycleCounts' => $lifecycleCounts,
    ]);
});

$router->post(function () {
    $session = LoginService::getSession();
    $userId = (int)$session->getId();
    $email = method_exists($session, 'getEmail') ? trim((string)$session->getEmail()) : '';
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    if (!in_array($action, ['request_return','request_cancellation'], true) || $orderId <= 0) {
        MessageUtil::setMessage('Invalid Store order request.');
        LocationUtils::reload();
    }
    $repo = new StoreOrdersRepository();
    $order = $repo->getById($orderId);
    $ownsOrder = $order && ((int)($order->id_user ?? 0) === $userId || ($email !== '' && strtolower((string)($order->guest_email ?? '')) === strtolower($email)));
    if (!$ownsOrder) {
        MessageUtil::setMessage('This Store order does not belong to your account.');
        LocationUtils::reload();
    }
    if ($action === 'request_cancellation') {
        if (!StoreOrderLifecycleService::canClientCancel($order)) { MessageUtil::setMessage('This order can no longer be cancelled from the client portal.'); LocationUtils::reload(); }
        $reason = trim((string)($_POST['cancellation_reason'] ?? ''));
        if ($reason === '') { MessageUtil::setMessage('Please explain why you need to cancel this order.'); LocationUtils::reload(); }
        $ok = $repo->requestCancellation($orderId, $reason, (string)$order->status);
        if ($ok) { try { StoreDeliveryNotificationService::notify((int)$order->id_owner, $orderId, 'cancellation_requested', trim($session->getName().' '.$session->getLastname())); } catch (Throwable $e) { error_log('Cancellation notification failed: '.$e->getMessage()); } }
        MessageUtil::setMessage($ok ? 'Cancellation request sent to the business.' : 'Unable to send the cancellation request.', $ok ? 'Success' : 'Error', $ok ? 'success' : 'danger');
        LocationUtils::redirectInternal('panel/store/orders/home?tab=incidents');
    }
    if (!StoreOrderLifecycleService::canClientRequestReturn($order)) {
        MessageUtil::setMessage('Only delivered Store orders can request a return.');
        LocationUtils::reload();
    }
    $notes = trim((string)($_POST['return_notes'] ?? ''));
    $repo->requestReturn($orderId, $notes);
    MessageUtil::setMessage('Return request sent to the business.', 'Success', 'success');
    LocationUtils::redirectInternal('panel/store/orders/home?tab=incidents');
});

$router->run();
