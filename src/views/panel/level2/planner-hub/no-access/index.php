<?php

use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\ModuleAccessService;
use App\Services\OphyraPricingService;
use App\Services\UserCurrencyPreferenceService;
use App\Services\ProductProfileService;
use App\Repositories\UserCardsRepository;

$router = new Router();

$router->get(function () {
    $module = trim((string)($_GET['module'] ?? 'module'));
    $required = array_filter(array_map('trim', explode(',', (string)($_GET['required'] ?? $module))));
    if (ProductProfileService::includedWithStoreLogistics($module)) {
        $module = 'store_delivery_tracking';
        $required = ['store_delivery_tracking'];
    }
    $pricing = new OphyraPricingService();
    $currencyPreference = new UserCurrencyPreferenceService($pricing);
    $access = new ModuleAccessService();
    $user = \App\Services\LoginService::getSession();
    $ownerId = $user ? (int)($user->getOwner() ?: $user->getId()) : 0;
    $hasPaymentMethod = $ownerId > 0 && (new UserCardsRepository())->getMainCardByUserId($ownerId) !== null;
    $selectedCurrency = $currencyPreference->resolveCurrency($ownerId, $_GET['payment_currency'] ?? null);
    $catalog = $access->getLockedModuleCatalog($ownerId, $selectedCurrency);

    foreach ($catalog as $slug => $catalogModule) {
        $price = $pricing->getModulePrice((string)($catalogModule['canonical_slug'] ?? $slug), $selectedCurrency);
        $catalog[$slug]['selected_currency'] = $selectedCurrency;
        $catalog[$slug]['selected_price'] = $price;
        $catalog[$slug]['selected_price_label'] = $price === null ? null : $pricing->format((float)$price, $selectedCurrency);
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'requestedModule' => $module,
        'requiredModules' => $required,
        'moduleCatalog' => $catalog,
        'pricing' => $pricing->publicPricing($selectedCurrency),
        'selectedCurrency' => $selectedCurrency,
        'hasPaymentMethod' => $hasPaymentMethod,
        'activationReady' => ($_GET['activation_ready'] ?? '') === '1',
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
