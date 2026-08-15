<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\OrdersContractRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    TranslationService::detectLocale();
    
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $repo = new OrdersContractRepository();

    $id = $_GET["id"] ?? null;
    if (!$id) LocationUtils::redirectInternal("panel/planner-hub/management/orders/contracts");

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $contract = $repo->getOneByIdAndOwner($id, $institution->id_owner);
            } else {
                $contract = null;
            }
        } else {
            $contract = null;
        }
    } else {
        $contract = $repo->getOneByIdAndOwner((int)$id, $ownerId);
    }
    
    if (!$contract) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.contract_not_found_permission'));
        LocationUtils::redirectInternal("panel/planner-hub/management/orders/contracts");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "contract" => $contract
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $repo = new OrdersContractRepository();

    $id = $_POST["id"] ?? null;
    $title = trim($_POST["title"] ?? "");
    $content = trim($_POST["content"] ?? "");

    if (!$id || $title === "" || $content === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.all_fields_required'));
        LocationUtils::reload();
    }

    $contract = $repo->getOneByIdAndOwner((int)$id, $ownerId);
    if (!$contract) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.contract_not_found_permission'));
        LocationUtils::reload();
    }

    $repo->update([
        "title" => $title,
        "content" => $content
    ], ["id" => $id]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.contract_updated_successfully'));
    LocationUtils::redirectInternal("panel/planner-hub/management/orders/contracts");
});

$router->run();
