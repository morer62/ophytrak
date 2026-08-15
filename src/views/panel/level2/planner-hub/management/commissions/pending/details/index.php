<?php

use App\Repositories\AffiliateCommissionsRepository;
use App\Repositories\AffiliateCommissionPaymentsRepository;
use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\FileUtils;
use App\Utils\Router;
use App\Services\NotificationService;
use App\Repositories\UserCardsRepository;
use App\Repositories\StripeAccountsRepository;
use App\Services\StripeService;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $repoCommissions = new AffiliateCommissionsRepository();
    $repoUsers = new UserRepository();

    $affiliateId = $_GET["id"] ?? null;
    if (!$affiliateId) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_affiliate_id'));
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending");
    }

    // Verificar que el afiliado existe
    $affiliate = $repoUsers->getOne(["id" => $affiliateId]);
    if (!$affiliate) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.affiliate_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending");
    }

    // Verificar cuenta de Stripe
    $stripeRepo = new StripeAccountsRepository();
    $stripeAccount = $stripeRepo->getByUser($affiliateId);
    $isStripeVerified = $stripeAccount && $stripeAccount->is_verified == 1;

    // Obtener comisiones pendientes del afiliado
    $commissions = $repoCommissions->getPendingByReferrer($affiliateId);
    
    // Calcular total
    $totalAmount = 0;
    foreach ($commissions as $commission) {
        $totalAmount += $commission->commission_amount;
    }

    // Verificar si el admin tiene tarjetas configuradas
    $cardsRepo = new UserCardsRepository();
    $cards = $cardsRepo->getByUserId($user->getId());
    $hasCard = count($cards) > 0;

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "commissions" => $commissions,
        "affiliateId" => $affiliateId,
        "affiliate" => $affiliate,
        "totalAmount" => $totalAmount,
        "hasCard" => $hasCard,
        "cards" => $cards,
        "isStripeVerified" => $isStripeVerified,
        "stripe_key" => $_ENV["STRIPE_PUBLIC"],
        'stripe_account_id' => $stripeAccount->stripe_account_id ?? null
    ]);
});

$router->post(function () {
    try {
        $repoCommissions = new AffiliateCommissionsRepository();
        $repoPayments = new AffiliateCommissionPaymentsRepository();
        $user = LoginService::getSession();

        if (!$user) {
            TranslationService::detectLocale();
            MessageUtil::setMessage("❌ " . TranslationService::trans('planner_hub.session_expired'));
            LocationUtils::redirectInternal("login");
            return;
        }

        $action = $_POST["action"] ?? "";
        $paymentType = $_POST["payment_type"] ?? "manual";
        $affiliateId = $_POST["affiliate_id"] ?? null;
        $ids = array_map('intval', $_POST["selected_ids"] ?? []);

        error_log("DEBUG: Processing commission payment - Affiliate ID: " . ($affiliateId ?? 'null') . ", Payment Type: " . $paymentType . ", IDs: " . json_encode($ids));

    if (!$affiliateId || empty($ids)) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_input_no_commissions'));
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . ($affiliateId ?? ''));
        return;
    }

    // Verificar que las comisiones existen y están pendientes
    $validCommissions = $repoCommissions->getByIds($ids);
    $validIds = [];
    $totalAmount = 0;
    
    foreach ($validCommissions as $commission) {
        if ($commission->referrer_id == $affiliateId && in_array($commission->status, ['pending', 'approved'])) {
            $validIds[] = $commission->id;
            $totalAmount += $commission->commission_amount;
        }
    }
    
    if (empty($validIds)) {
        TranslationService::detectLocale();
        MessageUtil::setMessage("❌ " . TranslationService::trans('planner_hub.no_valid_commissions_selected'));
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . $affiliateId);
        return;
    }
    
    // Usar solo los IDs válidos
    $ids = $validIds;

    if ($paymentType === "card") {
        $stripeRepo = new StripeAccountsRepository();
        $stripe = new StripeService();

        $affiliateStripe = $stripeRepo->getByUser($affiliateId);
        $cardToken = $_POST["customer_token"] ?? null; 
        
        if (!$affiliateStripe || !$affiliateStripe->is_verified) {
            TranslationService::detectLocale();
            MessageUtil::setMessage("❌ " . TranslationService::trans('planner_hub.affiliate_stripe_not_verified'));
            LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . $affiliateId);
            return;
        }

        // Paso 1: Crear customer en cuenta conectada
        $customerId = $stripe->createCustomerWithCardOnConnectedAccount($cardToken, $user->getEmail(), $user->getName(), $affiliateStripe->stripe_account_id); 

        // Paso 2: Hacer cargo al customer
        $result = $stripe->chargeCustomerOnConnectedAccount($customerId, $totalAmount, $affiliateStripe->stripe_account_id);

        if (!$result) {
            TranslationService::detectLocale();
            MessageUtil::setMessage("❌ " . TranslationService::trans('planner_hub.stripe_payment_failed'));
            LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . $affiliateId);
            return;
        } 
    }

    // Procesar archivo comprobante si se envía
    $proofUrl = "";
    if (isset($_FILES["payment_proof_file"]) && $_FILES["payment_proof_file"]["error"] === 0) {
        $proofUrl = FileUtils::saveFile($_FILES["payment_proof_file"], "commission_payments");
    }

    $additionalInfo = $_POST['additional_info'] ?? 'No additional message';

    // Generar ID de lote único
    $payoutBatchId = 'COMM_' . date('YmdHis') . '_' . $affiliateId;

    // Guardar pago en la base
    error_log("DEBUG: About to save payment record");
    $paymentData = [
        "referrer_id" => $affiliateId,
        "commission_ids" => json_encode($ids),
        "total_amount" => $totalAmount,
        "commission_count" => count($ids),
        "payment_method" => $paymentType,
        "payment_proof_url" => $proofUrl,
        "stripe_transfer_id" => $paymentType === "card" ? ($result['id'] ?? null) : null,
        "payout_batch_id" => $payoutBatchId,
        "status" => "completed",
        "paid_at" => date("Y-m-d H:i:s"),
        "notes" => $additionalInfo
    ];
    
    error_log("DEBUG: Payment data: " . json_encode($paymentData));
    $save = $repoPayments->createPayment($paymentData);
    
    if (!$save) {
        error_log("ERROR: Failed to save payment record");
        throw new \Exception("Failed to save payment record");
    }
    
    error_log("DEBUG: Payment record saved successfully");

    // Marcar comisiones como pagadas
    error_log("DEBUG: About to mark commissions as paid");
    $markResult = $repoCommissions->markAsPaid($ids, $paymentType, $payoutBatchId);
    
    if (!$markResult) {
        error_log("ERROR: Failed to mark commissions as paid");
        throw new \Exception("Failed to mark commissions as paid");
    }
    
    error_log("DEBUG: Commissions marked as paid successfully");

    // Enviar notificación al afiliado
    TranslationService::detectLocale();
    $notificationMessage = str_replace('{amount}', number_format($totalAmount, 2), TranslationService::trans('planner_hub.your_affiliate_commissions_paid'));
    NotificationService::sendToUsers(
        [$affiliateId],
        TranslationService::trans('planner_hub.commission_payment_received'),
        $notificationMessage
    );

    $successMessage = str_replace('{amount}', number_format($totalAmount, 2), TranslationService::trans('planner_hub.commissions_paid_successfully'));
    MessageUtil::setMessage("✅ " . $successMessage);
    LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . $affiliateId);
    
    } catch (\Exception $e) {
        error_log("ERROR: Commission payment failed - " . $e->getMessage());
        error_log("ERROR: Stack trace - " . $e->getTraceAsString());
        TranslationService::detectLocale();
        $errorMessage = str_replace('{error}', $e->getMessage(), TranslationService::trans('planner_hub.payment_processing_failed'));
        MessageUtil::setMessage("❌ " . $errorMessage);
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . ($affiliateId ?? ''));
    }
});

$router->run();
