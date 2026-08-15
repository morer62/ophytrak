<?php

use App\Repositories\Connection;
use App\Repositories\StoreProductsNutritionRepository;
use App\Repositories\StoreProductsRepository;
use App\Repositories\StoreProductsCategoriesRepository;
use App\Repositories\StoreProductsAttributesRepository;
use App\Services\CentralOperationsContextService;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    $productsRepo = new StoreProductsRepository();
    $productsCategoriesRepo = new StoreProductsCategoriesRepository();
    $productsAttributesRepo = new StoreProductsAttributesRepository();
    $nutritionRepo = new StoreProductsNutritionRepository();
    $operationContext = (new CentralOperationsContextService())->getContext();
    $ownerId = (int)($operationContext['owner_id'] ?? 0);
    $operationQuery = (string)($operationContext['query'] ?? '');

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        MessageUtil::setMessage("Invalid product.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $product = $ownerId > 0 ? $productsRepo->getOneByOwner(['id' => $id], $ownerId) : null;

    if (!$product) {
        MessageUtil::setMessage("Product not found.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $db = new Connection();
    $db->query("
        SELECT COUNT(*) AS total
        FROM store_order_items
        WHERE id_product = :id_product
    ");
    $db->bind(':id_product', $id);
    $rel = $db->fetchOne();

    if ((int)($rel->total ?? 0) > 0) {
        MessageUtil::setMessage("This product cannot be deleted because it is already associated with one or more orders.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    $productsCategoriesRepo->deleteByProduct($id);
    $productsAttributesRepo->deleteByProduct($id);

    $nutrition = $nutritionRepo->getByProduct($id);
    if ($nutrition) {
        $nutritionRepo->delete(['id' => $nutrition->id]);
    }

    if (!empty($product->main_image)) {
        FileUtils::removeFile($product->main_image);
    }

    $ok = $productsRepo->delete(['id' => $id]);

    if (!$ok) {
        MessageUtil::setMessage("Product could not be deleted.");
        LocationUtils::redirectInternal("panel/planner-hub/store/products/home" . ($operationQuery ? '?' . $operationQuery : ''));
    }

    MessageUtil::setMessage("Product deleted successfully.");
    LocationUtils::redirectInternal("panel/planner-hub/store/products/home" . ($operationQuery ? '?' . $operationQuery : ''));
});

$router->run();
