<?php

use App\Services\ModuleGuardService;


use App\Repositories\StorageContainerRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

ModuleGuardService::requireModule('inventory_storage');


$router = new Router();

$router->get(function () {
    $context = UserContext::get();

   
    $containerRepo = new StorageContainerRepository();

    $containers = $containerRepo->getDetailedByOwner((int)LoginService::getSession()->getOwner());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "containers" => $containers,
        ...$context
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $containerRepo = new StorageContainerRepository();
    $user = LoginService::getSession();

    $containerId = $_POST["id"] ?? null;

    $container = $containerRepo->getOne([
        "id" => $containerId,
        ...LoginService::getOwnerAsArray()
    ]);

    if (!$container) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.container_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    $containerRepo->delete([
        "id" => $containerId
    ]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.container_deleted'));
    LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
});

$router->run();
