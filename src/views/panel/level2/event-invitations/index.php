<?php

use App\Services\ModuleGuardService;


use App\Repositories\EventsRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Services\LoginService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\LocationUtils;

ModuleGuardService::requireModule('tickets_rsvp');


$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $eventsRepo = new EventsRepository();
    
    $events = $eventsRepo->getAllByUser($user->getId());
    
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "events" => $events,
        "message" => MessageUtil::getMessage(),
        "env" => $_ENV
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}

