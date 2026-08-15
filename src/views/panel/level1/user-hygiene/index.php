<?php
use App\Services\Level1MembershipOperationsService;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
$router = new Router();
$router->post(function(){ $service=new Level1MembershipOperationsService(); $admin=LoginService::getSession(); $userId=(int)($_POST['user_id']??0); $action=(string)($_POST['action']??''); $reason=trim((string)($_POST['reason']??'')); if($userId<=0||$action===''||$reason===''){ MessageUtil::setMessage('User, action and reason are required.','Error','error'); LocationUtils::reload(); } $service->markUserHygieneAction((int)$admin->getId(),$userId,$action,$reason); MessageUtil::setMessage('Hygiene action recorded. Financial records were preserved.'); LocationUtils::reload(); });
$router->get(function(){ $service=new Level1MembershipOperationsService(); $filters=['search'=>$_GET['search']??'','filter'=>$_GET['filter']??'suspicious','page'=>$_GET['page']??1,'per_page'=>$_GET['per_page']??25]; return TemplateResponse::render(__DIR__.'/index.twig',['hygiene'=>$service->getUserHygiene($filters),'health'=>$service->getCaptchaHealth(),'filters'=>$filters]); });
try{$router->run();}catch(Exception $e){echo $e->getMessage();}
