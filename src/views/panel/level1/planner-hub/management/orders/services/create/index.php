<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\OrdersServiceRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();

    

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$context
    ]);
});

$router->post(function () {
    $context = UserContext::get();
 
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $repo = new OrdersServiceRepository();

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $description_url = trim($_POST["description_url"] ?? "");
    $price = floatval($_POST["price"] ?? 0);
    $is_variable = isset($_POST["is_variable"]) ? "YES" : "NO";

    TranslationService::detectLocale();
    
    if ($name === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.service_name_required'));
        LocationUtils::reload();
    }

    if ($is_variable === "NO" && $price <= 0) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.price_required_fixed'));
        LocationUtils::reload();
    }

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $repo->addWithExplicitOwner([
                    "name" => $name,
                    "description" => $description,
                    "description_url" => $description_url,
                    "price" => $price,
                    "is_variable" => $is_variable,
                    "is_archived" => 0,
                    "id_owner" => $institution->id_owner
                ]);
            } else {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.institution_not_found'));
                LocationUtils::reload();
            }
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.no_institution_selected'));
            LocationUtils::reload();
        }
    } else {
        $repo->addWithExplicitOwner([
            "name" => $name,
            "description" => $description,
            "description_url" => $description_url,
            "price" => $price,
            "is_variable" => $is_variable,
            "is_archived" => 0,
            "id_owner" => $ownerId,
        ]);
    }

    MessageUtil::setMessage(TranslationService::trans('planner_hub.service_created_successfully'));
    LocationUtils::redirectInternal("panel/planner-hub/management/orders/services");
});

$router->run();
