<?php

use App\Repositories\UserCardsRepository;
use App\Repositories\UserRepository;
use App\Repositories\AutopaySettingRepository;
use App\Repositories\Connection;
use App\Services\LoginService;
use App\Services\StripeService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;


$user = LoginService::getSession();
$membershipActive = $user->hasActiveMembership();
$membershipDue = $user->getMembershipDueDate();

$isMobileApp = isset($_SESSION['IS_MOBILE_APP']) && $_SESSION['IS_MOBILE_APP'] === true;

// Get membership payment history from payments_all
$db = new Connection();
$db->query("SELECT * FROM payments_all WHERE concept = 'Membership' AND user_id = ? ORDER BY payment_date DESC");
$db->bind(1, $user->getId());
$payments = $db->fetchAll();

$cardRepo = new UserCardsRepository();
$cards = $cardRepo->getByUserId($user->getId());
$hasCard = !empty($cards);
$mainCard = $cardRepo->getMainCardByUserId($user->getId());

// Si el usuario no tiene tarjeta, redirigir a la página de agregar tarjeta
if (!$hasCard) {
    TranslationService::detectLocale();
    MessageUtil::setMessage("💳 " . TranslationService::trans('planner_hub.please_add_payment_method_membership'));
    LocationUtils::redirectInternal("panel/cards");
}

// Autopay settings
$autopayRepo = new AutopaySettingRepository();
$autopaySetting = $autopayRepo->getByUserId($user->getId());
$autopayEnabled = $autopaySetting ? $autopaySetting->isEnabled() : false;
$autopayPlan = $autopaySetting ? $autopaySetting->getPlanType() : 'monthly';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    TranslationService::detectLocale();
    
    // Handle Autopay toggle
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_autopay') {
        $enabled = isset($_POST['autopay_enabled']) && $_POST['autopay_enabled'] === '1';
        $planType = $_POST['autopay_plan'] ?? 'monthly';
        
        if (!$hasCard) {
            MessageUtil::setMessage("⚠️ " . TranslationService::trans('planner_hub.add_payment_method_before_autopay'));
            LocationUtils::redirectInternal("panel/cards");
        }
        
        $autopayRepo->upsertAutopay($user->getId(), $planType, $enabled);
        
        if ($enabled) {
            $planName = TranslationService::trans('planner_hub.' . $planType . '_plan_option');
            MessageUtil::setMessage("✅ " . str_replace('{plan}', $planType, TranslationService::trans('planner_hub.autopay_enabled_success')));
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.autopay_disabled_success'));
        }
        
        LocationUtils::reload();
    }
    
    // Handle manual payment
    if ($isMobileApp) {
        MessageUtil::setMessage("⚠️ " . TranslationService::trans('planner_hub.payments_not_available_mobile'));
        LocationUtils::reload();
    }

    $plan = $_POST['plan'] ?? '';
    $amount = 0;
    $days = 0;

    if ($plan === 'monthly') {
        $amount = floatval($_ENV['MEMBERSHIP_PLAN_MONTHLY'] ?? 16.99);
        $days = 30;
    } elseif ($plan === 'quarterly') {
        $amount = floatval($_ENV['MEMBERSHIP_PLAN_QUARTERLY'] ?? 45.99);
        $days = 90;
    } elseif ($plan === 'annual') {
        $amount = floatval($_ENV['MEMBERSHIP_PLAN_ANNUAL'] ?? 169.99);
        $days = 365;
    } else {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_plan_selected'), "Error", "error");
        LocationUtils::reload();
    }
    
    $cards = $cardRepo->getByUserId($user->getId());

    if (empty($cards)) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.no_card_found_add_one'), "Error", "error");
        LocationUtils::redirectInternal("panel/cards");
    }

    $card = $cards[0];
    $stripe = new StripeService();
    $success = $stripe->createChargeV1($card->token, $amount);

    if (!$success) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.payment_failed_try_again'), "Error", "error");
        LocationUtils::reload();
    }

    // Calcular nueva fecha de vencimiento
    $newDueDate = new DateTime();
    $existingDue = $user->getMembershipDueDate();
    if ($existingDue) {
        $dueDateObj = new DateTime($existingDue);
        if ($dueDateObj > $newDueDate) {
            $newDueDate = $dueDateObj;
        }
    }
    $newDueDate->modify("+$days days");

    // Guardar pago y actualizar membresía usando el método estándar
    $userRepo = new UserRepository();
    $userRepo->updateMembershipAndRegisterPayment($user->getId(), $newDueDate->format('Y-m-d'), $amount);

    // Actualizar sesión
    $user->setMembershipDueDate($newDueDate->format('Y-m-d'));
    $user->setMembershipType('PAID');
    LoginService::setSession($user);

    MessageUtil::setMessage(str_replace('{date}', $newDueDate->format('Y-m-d'), TranslationService::trans('planner_hub.membership_renewed_until')));
    LocationUtils::reload();
}

echo TemplateResponse::render(__DIR__ . "/index.twig", [
    "membershipActive" => $membershipActive,
    "membershipDue" => $membershipDue,
    "payments" => $payments,
    "monthlyPrice" => $_ENV["MEMBERSHIP_PLAN_MONTHLY"] ?? 16.99,
    "quarterlyPrice" => $_ENV["MEMBERSHIP_PLAN_QUARTERLY"] ?? 45.99,
    "annualPrice" => $_ENV["MEMBERSHIP_PLAN_ANNUAL"] ?? 169.99,
    "hasCard" => $hasCard,
    "mainCard" => $mainCard,
    "isMobileApp" => $isMobileApp,
    "websiteUrl" => $_ENV["APP_URL"] ?? "https://ophyra.com",
    "autopayEnabled" => $autopayEnabled,
    "autopayPlan" => $autopayPlan
]);

