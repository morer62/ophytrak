<?php

use App\Services\BusinessProfileBuilderService;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$resolveOwnerId = static function () {
    $user = LoginService::getSession();
    $ownerId = (int)($_GET['owner_id'] ?? 0);

    return $ownerId > 0 ? $ownerId : $user->getOwner();
};

$router->get(function () use ($resolveOwnerId) {
    $builder = new BusinessProfileBuilderService();

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$builder->getBuilderData($resolveOwnerId()),
        'adminOwnerId' => $resolveOwnerId(),
    ]);
});

$router->post(function () use ($resolveOwnerId) {
    $builder = new BusinessProfileBuilderService();

    try {
        $result = $builder->save($resolveOwnerId(), $_POST, $_FILES);
        MessageUtil::setMessage($result['message']);
    } catch (Exception $e) {
        MessageUtil::setMessage("Error saving business profile: " . $e->getMessage());
    }

    LocationUtils::reload();
});

$router->run();
