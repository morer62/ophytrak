<?php

use App\Repositories\StoreAttributesRepository;
use App\Repositories\StoreAttributeValuesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {

    $attributeRepo = new StoreAttributesRepository();
    $valuesRepo = new StoreAttributeValuesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $idAttribute = intval($_GET['id_attribute'] ?? 0);

    if ($idAttribute <= 0) {
        MessageUtil::setMessage("Invalid attribute.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $attribute = $ownerId > 0 ? $attributeRepo->getOneByOwner(['id' => $idAttribute], $ownerId) : null;

    if (!$attribute) {
        MessageUtil::setMessage("Attribute not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "attribute" => $attribute,
        "values" => $valuesRepo->getByAttribute($idAttribute, $ownerId),
        "operation_context" => $operationContext
    ]);
});

$router->run();
