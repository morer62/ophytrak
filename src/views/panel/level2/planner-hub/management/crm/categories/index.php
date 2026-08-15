<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\CrmCategoryRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();
    $user = LoginService::getSession();
    $repo = new CrmCategoryRepository();

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        $institutionOwnerId = null;
        
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $institutionOwnerId = $institution ? $institution->id_owner : null;
        }
        
        if ($institutionOwnerId) {
            $categories = $repo->getAllByInstitutionOwner($institutionOwnerId);
        } else {
            $categories = [];
        }
    } else {
        $categories = $repo->getAllBy([
            ...LoginService::getUserIdAsArray(),
            ...LoginService::getOwnerAsArray()
        ]);
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$context,
        "categories" => $categories
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $context = UserContext::get();

    // ⬅️ Agrega esto:
    if ($context["level"] === 4) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.only_administrators_delete'));
        LocationUtils::reload();
    }

    $user = LoginService::getSession();
    $repo = new CrmCategoryRepository();
    $id = $_POST["id"] ?? null;

    if (!$id) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_category_id'));
        LocationUtils::reload();
    }

    $category = $repo->getOne([
        "id" => $id,
        "id_user" => $user->getId()
    ]);

    if (!$category) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.category_not_found'));
        LocationUtils::reload();
    }

    $repo->delete([
        "id" => $id
    ]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.category_deleted_successfully'));
    LocationUtils::reload();
});

$router->run();
