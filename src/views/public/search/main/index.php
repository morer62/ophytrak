<?php

use App\Repositories\VenueRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\VenueCategoriesRepository;
use App\Repositories\ServiceCategoriesRepository;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    $category = $_GET['category'] ?? null;
    $type = $_GET['type'] ?? 'all'; // 'all', 'venues', 'vendors'
    $range = $_GET['range'] ?? 25;
    $filterByLocation = isset($_GET['filter_location']) && $_GET['filter_location'] === '1';
    $globalSearch = isset($_GET['global_search']) && $_GET['global_search'] === '1'; // Opción explícita para búsqueda global

    // Si hay lat/lng, automáticamente filtrar por ubicación (a menos que se pida explícitamente búsqueda global)
    $shouldFilterByLocation = ($lat && $lng && !$globalSearch) || $filterByLocation;

    // Normalizar categorías a array de enteros si viene category[]
    $categoryIds = [];
    if (is_array($category)) {
        $categoryIds = array_values(array_unique(array_map('intval', $category)));
    } elseif (!is_null($category) && $category !== '') {
        $categoryIds = [intval($category)];
    }

    $venues = [];
    $services = [];
    $noResults = false;
    $isGlobalSearch = !$shouldFilterByLocation;

    $venueRepo = new VenueRepository();
    $serviceRepo = new ServiceRepository();
    $venueCatRepo = new VenueCategoriesRepository();
    $serviceCatRepo = new ServiceCategoriesRepository();

    // Búsqueda de venues
    if ($type === 'all' || $type === 'venues') {
        if ($shouldFilterByLocation && $lat && $lng) {
            $isGlobalSearch = false;
            $venues = $venueRepo->searchByCategoriesAndLocation($categoryIds, $lat, $lng, $range);
        } else {
            if (!empty($categoryIds)) {
                $venues = $venueRepo->searchByCategoriesGlobal($categoryIds);
            } else {
                $venues = $venueRepo->getAllApprovedGlobal();
            }
        }
    }

    // Búsqueda de vendors/services
    if ($type === 'all' || $type === 'vendors') {
        if ($shouldFilterByLocation && $lat && $lng) {
            $isGlobalSearch = false;
            if (empty($categoryIds)) {
                $services = $serviceRepo->searchAllByLocation($lat, $lng, $range);
            } elseif (count($categoryIds) === 1) {
                $services = $serviceRepo->searchByCategoryAndLocation($categoryIds[0], $lat, $lng, $range);
            } else {
                $found = [];
                foreach ($categoryIds as $cid) {
                    foreach ($serviceRepo->searchByCategoryAndLocation($cid, $lat, $lng, $range) as $row) {
                        $found[$row->id] = $row;
                    }
                }
                $services = array_values($found);
            }
        } else {
            if (!empty($categoryIds)) {
                $services = $serviceRepo->searchByCategoriesGlobal($categoryIds);
            } else {
                $services = $serviceRepo->getAllApprovedGlobal();
            }
        }
    }

    if (empty($venues) && empty($services)) {
        $noResults = true;
    }

    // Combinar todas las categorías
    $venueCategories = $venueCatRepo->getAll();
    $serviceCategories = $serviceCatRepo->getAll();
    $allCategories = array_merge(
        array_map(function($cat) {
            return ['id' => $cat->id, 'name' => $cat->name ?? '', 'type' => 'venue'];
        }, $venueCategories),
        array_map(function($cat) {
            return ['id' => $cat->id, 'service_category' => $cat->service_category ?? '', 'type' => 'service'];
        }, $serviceCategories)
    );

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'venues' => $venues,
        'services' => $services,
        'categories' => $allCategories,
        'venue_categories' => $venueCategories,
        'service_categories' => $serviceCategories,
        'selected_category' => $categoryIds,
        'selected_type' => $type,
        'selected_range' => $range,
        'lat' => $lat,
        'lng' => $lng,
        'address' => $_GET['address'] ?? null,
        'filter_by_location' => $shouldFilterByLocation,
        'is_global_search' => $isGlobalSearch,
        'base_url' => $_ENV["APP_URL"],
        'no_results' => $noResults,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
