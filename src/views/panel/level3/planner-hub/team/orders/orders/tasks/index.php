<?php

use App\Services\LoginService;
use App\Repositories\OrdersServiceRepository;
use App\Repositories\OrdersServicesAssignedRepository;
use App\Repositories\OrderSuborderServicesAssignedRepository;
use App\Repositories\OrdersServiceTasksRepository;
use App\Repositories\OrdersTeamTasksRepository;
use App\Repositories\OrdersTeamTaskPhotosRepository;
use App\Repositories\UserRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\OrdersSuborderRepository;
use App\Utils\LocationUtils;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\FileUtils;
use App\Services\NotificationService;

$router = new Router();

$router->get(function () {
    $orderId = $_GET["id"] ?? null;
    $suborderId = $_GET["suborder"] ?? null;
    
    if (!$orderId) LocationUtils::redirectInternal("panel/planner-hub/team/orders/orders");

    $user = LoginService::getSession();
    $serviceRepo = new OrdersServiceRepository();
    $taskRepo = new OrdersServiceTasksRepository();
    $teamTaskRepo = new OrdersTeamTasksRepository();
    $photosRepo = new OrdersTeamTaskPhotosRepository();
    $userRepo = new UserRepository();

    $services = [];
    $manualTasks = [];

    if ($suborderId) {
        $assignedRepo = new OrderSuborderServicesAssignedRepository();
        $servicesAssigned = $assignedRepo->getAllBy(["id_suborder" => $suborderId]);

        foreach ($servicesAssigned as $assigned) {
            $service = $serviceRepo->getByIdWithoutOwnershipCheck($assigned->id_service);
            $tasks = $taskRepo->getAllWithoutOwner(["id_service" => $assigned->id_service]);

            foreach ($tasks as $task) {
                $assignedTask = $teamTaskRepo->getOneWithoutOwnershipCheck([
                    "id_suborder" => $suborderId,
                    "id_service" => $assigned->id_service,
                    "task_description" => $task->task_name,
                    "id_user" => $user->getId()
                ]);

                $task->assigned_id_user = $assignedTask?->id_user ?? null;
                $task->assigned_user_name = $assignedTask?->id_user ? $userRepo->getOne(["id" => $assignedTask->id_user])->name : null;
                $task->id_task = $assignedTask?->id ?? null;
                $task->is_done = $assignedTask?->is_done ?? 0;
                if ($task->id_task) {
                    $task->photos = $photosRepo->getByTask($task->id_task);
                } else {
                    $task->photos = [];
                }
            }

            $services[] = [
                "name" => $service ? $service->name : "Unknown Service",
                "id_service" => $assigned->id_service,
                "tasks" => $tasks
            ];
        }

        $manualTasks = $teamTaskRepo->getAllWithoutOwner([
            "id_suborder" => $suborderId,
            "id_service" => 0,
            "id_user" => $user->getId()
        ]);
    } else {
        // Servicios de la orden principal con tareas asignadas al usuario
        $assignedRepo = new OrdersServicesAssignedRepository();
        $servicesAssigned = $assignedRepo->getAllWithoutOwner(["id_order" => $orderId]);

        foreach ($servicesAssigned as $assigned) {
            $service = $serviceRepo->getByIdWithoutOwnershipCheck($assigned->id_service);
            $tasks = $taskRepo->getAllWithoutOwner(["id_service" => $assigned->id_service]);
            $assignedTasksCount = 0;

            foreach ($tasks as $task) {
                $assignedTask = $teamTaskRepo->getOneWithoutOwnershipCheck([
                    "id_order" => $orderId,
                    "id_service" => $assigned->id_service,
                    "task_description" => $task->task_name,
                    "id_user" => $user->getId()
                ]);

                if ($assignedTask && $assignedTask->id_user) {
                    $assignedTasksCount++;
                }

                $task->assigned_id_user = $assignedTask?->id_user ?? null;
                $task->assigned_user_name = $assignedTask?->id_user ? $userRepo->getOne(["id" => $assignedTask->id_user])->name : null;
                $task->id_task = $assignedTask?->id ?? null;
                $task->is_done = $assignedTask?->is_done ?? 0;
                if ($task->id_task) {
                    $task->photos = $photosRepo->getByTask($task->id_task);
                } else {
                    $task->photos = [];
                }
            }

            // Solo incluir servicios que tengan tareas asignadas al usuario
            if ($assignedTasksCount > 0) {
                $services[] = [
                    "name" => $service ? $service->name : "Unknown Service",
                    "id_service" => $assigned->id_service,
                    "tasks" => $tasks,
                    "source" => "main_order",
                    "suborder_id" => null
                ];
            }
        }

        $manualTasks = $teamTaskRepo->getAllWithoutOwner([
            "id_order" => $orderId,
            "id_service" => 0,
            "id_user" => $user->getId()
        ]);

        // Servicios de subórdenes con tareas asignadas al usuario
        $suborderRepo = new OrdersSuborderRepository();
        $suborderServicesRepo = new OrderSuborderServicesAssignedRepository();
        $allSuborders = $suborderRepo->getByOrder($orderId);

        foreach ($allSuborders as $suborder) {
            $suborderServicesAssigned = $suborderServicesRepo->getServicesWithDetails($suborder->id);
            $hasAssignedTasks = false;
            $suborderServices = [];

            foreach ($suborderServicesAssigned as $assigned) {
                $service = $serviceRepo->getByIdWithoutOwnershipCheck($assigned->id_service);
                $tasks = $taskRepo->getAllWithoutOwner(["id_service" => $assigned->id_service]);
                $assignedTasksCount = 0;

                foreach ($tasks as $task) {
                    $assignedTask = $teamTaskRepo->getOneWithoutOwnershipCheck([
                        "id_suborder" => $suborder->id,
                        "id_service" => $assigned->id_service,
                        "task_description" => $task->task_name,
                        "id_user" => $user->getId()
                    ]);

                    if ($assignedTask && $assignedTask->id_user) {
                        $hasAssignedTasks = true;
                        $assignedTasksCount++;
                    }

                    $task->assigned_id_user = $assignedTask?->id_user ?? null;
                    $task->assigned_user_name = $assignedTask?->id_user ? $userRepo->getOne(["id" => $assignedTask->id_user])->name : null;
                    $task->id_task = $assignedTask?->id ?? null;
                    $task->is_done = $assignedTask?->is_done ?? 0;
                }

                // Solo incluir servicios que tengan tareas asignadas al usuario
                if ($assignedTasksCount > 0) {
                    $suborderServices[] = [
                        "name" => $service ? $service->name : ($assigned->service_name ?? "Unknown Service"),
                        "id_service" => $assigned->id_service,
                        "tasks" => $tasks,
                        "source" => "suborder",
                        "suborder_id" => $suborder->id
                    ];
                }
            }

            // Verificar tareas manuales de la suborden
            $suborderManualTasks = $teamTaskRepo->getAllWithoutOwner([
                "id_suborder" => $suborder->id,
                "id_service" => 0,
                "id_user" => $user->getId()
            ]);

            if (!empty($suborderManualTasks)) {
                $hasAssignedTasks = true;
            }

            // Agregar servicios de suborden si tiene tareas asignadas
            if ($hasAssignedTasks) {
                $services = array_merge($services, $suborderServices);
                $manualTasks = array_merge($manualTasks, $suborderManualTasks);
            }
        }
    }

    foreach ($manualTasks as $task) {
        $task->id_task = $task->id;
        $task->assigned_id_user = $task->id_user;
        $task->is_done = $task->is_done ?? 0;
        $task->photos = $photosRepo->getByTask($task->id);
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "order_id" => $orderId,
        "suborder_id" => $suborderId,
        "services" => $services,
        "members" => [$userRepo->getOne(["id" => $user->getId()])],
        "manualTasks" => $manualTasks,
    ]);
});

$router->post(function () {
    $formType = $_POST["form_type"] ?? null;
    $taskId = intval($_POST["task_id"] ?? 0);
    $user = LoginService::getSession();
    $teamTaskRepo = new OrdersTeamTasksRepository();
    $photosRepo = new OrdersTeamTaskPhotosRepository();

    if ($formType === "upload_photo" && $taskId > 0) {
        $task = $teamTaskRepo->getOne(["id" => $taskId]);
        
        if (!$task || $task->id_user != $user->getId()) {
            LocationUtils::reload();
            return;
        }

        if (!isset($_FILES["photos"]) || $_FILES["photos"]["error"][0] == UPLOAD_ERR_NO_FILE) {
            LocationUtils::reload();
            return;
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $uploadedCount = 0;

        $files = $_FILES["photos"];
        $fileCount = count($files["name"]);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files["error"][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $fileType = $files["type"][$i];
            $fileSize = $files["size"][$i];

            if (!in_array($fileType, $allowedTypes)) {
                continue;
            }

            if ($fileSize > $maxFileSize) {
                continue;
            }

            $fileArray = [
                'name' => $files["name"][$i],
                'type' => $fileType,
                'tmp_name' => $files["tmp_name"][$i],
                'error' => $files["error"][$i],
                'size' => $fileSize
            ];

            try {
                $photoUrl = FileUtils::saveFile($fileArray, "team-task-photos");
                
                $photosRepo->add([
                    "id_task" => $taskId,
                    "photo_url" => $photoUrl,
                    "uploaded_by" => $user->getId()
                ]);
                
                $uploadedCount++;
            } catch (\Exception $e) {
            }
        }

        LocationUtils::reload();
        return;
    }

    if ($formType === "delete_photo") {
        $photoId = intval($_POST["photo_id"] ?? 0);
        
        if ($photoId > 0) {
            $photo = $photosRepo->getOne(["id" => $photoId]);
            
            if ($photo && $photo->uploaded_by == $user->getId()) {
                try {
                    FileUtils::removeFile($photo->photo_url);
                } catch (\Exception $e) {
                }
                
                $photosRepo->delete(["id" => $photoId]);
            }
        }

        LocationUtils::reload();
        return;
    }

    if ($formType === "toggle_done" && $taskId > 0) {
            $task = $teamTaskRepo->getOne(["id" => $taskId]);

            if ($task) {
                // Actualizar estado
                $teamTaskRepo->update(
                    ["is_done" => $task->is_done ? 0 : 1],
                    ["id" => $taskId]
                );

                // Obtener datos relacionados
                $orderRepo = new OrdersRepository();
                $userRepo = new UserRepository();
                $order = $orderRepo->getOne(["id" => $task->id_order]);
                $memberName = $user->getName() . ' ' . $user->getLastname();
                $statusText = $task->is_done ? 'reopened' : 'marked as done';

                // 🔔 Notificar al dueño de la orden
                NotificationService::sendToUsers(
                    [$order->id_owner],
                    '🧹 Task Updated',
                    $memberName . ' ' . $statusText . ' a task in Order VNV 341' . $task->id_order . '.'
                );

                // Verificar si todas las tareas están completadas
                $allTasks = $teamTaskRepo->getAllBy(["id_order" => $task->id_order]);
                $allDone = count($allTasks) > 0 && count(array_filter($allTasks, fn($t) => !$t->is_done)) === 0;

                if ($allDone) {
                    NotificationService::sendToUsers(
                        [$order->id_owner],
                        '🎉 All Tasks Completed',
                        'All tasks in Order VNV 341' . $task->id_order . ' have been completed by the team.'
                    );
                }
            }

            LocationUtils::reload();
        }

        });

$router->run();
