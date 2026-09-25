<?php

use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\LoginService;
use App\Services\UserInstitutionService;
use App\Services\UserWorkspaceContextService;
use App\Services\ProductProfileService;
use App\Repositories\UserRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\UserInstitutionsRepository;
use App\Repositories\TeamMemberContractsRepository;
use App\Repositories\CarrierPackageRepository;
use App\Utils\LocationUtils;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $userInstitutionService = new UserInstitutionService();
    $workspaceContextService = new UserWorkspaceContextService();
    $institutionRepo = new InstitutionProfileRepository();
    $teamContext = $workspaceContextService->getTeamContext($user);

    if (!empty($teamContext['selectedInstitutionId'])) {
        LoginService::reloadUserPermissions((int)$teamContext['selectedInstitutionId']);
        $user = LoginService::getSession();
    }

    $selectedOwnerId = (int)($teamContext['selectedOwnerId'] ?? $user->getOwner());
    if (ProductProfileService::isOphytrack() && $selectedOwnerId > 0 && (new CarrierPackageRepository())->isCarrier($selectedOwnerId)) {
        LocationUtils::redirectInternal('panel/planner-hub/team/driver-mode?view=deliveries');
        return;
    }
    
    $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
    $currentInstitutionRole = $_SESSION['current_institution_role'] ?? null;
    
    $userInstitutions = $userInstitutionService->getUserAvailableInstitutions($user->getId());
    
    $currentInstitution = null;
    $userInstitutionsRepo = new UserInstitutionsRepository();
    $userInstitutionData = null;
    $roleName = null;
    $hourlyRate = null;
    
    try {
        if ($currentInstitutionId) {
            $currentInstitution = $institutionRepo->getById($currentInstitutionId);
            
            if (!$currentInstitution) {
                $primaryInstitution = $userInstitutionService->getUserPrimaryInstitution($user->getId());
                if ($primaryInstitution) {
                    $currentInstitution = $institutionRepo->getById($primaryInstitution->institution_id);
                    $_SESSION['current_institution_id'] = $primaryInstitution->institution_id;
                    $_SESSION['current_institution_role'] = 'owner';
                }
            }
        } else {
            $primaryInstitution = $userInstitutionService->getUserPrimaryInstitution($user->getId());
            if ($primaryInstitution) {
                $currentInstitution = $institutionRepo->getById($primaryInstitution->institution_id);
                $_SESSION['current_institution_id'] = $primaryInstitution->institution_id;
                $_SESSION['current_institution_role'] = 'owner';
            }
        }
        
        if ($currentInstitution) {
            $userInstitutionData = $userInstitutionsRepo->getUserInstitutionRecord($user->getId(), $currentInstitution->id);
            if ($userInstitutionData) {
                $roleName = $userInstitutionData->role_name ?? 'No Role Assigned';
                $hourlyRate = $userInstitutionData->hourly_rate ?? 0;
                $contractDetail = $userInstitutionData->contract_detail ?? null;
            }
        }
    } catch (Exception $e) {
    }
    
    $hasMultipleInstitutions = count($userInstitutions) > 1;
    $teamContract = null;
    if ($currentInstitution && !empty($currentInstitution->id_owner)) {
        $teamContract = (new TeamMemberContractsRepository())->getLatestForMember($user->getId(), (int)$currentInstitution->id_owner);
    }
    
    $permissionModules = [];
    $delegableModules = ['users', 'orders', 'storage', 'crm', 'roles', 'payroll'];
    foreach ($user->getPermissions2() as $permission) {
        $module = strtolower((string)$permission->getModule());
        if (in_array($module, $delegableModules, true)) $permissionModules[] = $module;
    }
    $permissionModules = array_values(array_unique($permissionModules));

    return TemplateResponse::render(ProductProfileService::isOphytrack() ? __DIR__ . "/ophytrack.twig" : __DIR__ . "/index.twig", [
        'user' => $user,
        'userInstitutions' => $userInstitutions,
        'currentInstitution' => $currentInstitution,
        'currentInstitutionRole' => $currentInstitutionRole,
        'hasMultipleInstitutions' => $hasMultipleInstitutions,
        'userInstitutionData' => $userInstitutionData,
        'roleName' => $roleName,
        'hourlyRate' => $hourlyRate,
        'contractDetail' => $contractDetail ?? null,
        'teamContract' => $teamContract,
        'teamContext' => $teamContext,
        'permissionModules' => $permissionModules,
    ]);
});

$router->post(function () {
    if (isset($_POST['switch_institution'])) {
        $institutionId = $_POST['institution_id'] ?? null;
        $role = $_POST['role'] ?? 'employee';
        
        if ($institutionId) {
            $_SESSION['current_institution_id'] = $institutionId;
            $_SESSION['current_institution_role'] = $role;
            (new UserWorkspaceContextService())->getTeamContext(LoginService::getSession());
            
            LoginService::reloadUserPermissions((int)$institutionId);
            
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No institution ID provided']);
            exit;
        }
    }
    
    if (isset($_POST['level']) && ProductProfileService::isOphytrack()) {
        \App\Utils\MessageUtil::setMessage('Team access is controlled by the business owner.');
        LocationUtils::redirectInternal('panel/home');
        return;
    }

    if (isset($_POST['level'])) {
        $newLevel = (int)($_POST['level'] ?? 0);

        if ($newLevel === 2) {
            $user = LoginService::getSession();
            (new UserRepository())->updateData((int)$user->getId(), [
                'level' => 2,
                'id_owner' => (int)$user->getId(),
            ]);
            LoginService::reloadUserPermissions();
            LocationUtils::redirectInternal("panel/planner-hub/institution-profile");
            return;
        }

        if ($newLevel === 5) {
            $user = LoginService::getSession();
            (new UserRepository())->updateData((int)$user->getId(), [
                'level' => 5,
            ]);
            LoginService::reloadUserPermissions();
            LocationUtils::redirectInternal("panel/home");
            return;
        }

        LocationUtils::redirectInternal("panel/home");
        return;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}


