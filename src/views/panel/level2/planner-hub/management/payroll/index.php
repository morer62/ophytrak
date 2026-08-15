<?php

use App\Repositories\InstitutionProfileRepository;
use App\Repositories\PayrollHoursRepository;
use App\Services\LoginService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $summary = [
        "pending_count" => 0,
        "pending_hours" => 0.0,
        "week_hours" => 0.0,
        "week_label" => "",
    ];

    try {
        $ownerId = $user ? (int)$user->getId() : 0;

        if ($user && (int)$user->getLevel() === 4) {
            $currentInstitutionId = $_SESSION["current_institution_id"] ?? null;
            if ($currentInstitutionId) {
                $institutionRepo = new InstitutionProfileRepository();
                $institution = $institutionRepo->getById($currentInstitutionId);
                if ($institution && isset($institution->id_owner)) {
                    $ownerId = (int)$institution->id_owner;
                }
            }
        }

        $repo = new PayrollHoursRepository();
        $unpaid = $repo->getHistoryWithUserAndStatus("", "", null, false, $ownerId);
        $pendingUsers = [];
        $pendingSeconds = 0;

        foreach ($unpaid as $row) {
            if (!empty($row->id_user)) {
                $pendingUsers[(int)$row->id_user] = true;
            }
            if (!empty($row->start_time) && !empty($row->end_time)) {
                $start = strtotime($row->start_time);
                $end = strtotime($row->end_time);
                if ($start && $end && $end > $start) {
                    $pendingSeconds += ($end - $start);
                }
            }
        }

        $weekStart = (new DateTimeImmutable("monday this week"))->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify("+6 days")->setTime(23, 59, 59);
        $weekFrom = $weekStart->format("Y-m-d H:i:s");
        $weekTo = $weekEnd->format("Y-m-d H:i:s");
        $weekRows = array_merge(
            $repo->getHistoryWithUserAndStatus($weekFrom, $weekTo, null, false, $ownerId),
            $repo->getHistoryWithUserAndStatus($weekFrom, $weekTo, null, true, $ownerId)
        );
        $weekSeconds = 0;

        foreach ($weekRows as $row) {
            if (!empty($row->start_time) && !empty($row->end_time)) {
                $start = strtotime($row->start_time);
                $end = strtotime($row->end_time);
                if ($start && $end && $end > $start) {
                    $weekSeconds += ($end - $start);
                }
            }
        }

        $summary = [
            "pending_count" => count($pendingUsers),
            "pending_hours" => round($pendingSeconds / 3600, 2),
            "week_hours" => round($weekSeconds / 3600, 2),
            "week_label" => $weekStart->format("M j") . " - " . $weekEnd->format("M j, Y"),
        ];
    } catch (Throwable $e) {
        // Keep payroll navigation available even if historical tables are incomplete.
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "payrollSummary" => $summary,
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
