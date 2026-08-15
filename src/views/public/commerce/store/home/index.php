<?php

use App\Repositories\StoreProductsRepository;
use App\Repositories\StoreProductVariationsRepository;
use App\Repositories\StoreProductsCategoriesRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Services\StoreOwnerResolverService;
use App\Services\StorefrontAccessService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $ownerId = StoreOwnerResolverService::resolve((int)($_GET['owner'] ?? 0));
    $storeAvailable = StorefrontAccessService::ownerCanUseStore($ownerId);
    $products = $storeAvailable ? (new StoreProductsRepository())->getPublicActiveProducts(500, $ownerId) : [];
    $requestedProduct = trim((string)($_GET['product'] ?? ''));
    $requestedCategory = trim((string)($_GET['category'] ?? ''));
    if ($requestedCategory !== '') {
        $categoryProductIds = array_flip((new StoreProductsCategoriesRepository())
            ->getProductIdsByCategorySlug($ownerId, $requestedCategory));
        $products = array_values(array_filter(
            $products,
            static fn ($product) => isset($categoryProductIds[(int)$product->id])
        ));
    }
    $variationsRepo = new StoreProductVariationsRepository();
    $profile = (new InstitutionProfileRepository())->getByOwner($ownerId);

    foreach ($products as $product) {
        if (($product->product_type ?? '') !== StoreProductsRepository::PRODUCT_TYPE_VARIABLE) {
            continue;
        }

        $product->variations = array_values(array_filter(
            $variationsRepo->getDetailedByProduct((int)$product->id),
            static fn ($variation) => ($variation->status ?? '') === 'ACTIVE'
                && (int)($variation->stock_quantity ?? 0) > 0
        ));
    }

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'owner_id' => $ownerId,
        'products' => $products,
        'store_available' => $storeAvailable,
        'profile' => $profile,
        'profile_url' => $profile && !empty($profile->slug) ? ('/business-profile?slug=' . urlencode((string)$profile->slug)) : '',
        'requested_product' => $requestedProduct,
        'requested_category' => $requestedCategory,
    ]);
});

$router->run();
