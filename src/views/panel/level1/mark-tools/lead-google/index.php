<?php

use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Repositories\LeadsCollectionsRepository;
use App\Repositories\LeadsCollectionsItemsRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Services\TranslationService;

$router = new Router();

$router->get(function () {
    $LeadsCollectionsRepo = new LeadsCollectionsRepository();
    $collections = $LeadsCollectionsRepo->getAll();

    echo TemplateResponse::render(__DIR__ . "/index.twig", [
        "collections" => $collections,
        "message" => MessageUtil::getMessage()
    ]);
});

$router->post(function () {
    if (!isset($_POST["collection_id"]) || !isset($_POST["leads"])) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.missing_data_add_leads'), "danger");
        LocationUtils::reload();
        return;
    }

    $collection_id = intval($_POST["collection_id"]);
    $leadsEncoded = $_POST["leads"];

    $repo = new LeadsCollectionsItemsRepository();
    $count = 0;

    foreach ($leadsEncoded as $leadDataEncoded) {
        $decoded = json_decode(base64_decode($leadDataEncoded), true);

        if (!$decoded || !is_array($decoded)) {
            continue; // skip invalid
        }

        $repo->add([
            "collection_id" => $collection_id,
            "name" => $decoded["name"] ?? null,
            "phone" => $decoded["phone"] ?? null,
            "email" => $decoded["email"] ?? null,
            "website" => $decoded["website"] ?? null,
            "address" => $decoded["address"] ?? null,
            "place_id" => $decoded["place_id"] ?? null,
        ]);

        $count++;
    }

    TranslationService::detectLocale();
    $message = str_replace('{count}', $count, TranslationService::trans('planner_hub.leads_saved_to_collection'));
    MessageUtil::setMessage("✅ " . $message);
    LocationUtils::reload();
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
