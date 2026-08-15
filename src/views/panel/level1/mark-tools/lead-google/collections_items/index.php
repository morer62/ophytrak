<?php

use App\Repositories\LeadsCollectionsRepository;
use App\Repositories\LeadsCollectionsItemsRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$itemsRepo = new LeadsCollectionsItemsRepository();
$collectionsRepo = new LeadsCollectionsRepository();

$user = LoginService::getSession();

// Validar collection_id via GET
$collectionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$collectionId) {
    TranslationService::detectLocale();
    MessageUtil::setMessage(TranslationService::trans('planner_hub.collection_not_found'), 'danger');
    LocationUtils::reload();
}

// Obtener colección (opcional)
$collection = $collectionsRepo->getOne(['id' => $collectionId]);
if (!$collection) {
    TranslationService::detectLocale();
    MessageUtil::setMessage(TranslationService::trans('planner_hub.collection_not_found'), 'danger');
    LocationUtils::reload();
}

// Acción de eliminación de un lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $itemId = (int)$_POST['delete_item'];
    $itemsRepo->delete(['id' => $itemId]);
    TranslationService::detectLocale();
    MessageUtil::setMessage(TranslationService::trans('planner_hub.lead_deleted_successfully'));
    LocationUtils::reload();
}

// Exportar CSV vía POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["export_csv"])) {
    $collectionIdExport = (int) $_POST["collection_id"];
    $exportItems = $itemsRepo->getAllBy(["collection_id" => $collectionIdExport]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=leads_collection_' . $collectionIdExport . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Phone', 'Email', 'Website', 'Address']);

    foreach ($exportItems as $item) {
        fputcsv($output, [
            $item->name,
            $item->phone,
            $item->email,
            $item->website,
            $item->address
        ]);
    }

    fclose($output);
    exit;
}


// Listar leads de la colección
$items = $itemsRepo->getAllBy(['collection_id' => $collectionId]);

echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'collection' => $collection,
    'items' => $items, 
]);
