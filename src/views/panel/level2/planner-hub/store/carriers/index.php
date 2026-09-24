<?php

use App\Repositories\CarrierRelationshipRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$session = LoginService::getSession();
$ownerId = (int)($session->getOwner() ?: $session->getId());
$repository = new CarrierRelationshipRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $carrierOwnerId = (int)($_POST['carrier_owner_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $ok = $action === 'remove'
        ? $repository->removeRelationship($ownerId, $carrierOwnerId)
        : $repository->addRelationship($ownerId, $carrierOwnerId, (int)$session->getId());
    MessageUtil::setMessage($ok ? ($action === 'remove' ? 'Shipping company removed.' : 'Shipping company added.') : 'The shipping company relationship could not be updated.');
    LocationUtils::reload();
}

echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'dbReady' => $repository->isReady(),
    'associatedCarriers' => $repository->getAssociated($ownerId),
    'availableCarriers' => $repository->getAvailable($ownerId),
]);
