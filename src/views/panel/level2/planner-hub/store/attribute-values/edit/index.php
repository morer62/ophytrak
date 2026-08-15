<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreAttributeValuesRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $repo = new StoreAttributeValuesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid attribute value.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $value = $repo->getOneByOwner(['id' => $id], $ownerId);

    if (!$value) {
        MessageUtil::setMessage("Attribute value not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "value" => $value
    ]);
});

$router->post(function () {
    $repo = new StoreAttributeValuesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $id = intval($_POST['id'] ?? 0);
    $idAttribute = intval($_POST['id_attribute'] ?? 0);
    $value = trim($_POST['value'] ?? '');
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $status = trim($_POST['status'] ?? StoreAttributeValuesRepository::STATUS_ACTIVE);

    if ($id <= 0 || $idAttribute <= 0) {
        MessageUtil::setMessage("Invalid attribute value.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    if ($value === '') {
        MessageUtil::setMessage("Value is required.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attribute-values/edit?id=" . $id);
    }

    $existingValue = $repo->getOneByOwner(['id' => $id, 'id_attribute' => $idAttribute], $ownerId);
    if (!$existingValue) {
        MessageUtil::setMessage("Attribute value not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $slug = $repo->generateUniqueSlug($idAttribute, $value, $id, $ownerId);

    $repo->update([
        'value' => $value,
        'slug' => $slug,
        'sort_order' => $sortOrder,
        'status' => $status
    ], [
        'id' => $id,
        'id_owner' => $ownerId
    ]);

    MessageUtil::setMessage("Attribute value updated successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/attribute-values/home?id_attribute=" . $idAttribute);
});

$router->run();
