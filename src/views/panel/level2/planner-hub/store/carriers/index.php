<?php

use App\Repositories\CarrierRelationshipRepository;
use App\Repositories\CarrierPackageRepository;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$session = LoginService::getSession();
$ownerId = (int)($session->getOwner() ?: $session->getId());
$repository = new CarrierRelationshipRepository();
$isCarrierOrganization = (new CarrierPackageRepository())->isCarrier($ownerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isCarrierOrganization) {
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
    'isCarrierOrganization' => $isCarrierOrganization,
    'sellerRelationships' => $isCarrierOrganization ? $repository->getSellersForCarrier($ownerId) : [],
    'carrierContactEmail' => $session->getEmail(),
]);
