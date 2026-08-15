<?php

use App\Services\ModuleGuardService;


use App\Repositories\Connection;
use App\Repositories\StoreCategoriesRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;

ModuleGuardService::requireModule('store_delivery_tracking');


$router = new Router();

$router->get(function () {
    $repo = new StoreCategoriesRepository();
    $ownerId = (int)LoginService::getSession()->getOwner();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid category.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home");
    }

    $category = $repo->getOneByOwner(['id' => $id], $ownerId);

    if (!$category) {
        MessageUtil::setMessage("Category not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home");
    }

    $db = new Connection();
    $db->query("
        SELECT COUNT(*) AS total
        FROM store_products_categories
        WHERE id_category = :id_category
    ");
    $db->bind(':id_category', $id);
    $rel = $db->fetchOne();

    $totalRelated = (int)($rel->total ?? 0);

    if ($totalRelated > 0) {
        MessageUtil::setMessage("This category cannot be deleted because it is already assigned to one or more products.");


        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home");
    }

    $ok = $repo->delete(['id' => $id, 'id_owner' => $ownerId]);

    if (!$ok) {
        MessageUtil::setMessage("Category could not be deleted.");
        LocationUtils::redirectInternal("panel/planner-hub/store/categories/home");
    }

    MessageUtil::setMessage("Category deleted successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/categories/home");
});

$router->run();
