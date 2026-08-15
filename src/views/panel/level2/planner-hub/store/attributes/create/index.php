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
    return TemplateResponse::render(__DIR__ . "/index.twig", []);
});

$router->post(function () {
    $repo = new StoreAttributesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $name = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? StoreAttributesRepository::STATUS_ACTIVE);

    if ($name === '') {
        MessageUtil::setMessage("store_attributes_messages.name_required");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/create");
    }

    $slug = $repo->generateUniqueSlug($name, null, $ownerId);

    $ok = $repo->add([
        'id_owner' => $ownerId,
        'name' => $name,
        'slug' => $slug,
        'status' => $status
    ]);

    if (!$ok) {
        MessageUtil::setMessage("store_attributes_messages.create_failed");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/create");
    }

    MessageUtil::setMessage("store_attributes_messages.created");
    LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
});

$router->run();
