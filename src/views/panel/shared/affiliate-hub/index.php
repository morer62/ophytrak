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

$buildPayload = static function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("/login");
    }

    $affiliateService = new AffiliateService();
    $profilesRepository = new AffiliateProfilesRepository();
    $commissionsRepository = new AffiliateCommissionsRepository();
    $paymentsRepository = new AffiliateCommissionPaymentsRepository();

    $profile = $profilesRepository->getByUserId($user->getId());
    $status = $profile ? (string)$profile->application_status : 'NOT_APPLIED';
    $affiliateCode = $status === 'APPROVED' ? $affiliateService->getOrCreateAffiliateCode($user->getId()) : '';
    $stats = $status === 'APPROVED' ? $affiliateService->getAffiliateStats($user->getId()) : null;

    return [
        "user" => $user,
        "affiliate_code" => $affiliateCode,
        "affiliate_profile" => $profile,
        "affiliate_status" => $status,
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
        "referrals" => $status === 'APPROVED' ? $affiliateService->getReferrals($user->getId(), 50) : [],
        "commissions" => $status === 'APPROVED' ? $affiliateService->getCommissions($user->getId(), 50) : [],
        "monthly_summary" => $status === 'APPROVED' ? $commissionsRepository->getMonthlySummaryByReferrer($user->getId()) : [],
        "payout_history" => $status === 'APPROVED' ? $paymentsRepository->getByReferrer($user->getId()) : [],
        "pending_total" => $status === 'APPROVED' ? $commissionsRepository->getPendingTotalByReferrer($user->getId()) : 0,
    ];
};

$router->get(function () use ($buildPayload) {
    return TemplateResponse::render(__DIR__ . "/index.twig", $buildPayload());
});

$router->post(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("/login");
    }

    $profilesRepository = new AffiliateProfilesRepository();
    $payoutMethod = strtoupper((string)($_POST['payout_method'] ?? ''));
    $allowedPayoutMethods = ['PAYPAL', 'ACH', 'OTHER', 'MANUAL'];
    $accountNumber = preg_replace('/\D+/', '', (string)($_POST['account_number'] ?? ''));

    $data = [
        'legal_name' => trim((string)($_POST['legal_name'] ?? ($user->getName() . ' ' . $user->getLastname()))),
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
        'account_number_encrypted' => $accountNumber,
        'account_type' => trim((string)($_POST['account_type'] ?? '')),
        'payout_notes' => trim((string)($_POST['payout_notes'] ?? '')),
    ];

    $profile = $profilesRepository->getByUserId($user->getId());
    $termsAccepted = isset($_POST['terms_accepted']);

    if (!$profile && !$termsAccepted) {
        MessageUtil::setMessage('You must accept the affiliate program terms before applying.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/afiliate-hub');
    }

    if ($profile) {
        $saved = $profilesRepository->upsertProfile($user->getId(), $data);
        if ($saved && ($profile->application_status ?? '') === 'REJECTED' && $termsAccepted) {
            $profilesRepository->updateApplicationStatus($user->getId(), 'PENDING');
        }
    } else {
        $saved = $profilesRepository->markApplied($user->getId(), $data);
    }

    MessageUtil::setMessage($saved
        ? 'Affiliate profile saved. Applications remain pending until platform approval.'
        : 'Affiliate profile could not be saved.'
    );
    LocationUtils::redirectInternal('panel/afiliate-hub');
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
