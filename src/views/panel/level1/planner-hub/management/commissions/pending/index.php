<?php

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

    $repoCommissions = new AffiliateCommissionsRepository();
    $profilesRepository = new AffiliateProfilesRepository();

    $affiliateId = $_GET["affiliate_id"] ?? null;
    $from = $_GET["from"] ?? "";
    $to = $_GET["to"] ?? "";
    $status = $_GET["status"] ?? null;

    $groupedCommissions = $repoCommissions->getGroupedPendingCommissions();

    if ($affiliateId) {
        $groupedCommissions = array_filter($groupedCommissions, fn($item) => (int)$item->referrer_id === (int)$affiliateId);
    }

    if ($from || $to) {
        $groupedCommissions = array_filter($groupedCommissions, function ($item) use ($from, $to) {
            $oldestDate = date('Y-m-d', strtotime($item->oldest_commission));
            $newestDate = date('Y-m-d', strtotime($item->newest_commission));

            if ($from && $oldestDate < $from) return false;
            if ($to && $newestDate > $to) return false;

            return true;
        });
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "affiliates" => $profilesRepository->getAllForFilter(),
        "affiliateProfiles" => $profilesRepository->getAllWithUsers($status ?: null),
        "commissionTotals" => $repoCommissions->getTotalsByStatus(),
        "monthlyRanking" => $repoCommissions->getMonthlyRanking(),
        "affiliateId" => $affiliateId,
        "from" => $from,
        "to" => $to,
        "status" => $status,
        "groupedCommissions" => $groupedCommissions,
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("/login");
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'updateAffiliateProfile') {
        LocationUtils::reload();
    }

    $affiliateId = (int)($_POST['affiliate_id'] ?? 0);
    $status = strtoupper((string)($_POST['application_status'] ?? 'PENDING'));
    $rate = (float)($_POST['commission_rate'] ?? 30);
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));

    if (!$affiliateId || !in_array($status, ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'], true)) {
        MessageUtil::setMessage('Invalid affiliate profile update.');
        LocationUtils::reload();
    }

    if (!in_array((int)$rate, [30, 40, 50], true)) {
        $rate = 30;
    }

    $profilesRepository = new AffiliateProfilesRepository();
    $profilesRepository->updateApplicationStatus($affiliateId, $status, $user->getId(), $rate, $reason ?: null);
    $affiliateService = new AffiliateService();
    if ($status === 'APPROVED') {
        $affiliateService->getOrCreateAffiliateCode($affiliateId);
        $affiliateService->setAffiliateCodeStatus($affiliateId, 'active');
    } elseif ($status === 'SUSPENDED') {
        $affiliateService->setAffiliateCodeStatus($affiliateId, 'suspended');
    } else {
        $affiliateService->setAffiliateCodeStatus($affiliateId, 'inactive');
    }

    MessageUtil::setMessage('Affiliate profile updated.');
    LocationUtils::redirectInternal('panel/planner-hub/management/commissions/pending');
});

try {
    $router->run();
} catch (\Exception $e) {
    error_log("Router error in commissions pending: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}
