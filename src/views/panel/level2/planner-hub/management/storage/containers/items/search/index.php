<?php

use App\Services\ModuleGuardService;


use App\Repositories\StorageItemRepository;
use App\Services\LoginService;
use App\Utils\TemplateResponse;
use App\Utils\Router;

ModuleGuardService::requireModule('inventory_storage');


$router = new Router();

$router->get(callback: function (): void {
    $user = LoginService::getSession();
    $term = $_GET["q"] ?? "";
    $repo = new StorageItemRepository();
    $results = trim((string)$term) !== ''
        ? $repo->searchByName(userId: (int)$user->getOwner(), term: trim((string)$term))
        : $repo->getAllByOwner((int)$user->getOwner());

    echo TemplateResponse::render(templateLocation: __DIR__ . "/index.twig", data: [
        "results" => $results,
        "term" => $term
    ]);
});

$router->run();
