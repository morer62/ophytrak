<?php

use App\Services\LoginService;
use App\Services\UserWorkspaceContextService;
use App\Services\TranslationService;
use App\Repositories\OrdersRepository;
use App\Repositories\OrdersStatusHistoryRepository;
use App\Utils\TemplateResponse;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $id = $_GET["id"] ?? null;

    if (!$id) {
        MessageUtil::setMessage(TranslationService::trans('client_service_orders.order_id_required'));
        LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
    }

    $ordersRepo = new OrdersRepository();
    $historyRepo = new OrdersStatusHistoryRepository();
    $workspaceContextService = new UserWorkspaceContextService();

    $order = $ordersRepo->getByIdWithoutOwnershipCheck((int)$id);
    $clientContext = $workspaceContextService->getClientContext($user);
    $selectedOwnerId = (int)($clientContext["selectedOwnerId"] ?? 0);

    if (
        !$order
        || (int)$order["id_client"] !== $user->getId()
        || ($selectedOwnerId > 0 && (int)$order["id_owner"] !== $selectedOwnerId)
    ) {
        MessageUtil::setMessage(TranslationService::trans('client_service_orders.order_not_found_selected_company'));
        LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
    }

    $history = $historyRepo->getAllBy(["id_order" => (int)$id]);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "order" => $order,
        "history" => $history
    ]); 
});

$router->run();
