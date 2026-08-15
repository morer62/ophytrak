<?php

use App\Repositories\StoreAttributesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repo = new StoreAttributesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid attribute.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $attribute = $ownerId > 0 ? $repo->getOneByOwner(['id' => $id], $ownerId) : null;

    if (!$attribute) {
        MessageUtil::setMessage("Attribute not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "attribute" => $attribute,
        "operation_context" => $operationContext
    ]);
});

$router->post(function () {
    $repo = new StoreAttributesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? StoreAttributesRepository::STATUS_ACTIVE);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid attribute.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    if ($name === '') {
        MessageUtil::setMessage("Attribute name is required.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/edit?id=" . $id);
    }

    if ($ownerId <= 0 || !$repo->getOneByOwner(['id' => $id], $ownerId)) {
        MessageUtil::setMessage("Attribute not found in this operation.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
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

    MessageUtil::setMessage("Attribute updated successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
});

$router->run();
