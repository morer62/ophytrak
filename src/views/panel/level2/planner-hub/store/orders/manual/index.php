<?php

use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Services\StoreManualOrderService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');

$router = new Router();

$router->get(function () {
    $session = LoginService::getSession();
    $service = new StoreManualOrderService();
    $clients = $service->getClients((int)$session->getOwner());
    $requestedClientId = (int)($_GET['client_id'] ?? 0);
    $selectedClientId = in_array($requestedClientId, array_map(static fn($client) => (int)$client->id, $clients), true)
        ? $requestedClientId
        : 0;

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'products' => $service->getProductsForOwner((int)$session->getOwner()),
        'clients' => $clients,
        'selected_client_id' => $selectedClientId,
        'online_payment_available' => $service->hasConnectedOnlinePaymentProvider((int)$session->getOwner()),
        'saved_card_payment_available' => $service->hasConnectedSavedCardProvider((int)$session->getOwner()),
        'require_existing_client' => true,
        'new_client_url' => 'panel/planner-hub/management/users/create?return_to=store_manual_order',
    ]);
});

$router->post(function () {
    $session = LoginService::getSession();
    $service = new StoreManualOrderService();
    $ownerId = (int)$session->getOwner();
    $clientId = (int)($_POST['id_user'] ?? 0);
    $validClientIds = array_map(static fn($client) => (int)$client->id, $service->getClients($ownerId));
    if ($clientId <= 0 || !in_array($clientId, $validClientIds, true)) {
        MessageUtil::setMessage('Seleccione o cree un cliente asignado a su empresa antes de crear el pedido.', 'Cliente requerido', 'warning');
        LocationUtils::redirectInternal('panel/planner-hub/store/orders/manual');
    }
    if (trim((string)($_POST['shipping_place_id'] ?? '')) === '') {
        MessageUtil::setMessage('Seleccione la dirección de entrega desde las sugerencias de Google.', 'Dirección requerida', 'warning');
        LocationUtils::redirectInternal('panel/planner-hub/store/orders/manual?client_id=' . $clientId);
    }
    $result = $service->createFromRequest($ownerId, $_POST, $_FILES, (int)$session->getId());
    MessageUtil::setMessage((string)$result['message'], $result['success'] ? 'Success' : 'Error', $result['success'] ? 'success' : 'danger');
    LocationUtils::redirectInternal('panel/planner-hub/store/orders/home');
});

$router->run();
