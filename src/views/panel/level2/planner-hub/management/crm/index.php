<?php

use App\Entity\User;
use App\Repositories\CrmCategoryRepository;
use App\Repositories\CrmLeadRepository;
use App\Services\LoginService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();
    $user = LoginService::getSession();
    $leadRepo = new CrmLeadRepository();
    $categoryRepo = new CrmCategoryRepository();

    $institutionOwnerId = null;
    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $institutionOwnerId = $institution ? $institution->id_owner : null;
        }
    }

    if ($user->getLevel() === 4 && $institutionOwnerId) {
        $baseFilters = [
            'id_user' => $user->getId(),
            'id_owner' => $institutionOwnerId,
        ];
        $categories = $categoryRepo->getAllByInstitutionOwner($institutionOwnerId);
    } else {
        $baseFilters = [
            ...(in_array($user->getLevel(), User::EXTERNAL_USER_LEVEL) ? LoginService::getUserIdAsArray(true) : []),
            ...LoginService::getOwnerAsArray(),
        ];
        $categories = $categoryRepo->getAllBy(['id_user' => $user->getId()]);
    }

    $activeLeads = $leadRepo->paginateAndFilter([...$baseFilters, 'archived' => 'NO'], 1, 1);
    $archivedLeads = $leadRepo->paginateAndFilter([...$baseFilters, 'archived' => 'YES'], 1, 1);

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        ...$context,
        'active_leads_count' => $activeLeads['total'],
        'archived_leads_count' => $archivedLeads['total'],
        'total_leads_count' => $activeLeads['total'] + $archivedLeads['total'],
        'categories_count' => count($categories),
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}