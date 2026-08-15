<?php

use App\Services\LoginService;
use App\Services\UserWorkspaceContextService;
use App\Services\TranslationService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Repositories\OrdersRepository;
use App\Repositories\OrdersSuborderRepository;
use App\Repositories\OrderSuborderServicesAssignedRepository;
use App\Repositories\OrdersPaymentsRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

$router = new Router();

$router->get(callback: function () {
    $user = LoginService::getSession();
    $workspaceContext = (new UserWorkspaceContextService())->getClientContext($user);
    $selectedOwnerId = (int)($workspaceContext['selectedOwnerId'] ?? 0);
    $orderRepo = new OrdersRepository();
    $suborderRepo = new OrdersSuborderRepository();
    $suborderServicesRepo = new OrderSuborderServicesAssignedRepository();
    $paymentsRepo = new OrdersPaymentsRepository();
    $profileRepo = new InstitutionProfileRepository();

    $orderId = $_GET["id"] ?? null;
    if (!$orderId) {
        MessageUtil::setMessage(TranslationService::trans('client_service_orders.order_id_required'));
        LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
    }

    $order = $orderRepo->getByIdWithoutOwnershipCheck($orderId);
    if ($order) {
        $order = (object)$order;
    }
    
    if (!$order || $order->id_client != $user->getId() || ($selectedOwnerId > 0 && (int)$order->id_owner !== $selectedOwnerId)) {
        MessageUtil::setMessage(TranslationService::trans('client_service_orders.order_access_denied'));
        LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
    }

    $suborders = $suborderRepo->getByOrder($orderId);
    
    $secret = $_ENV["VNV_SECRET_KEY"] ?? "mySuperSecretKey";
    
    foreach ($suborders as $suborder) {
        $suborder->services = $suborderServicesRepo->getServicesWithDetails($suborder->id);
        
        $subPayload = [
            "suborder_id" => $suborder->id,
            "user_id" => $order->id_client,
            "exp" => time() + (86400 * 30)
        ];
        $subPayload["hash"] = hash_hmac("sha256", json_encode([
            "suborder_id" => $subPayload["suborder_id"],
            "user_id" => $subPayload["user_id"],
            "exp" => $subPayload["exp"]
        ]), $secret);
        $suborder->payment_token = base64_encode(json_encode($subPayload));

        $subtotalCalculated = 0;
        foreach ($suborder->services as $service) {
            $subtotalCalculated += ((float)$service->quantity) * ((float)$service->actual_price);
        }
        $taxRate = isset($suborder->tax_percertance) ? (float)$suborder->tax_percertance : 0.0;
        $taxAmount = $subtotalCalculated * ($taxRate / 100.0);
        $totalAmount = round($subtotalCalculated + $taxAmount, 2);

        $payments = $paymentsRepo->getAllBy(["id_order" => $orderId, "id_suborder" => $suborder->id]);
        $amountPaid = 0.0;
        foreach ($payments as $p) {
            $paid = isset($p->amount) ? (float)$p->amount : 0.0;
            $refunded = isset($p->refunded_amount) ? (float)$p->refunded_amount : 0.0;
            $amountPaid += max(0.0, $paid - $refunded);
        }
        $balanceDue = max(0.0, round($totalAmount - $amountPaid, 2));

        $suborder->total_amount = $totalAmount;
        $suborder->amount_paid = $amountPaid;
        $suborder->balance_due = $balanceDue;
    }

    $order->institution = $profileRepo->getByOwner($order->id_owner);

    $appUrl = $_ENV["APP_URL"] ?? "http://localhost/vnv-venue";
    $appUrl = rtrim($appUrl, '/');

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "order" => $order,
        "suborders" => $suborders,
        "app_url" => $appUrl
    ]);
});

$router->run();
