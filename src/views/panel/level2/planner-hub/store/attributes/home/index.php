<?php

use App\Services\ModuleGuardService;


use App\Repositories\StoreAttributesRepository;
use App\Repositories\StoreAttributeValuesRepository;
use App\Services\LoginService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $attributesRepo = new StoreAttributesRepository();
    $valuesRepo = new StoreAttributeValuesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $attributes = [];

    try {
        $attributes = $attributesRepo->getActive($ownerId);
    } catch (\Throwable $exception) {
        error_log('Store attributes home attributes failed: ' . $exception->getMessage());
    }

    foreach ($attributes as $attribute) {
        $allValues = [];
        $activeValues = [];

        try {
            $allValues = $valuesRepo->getByAttribute((int)$attribute->id, $ownerId);
            $activeValues = $valuesRepo->getActiveByAttribute((int)$attribute->id, $ownerId);
        } catch (\Throwable $exception) {
            error_log('Store attributes home values failed for attribute ' . (int)$attribute->id . ': ' . $exception->getMessage());
        }

        $attribute->values_count = is_array($allValues) ? count($allValues) : 0;
        $attribute->active_values_count = is_array($activeValues) ? count($activeValues) : 0;
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "attributes" => $attributes
    ]);
});

$router->run();
