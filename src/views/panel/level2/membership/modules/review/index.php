<?php

use App\Repositories\UserCardsRepository;
use App\Repositories\UserModulesRepository;
use App\Services\AddonBillingService;
use App\Services\CurrencyPricingService;
use App\Services\LoginService;
use App\Services\ModuleAccessService;
use App\Services\OphyraPricingService;
use App\Services\StripeService;
use App\Services\TranslationService;
use App\Services\UserCurrencyPreferenceService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$resolveModuleSlug = static function (OphyraPricingService $pricing): string {
    $slug = trim((string)($_POST['module_slug'] ?? $_GET['module_slug'] ?? $_GET['module'] ?? ''));
    return $pricing->legacySlug($slug);
};

$buildPayload = static function (string $moduleSlug, ?string $paymentCurrency = null): array {
    $user = LoginService::getSession();
    $billingOwnerId = (int)($user->getOwner() ?: $user->getId());
    $pricing = new OphyraPricingService();
    $currencyPricing = new CurrencyPricingService();
    $currencyPreference = new UserCurrencyPreferenceService($pricing);
    $moduleAccess = new ModuleAccessService();
    $billing = new AddonBillingService(null, null, $currencyPricing, $pricing);
    $selectedCurrency = $currencyPreference->resolveCurrency(
        $billingOwnerId,
        $paymentCurrency ?? $_POST['payment_currency'] ?? $_GET['payment_currency'] ?? null
    );
    $quote = $billing->getAddonQuote($billingOwnerId, $moduleSlug, $selectedCurrency);

    if (!$quote || !$pricing->checkoutAllowedForCurrency($moduleSlug, $selectedCurrency)) {
        MessageUtil::setMessage('This module is not available for checkout in the selected currency.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/membership/manage');
    }
    $translatedModuleName = TranslationService::trans('billing_modules.' . $moduleSlug . '.name');
    if ($translatedModuleName !== 'billing_modules.' . $moduleSlug . '.name') {
        $quote['name'] = $translatedModuleName;
    }

    $activeModules = $moduleAccess->getUserAddonModules($billingOwnerId);
    $currentModule = null;
    $compatibleSlugs = $pricing->compatibleSlugs($moduleSlug);
    foreach ($activeModules as $module) {
        if (in_array((string)$module->slug, $compatibleSlugs, true)) {
            $currentModule = $module;
            break;
        }
    }

    if ($currentModule && strtoupper((string)$currentModule->status) === UserModulesRepository::STATUS_ACTIVE) {
        MessageUtil::setMessage('This module is already active.');
        LocationUtils::redirectInternal('panel/membership/manage');
    }

    $cardRepo = new UserCardsRepository();
    $mainCard = $cardRepo->getMainCardByUserId($billingOwnerId);

    return [
        'user' => $user,
        'billingOwnerId' => $billingOwnerId,
        'pricing' => $pricing,
        'billing' => $billing,
        'quote' => $quote,
        'moduleSlug' => $moduleSlug,
        'selectedCurrency' => $selectedCurrency,
        'allowedCurrencies' => $pricing->allowedOphyraPaymentCurrencies(),
        'currentModule' => $currentModule,
        'mainCard' => $mainCard,
        'hasCard' => $mainCard && !empty($mainCard->token),
        'renewalDate' => $quote['renewal_at'],
        'features' => [
            'services' => ['CRM', 'Clients', 'Service orders', 'Contracts', 'Team', 'Payroll basics', 'Team Chat'],
            'store_delivery_tracking' => ['Products', 'Store orders', 'Fulfillment workflow', 'Delivery tracking', 'Store reports'],
            'inventory_storage' => ['Warehouse locations', 'Containers', 'QR labels', 'Physical stock'],
            'ai_advisor' => ['AI Advisor', 'Operational summaries', 'Recommendations', 'Content support'],
            'tickets_rsvp' => ['Ticket sales', 'RSVP', 'Guest lists', 'Check-in basics'],
            'marketplace_connectors' => ['Mercado Libre', 'TikTok Business / Shop', 'Shopify', 'Manual sync'],
        ][$moduleSlug] ?? ['Operational module access'],
    ];
};

$router->get(function () use ($resolveModuleSlug, $buildPayload) {
    $pricing = new OphyraPricingService();
    $moduleSlug = $resolveModuleSlug($pricing);
    $payload = $buildPayload($moduleSlug);

    return TemplateResponse::render(__DIR__ . '/index.twig', $payload + [
        'mode' => 'review',
    ]);
});

$router->post(function () use ($resolveModuleSlug, $buildPayload) {
    $pricing = new OphyraPricingService();
    $moduleSlug = $resolveModuleSlug($pricing);
    $action = trim((string)($_POST['action'] ?? 'confirm_pay'));
    $payload = $buildPayload($moduleSlug, $_POST['payment_currency'] ?? null);
    /** @var AddonBillingService $billing */
    $billing = $payload['billing'];
    $user = $payload['user'];
    $billingOwnerId = (int)$payload['billingOwnerId'];
    $quote = $payload['quote'];

    if ($action === 'cancel_checkout') {
        $billing->markAddonCheckoutCancelled($billingOwnerId, $moduleSlug);
        MessageUtil::setMessage('Checkout cancelled. The module was not activated.');
        LocationUtils::redirectInternal('panel/membership/manage');
    }

    if (!$payload['hasCard']) {
        MessageUtil::setMessage('Add a payment method before confirming this module.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/cards');
    }

    $started = $billing->markAddonCheckoutStarted($billingOwnerId, $moduleSlug, $payload['selectedCurrency']);
    if (empty($started['success'])) {
        MessageUtil::setMessage($started['message'] ?? 'Checkout could not be started.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/membership/manage');
    }

    try {
        $chargeId = (new StripeService())->createChargeV1(
            (string)$payload['mainCard']->token,
            (float)$quote['payment_amount'],
            strtolower((string)$quote['payment_currency']),
            [
                'module_key' => (string)$quote['canonical_slug'],
                'module_slug' => $moduleSlug,
                'user_id' => (string)$billingOwnerId,
                'business_id' => (string)$billingOwnerId,
                'base_currency' => (string)$quote['base_currency'],
                'base_amount_usd' => (string)$quote['base_amount'],
                'selected_currency' => (string)$payload['selectedCurrency'],
                'charged_currency' => (string)$quote['payment_currency'],
                'charged_amount' => (string)$quote['payment_amount'],
                'pricing_source' => 'env',
            ]
        );
    } catch (Throwable $e) {
        $chargeId = false;
        error_log('Level2 module checkout Stripe charge failed: ' . $e->getMessage());
    }

    if (!$chargeId) {
        $billing->markAddonPaymentFailed($billingOwnerId, $moduleSlug, 'payment_failed');
        MessageUtil::setMessage('Payment failed. The module was not activated.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/membership/modules/review?module_slug=' . urlencode($moduleSlug) . '&payment_currency=' . urlencode($payload['selectedCurrency']));
    }

    $result = $billing->activateAddonAfterPayment(
        $billingOwnerId,
        $moduleSlug,
        (float)$quote['payment_amount'],
        'Stripe add-on charge ' . $chargeId,
        $quote + [
            'provider_type' => 'stripe',
            'payment_method' => 'saved_card_confirmed_review',
            'billing_transaction_id' => 'stripe_charge:' . $chargeId . ':addon:' . $moduleSlug,
            'stripe_payment_intent_id' => $chargeId,
        ]
    );

    if (empty($result['success'])) {
        MessageUtil::setMessage($result['message'] ?? 'Payment was confirmed, but module activation needs review.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/membership/manage');
    }

    LocationUtils::redirectInternal('panel/membership/modules/success?payment_id=' . urlencode((string)($result['payment_id'] ?? '')) . '&module_slug=' . urlencode($moduleSlug));
});

$router->run();
