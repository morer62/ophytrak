<?php

use App\Repositories\OrdersServiceRepository;
use App\Repositories\OrdersServicesAssignedRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\OrderSuborderServicesAssignedRepository;
use App\Repositories\OrdersSuborderRepository;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;
use App\Repositories\UserRepository;
use App\Services\LoginService;


$router = new Router();

$router->get(function () {
    $repo = new OrdersServiceRepository(); 
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            
            if ($institution && $institution->id_owner) {
                $services = $repo->getAllByInstitutionOwner($institution->id_owner, 0);
            } else {
                $services = [];
            }
        } else {
            $services = [];
        }
    } else {
        $services = $repo->getAllBy([
            "id_owner" => $ownerId,
            "is_archived" => 0,
        ]);
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "services" => $services
    ]);
});

$router->post(function () {
    $id = $_POST["id"] ?? null;
    $repo = new OrdersServiceRepository();
    $assignedRepo = new OrdersServicesAssignedRepository();
    $ordersRepo = new OrdersRepository();
    $subAssignedRepo = new OrderSuborderServicesAssignedRepository();
    $subRepo = new OrdersSuborderRepository();

    if (!$id) {
        MessageUtil::setMessage("Invalid service ID.");
        LocationUtils::reload();
    }

    if (($_POST["action"] ?? "") === "duplicate") {
        $user = LoginService::getSession();
        $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
        
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
            MessageUtil::setMessage("Service not found.");
            LocationUtils::reload();
        }

        $duplicateData = [
            "name" => $service->name . " (Duplicated)",
            "price" => $service->price,
            "description" => $service->description,
            "requirements" => $service->requirements,
            "description_url" => $service->description_url,
            "is_variable" => $service->is_variable ?? 'NO',
            "id_owner" => $ownerId,
            "is_archived" => 0
        ];

        error_log("Duplicating service data: " . json_encode($duplicateData));
        
        if ($user->getLevel() === 4) {
            $success = $repo->addWithExplicitOwner($duplicateData);
        } else {
            $success = $repo->addWithExplicitOwner($duplicateData);
        }
        
        TranslationService::detectLocale();
        
        if ($success) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.service_duplicated_successfully'));
        } else {
            error_log("Failed to duplicate service. Repository add returned false.");
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_duplicate_service'));
        }
        LocationUtils::reload();
    }

    // Si acción es duplicar, ya manejado arriba

    // Advertencia antes de archivar
    $messages = [];
    $asa = $assignedRepo->getAllWithoutOwner(["id_service" => (int)$id]);
    $pending = 0;
    foreach ($asa as $row) {
        $order = $ordersRepo->getOne(["id" => $row->id_order]);
        if ($order) {
            $status = trim($order->status_workflow);
            if (in_array($status, ['INVOICE_READY','INVOICE_PARTIAL','INVOICE_PAID'], true)) { $pending++; }
        }
    }
    $subAssigned = $subAssignedRepo->getAllBy(["id_service" => (int)$id]);
    foreach ($subAssigned as $row) {
        $sub = $subRepo->getOne(["id" => $row->id_suborder]);
        if ($sub) {
            $status = trim($sub->status_workflow);
            if (in_array($status, ['INVOICE_READY','INVOICE_PARTIAL','INVOICE_PAID'], true)) { $pending++; }
        }
    }

    if ($pending > 0 && empty($_POST['confirm_pending'])) {
        // Renderizar la tabla con un modal de confirmación
        $user = LoginService::getSession();
        $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
        
        if ($user->getLevel() === 4) {
            $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
            if ($currentInstitutionId) {
                $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
                $institution = $institutionRepo->getById($currentInstitutionId);
                if ($institution && $institution->id_owner) {
                    $services = $repo->getAllByInstitutionOwner($institution->id_owner, 0);
                } else {
                    $services = [];
                }
            } else {
                $services = [];
            }
        } else {
            $services = $repo->getAllBy([
                "id_owner" => $ownerId,
                "is_archived" => 0,
            ]);
        }

        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "services" => $services,
            "pending_modal_open" => true,
            "pending_modal_service_id" => (int)$id,
            "pending_modal_count" => $pending
        ]);
    }

    TranslationService::detectLocale();
    
    if ($pending > 0) {
        $messages[] = "⚠️ " . TranslationService::trans('planner_hub.service_used_active_orders', ['count' => $pending]);
    }

    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    if ($user->getLevel() !== 4) {
        $service = $repo->getOneByIdAndOwner((int)$id, $ownerId);
        if (!$service) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.service_not_found_delete'));
            LocationUtils::reload();
        }
    }
    $repo->archive((int)$id);

    $messages[] = TranslationService::trans('planner_hub.service_deleted_successfully');
    MessageUtil::setMessage(implode(' ', $messages));
    LocationUtils::reload();
});

$router->run();
