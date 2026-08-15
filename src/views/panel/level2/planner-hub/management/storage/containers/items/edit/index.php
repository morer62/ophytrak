<?php

use App\Services\ModuleGuardService;


use App\Repositories\StorageContainerRepository;
use App\Repositories\StorageItemRepository;
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
    $itemRepo = new StorageItemRepository();

    $container = $containerRepo->getOne([
        ...LoginService::getOwnerAsArray(),
        "id" => $_GET["container"] ?? null
    ]);

    if (!$container) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.container_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    $item = $itemRepo->getOne([
        "id_container" => $container->id,
        "id" => $_GET["item"] ?? null
    ]);

    if (!$item) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.item_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers/items?container_id=$container->id");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "container_id" => $container->id,
        "item"  => $item,
        ...$context
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $context = UserContext::get();

   
    $repo = new StorageItemRepository();
    $containerRepo = new StorageContainerRepository();

    $name = $_POST["name"] ?? "";
    $quantity = intval($_POST["quantity"] ?? 0);

    $containerId = $_GET["container"] ?? null;
    $itemId = $_GET["item"] ?? null;

    if ($name === "" || !$containerId || $quantity <= 0 || !$itemId) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.all_fields_required'));
        LocationUtils::reload();
    }

    // Never allow a crafted URL to update inventory owned by another account.
    $container = $containerRepo->getOne([
        "id" => $containerId,
        ...LoginService::getOwnerAsArray()
    ]);
    $item = $container ? $repo->getOne([
        "id" => $itemId,
        "id_container" => $containerId
    ]) : null;

    if (!$container || !$item) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.item_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    $repo->update([
        "name" => $name,
        "quantity" => $quantity
    ], [
        "id_container" => $containerId,
        "id" => $itemId
    ]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.item_updated'));
    LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers/items?container_id=$containerId");
});

$router->run();
