<?php

use App\Repositories\StoreAttributeValuesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repo = new StoreAttributeValuesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid attribute value.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $value = $ownerId > 0 ? $repo->getOneByOwner(['id' => $id], $ownerId) : null;

    if (!$value) {
        MessageUtil::setMessage("Attribute value not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "value" => $value,
        "operation_context" => $operationContext
    ]);
});

$router->post(function () {
    $repo = new StoreAttributeValuesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_POST['id'] ?? 0);
    $idAttribute = intval($_POST['id_attribute'] ?? 0);
    $value = trim($_POST['value'] ?? '');
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $status = trim($_POST['status'] ?? StoreAttributeValuesRepository::STATUS_ACTIVE);

    if ($id <= 0 || $idAttribute <= 0) {
        MessageUtil::setMessage("Invalid attribute value.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    if ($value === '') {
        MessageUtil::setMessage("Value is required.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attribute-values/edit?id=" . $id);
    }

    if ($ownerId <= 0 || !$repo->getOneByOwner(['id' => $id], $ownerId)) {
        MessageUtil::setMessage("Attribute value not found in this operation.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
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
    LocationUtils::redirectInternal("panel/planner-hub/store/attribute-values/home?id_attribute=" . $idAttribute . ($operationQuery ? '&' . $operationQuery : ''));
});

$router->run();
