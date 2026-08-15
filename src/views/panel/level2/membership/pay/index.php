<?php

use App\Repositories\UserCardsRepository;
use App\Repositories\UserRepository;
use App\Services\CurrencyPricingService;
use App\Services\LoginService;
use App\Services\OphyraPricingService;
use App\Services\StripeService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\PlatformDetector;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $pricing = new OphyraPricingService();
    $basePrice = $pricing->baseProfilePrice();

    if ($basePrice <= 0) {
        MessageUtil::setMessage("Ophyra Base Profile is free. Activate paid modules only when your business needs them.");
        LocationUtils::redirectInternal("panel/membership/manage");
    }

    if (PlatformDetector::isMobileApp()) {
        return TemplateResponse::renderInTemplates("web-only-feature.twig", [
            "title" => "Ophyra Base Activation",
            "message" => "Ophyra Base payments are only available on the website.",
            "showWebLink" => false,
            "icon" => "membership",
            "websiteUrl" => $_ENV["APP_URL"]
        ]);
    }

    $cardRepo = new UserCardsRepository();
    $mainCard = $cardRepo->getOne([
        "id_user" => $user->getId(),
        "main_card" => "yes"
    ]);

    if (!$mainCard) {
        MessageUtil::setMessage("You must add a payment method first.");
        LocationUtils::redirectInternal("panel/cards");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "base_price" => $basePrice,
        "base_price_label" => $pricing->format($basePrice, $pricing->getBaseCurrency()),
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $pricing = new OphyraPricingService();
    $currencyPricing = new CurrencyPricingService();

    $cardRepo = new UserCardsRepository();
    $stripe = new StripeService();
    $userRepo = new UserRepository();
    $amount = $pricing->baseProfilePrice();

    if ($amount <= 0) {
        MessageUtil::setMessage("Ophyra Base Profile is already free and active.");
        LocationUtils::redirectInternal("panel/membership/manage");
    }

    $mainCard = $cardRepo->getOne([
        "id_user" => $user->getId(),
        "main_card" => "yes"
    ]);

    if (!$mainCard) {
        MessageUtil::setMessage("You need a payment method.");
        LocationUtils::redirectInternal("panel/cards");
    }

    $snapshot = $currencyPricing->createSnapshot($amount, OphyraPricingService::BASE_CURRENCY, OphyraPricingService::BASE_CURRENCY);
    $success = $stripe->createChargeV1($mainCard->token, (float)$snapshot['payment_amount'], strtolower((string)$snapshot['payment_currency']));

    if (!$success) {
        MessageUtil::setMessage("Payment failed.");
        LocationUtils::redirectInternal("panel/membership/manage");
    }

    $newDate = new DateTime();
    $existingDue = $user->getMembershipDueDate();

    if ($existingDue) {
        $existingDate = new DateTime($existingDue);
        if ($existingDate > $newDate) {
            $newDate = $existingDate;
        }
    }

    $newDate->add(new DateInterval("P1M"));
    $userRepo->updateMembershipAndRegisterPayment(
        $user->getId(),
        $newDate->format("Y-m-d"),
        (float)$snapshot['payment_amount'],
        'Stripe Ophyra Base charge ' . $success,
        'stripe_charge:' . $success . ':base',
        $snapshot + [
            'provider_type' => 'stripe',
            'payment_method' => 'saved_card',
        ]
    );

    $updatedUser = $userRepo->getOneWithoutOwnership(['id' => $user->getId()]);
    if ($updatedUser) {
        LoginService::authenticateFromUserDbo($updatedUser);
    }

    MessageUtil::setMessage("Ophyra Base activated successfully.");
    LocationUtils::redirectInternal("panel/home");
});

$router->run();
