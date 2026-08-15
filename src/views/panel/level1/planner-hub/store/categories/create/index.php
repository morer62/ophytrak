<?php

use App\Repositories\StoreCategoriesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

function normalizeBuilderPayload($raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }

    json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $raw;
}

$router->get(function () {
    $operationContext = (new CentralOperationsContextService())->getContext();
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "operation_context" => $operationContext,
    ]);
});

$router->post(function () {
    $repo = new StoreCategoriesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $status = trim($_POST['status'] ?? StoreCategoriesRepository::STATUS_ACTIVE);
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $pageBuilderJson = normalizeBuilderPayload($_POST['page_builder_json'] ?? '');

    if ($name === '') {
        MessageUtil::setMessage("Category name is required.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/create" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    if ($ownerId <= 0) {
        MessageUtil::setMessage("Select a valid central operation before creating categories.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home");
    }

    $slugBase = $slugInput !== '' ? $slugInput : $name;
    $slug = $repo->generateUniqueSlug($slugBase, null, $ownerId);

    $ok = $repo->add([
        'id_owner' => $ownerId,
        'name' => $name,
        'slug' => $slug,
        'description' => $description ?: null,
        'icon' => $icon ?: null,
        'meta_title' => $metaTitle ?: null,
        'meta_description' => $metaDescription ?: null,
        'page_builder_json' => $pageBuilderJson,
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    if (!$ok) {
        MessageUtil::setMessage("Category could not be created.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/create" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    MessageUtil::setMessage("Category created successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/categories/home" . ($operationQuery ? '?' . $operationQuery : ''));
});

$router->run();
