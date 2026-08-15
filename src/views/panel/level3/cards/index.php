<?php

use App\Repositories\UserCardsRepository;
use App\Repositories\UserBillingInfoRepository;
use App\Repositories\VenueRepository;
use App\Services\LoginService;
use App\Services\StripeService;
use App\Services\TranslationService;
use App\Utils\JsonResponse;
use App\Utils\LocationUtils;
use App\Utils\PlatformDetector;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();

    if (PlatformDetector::isMobileApp()) {
        TranslationService::detectLocale();
        return TemplateResponse::renderInTemplates("web-only-feature.twig", [
            "title" => TranslationService::trans('planner_hub.payment_config_required'),
            "message" => TranslationService::trans('planner_hub.payment_config_message'),
            "showWebLink" => false,
            "icon" => "💳",
            "websiteUrl" => $_ENV["APP_URL"] ?? "https://ophyra.com"
        ]);
    }

    $billingRepo = new UserBillingInfoRepository();
    $billing = $billingRepo->getByUserId($user->getId());

    if (!$billing) {
        LocationUtils::redirectInternal("panel/billing");
    }

    $cardRepo = new UserCardsRepository();
    $cards = $cardRepo->getByUserId($user->getId());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "stripe_key" => $_ENV["STRIPE_PUBLIC"],
        "cards" => $cards,
        "websiteUrl" => $_ENV["APP_URL"] ?? "https://ophyra.com"
    ]);
});

$router->post(function () {
    try {
        $user = LoginService::getSession();
        
        if (PlatformDetector::isMobileApp()) {
            TranslationService::detectLocale();
            return JsonResponse::createResponse([
                "success" => false,
                "message" => TranslationService::trans('planner_hub.payment_methods_not_available')
            ], 403);
        }
        
        $repo = new UserCardsRepository();

        // Eliminar tarjeta
        if (isset($_POST["delete_card"])) {
            $repo->deleteCard(intval($_POST["delete_card"]));
            LocationUtils::redirectInternal("panel/cards");
        }

        // Establecer tarjeta principal
        if (isset($_POST["set_main"])) {
            $cardId = intval($_POST["set_main"]);
            $repo->setMainCard($user->getId(), $cardId);
            LocationUtils::redirectInternal("panel/cards");
        }

        // Agregar tarjeta
        $token = $_POST["token"] ?? "";
        $cardInfo = json_decode($_POST["card_info"] ?? '{}', true);

        if (!$token || empty($cardInfo)) {
            TranslationService::detectLocale();
            return JsonResponse::createResponse([
                "success" => false,
                "message" => TranslationService::trans('planner_hub.token_card_required')
            ]);
        }

        $stripe = new StripeService();
        $customer = $stripe->createCustomerWithCard($token, $user->getEmail());

        if (!$customer) {
            TranslationService::detectLocale();
            return JsonResponse::createResponse([
                "success" => false,
                "message" => TranslationService::trans('planner_hub.card_validation_failed')
            ], 420);
        }

        // Si no hay tarjeta principal, esta será la principal
        $main = $repo->countCards($user->getId()) == 0 ? "yes" : "no";

        $repo->add([
            "id_user" => $user->getId(),
            "brand" => $cardInfo["brand"],
            "last4" => $cardInfo["last4"],
            "exp" => $cardInfo["exp"],
            "token" => $customer,
            "main_card" => $main
        ]);

        // Verificar si el usuario ya tiene al menos un VENUE (nivel 2)
        $venueRepo = new VenueRepository();
        $hasVenue = !!$venueRepo->getOne(["user_id" => $user->getId()]);

        $needsProfile = !$hasVenue;
        TranslationService::detectLocale();
        $nextTitle = $needsProfile ? TranslationService::trans('planner_hub.create_venue_profile') : "";
        $base = rtrim($_ENV["APP_URL"] ?? '', '/');
        $nextUrl = $needsProfile ? ($base . "/panel/venues/create") : "";

        return JsonResponse::createResponse([
            "success" => true,
            "card" => $customer,
            "needs_profile" => $needsProfile,
            "next_title" => $nextTitle,
            "next_url" => $nextUrl
        ]);
    } catch (Exception $e) {
        return JsonResponse::createResponse([
            "success" => false,
            "message" => $e->getMessage()
        ], 500);
    }
});

$router->run();