<?php

use App\Services\LoginService;
use App\Repositories\OrdersServiceTasksRepository;
use App\Repositories\OrdersServiceRepository;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\LocationUtils;
use App\Utils\Router;

$router = new Router();

$router->get(callback: function () {
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $tasksRepo = new OrdersServiceTasksRepository();
    $serviceRepo = new OrdersServiceRepository();

    $serviceId = (int)($_GET["service_id"] ?? 0);
    if ($serviceId <= 0) {
        MessageUtil::setMessage("Service not found.");
        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "service_id" => 0,
            "tasks" => [],
            "requirements" => "",
            "service_name" => "Unknown"
        ]);
    }

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $service = $serviceRepo->getOneByIdAndOwner($serviceId, $institution->id_owner);
            } else {
                $service = null;
            }
        } else {
            $service = null;
        }
    } else {
        $service = $serviceRepo->getOneByIdAndOwner($serviceId, $ownerId);
    }

    $tasks = $tasksRepo->getAllBy(["id_service" => $serviceId]);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "service_id"   => $serviceId,
        "tasks"        => $tasks,
        "requirements" => $service?->requirements ?? "",
        "service_name" => $service?->name ?? "Unknown",
    ]);
});

$router->post(callback: function () {
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $tasksRepo   = new OrdersServiceTasksRepository();
    $serviceRepo = new OrdersServiceRepository();

    $serviceId = (int)($_POST["service_id"] ?? 0);
    if ($serviceId <= 0) {
        MessageUtil::setMessage("Missing service_id.");
        LocationUtils::reload();
    }

    $effectiveOwnerId = $ownerId;
    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $effectiveOwnerId = $institution->id_owner;
            }
        }
    }
    $service = $serviceRepo->getOneByIdAndOwner($serviceId, $effectiveOwnerId);
    if (!$service) {
        MessageUtil::setMessage("Service not found or you don't have permission.");
        LocationUtils::reload();
    }

    // Eliminar tarea
    if (isset($_POST["delete_id"])) {
        $tasksRepo->delete(["id" => (int)$_POST["delete_id"]]);
        MessageUtil::setMessage("Task deleted.");
        LocationUtils::reload();
    }

    // Guardar requirements
    if (isset($_POST["save_requirements"])) {
        $requirements = trim((string)($_POST["requirements"] ?? ""));
        $serviceRepo->update([
            "requirements" => $requirements === "" ? null : $requirements
        ], ["id" => $serviceId]);
        MessageUtil::setMessage("Requirements saved.");
        LocationUtils::reload();
    }

    // Agregar tarea
    $taskName = trim((string)($_POST["task_name"] ?? ""));
    if ($taskName === "") {
        MessageUtil::setMessage("Task name is required.");
        LocationUtils::reload();
    }

    $tasksRepo->addWithExplicitOwner([
        "id_service" => $serviceId,
        "task_name"  => $taskName,
        "id_owner"   => $effectiveOwnerId
    ]);

    MessageUtil::setMessage("Task added.");
    LocationUtils::reload();
});

$router->run();
