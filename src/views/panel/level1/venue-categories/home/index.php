<?php

use App\Repositories\VenueCategoriesRepository;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {

    $venueCategoryRepo = new VenueCategoriesRepository();
    $categories = $venueCategoryRepo->getAll();



    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "categories" => $categories,
    ]);
});

$router->post(function () {
   TranslationService::detectLocale();
   $id = $_POST['id'];
   $repo = new VenueCategoriesRepository();

   $cat = $repo->getOne([
       "id" => $id
   ]);

   if (is_null($cat)) {
       MessageUtil::setMessage(TranslationService::trans('planner_hub.category_not_found'), "Error", "error");
       LocationUtils::redirectInternal('panel/venue-categories/home');
   }

   $repo->delete([
       "id" => $id
   ]);
   MessageUtil::setMessage(TranslationService::trans('planner_hub.category_deleted_successfully'));
   LocationUtils::redirectInternal('panel/venue-categories/home');
});
try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
