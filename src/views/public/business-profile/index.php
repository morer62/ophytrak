<?php

use App\Repositories\BusinessProfileSectionsRepository;
use App\Repositories\Connection;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\StoreProductsRepository;
use App\Services\OphyraSeoService;
use App\Services\StorefrontAccessService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $slug = trim((string)($_GET['slug'] ?? ''));
    if ($slug === '') {
        return "Business profile not found.";
    }

    $profileRepo = new InstitutionProfileRepository();
    $sectionsRepo = new BusinessProfileSectionsRepository();
    $profile = $profileRepo->getBySlug($profileRepo->normalizeSlug($slug));

    if (!$profile) {
        return "Business profile not found.";
    }

    $sections = $sectionsRepo->getByProfile((int)$profile->id, true);
    if (!$sections) {
        $sections = array_map(static fn ($section) => (object)[
            'section_key' => $section['key'],
            'section_label' => $section['label'],
            'is_visible' => $section['visible'],
            'sort_order' => $section['sort'],
            'is_fixed' => $section['fixed'],
        ], $sectionsRepo->getDefaultSections());
    }

    $events = [];
    try {
        $db = new Connection();
        $db->query("SELECT event_name, event_type, event_date, event_time, venue_city, venue_state, slug
            FROM events
            WHERE id_owner = :owner_id AND status IN ('active', 'draft') AND event_date >= CURDATE()
            ORDER BY event_date ASC
            LIMIT 3");
        $db->bind(':owner_id', (int)$profile->id_owner);
        $events = $db->fetchAll();
    } catch (Exception $e) {
        $events = [];
    }

    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $profileSlug = $profileRepo->normalizeSlug((string)$profile->slug);
    $canonical = $appUrl . '/business-profile/' . rawurlencode($profileSlug);
    $description = trim((string)($profile->short_description ?: $profile->rich_description ?: 'Public business profile powered by Ophyra.'));
    $hasEnoughPublicContent = trim((string)$profile->company_name) !== ''
        && trim($description) !== ''
        && $description !== 'Public business profile powered by Ophyra.';
    $robots = $hasEnoughPublicContent ? 'index, follow' : 'noindex, follow';
    $seoService = new OphyraSeoService();
    $seo = [
        'title' => (string)$profile->company_name . ' | Ophyra Business Profile',
        'description' => strlen($description) > 155 ? substr($description, 0, 152) . '...' : $description,
        'canonical' => $canonical,
        'og_title' => (string)$profile->company_name . ' | Ophyra Business Profile',
        'og_description' => strlen($description) > 155 ? substr($description, 0, 152) . '...' : $description,
        'og_image' => !empty($profile->logo_path) ? $profile->logo_path : $appUrl . '/assets/images/planner-hub-logo-positive.png',
        'twitter_title' => (string)$profile->company_name . ' | Ophyra Business Profile',
        'twitter_description' => strlen($description) > 155 ? substr($description, 0, 152) . '...' : $description,
        'robots' => $robots,
    ];

    $businessSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        '@id' => $canonical . '#business',
        'name' => (string)$profile->company_name,
        'url' => $canonical,
        'description' => $description,
        'image' => !empty($profile->logo_path) ? $profile->logo_path : null,
    ];

    if (!empty($profile->phone)) {
        $businessSchema['telephone'] = $profile->phone;
    }
    if (!empty($profile->email)) {
        $businessSchema['email'] = $profile->email;
    }
    if (!empty($profile->address_line1) || !empty($profile->city) || !empty($profile->state) || !empty($profile->zip)) {
        $businessSchema['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => $profile->address_line1 ?? null,
            'addressLocality' => $profile->city ?? null,
            'addressRegion' => $profile->state ?? null,
            'postalCode' => $profile->zip ?? null,
        ];
    }

    $storeModuleActive = StorefrontAccessService::ownerCanUseStore((int)$profile->id_owner);
    $storeProducts = [];
    if ($storeModuleActive) {
        $storeProducts = (new StoreProductsRepository())->getPublicActiveProducts(8, (int)$profile->id_owner);
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'profile' => $profile,
        'sections' => $sections,
        'events' => $events,
        'services_module_active' => StorefrontAccessService::ownerCanUseModule((int)$profile->id_owner, 'services'),
        'store_module_active' => $storeModuleActive,
        'store_products' => $storeProducts,
        'store_products_count' => count($storeProducts),
        'storeUrl' => rtrim($_ENV['APP_URL'] ?? '', '/') . '/store/home?owner=' . (int)$profile->id_owner,
        'quoteUrl' => rtrim($_ENV['APP_URL'] ?? '', '/') . '/search/client_request/index?profile_cat=business_profile&id=' . (int)$profile->id,
        'seo' => $seo,
        'schemaJsonList' => $seoService->schemaJsonListForRoute('business-profile/' . $profileSlug, $appUrl, $seo, $businessSchema),
    ]);
});

$router->run();
