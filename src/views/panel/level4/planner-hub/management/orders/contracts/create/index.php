<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\OrdersContractRepository;
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
    TranslationService::detectLocale();
    
    $context = UserContext::get();
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();

    $repo = new OrdersContractRepository();

    $title = trim($_POST["title"] ?? "");
    $content = trim($_POST["content"] ?? "");

    if ($title === "" || $content === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.title_content_required'));
        LocationUtils::reload();
    }

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $repo->addWithExplicitOwner([
                    "title" => $title,
                    "content" => $content,
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
            "title" => $title,
            "content" => $content,
            "id_owner" => $ownerId
        ]);
    }

    MessageUtil::setMessage(TranslationService::trans('planner_hub.contract_created_successfully'));
    LocationUtils::redirectInternal("panel/planner-hub/management/orders/contracts");
});

$router->run();
