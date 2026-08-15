<?php

use App\Repositories\UserCardsRepository;
use App\Repositories\UserRepository;
use App\Repositories\AutopaySettingRepository;
use App\Repositories\Connection;
use App\Repositories\UserModulesRepository;
use App\Repositories\UserBillingInfoRepository;
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
use App\Utils\TemplateResponse;
use App\Utils\BillingDateUtils;

$user = LoginService::getSession();
$billingOwnerId = (int)($user->getOwner() ?: $user->getId());
$membershipActive = $user->hasActiveMembership();
$membershipDue = $user->getMembershipDueDate();
$isMobileApp = isset($_SESSION['IS_MOBILE_APP']) && $_SESSION['IS_MOBILE_APP'] === true;
$currentLocale = TranslationService::getCurrentLocale();
$ophyraPricingService = new OphyraPricingService();
$currencyPricingService = new CurrencyPricingService();
$currencyPreferenceService = new UserCurrencyPreferenceService($ophyraPricingService);
$freeStarterBase = $ophyraPricingService->freeStarterBaseEnabled();
$basePrice = $ophyraPricingService->baseProfilePrice();
$ophyraPaymentCurrencies = $ophyraPricingService->allowedOphyraPaymentCurrencies();
$billingInfo = (new UserBillingInfoRepository())->getByUserId($billingOwnerId);
$requestedPaymentCurrency = $_POST['payment_currency'] ?? $_GET['payment_currency'] ?? null;
$selectedPaymentCurrency = $currencyPreferenceService->resolveCurrency($billingOwnerId, $requestedPaymentCurrency);
$baseQuote = $currencyPricingService->createSnapshot($basePrice, $selectedPaymentCurrency, $selectedPaymentCurrency);

$db = new Connection();
$db->query("SELECT * FROM payments_all WHERE concept = 'Membership' AND user_id = ? ORDER BY payment_date DESC");
$db->bind(1, $billingOwnerId);
$payments = $db->fetchAll();

$cardRepo = new UserCardsRepository();
$cards = $cardRepo->getByUserId($billingOwnerId);
$hasCard = !empty($cards);
$mainCard = $cardRepo->getMainCardByUserId($billingOwnerId);
$moduleAccessService = new ModuleAccessService();
$addonBillingService = new AddonBillingService(null, null, $currencyPricingService, $ophyraPricingService);
$baseMembershipActive = $moduleAccessService->userHasActiveBaseMembership($user);
$availableAddons = $moduleAccessService->getAvailableAddons($selectedPaymentCurrency);
$addonQuotes = $addonBillingService->getAddonQuotes($billingOwnerId, $selectedPaymentCurrency);
$userAddonModules = $moduleAccessService->getUserAddonModules($billingOwnerId);
$activeAddonSlugs = [];
$addonStatusBySlug = [];

foreach ($userAddonModules as $module) {
    $moduleState = [
        'status' => strtoupper((string)$module->status),
        'billing_status' => (string)($module->billing_status ?? ''),
        'renewal_at' => $module->renewal_at ?? null,
    ];
    foreach ($ophyraPricingService->compatibleSlugs((string)$module->slug) as $compatibleSlug) {
        $addonStatusBySlug[$compatibleSlug] = $moduleState;
    }

    if (strtoupper((string)$module->status) === UserModulesRepository::STATUS_ACTIVE) {
        $activeAddonSlugs[] = $module->slug;
        $activeAddonSlugs[] = $ophyraPricingService->canonicalSlug((string)$module->slug);
        $activeAddonSlugs[] = $ophyraPricingService->legacySlug((string)$module->slug);
    }
}

$activeAddonSlugs = array_values(array_unique($activeAddonSlugs));

$moduleLabel = static function (string $slug, string $field, string $fallback) {
    $translated = TranslationService::trans('billing_modules.' . $slug . '.' . $field);
    return $translated === 'billing_modules.' . $slug . '.' . $field ? $fallback : $translated;
};

foreach ($availableAddons as $addon) {
    $addonSlug = (string)($addon->slug ?? '');
    if ($addonSlug === '') {
        continue;
    }

    $addon->name = $moduleLabel($addonSlug, 'name', (string)($addon->name ?? $addonSlug));
    $addon->description = $moduleLabel($addonSlug, 'description', (string)($addon->description ?? ''));
}

$autopayRepo = new AutopaySettingRepository();
$autopaySetting = $autopayRepo->getByUserId($billingOwnerId);
$autopayEnabled = $autopaySetting ? $autopaySetting->isEnabled() : false;
$autopayPlan = 'monthly';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    TranslationService::detectLocale();

    if (isset($_POST['action']) && $_POST['action'] === 'toggle_autopay') {
        $enabled = isset($_POST['autopay_enabled']) && $_POST['autopay_enabled'] === '1';

        if (!$hasCard) {
            MessageUtil::setMessage("Add a payment method before enabling autopay.", "Error", "error");
            LocationUtils::redirectInternal("panel/cards");
        }

        $autopayRepo->upsertAutopay($billingOwnerId, 'monthly', $enabled);

        MessageUtil::setMessage($enabled ? "Monthly autopay enabled." : "Autopay disabled.");
        LocationUtils::reload();
    }

    if (isset($_POST['action']) && in_array($_POST['action'], ['cancel_module_renewal', 'reactivate_module_renewal'], true)) {
        $moduleSlug = trim((string)($_POST['module_slug'] ?? ''));
        if ($moduleSlug === '') {
            MessageUtil::setMessage("Module not found.", "Error", "error");
            LocationUtils::reload();
        }

        $moduleRepo = new UserModulesRepository();
        $compatibleSlugs = $ophyraPricingService->compatibleSlugs($moduleSlug);
        $newBillingStatus = $_POST['action'] === 'cancel_module_renewal' ? 'cancel_at_period_end' : 'current';
        $updated = $moduleRepo->setBillingStatusByCompatibleSlugs($billingOwnerId, $compatibleSlugs, $newBillingStatus);

        MessageUtil::setMessage($updated
            ? ($_POST['action'] === 'cancel_module_renewal' ? "This module will stay active until the end of the current billing period." : "Module renewal reactivated.")
            : "No billing status changed."
        );
        LocationUtils::redirectInternal("panel/membership/manage?payment_currency=" . urlencode((string)$selectedPaymentCurrency) . "#billing-overview");
    }

    if (isset($_POST['action']) && $_POST['action'] === 'pay_upcoming_renewal') {
        if (!$hasCard) {
            MessageUtil::setMessage("Add a payment method before paying an upcoming renewal.", "Error", "error");
            LocationUtils::redirectInternal("panel/cards");
        }

        MessageUtil::setMessage("No manual renewal charge is due today. Upcoming module renewals will be handled on the renewal date.");
        LocationUtils::redirectInternal("panel/membership/manage?payment_currency=" . urlencode((string)$selectedPaymentCurrency) . "#billing-overview");
    }

    if ($isMobileApp) {
        MessageUtil::setMessage("Payments are not available in the mobile app.");
        LocationUtils::reload();
    }

    if ($freeStarterBase) {
        MessageUtil::setMessage("Ophyra Base is free to start. Add paid modules when your business needs them.");
        LocationUtils::reload();
    }

    $cards = $cardRepo->getByUserId($billingOwnerId);

    if (empty($cards)) {
        MessageUtil::setMessage("No card found. Please add one first.", "Error", "error");
        LocationUtils::redirectInternal("panel/cards");
    }

    $card = $cards[0];
    $stripe = new StripeService();
    $selectedPaymentCurrency = $currencyPreferenceService->resolveCurrency($billingOwnerId, $_POST['payment_currency'] ?? null);
    $baseQuote = $currencyPricingService->createSnapshot($basePrice, $selectedPaymentCurrency, $selectedPaymentCurrency);
    $success = $stripe->createChargeV1($card->token, (float)$baseQuote['payment_amount'], strtolower((string)$baseQuote['payment_currency']));

    if (!$success) {
        MessageUtil::setMessage("Payment failed. Please try again.", "Error", "error");
        LocationUtils::reload();
    }

    $newDueDate = new DateTime();
    $existingDue = $user->getMembershipDueDate();

    if ($existingDue) {
        $dueDateObj = new DateTime($existingDue);
        if ($dueDateObj > $newDueDate) {
            $newDueDate = $dueDateObj;
        }
    }

    $newDueDate->add(new DateInterval("P1M"));

    $userRepo = new UserRepository();
    $userRepo->updateMembershipAndRegisterPayment(
        $billingOwnerId,
        $newDueDate->format('Y-m-d'),
        (float)$baseQuote['payment_amount'],
        'Stripe Ophyra Base charge ' . $success,
        'stripe_charge:' . $success . ':base',
        $baseQuote + [
            'provider_type' => 'stripe',
            'payment_method' => 'saved_card',
        ]
    );

    $user->setMembershipDueDate($newDueDate->format('Y-m-d'));
    $user->setMembershipType('PAID');
    LoginService::setSession($user);

    MessageUtil::setMessage("Ophyra Base renewed until " . $newDueDate->format('Y-m-d') . ".");
    LocationUtils::reload();
}

$zeroAmountLabel = $currencyPricingService->format(0.0, $selectedPaymentCurrency);
$billingModules = [];
$nextChargeAmount = 0.0;
$billingRenewalDate = $membershipDue;

$addonValue = static function ($item, string $key, $default = null) {
    if (is_array($item)) {
        return $item[$key] ?? $default;
    }

    return $item->{$key} ?? $default;
};

foreach ($availableAddons as $addon) {
    $slug = (string)$addonValue($addon, 'slug', '');
    if ($slug === '') {
        continue;
    }

    $name = (string)$addonValue($addon, 'name', $slug);
    $quote = $addonQuotes[$slug] ?? null;
    $state = $addonStatusBySlug[$slug] ?? null;
    $status = strtoupper((string)($state['status'] ?? 'INACTIVE'));
    $billingStatus = strtolower((string)($state['billing_status'] ?? ''));
    $isActive = in_array($slug, $activeAddonSlugs, true) && $status === UserModulesRepository::STATUS_ACTIVE;
    $cancelAtPeriodEnd = $billingStatus === 'cancel_at_period_end';
    $includedNextRenewal = $isActive && !$cancelAtPeriodEnd;
    $renewalAt = (string)($state['renewal_at'] ?? ($quote['renewal_at'] ?? ''));
    $monthlyPrice = (float)($quote['monthly_price'] ?? $addonValue($addon, 'monthly_price', 0));

    if ($includedNextRenewal) {
        $nextChargeAmount += $monthlyPrice;
        if ($billingRenewalDate === null || $billingRenewalDate === '') {
            $billingRenewalDate = $renewalAt;
        }
    }

    $billingModules[] = [
        'slug' => $slug,
        'name' => $moduleLabel($slug, 'name', $name),
        'description' => $moduleLabel($slug, 'description', ''),
        'status' => $cancelAtPeriodEnd ? 'cancel_at_period_end' : ($isActive ? 'active' : strtolower($status ?: 'inactive')),
        'monthly_price' => $quote['monthly_price_formatted'] ?? $currencyPricingService->format($monthlyPrice, $selectedPaymentCurrency),
        'proration' => $isActive ? $zeroAmountLabel : ($quote['formatted_amount_due'] ?? $zeroAmountLabel),
        'included_next_renewal' => $includedNextRenewal,
        'renews_on' => BillingDateUtils::format($renewalAt, $currentLocale),
        'renewal_raw' => $renewalAt,
    ];
}

$billingOverview = [
    'renewal_date' => BillingDateUtils::format((string)$billingRenewalDate, $currentLocale),
    'next_charge' => $currencyPricingService->format($nextChargeAmount, $selectedPaymentCurrency),
    'due_today' => $zeroAmountLabel,
    'payment_method_last4' => $mainCard ? (string)$mainCard->last4 : '',
    'has_payment_method' => (bool)$mainCard,
    'autorenew' => $autopayEnabled ? TranslationService::trans('billing_overview.autorenew_on_short') : TranslationService::trans('billing_overview.autorenew_off_short'),
    'payment_status' => $hasCard
        ? ($autopayEnabled ? TranslationService::trans('billing_overview.payment_status_scheduled') : TranslationService::trans('billing_overview.payment_status_manual'))
        : TranslationService::trans('billing_overview.payment_status_required'),
];

$billingStatements = [];
foreach (array_slice((array)$payments, 0, 8) as $payment) {
    $paymentDate = is_array($payment) ? ($payment['payment_date'] ?? null) : ($payment->payment_date ?? null);
    $amount = is_array($payment) ? ($payment['amount'] ?? null) : ($payment->amount ?? null);
    $currency = is_array($payment) ? ($payment['currency'] ?? $selectedPaymentCurrency) : ($payment->currency ?? $selectedPaymentCurrency);
    $reference = is_array($payment) ? ($payment['reference'] ?? $payment['transaction_id'] ?? '') : ($payment->reference ?? $payment->transaction_id ?? '');
    $billingStatements[] = [
        'date' => BillingDateUtils::format((string)$paymentDate, $currentLocale),
        'amount' => $amount !== null ? $currencyPricingService->format((float)$amount, (string)$currency) : '',
        'status' => TranslationService::trans('billing_overview.statement_paid'),
        'reference' => (string)$reference,
    ];
}

echo TemplateResponse::render(__DIR__ . "/index.twig", [
    "membershipActive" => $membershipActive,
    "membershipDue" => $membershipDue,
    "payments" => $payments,
    "basePrice" => $basePrice,
    "baseQuote" => $baseQuote,
    "basePriceLabel" => $currencyPricingService->format((float)$baseQuote['payment_amount'], (string)$baseQuote['payment_currency']),
    "ophyraPaymentCurrencies" => $ophyraPaymentCurrencies,
    "selectedPaymentCurrency" => $selectedPaymentCurrency,
    "hasCard" => $hasCard,
    "mainCard" => $mainCard,
    "baseMembershipActive" => $baseMembershipActive,
    "freeStarterBase" => $freeStarterBase,
    "availableAddons" => $availableAddons,
    "addonQuotes" => $addonQuotes,
    "activeAddonSlugs" => $activeAddonSlugs,
    "addonStatusBySlug" => $addonStatusBySlug,
    "isMobileApp" => $isMobileApp,
    "websiteUrl" => $_ENV["APP_URL"] ?? "https://ophyra.com",
    "autopayEnabled" => $autopayEnabled,
    "autopayPlan" => $autopayPlan,
    "billingOverview" => $billingOverview,
    "billingModules" => $billingModules,
    "billingStatements" => $billingStatements,
    "highlightModule" => $_GET['highlight'] ?? ''
]);
