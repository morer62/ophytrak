<?php

use App\Repositories\AffiliateCommissionPaymentsRepository;
use App\Repositories\AffiliateCommissionsRepository;
use App\Repositories\AffiliateProfilesRepository;
use App\Services\AffiliateService;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("/login");
    }

    $affiliateService = new AffiliateService();
    $profilesRepository = new AffiliateProfilesRepository();
    $commissionsRepository = new AffiliateCommissionsRepository();
    $paymentsRepository = new AffiliateCommissionPaymentsRepository();

    $affiliateCode = $affiliateService->getOrCreateAffiliateCode($user->getId());
    $profile = $profilesRepository->ensureForUser($user->getId(), $user->getEmail());
    $stats = $affiliateService->getAffiliateStats($user->getId());
    $referrals = $affiliateService->getReferrals($user->getId(), 50);
    $commissions = $affiliateService->getCommissions($user->getId(), 50);
    $monthlySummary = $commissionsRepository->getMonthlySummaryByReferrer($user->getId());
    $payoutHistory = $paymentsRepository->getByReferrer($user->getId());
    $pendingTotal = $commissionsRepository->getPendingTotalByReferrer($user->getId());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "affiliate_code" => $affiliateCode,
        "affiliate_profile" => $profile,
        "stats" => $stats ?: (object)[
            'affiliate_code' => $affiliateCode,
            'clicks' => 0,
            'conversions' => 0,
            'total_referrals' => 0,
            'confirmed_referrals' => 0,
            'pending_commissions' => 0,
            'approved_commissions' => 0,
            'paid_commissions' => 0,
            'total_commissions_earned' => 0,
            'last_referral_date' => null,
        ],
        "referrals" => $referrals,
        "referrals_total" => count($referrals),
        "commissions" => $commissions,
        "monthly_summary" => $monthlySummary,
        "payout_history" => $payoutHistory,
        "pending_total" => $pendingTotal,
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("/login");
    }

    $profilesRepository = new AffiliateProfilesRepository();
    $payoutMethod = strtoupper((string)($_POST['payout_method'] ?? ''));
    $allowedPayoutMethods = ['PAYPAL', 'ACH', 'OTHER', 'MANUAL'];

    $profilesRepository->upsertProfile($user->getId(), [
        'legal_name' => trim((string)($_POST['legal_name'] ?? '')),
        'business_name' => trim((string)($_POST['business_name'] ?? '')),
        'business_type' => trim((string)($_POST['business_type'] ?? '')),
        'tax_country' => trim((string)($_POST['tax_country'] ?? '')),
        'tax_reference' => trim((string)($_POST['tax_reference'] ?? '')),
        'tax_id_last4' => substr(preg_replace('/\D+/', '', (string)($_POST['tax_id_last4'] ?? '')), -4),
        'contact_email' => trim((string)($_POST['contact_email'] ?? $user->getEmail())),
        'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')),
        'address_line1' => trim((string)($_POST['address_line1'] ?? '')),
        'city' => trim((string)($_POST['city'] ?? '')),
        'state' => trim((string)($_POST['state'] ?? '')),
        'zip' => trim((string)($_POST['zip'] ?? '')),
        'country' => trim((string)($_POST['country'] ?? '')),
        'payout_method' => in_array($payoutMethod, $allowedPayoutMethods, true) ? $payoutMethod : null,
        'paypal_email' => trim((string)($_POST['paypal_email'] ?? '')),
        'account_holder_name' => trim((string)($_POST['account_holder_name'] ?? '')),
        'bank_name' => trim((string)($_POST['bank_name'] ?? '')),
        'routing_number' => trim((string)($_POST['routing_number'] ?? '')),
        'account_number_last4' => substr(preg_replace('/\D+/', '', (string)($_POST['account_number_last4'] ?? '')), -4),
        'account_type' => trim((string)($_POST['account_type'] ?? '')),
        'payout_notes' => trim((string)($_POST['payout_notes'] ?? '')),
    ]);

    MessageUtil::setMessage('Affiliate profile updated. Ophyra will review payout and partner information.');
    LocationUtils::redirectInternal('panel/afiliate-hub');
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
