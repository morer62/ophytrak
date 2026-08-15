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
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $repo = new StoreCategoriesRepository();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid category.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $category = $ownerId > 0 ? $repo->getOneByOwner(['id' => $id], $ownerId) : null;

    if (!$category) {
        MessageUtil::setMessage("Category not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "category" => $category,
        "operation_context" => $operationContext
    ]);
});

$router->post(function () {

    $repo = new StoreCategoriesRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $status = trim($_POST['status'] ?? 'ACTIVE');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $pageBuilderJson = normalizeBuilderPayload($_POST['page_builder_json'] ?? '');

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid category.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    if ($name === '') {
        MessageUtil::setMessage("Category name is required.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/edit?id=" . $id);
    }

    if ($ownerId <= 0 || !$repo->getOneByOwner(['id' => $id], $ownerId)) {
        MessageUtil::setMessage("Category not found in this operation.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $slugBase = $slugInput !== '' ? $slugInput : $name;
    $slug = $repo->generateUniqueSlug($slugBase, $id, $ownerId);

    $repo->update([
        'name' => $name,
        'slug' => $slug,
        'description' => $description ?: null,
        'icon' => $icon ?: null,
        'meta_title' => $metaTitle ?: null,
        'meta_description' => $metaDescription ?: null,
        'page_builder_json' => $pageBuilderJson,
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ], [
        'id' => $id,
        'id_owner' => $ownerId
    ]);

    MessageUtil::setMessage("Category updated successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/categories/home" . ($operationQuery ? '?' . $operationQuery : ''));
});

$router->run();
