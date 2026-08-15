<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\OrdersServiceRepository;
use App\Repositories\OrdersServicesAssignedRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\OrderSuborderServicesAssignedRepository;
use App\Repositories\OrdersSuborderRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    TranslationService::detectLocale();
    
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $repo = new OrdersServiceRepository();

    $id = $_GET["id"] ?? null;
    if (!$id) LocationUtils::redirectInternal("panel/planner-hub/management/orders/services");

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $service = $repo->getOneByIdAndOwner($id, $institution->id_owner);
            } else {
                $service = null;
            }
        } else {
            $service = null;
        }
    } else {
        $service = $repo->getOneByIdAndOwner((int)$id, $ownerId);
    }
    
    if (!$service) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.service_not_found_permission'));
        LocationUtils::redirectInternal("panel/planner-hub/management/orders/services");
    }

    // Advertir si hay órdenes/subórdenes pendientes que usan este servicio
    try {
        $assignedRepo = new OrdersServicesAssignedRepository();
        $ordersRepo = new OrdersRepository();
        $asa = $assignedRepo->getAllWithoutOwner(["id_service" => (int)$id]);
        $pendingOrders = 0;
        foreach ($asa as $row) {
            $order = $ordersRepo->getOne(["id" => $row->id_order]);
            if ($order) {
                $status = trim($order->status_workflow);
                if (in_array($status, ['INVOICE_READY','INVOICE_PARTIAL','INVOICE_PAID'], true)) {
                    $pendingOrders++;
                }
            }
        }

        $subAssignedRepo = new OrderSuborderServicesAssignedRepository();
        $subRepo = new OrdersSuborderRepository();
        $subAssigned = $subAssignedRepo->getAllBy(["id_service" => (int)$id]);
        $pendingSuborders = 0;
        foreach ($subAssigned as $row) {
            $sub = $subRepo->getOne(["id" => $row->id_suborder]);
            if ($sub) {
                $status = trim($sub->status_workflow);
                if (in_array($status, ['INVOICE_READY','INVOICE_PARTIAL','INVOICE_PAID'], true)) {
                    $pendingSuborders++;
                }
            }
        }

        $totalPending = $pendingOrders + $pendingSuborders;
        if ($totalPending > 0) {
            MessageUtil::setMessage("⚠️ " . TranslationService::trans('planner_hub.there_are_active_orders', ['count' => $totalPending]));
        }
    } catch (\Throwable $e) {
        // Silencioso; no bloquear la edición por el warning
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "service" => $service
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $repo = new OrdersServiceRepository();

    $id = $_GET["id"] ?? null;
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $description_url = trim($_POST["description_url"] ?? "");
    $price = floatval($_POST["price"] ?? 0);
    $is_variable = isset($_POST["is_variable"]) ? "YES" : "NO";

    if (!$id || $name === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.service_name_required'));
        LocationUtils::reload();
    }

    if ($is_variable === "NO" && $price <= 0) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.price_required_fixed'));
        LocationUtils::reload();
    }

    $service = $repo->getOneByIdAndOwner((int)$id, $ownerId);
    if (!$service) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.service_not_found_permission'));
        LocationUtils::reload();
    }

    $repo->update([
        "name" => $name,
        "description" => $description,
        "description_url" => $description_url,
        "price" => $price,
        "is_variable" => $is_variable
    ], ["id" => $id]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.service_updated_successfully'));
    LocationUtils::redirectInternal("panel/planner-hub/management/orders/services");
});

$router->run();
