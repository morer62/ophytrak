<?php

use App\Repositories\AffiliateCommissionPaymentsRepository;
use App\Repositories\AffiliateCommissionsRepository;
use App\Repositories\AffiliateProfilesRepository;
use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\NotificationService;
use App\Services\TranslationService;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repoCommissions = new AffiliateCommissionsRepository();
    $repoUsers = new UserRepository();
    $profilesRepository = new AffiliateProfilesRepository();

    $affiliateId = (int)($_GET["id"] ?? 0);
    if (!$affiliateId) {
        MessageUtil::setMessage('Invalid affiliate ID.');
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending");
    }

    $affiliate = $repoUsers->getOne(["id" => $affiliateId]);
    if (!$affiliate) {
        MessageUtil::setMessage('Affiliate not found.');
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending");
    }

    $profile = $profilesRepository->ensureForUser($affiliateId, $affiliate->email ?? null);
    $commissions = $repoCommissions->getPendingByReferrer($affiliateId);
    $totalAmount = array_reduce($commissions, fn($sum, $commission) => $sum + (float)$commission->commission_amount, 0.0);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "commissions" => $commissions,
        "affiliateId" => $affiliateId,
        "affiliate" => $affiliate,
        "affiliateProfile" => $profile,
        "totalAmount" => $totalAmount,
    ]);
});

$router->post(function () {
    try {
        $repoCommissions = new AffiliateCommissionsRepository();
        $repoPayments = new AffiliateCommissionPaymentsRepository();
        $user = LoginService::getSession();

        if (!$user) {
            LocationUtils::redirectInternal("login");
        }

        $affiliateId = (int)($_POST["affiliate_id"] ?? 0);
        $ids = array_map('intval', $_POST["selected_ids"] ?? []);
        $payoutMethod = strtoupper((string)($_POST["payout_method"] ?? "MANUAL"));
        $paymentReference = trim((string)($_POST["payment_reference"] ?? ''));
        $internalNotes = trim((string)($_POST["internal_notes"] ?? ''));
        $externalNotes = trim((string)($_POST["external_notes"] ?? ''));

        if (!in_array($payoutMethod, ['PAYPAL', 'ACH', 'OTHER', 'MANUAL'], true)) {
            $payoutMethod = 'MANUAL';
        }

        if (!$affiliateId || empty($ids)) {
            MessageUtil::setMessage('Invalid input or no commissions selected.');
            LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . ($affiliateId ?: ''));
        }

        $validCommissions = $repoCommissions->getByIds($ids);
        $validIds = [];
        $totalAmount = 0.0;

        foreach ($validCommissions as $commission) {
            if ((int)$commission->referrer_id === $affiliateId && in_array($commission->status, ['pending', 'approved', 'payable'], true)) {
                $validIds[] = (int)$commission->id;
                $totalAmount += (float)$commission->commission_amount;
            }
        }

        if (empty($validIds)) {
            MessageUtil::setMessage('No valid commissions selected for payout.');
            LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . $affiliateId);
        }

        $proofUrl = "";
        $proofOriginalName = null;
        $proofMime = null;
        $proofSize = null;

        if (isset($_FILES["payment_proof_file"]) && $_FILES["payment_proof_file"]["error"] === 0) {
            $proofOriginalName = $_FILES["payment_proof_file"]["name"] ?? null;
            $proofMime = $_FILES["payment_proof_file"]["type"] ?? null;
            $proofSize = $_FILES["payment_proof_file"]["size"] ?? null;
            $proofUrl = FileUtils::saveFile($_FILES["payment_proof_file"], "commission_payments");
        }

        $payoutBatchId = 'COMM_' . date('YmdHis') . '_' . $affiliateId;
        $paidAt = date("Y-m-d H:i:s");

        $save = $repoPayments->createPayment([
            "referrer_id" => $affiliateId,
            "paid_by_user_id" => $user->getId(),
            "commission_ids" => json_encode($validIds),
            "total_amount" => $totalAmount,
            "commission_count" => count($validIds),
            "payment_method" => strtolower($payoutMethod),
            "payout_method" => $payoutMethod,
            "payment_proof_url" => $proofUrl,
            "payment_reference" => $paymentReference,
            "payment_proof_original_name" => $proofOriginalName,
            "payment_proof_mime" => $proofMime,
            "payment_proof_size" => $proofSize,
            "payout_batch_id" => $payoutBatchId,
            "status" => "completed",
            "paid_at" => $paidAt,
            "payout_year" => (int)date('Y'),
            "payout_month" => (int)date('n'),
            "notes" => $externalNotes,
            "internal_notes" => $internalNotes,
            "external_notes" => $externalNotes,
        ]);

        if (!$save || !$repoCommissions->markAsPaid($validIds, strtolower($payoutMethod), $payoutBatchId)) {
            throw new \Exception("Unable to save payout or mark commissions as paid.");
        }

        TranslationService::detectLocale();
        NotificationService::sendToUsers(
            [$affiliateId],
            TranslationService::trans('planner_hub.commission_payment_received'),
            str_replace('{amount}', number_format($totalAmount, 2), TranslationService::trans('planner_hub.your_affiliate_commissions_paid'))
        );

        MessageUtil::setMessage("Affiliate payout recorded successfully. Total: $" . number_format($totalAmount, 2));
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . $affiliateId);
    } catch (\Exception $e) {
        error_log("Commission payout failed: " . $e->getMessage());
        MessageUtil::setMessage("Payment processing failed: " . $e->getMessage());
        LocationUtils::redirectInternal("panel/planner-hub/management/commissions/pending/details?id=" . ($_POST["affiliate_id"] ?? ''));
    }
});

$router->run();
