<?php

use App\Repositories\TipsRepository;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

// Tips sin columna id_owner: lista global (todos los niveles ven los mismos tips)
$router->get(function () {
    $tipsRepo = new TipsRepository();
    $tips = $tipsRepo->getAll();

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "tips" => $tips
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    
    $tipsRepo = new TipsRepository();
    
    $action = $_POST['action'] ?? null;
    
    if ($action === 'create') {
        $percentage = $_POST['percentage'] ?? null;
        
        if (!$percentage || $percentage <= 0) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_percentage_value'));
            LocationUtils::reload();
        }
        
        $tipsRepo->add([
            'percentage' => $percentage,
            'is_active' => 1
        ]);
        
        MessageUtil::setMessage(TranslationService::trans('planner_hub.tip_created_successfully'));
        LocationUtils::reload();
    }
    
    if ($action === 'toggle') {
        $id = $_POST['id'] ?? null;
        $currentStatus = $_POST['current_status'] ?? 0;
        
        if (!$id) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_tip_id'));
            LocationUtils::reload();
        }
        
        $newStatus = $currentStatus == 1 ? 0 : 1;
        
        $tipsRepo->update([
            'is_active' => $newStatus
        ], ['id' => $id]);
        
        MessageUtil::setMessage(TranslationService::trans('planner_hub.tip_status_updated'));
        LocationUtils::reload();
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_tip_id'));
            LocationUtils::reload();
        }
        
        $tipsRepo->delete(['id' => $id]);
        
        MessageUtil::setMessage(TranslationService::trans('planner_hub.tip_deleted_successfully'));
        LocationUtils::reload();
    }
});

$router->run();



