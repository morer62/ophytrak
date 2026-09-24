<?php

use App\Repositories\CarrierRelationshipRepository;
use App\Repositories\CarrierPackageRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$session = LoginService::getSession();
$ownerId = (int)($session->getOwner() ?: $session->getId());
$repository = new CarrierRelationshipRepository();
$isCarrierOrganization = (new CarrierPackageRepository())->isCarrier($ownerId);
$carrierLookup = null;
$lookupEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isCarrierOrganization) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'lookup') {
        $lookupEmail = strtolower(trim((string)($_POST['carrier_email'] ?? '')));
        $carrierLookup = $repository->findAvailableByEmail($ownerId, $lookupEmail);
        if (!$carrierLookup) {
            MessageUtil::setMessage(TranslationService::trans('carrier_relations.not_found'));
        }
    } elseif ($action === 'add') {
        $lookupEmail = strtolower(trim((string)($_POST['carrier_email'] ?? '')));
        $carrierLookup = $repository->findAvailableByEmail($ownerId, $lookupEmail);
        $ok = $carrierLookup
            ? $repository->addRelationship($ownerId, (int)$carrierLookup->id_owner, (int)$session->getId())
            : false;
        MessageUtil::setMessage(TranslationService::trans($ok ? 'carrier_relations.added' : 'carrier_relations.not_found'));
        LocationUtils::reload();
    } elseif ($action === 'remove') {
        $carrierOwnerId = (int)($_POST['carrier_owner_id'] ?? 0);
        $ok = $repository->removeRelationship($ownerId, $carrierOwnerId);
        MessageUtil::setMessage(TranslationService::trans($ok ? 'carrier_relations.removed' : 'carrier_relations.update_failed'));
        LocationUtils::reload();
    }
}

echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'dbReady' => $repository->isReady(),
    'associatedCarriers' => $repository->getAssociated($ownerId),
    'carrierLookup' => $carrierLookup,
    'lookupEmail' => $lookupEmail,
    'isCarrierOrganization' => $isCarrierOrganization,
    'sellerRelationships' => $isCarrierOrganization ? $repository->getSellersForCarrier($ownerId) : [],
    'carrierContactEmail' => $session->getEmail(),
]);
