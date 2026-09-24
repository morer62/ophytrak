<?php

use App\Repositories\UserCardsRepository;
use App\Repositories\UserBillingInfoRepository;
use App\Repositories\ClientSavedPaymentMethodsRepository;
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

    $cardRepo = new UserCardsRepository();
    $savedMethodRepo = new ClientSavedPaymentMethodsRepository();
    $cards = $cardRepo->getByUserId($user->getId());
    $savedMethods = $savedMethodRepo->getActiveForClientAcrossBusinesses((int)$user->getId(), (string)$user->getEmail());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "stripe_key" => $_ENV["STRIPE_PUBLIC"],
        "cards" => $cards,
        "saved_methods" => $savedMethods,
        "billing" => $billing,
        "websiteUrl" => $_ENV["APP_URL"] ?? "https://ophyra.com",
        "activationFlow" => ($_GET['activation_flow'] ?? '') === 'store_delivery_tracking' ? 'store_delivery_tracking' : null,
        "currentLocale" => TranslationService::getCurrentLocale(),
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
        $savedMethodRepo = new ClientSavedPaymentMethodsRepository();

        if (isset($_POST["delete_saved_method"])) {
            $savedMethodRepo->deactivateForClient(intval($_POST["delete_saved_method"]), (int)$user->getId(), (string)$user->getEmail());
            LocationUtils::redirectInternal("panel/cards");
        }

        if (isset($_POST["set_saved_method_default"])) {
            $savedMethodRepo->setDefaultForClient(intval($_POST["set_saved_method_default"]), (int)$user->getId(), (string)$user->getEmail());
            LocationUtils::redirectInternal("panel/cards");
        }

        // Eliminar tarjeta
        if (isset($_POST["delete_card"])) {
            $repo->deleteCardForUser($user->getId(), intval($_POST["delete_card"]));
            $repo->ensureMainCard($user->getId());
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

        // If there is no main card, this one becomes the main card.
        $main = $repo->countCards($user->getId()) == 0 ? "yes" : "no";

        $repo->add([
            "id_user" => $user->getId(),
            "brand" => $cardInfo["brand"],
            "last4" => $cardInfo["last4"],
            "exp" => $cardInfo["exp"],
            "token" => $customer,
            "main_card" => $main
        ]);

        TranslationService::detectLocale();
        return JsonResponse::createResponse([
            "success" => true,
            "card" => $customer,
            "redirect" => ($_GET['activation_flow'] ?? '') === 'store_delivery_tracking'
                ? LocationUtils::pathFor('panel/planner-hub/no-access?module=store_delivery_tracking&activation_ready=1&locale=' . urlencode(TranslationService::getCurrentLocale()))
                : null,
        ]);
    } catch (Exception $e) {
        return JsonResponse::createResponse([
            "success" => false,
            "message" => $e->getMessage()
        ], 500);
    }
});

$router->run();
