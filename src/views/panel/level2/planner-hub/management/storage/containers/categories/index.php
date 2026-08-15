<?php

use App\Repositories\StorageContainerCategoryRepository;
use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('inventory_storage');
$router = new Router();
$router->get(function () {
    $ownerId = (int)LoginService::getSession()->getOwner();
    return TemplateResponse::render(__DIR__.'/index.twig', ['categories'=>(new StorageContainerCategoryRepository())->getByOwner($ownerId)]);
});
$router->post(function () {
    TranslationService::detectLocale();
    $ownerId = (int)LoginService::getSession()->getOwner();
    $repo = new StorageContainerCategoryRepository();
    $action = (string)($_POST['action'] ?? 'create');
    if ($action === 'delete') {
        $id=(int)($_POST['id']??0);$category=null;foreach($repo->getByOwner($ownerId) as $candidate){if((int)$candidate->id===$id){$category=$candidate;break;}}
        if (!$category || (int)$category->containers_count > 0) { MessageUtil::setMessage(TranslationService::trans('planner_hub.container_category_in_use')); LocationUtils::reload(); }
        $ok=$repo->delete(['id'=>$id,'id_owner'=>$ownerId]);
    } else {
        $name=trim((string)($_POST['name']??''));
        $ok=$name!=='' && !$repo->getOne(['id_owner'=>$ownerId,'name'=>$name]) && $repo->add(['id_owner'=>$ownerId,'name'=>$name]);
    }
    MessageUtil::setMessage(TranslationService::trans($ok?'planner_hub.container_category_saved':'planner_hub.container_category_not_saved'));
    LocationUtils::reload();
});
$router->run();
