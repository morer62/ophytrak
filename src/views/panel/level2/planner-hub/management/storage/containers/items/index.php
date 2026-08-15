<?php

use App\Services\ModuleGuardService;


use App\Repositories\StorageContainerRepository;
use App\Repositories\StorageItemRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;
use App\Utils\UserContext;

ModuleGuardService::requireModule('inventory_storage');


$router = new Router();

$router->get(function () {
    $context = UserContext::get();

   
    $user = LoginService::getSession();
    $containerId = $_GET["container_id"] ?? null;

    $storageItemRepository = new StorageItemRepository();
    $containerRepo = new StorageContainerRepository();

    if (!$containerId) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.missing_container_id'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    $container = $containerRepo->getOne([
       "id" => $containerId,
       ...LoginService::getOwnerAsArray()
    ]);

    if (!$container) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.container_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    $items = $storageItemRepository->getAllBy([
        "id_container" => $containerId
    ]);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "items" => $items,
        "containerId" => $containerId,
        "container" => $container,
        ...$context
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $itemId = $_POST["id"] ?? null;
    $containerId = $_GET["container_id"] ?? null;

    $containerRepo = new StorageContainerRepository();
    $storageItemRepository = new StorageItemRepository();
    $user = LoginService::getSession();

    $container = $containerRepo->getOne([
        "id" => $containerId,
        "id_owner" => $user->getId()
    ]);

    if (!$container) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.container_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    if (is_null($itemId)) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.missing_item_id'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers/items?container_id={$containerId}");
    }

    $item = $storageItemRepository->getOne([
       "id" => $itemId,
       "id_container" => $containerId,
    ]);

    if (!$item) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.item_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers/items?container_id={$containerId}");
    }

    $storageItemRepository->delete([
       "id" => $itemId
    ]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.item_deleted'));
    LocationUtils::reload();
});

$router->run();