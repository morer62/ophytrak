<?php

use App\Repositories\Connection;
use App\Repositories\StoreAttributesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    $repo = new StoreAttributesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid attribute.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $attribute = $ownerId > 0 ? $repo->getOneByOwner(['id' => $id], $ownerId) : null;

    if (!$attribute) {
        MessageUtil::setMessage("Attribute not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $db = new Connection();

    $db->query("
        SELECT COUNT(*) AS total
        FROM store_attribute_values
        WHERE id_attribute = :id_attribute
    ");
    $db->bind(':id_attribute', $id);
    $valuesCount = $db->fetchOne();

    if ((int)($valuesCount->total ?? 0) > 0) {
        MessageUtil::setMessage("This attribute cannot be deleted because it already has values associated.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $db->query("
        SELECT COUNT(*) AS total
        FROM store_products_attributes
        WHERE id_attribute = :id_attribute
    ");
    $db->bind(':id_attribute', $id);
    $productsCount = $db->fetchOne();

    if ((int)($productsCount->total ?? 0) > 0) {
        MessageUtil::setMessage("This attribute cannot be deleted because it is already assigned to one or more products.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $ok = $repo->delete(['id' => $id]);

    if (!$ok) {
        MessageUtil::setMessage("Attribute could not be deleted.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    MessageUtil::setMessage("Attribute deleted successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
});

$router->run();
