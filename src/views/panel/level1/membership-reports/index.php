<?php
use App\Services\Level1MembershipOperationsService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
$router = new Router();
$router->get(function(){ $reports=(new Level1MembershipOperationsService())->getReports(); return TemplateResponse::render(__DIR__.'/index.twig',['reports'=>$reports]); });
try{$router->run();}catch(Exception $e){echo $e->getMessage();}
