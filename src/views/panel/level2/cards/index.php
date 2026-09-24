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
    $activationFlow = ($_GET['activation_flow'] ?? '') === 'store_delivery_tracking' ? 'store_delivery_tracking' : null;
    $cardOwnerId = $activationFlow ? (int)($user->getOwner() ?: $user->getId()) : (int)$user->getId();

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
    $cards = $cardRepo->getByUserId($cardOwnerId);
    $savedMethods = $savedMethodRepo->getActiveForClientAcrossBusinesses((int)$user->getId(), (string)$user->getEmail());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "stripe_key" => $_ENV["STRIPE_PUBLIC"],
        "cards" => $cards,
        "saved_methods" => $savedMethods,
        "billing" => $billing,
        "websiteUrl" => $_ENV["APP_URL"] ?? "https://ophyra.com",
        "activationFlow" => $activationFlow,
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
        $activationFlow = ($_GET['activation_flow'] ?? '') === 'store_delivery_tracking';
        $cardOwnerId = $activationFlow ? (int)($user->getOwner() ?: $user->getId()) : (int)$user->getId();

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
            $repo->deleteCardForUser($cardOwnerId, intval($_POST["delete_card"]));
            $repo->ensureMainCard($cardOwnerId);
            LocationUtils::redirectInternal("panel/cards");
        }

        // Establecer tarjeta principal
        if (isset($_POST["set_main"])) {
            $cardId = intval($_POST["set_main"]);
            $repo->setMainCard($cardOwnerId, $cardId);
            LocationUtils::redirectInternal("panel/cards");
        }

        $stripe = new StripeService();
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'create_setup_intent') {
            $setup = $stripe->createCardSetupIntent((string)$user->getEmail(), [
                'user_id' => (string)$cardOwnerId,
                'product' => 'ophytrack',
                'purpose' => $activationFlow ? 'membership_activation' : 'saved_dashboard_card',
            ]);
            if (!$setup || empty($setup['client_secret'])) {
                return JsonResponse::createResponse([
                    'success' => false,
                    'message' => TranslationService::trans('planner_hub.card_validation_failed'),
                ], 502);
            }

            return JsonResponse::createResponse([
                'success' => true,
                'client_secret' => $setup['client_secret'],
            ]);
        }

        if ($action !== 'save_setup_intent') {
            return JsonResponse::createResponse([
                'success' => false,
                'message' => TranslationService::trans('planner_hub.token_card_required'),
            ], 400);
        }

        $verifiedSetup = $stripe->verifiedCardFromSetupIntent(trim((string)($_POST['setup_intent_id'] ?? '')));
        if (!$verifiedSetup || !hash_equals((string)$cardOwnerId, (string)($verifiedSetup['user_id'] ?? ''))) {
            return JsonResponse::createResponse([
                'success' => false,
                'message' => TranslationService::trans('billing.payment_method_verify_failed'),
            ], 422);
        }

        $main = $activationFlow || $repo->countCards($cardOwnerId) === 0 ? "yes" : "no";

        $saved = $repo->add([
            "id_user" => $cardOwnerId,
            "brand" => $verifiedSetup["brand"],
            "last4" => $verifiedSetup["last4"],
            "exp" => $verifiedSetup["exp"],
            "token" => $verifiedSetup["reference"],
            "billing_zip" => $verifiedSetup["billing_zip"],
            "main_card" => $main
        ]);

        if (!$saved) {
            return JsonResponse::createResponse([
                "success" => false,
                "message" => TranslationService::trans('billing.payment_method_save_failed')
            ], 500);
        }

        $insertedCardId = $repo->getLastId();
        if ($main === 'yes' && $insertedCardId > 0) {
            $repo->setMainCard($cardOwnerId, $insertedCardId);
        } else {
            $repo->ensureMainCard($cardOwnerId);
        }

        $verifiedCard = $repo->getMainCardByUserId($cardOwnerId);
        if (!$verifiedCard || empty($verifiedCard->token)) {
            return JsonResponse::createResponse([
                "success" => false,
                "message" => TranslationService::trans('billing.payment_method_verify_failed')
            ], 500);
        }

        TranslationService::detectLocale();
        return JsonResponse::createResponse([
            "success" => true,
            "saved" => true,
            "redirect" => $activationFlow
                ? LocationUtils::pathFor('panel/planner-hub/no-access?module=store_delivery_tracking&activation_ready=1&locale=' . urlencode(TranslationService::getCurrentLocale()))
                : null,
        ]);
    } catch (Throwable $e) {
        error_log('Level2 cards payment-method setup failed: ' . $e->getMessage());
        TranslationService::detectLocale();
        return JsonResponse::createResponse([
            "success" => false,
            "message" => TranslationService::trans('wallet.save_card_error')
        ], 500);
    }
});

$router->run();
