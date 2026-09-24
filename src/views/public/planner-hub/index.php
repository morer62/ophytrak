<?php

use App\Services\OphyraLandingPageService;
use App\Services\OphyraPricingService;
use App\Services\GeoPricingService;
use App\Services\OphyraSeoService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    header('Cache-Control: private, max-age=300, stale-while-revalidate=86400');
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $landing = new OphyraLandingPageService();
    $pricing = new OphyraPricingService();
    $geoPricing = new GeoPricingService($pricing);
    $seoService = new OphyraSeoService();
    $seo = $seoService->seoForRoute('', $appUrl);
    if (\App\Services\ProductProfileService::isOphytrack()) {
        $seo = array_merge($seo, [
            'title' => \App\Services\TranslationService::trans('ophytrack_public.meta_title'),
            'description' => \App\Services\TranslationService::trans('ophytrack_public.meta_description'),
            'og_title' => \App\Services\TranslationService::trans('ophytrack_public.meta_title'),
            'og_description' => \App\Services\TranslationService::trans('ophytrack_public.meta_description'),
            'author' => 'OPHYTRACK',
        ]);
    }
    $requestedCurrency = $pricing->normalizePaymentCurrency($_GET['currency'] ?? '');
    if (!empty($_GET['currency']) && $requestedCurrency) {
        setcookie('ophyra_public_currency', $requestedCurrency, [
            'expires' => time() + (365 * 24 * 60 * 60),
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE['ophyra_public_currency'] = $requestedCurrency;
    }

    $server = $_SERVER;
    if (!empty($_GET['currency'])) {
        $server['REQUEST_PUBLIC_CURRENCY'] = $requestedCurrency;
    }

    $pricingContext = $geoPricing->publicContext($server);
    $selectedCurrency = $pricingContext['currency_code'];

    $template = \App\Services\ProductProfileService::isOphytrack() ? '/ophytrack.twig' : '/index.twig';
    return TemplateResponse::render(__DIR__.$template, [
        'ophytrackMode' => \App\Services\ProductProfileService::isOphytrack(),
        'moduleLandingPages' => $landing->getModulePages($appUrl),
        'industryLandingPages' => $landing->getIndustryPages($appUrl),
        'ophyraPricing' => $pricing->publicPricing($selectedCurrency),
        'selectedCurrency' => $selectedCurrency,
        'supportedCurrencies' => $pricing->allowedOphyraPaymentCurrencies(),
        'pricingCountryCode' => $pricingContext['country_code'],
        'pricingSource' => $pricingContext['source'],
        'seo' => $seo,
        'schemaJsonList' => $seoService->schemaJsonListForRoute('', $appUrl, $seo),
        'marketing_lite_assets' => true,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
