<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreAttributesRepository;
use App\Repositories\StoreAttributeValuesRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {

    $attributeRepo = new StoreAttributesRepository();
    $valuesRepo = new StoreAttributeValuesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $idAttribute = intval($_GET['id_attribute'] ?? $_GET['attribute_id'] ?? 0);

    if ($idAttribute <= 0) {
        MessageUtil::setMessage("Invalid attribute.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $attribute = $attributeRepo->getOneByOwner(['id' => $idAttribute], $ownerId);

    if (!$attribute) {
        MessageUtil::setMessage("Attribute not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "attribute" => $attribute,
        "values" => $valuesRepo->getByAttribute($idAttribute, $ownerId)
    ]);
});

$router->run();
