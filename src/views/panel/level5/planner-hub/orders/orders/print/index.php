<?php

use App\Repositories\OrdersServicesAssignedRepository;
use App\Repositories\ClientsUsersRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\UserRepository;
use App\Repositories\OrdersContractRepository;
use App\Repositories\OrdersServiceRepository;
use App\Repositories\OrdersServiceTasksRepository;
use App\Repositories\OrdersTeamTasksRepository;
use App\Services\LoginService;
use App\Services\UserWorkspaceContextService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$id = $_GET["id"] ?? null;
$user = LoginService::getSession();
$workspaceContext = (new UserWorkspaceContextService())->getClientContext($user);
$selectedOwnerId = (int)($workspaceContext['selectedOwnerId'] ?? 0);

$orderRepo = new OrdersRepository();
$clientRepo = new UserRepository();
$contractRepo = new OrdersContractRepository();
$servicesRepo = new OrdersServicesAssignedRepository();
$tasksRepo = new OrdersServiceTasksRepository();
$teamTaskRepo = new OrdersTeamTasksRepository();
$clientsRepo = new ClientsUsersRepository();

if (!$id) {
    MessageUtil::setMessage(TranslationService::trans('client_service_orders.invalid_order_id'));
    LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
}

// Traer la orden sin restricciones de ownership
$orderArray = $orderRepo->getByIdWithoutOwnershipCheck((int)$id);
$order = $orderArray ? (object) $orderArray : null;

if (!$order) {
    MessageUtil::setMessage(TranslationService::trans('client_service_orders.order_not_found'));
    LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
}

// Validar acceso: si el cliente es dueño o está asociado
$ownerIds = $clientsRepo->getOwnerIdsForClient($user->getId());

if (
    ($order->id_client !== $user->getId() && !in_array($order->id_owner, $ownerIds))
    || ($selectedOwnerId > 0 && (int)$order->id_owner !== $selectedOwnerId)
) {
    MessageUtil::setMessage(TranslationService::trans('client_service_orders.order_access_denied'));
    LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
}

$client = $clientRepo->getOne(["id" => $order->id_client]);
$contract = $contractRepo->getOne(["id" => $order->id_contract]);
$services = $servicesRepo->getAllBy(["id_order" => $order->id]);

$tasksByService = [];
foreach ($services as $srv) {
    $tasksByService["$srv->id_service"] = $tasksRepo->getAllBy([
        "id_service" => $srv->id_service
    ]);
}

$teamTasks = $teamTaskRepo->getAllBy([
    "id_order" => $order->id
]);

echo TemplateResponse::render(__DIR__ . "/index.twig", [
    "order" => $order,
    "client" => $client,
    "contract" => $contract,
    "services" => $services,
    "tasksByService" => $tasksByService,
    "teamTasks" => $teamTasks,
    "id" => $id
]);
