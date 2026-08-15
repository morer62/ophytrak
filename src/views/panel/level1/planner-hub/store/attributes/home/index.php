<?php

use App\Repositories\StoreAttributesRepository;
use App\Repositories\StoreAttributeValuesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $attributesRepo = new StoreAttributesRepository();
    $valuesRepo = new StoreAttributeValuesRepository();

    $attributes = $ownerId > 0 ? $attributesRepo->getActive($ownerId) : [];

    foreach ($attributes as $attribute) {
        $allValues = $valuesRepo->getByAttribute((int)$attribute->id, $ownerId);
        $activeValues = $valuesRepo->getActiveByAttribute((int)$attribute->id, $ownerId);

        $attribute->values_count = is_array($allValues) ? count($allValues) : 0;
        $attribute->active_values_count = is_array($activeValues) ? count($activeValues) : 0;
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "attributes" => $attributes,
        "operation_context" => $operationContext
    ]);
});

$router->run();
