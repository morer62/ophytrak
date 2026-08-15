<?php

use App\Services\BusinessProfileBuilderService;
use App\Services\TranslationService;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $builder = new BusinessProfileBuilderService();

    return TemplateResponse::render(__DIR__ . "/index.twig", $builder->getBuilderData($user->getOwner()));
});

$router->post(function () {
    $user = LoginService::getSession();
    $builder = new BusinessProfileBuilderService();

    try {
        $result = $builder->save($user->getOwner(), $_POST, $_FILES);
        MessageUtil::setMessage($result['message']);
    } catch (Exception $e) {
        MessageUtil::setMessage(TranslationService::trans('business_profile_builder.alerts.save_exception') . ': ' . $e->getMessage(), 'danger', 'Error');
    }

    LocationUtils::reload();
});

$router->run();

