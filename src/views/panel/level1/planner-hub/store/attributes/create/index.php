<?php

use App\Repositories\StoreAttributesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $operationContext = (new CentralOperationsContextService())->getContext();
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "operation_context" => $operationContext,
    ]);
});

$router->post(function () {
    $repo = new StoreAttributesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $name = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? StoreAttributesRepository::STATUS_ACTIVE);

    if ($name === '') {
        MessageUtil::setMessage("Attribute name is required.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/create" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    if ($ownerId <= 0) {
        MessageUtil::setMessage("Select a valid central operation before creating attributes.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home");
    }

    $slug = $repo->generateUniqueSlug($name, null, $ownerId);

    $ok = $repo->add([
        'id_owner' => $ownerId,
        'name' => $name,
        'slug' => $slug,
        'status' => $status
    ]);

    if (!$ok) {
        MessageUtil::setMessage("Attribute could not be created.");
        LocationUtils::redirectInternal("panel/planner-hub/store/attributes/create" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    MessageUtil::setMessage("Attribute created successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/attributes/home" . ($operationQuery ? '?' . $operationQuery : ''));
});

$router->run();
