<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreAttributesRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $repo = new StoreAttributesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("store_attributes_messages.invalid");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $attribute = $repo->getOneByOwner(['id' => $id], $ownerId);

    if (!$attribute) {
        MessageUtil::setMessage("store_attributes_messages.not_found");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "attribute" => $attribute
    ]);
});

$router->post(function () {
    $repo = new StoreAttributesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? StoreAttributesRepository::STATUS_ACTIVE);

    if ($id <= 0) {
        MessageUtil::setMessage("store_attributes_messages.invalid");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    if ($name === '') {
        MessageUtil::setMessage("store_attributes_messages.name_required");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/edit?id=" . $id);
    }

    $attribute = $repo->getOneByOwner(['id' => $id], $ownerId);
    if (!$attribute) {
        MessageUtil::setMessage("store_attributes_messages.not_found");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $slug = $repo->generateUniqueSlug($name, $id, $ownerId);

    $repo->update([
        'name' => $name,
        'slug' => $slug,
        'status' => $status
    ], [
        'id' => $id,
        'id_owner' => $ownerId
    ]);

    MessageUtil::setMessage("store_attributes_messages.updated");
    LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
});

$router->run();
