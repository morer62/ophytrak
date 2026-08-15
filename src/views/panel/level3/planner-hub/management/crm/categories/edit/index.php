<?php

use App\Repositories\CrmCategoryRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

$router = new Router();
$repo = new CrmCategoryRepository();

$router->get(function () use ($repo) {
    $context = UserContext::get();
    $user = LoginService::getSession();

    $id = $_GET["id"] ?? null;

    if (!$id) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.all_fields_required'));
        LocationUtils::redirectInternal("panel/planner-hub/management/crm/categories");
    }

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $institutionOwnerId = $institution ? $institution->id_owner : null;
            
            if ($institutionOwnerId) {
                $category = $repo->getOneByIdAndOwner($id, $institutionOwnerId);
            } else {
                $category = null;
            }
        } else {
            $category = null;
        }
    } else {
        $category = $repo->getOne([
            "id" => $id,
            ...LoginService::getUserIdAsArray(),
            ...LoginService::getOwnerAsArray()
        ]);
    }

    if (!$category) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.category_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/crm/categories");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$context,
        "category" => $category
    ]);
});

$router->post(function () use ($repo) {
    TranslationService::detectLocale();
    $context = UserContext::get();
    $user = LoginService::getSession();

    $id = $_GET["id"] ?? null;
    $name = trim($_POST["name"] ?? "");

    if (!$id || $name === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.all_fields_required'));
        LocationUtils::reload();
    }

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $institutionOwnerId = $institution ? $institution->id_owner : null;
            
            if ($institutionOwnerId) {
                $category = $repo->getOneByIdAndOwner($id, $institutionOwnerId);
                
                if (!$category) {
                    MessageUtil::setMessage(TranslationService::trans('planner_hub.category_not_found_permission'));
                    LocationUtils::redirectInternal("panel/planner-hub/management/crm/categories");
                }
            } else {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.institution_not_found'));
                LocationUtils::redirectInternal("panel/planner-hub/management/crm/categories");
            }
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.no_institution_selected'));
            LocationUtils::redirectInternal("panel/planner-hub/management/crm/categories");
        }
    }

    $repo->update([
        "name" => $name
    ], [
        "id" => $id
    ]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.category_updated_successfully'));
    LocationUtils::redirectInternal("panel/planner-hub/management/crm/categories");
});

$router->run();
