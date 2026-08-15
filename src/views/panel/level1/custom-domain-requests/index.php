<?php

use App\Repositories\InstitutionProfileRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $repo = new InstitutionProfileRepository();

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'requests' => $repo->getCustomDomainRequests(),
    ]);
});

$router->post(function () {
    $repo = new InstitutionProfileRepository();
    $profileId = (int)($_POST['profile_id'] ?? 0);
    $action = strtoupper((string)($_POST['action'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($profileId <= 0 || !in_array($action, ['APPROVED', 'REJECTED', 'PENDING'], true)) {
        MessageUtil::setMessage('Invalid custom domain action.');
        LocationUtils::reload();
    }

    $repo->updateCustomDomainStatus($profileId, $action, $notes ?: null);
    MessageUtil::setMessage('Custom domain request updated.');
    LocationUtils::reload();
});

$router->run();
