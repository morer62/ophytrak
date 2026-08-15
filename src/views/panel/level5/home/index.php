<?php

use App\Services\LoginService;
use App\Services\UserWorkspaceContextService;
use App\Repositories\UserRepository;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\LocationUtils;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $mobileOwnerId = (int)($_ENV['MOBILE_OWNER_ID'] ?? 0);
    $workspaceContextService = new UserWorkspaceContextService();
    $clientContext = $workspaceContextService->getClientContext($user);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "mobile_owner_id" => $mobileOwnerId,
        "clientContext" => $clientContext,
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $workspaceContextService = new UserWorkspaceContextService();

    if (isset($_POST['switch_client_company'])) {
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $workspaceContextService->switchClientCompany($user, $ownerId);
        }
        LocationUtils::redirectInternal("panel/home");
        return;
    }

    $newLevel = (int)($_POST['level'] ?? 0);

    if ($newLevel === 2) {
        (new UserRepository())->updateData((int)$user->getId(), [
            'level' => 2,
            'id_owner' => (int)$user->getId(),
        ]);
        LoginService::reloadUserPermissions();
        LocationUtils::redirectInternal("panel/planner-hub/institution-profile");
        return;
    }

    if ($newLevel === 4) {
        (new UserRepository())->updateData((int)$user->getId(), [
            'level' => 4,
        ]);
        LoginService::reloadUserPermissions();
        LocationUtils::redirectInternal("panel/home");
        return;
    }

    LocationUtils::redirectInternal("panel/home");
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
