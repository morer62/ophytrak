<?php

use App\Services\LoginService;
use App\Utils\TemplateResponse;
use App\Utils\Router;
use App\Repositories\TicketCommissionsRepository;
use App\Repositories\OrdersPaymentsRepository;

$router = new Router();

$router->get(function () {
    $session = LoginService::getSession();

    $commissionsRepo = new TicketCommissionsRepository();
    $ticketCommissions = [];
    $totalTicketCommissions = 0;

    if ($session->getLevel() == 1) {
        $ticketCommissions = $commissionsRepo->getCommissionsByDateRange(
            date('Y-m-01'),
            date('Y-m-t')
        );
        $totalStats = $commissionsRepo->getTotalCommissions();
        $totalTicketCommissions = $totalStats->total_commission ?? 0;
    }

    // Pagos cobrados: filtro de fecha (default últimos 30 días)
    $dateFilter = $_GET['date_filter'] ?? '30d';
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;

    $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'America/New_York'));
    if ($dateFilter === 'today') {
        $start = $now->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d 23:59:59');
    } elseif ($dateFilter === '7d') {
        $start = $now->modify('-7 days')->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d 23:59:59');
    } elseif ($dateFilter === '30d') {
        $start = $now->modify('-30 days')->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d 23:59:59');
    } elseif ($dateFilter === 'month') {
        $start = $now->modify('first day of this month')->format('Y-m-d 00:00:00');
        $end = $now->modify('last day of this month')->format('Y-m-d 23:59:59');
    } elseif ($dateFilter === 'range' && $dateFrom && $dateTo) {
        $start = $dateFrom . ' 00:00:00';
        $end = $dateTo . ' 23:59:59';
    } else {
        $dateFilter = '30d';
        $start = $now->modify('-30 days')->format('Y-m-d 00:00:00');
        $end = $now->format('Y-m-d 23:59:59');
    }

    $ownerId = $session->getOwner();
    if ($session->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $ownerId = $institution->id_owner ?? $session->getOwner();
        }
    }

    $chartMonths = (int)($_GET['chart_months'] ?? 12);
    if (!in_array($chartMonths, [6, 12, 24], true)) {
        $chartMonths = 12;
    }

    $paymentRepo = new OrdersPaymentsRepository();
    $collectedPayments = $paymentRepo->getCollectedByOwnerWithDateRange($ownerId, $start, $end);
    $chartDaily = $paymentRepo->getDailyTotalsByOwner($ownerId, 30);
    $chartMonthly = $paymentRepo->getMonthlyTotalsByOwner($ownerId, $chartMonths);

    $totalCollected = 0;
    foreach ($collectedPayments as $p) {
        $net = (float)$p->amount - (float)($p->refunded_amount ?? 0);
        $totalCollected += $net;
    }

    // Generar token de contrato para poder ir directo a los detalles públicos del pedido
    $secret = $_ENV["VNV_SECRET_KEY"] ?? "mySuperSecretKey";
    foreach ($collectedPayments as $p) {
        if (!empty($p->id_order) && !empty($p->id_client)) {
            $payload = [
                "order_id" => (int)$p->id_order,
                "user_id" => (int)$p->id_client,
                "exp" => time() + (86400 * 30)
            ];
            $payload["hash"] = hash_hmac("sha256", json_encode($payload), $secret);
            $p->contract_token = base64_encode(json_encode($payload));
        }
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "ticketCommissions" => $ticketCommissions,
        "totalTicketCommissions" => $totalTicketCommissions,
        "collectedPayments" => $collectedPayments,
        "totalCollected" => $totalCollected,
        "chartDaily" => $chartDaily,
        "chartMonthly" => $chartMonthly,
        "chartMonths" => $chartMonths,
        "dateFilter" => $dateFilter,
        "dateFrom" => $dateFrom,
        "dateTo" => $dateTo,
        "dateStart" => $start,
        "dateEnd" => $end
    ]);
});

$router->run();
