<?php

use App\Services\ModuleGuardService;
ModuleGuardService::requireModule('inventory_storage');

use App\Repositories\StorageContainerRepository;
use App\Repositories\StorageItemRepository;
use App\Repositories\StorageContainerCategoryRepository;
use App\Services\LoginService;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

$user = LoginService::getSession();
$context = UserContext::get();
$containerRepo = new StorageContainerRepository();
$itemRepo = new StorageItemRepository();

$term = trim($_GET["q"] ?? "");
$containers = $containerRepo->getAllBy([
    ...LoginService::getOwnerAsArray()
]);
$results = $term !== ''
    ? $itemRepo->searchByName(userId: (int)$user->getOwner(), term: $term)
    : $itemRepo->getAllByOwner((int)$user->getOwner());

TemplateResponse::renderAndDisplay(__DIR__ . "/index.twig", [
    "containers_count" => count($containers),
    "items_count" => $itemRepo->countByOwner((int)$user->getOwner()),
    "container_categories_count" => count((new StorageContainerCategoryRepository())->getByOwner((int)$user->getOwner())),
    "first_container_id" => $containers[0]->id ?? null,
    "results" => $results,
    "term" => $term,
    ...$context
]);
