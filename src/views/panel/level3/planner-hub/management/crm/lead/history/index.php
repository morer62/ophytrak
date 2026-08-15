<?php

use App\Entity\User;
use App\Repositories\CrmLeadStatusHistoryRepository;
use App\Repositories\CrmLeadRepository;
use App\Repositories\CrmStatusRepository;
use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\LocationUtils;
use App\Utils\Router;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();


    $leadId = $_GET["id"] ?? 0;
    $user = LoginService::getSession();

    $historyRepo = new CrmLeadStatusHistoryRepository();
    $statusRepo = new CrmStatusRepository();
    $userRepo = new UserRepository();
    $leadRepo = new CrmLeadRepository();

    $filters = [
    "id_lead" => $leadId
    ];

    // Solo los niveles 4 y 5 deben tener filtro por dueño
    if (in_array($user->getLevel(), User::EXTERNAL_USER_LEVEL)) {
        $filters["id_owner"] = $user->getOwner();
    }

    $history = $historyRepo->getAllBy($filters);


    $statuses = $statusRepo->getAll();

    foreach ($history as &$record) {
        $userData = $record->id_user ? $userRepo->getOne(["id" => $record->id_user]) : null;
        $record->user_name = $userData ? $userData->name : "—";
    }

    $lead = $leadRepo->getOne([
        "id" => $leadId
    ]);

    if (is_null($lead)) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.lead_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/crm/lead");
    }

    TranslationService::detectLocale();
    $leadName = $lead ? $lead->name : TranslationService::trans('planner_hub.unknown_lead');

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$context,
        "history" => $history,
        "lead_id" => $leadId,
        "statuses" => $statuses,
        "lead_name" => $leadName
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $context = UserContext::get();

   

    $user = LoginService::getSession();
    $leadId = $_GET["id"] ?? null;
    $comment = trim($_POST["comment"] ?? "");

    // ⚠️ Validación defensiva de campo
    if (!isset($_POST["new_status"]) || $_POST["new_status"] === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.new_status_required'));
        LocationUtils::reload();
    }

    $newStatusId = (int) $_POST["new_status"];

    if (!$leadId || $newStatusId <= 0) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.new_status_required'));
        LocationUtils::reload();
    }

    $leadRepo = new CrmLeadRepository();
    $statusRepo = new CrmStatusRepository();
    $historyRepo = new CrmLeadStatusHistoryRepository();

    $lead = $leadRepo->getOne(["id" => $leadId]);

   


    $newStatus = $statusRepo->getOne(["id" => $newStatusId]);

    if (!$newStatus) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_status_selected'));
        LocationUtils::reload();
    }

    // Guardar historial
    $historyRepo->add([
        "id_lead"    => $leadId,
        "old_status" => $lead ? $lead->id_status : null,
        "new_status" => $newStatus->name,
        "comment"    => $comment,
        "id_user"    => $user->getId(),
        "id_owner"   => $user->getOwner()
    ]);

    // Actualizar status del lead
    $leadRepo->update(["id_status" => $newStatus->id], ["id" => $leadId]);

    // Verificar si se debe archivar automáticamente (cuando viene del modal)
    $shouldArchive = isset($_POST["archive_automatically"]) && $_POST["archive_automatically"] === "1";
    
    if ($shouldArchive) {
        // Archivar el lead automáticamente
        $leadRepo->update(["archived" => "YES"], ["id" => $leadId]);
        MessageUtil::setMessage(TranslationService::trans('planner_hub.status_updated_archived') . " " . $newStatus->name . " " . TranslationService::trans('planner_hub.and_archived_successfully'));
    } else {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.lead_status_updated'));
    }
    
    LocationUtils::reload();
});

$router->run();
