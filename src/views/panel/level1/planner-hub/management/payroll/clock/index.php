<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Services\TeamMemberContractService;
use App\Repositories\PayrollTimeLogsRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;

$router = new Router();
$repo = new PayrollTimeLogsRepository();
$user = LoginService::getSession();

$router->get(function () use ($repo, $user): string {
    $currentOwnerId = $user->getOwner();
    
    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $institutionOwnerId = $institution ? $institution->id_owner : null;
            $currentOwnerId = $institutionOwnerId ?? $currentOwnerId;
        }
    }
    
    $logs = $repo->getAllBy([
        "id_user" => $user->getId(),
        "end_time" => null,
        "id_owner" => $currentOwnerId
    ]);

    $activeLog = count($logs) > 0 ? $logs[0] : null;

    if ($activeLog) {
        $activeLog->start_time = strtotime($activeLog->start_time);
    }
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "activeLog" => $activeLog
    ]);
});

$router->post(callback: function () use ($repo, $user): void {
    $action = $_POST["action"] ?? "";
    
    $currentOwnerId = $user->getOwner();
    
    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            $institutionOwnerId = $institution ? $institution->id_owner : null;
            $currentOwnerId = $institutionOwnerId ?? $currentOwnerId;
        }
    }
    
    $logs = $repo->getAllBy([
        "id_user" => $user->getId(),
        "id_owner" => $currentOwnerId,
        "end_time" => null
    ]);

    TranslationService::detectLocale();
    
    if ($action === "start") {
        if (count($logs) > 0) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.you_already_started'));
            LocationUtils::reload();
        }

        $contractService = new TeamMemberContractService();
        if (!$contractService->isClockInAllowed($user->getId(), $currentOwnerId)) {
            MessageUtil::setMessage(TranslationService::trans('ui.team_contract_clock_blocked'));
            LocationUtils::reload();
        }

        $repo->add([
            "id_user" => $user->getId(),
            "start_time" => date("Y-m-d H:i:s"),
            "id_owner" => $currentOwnerId,
            "end_time" => null,
            "is_paid" => 0
        ]);

        MessageUtil::setMessage(TranslationService::trans('planner_hub.work_session_started'));
        LocationUtils::reload();
    }

    if ($action === "end") {

        $activeLog = count($logs) > 0 ? $logs[0] : null;

        if (!$activeLog) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.no_active_session'));
            LocationUtils::reload();
        }

        $repo->update([ "end_time" => date("Y-m-d H:i:s")], [
            "id" => $activeLog->id
        ]);

        MessageUtil::setMessage(TranslationService::trans('planner_hub.work_session_ended'));
        LocationUtils::reload();
    }

    MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_action'));
    LocationUtils::reload();
});

try {
    $router->run();
} catch (Exception $e) {

}
