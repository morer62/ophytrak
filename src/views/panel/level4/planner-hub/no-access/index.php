<?php

use App\Services\LoginService;
use App\Services\ModuleAccessService;
use App\Services\ProductProfileService;
use App\Services\UserWorkspaceContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $requestedModule = trim((string)($_GET['module'] ?? ''));
    $requiredModules = array_values(array_filter(array_map(
        'trim',
        explode(',', (string)($_GET['required'] ?? ''))
    )));

    if (ProductProfileService::includedWithStoreLogistics($requestedModule)) {
        $requestedModule = 'store_delivery_tracking';
        $requiredModules = ['store_delivery_tracking'];
    }

    $teamContext = $user ? (new UserWorkspaceContextService())->getTeamContext($user) : [];
    $selectedOwnerId = (int)($teamContext['selectedOwnerId'] ?? 0);
    $selectedInstitution = $teamContext['selectedInstitution'] ?? null;
    $companyName = trim((string)($selectedInstitution->company_name ?? ''));
    $isCompanyModuleLock = $selectedOwnerId > 0
        && $requiredModules !== []
        && !(new ModuleAccessService())->canAccessAnyRouteModule($selectedOwnerId, $requiredModules);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'requestedModule' => $requestedModule,
        'companyName' => $companyName,
        'isCompanyModuleLock' => $isCompanyModuleLock,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
