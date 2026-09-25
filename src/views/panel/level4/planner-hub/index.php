<?php

use App\Services\LoginService;
use App\Services\Level4AccessCenterService;
use App\Services\UserWorkspaceContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\LocationUtils;
use App\Services\ProductProfileService;
use App\Repositories\CarrierPackageRepository;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $teamContext = (new UserWorkspaceContextService())->getTeamContext($user);

    if (!empty($teamContext['selectedInstitutionId'])) {
        LoginService::reloadUserPermissions((int)$teamContext['selectedInstitutionId']);
        $user = LoginService::getSession();
    }

    $selectedOwnerId = (int)($teamContext['selectedOwnerId'] ?? $user->getOwner());
    if (ProductProfileService::isOphytrack() && $selectedOwnerId > 0 && (new CarrierPackageRepository())->isCarrier($selectedOwnerId)) {
        LocationUtils::redirectInternal('panel/planner-hub/team/driver-mode?view=deliveries');
        return;
    }

    $accessCenter = (new Level4AccessCenterService())->build($user, $teamContext);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "teamContext" => $teamContext,
        "accessCenter" => $accessCenter,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
