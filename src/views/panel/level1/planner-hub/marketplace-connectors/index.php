<?php

use App\Repositories\Connection;
use App\Repositories\MarketplaceConnectorsRepository;
use App\Services\MarketplaceSyncService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$db = new Connection();
$repository = new MarketplaceConnectorsRepository();
$syncService = new MarketplaceSyncService($repository);
$providers = MarketplaceSyncService::PROVIDERS;

$db->query("
    SELECT u.id, u.email, COALESCE(ip.company_name, CONCAT(u.name, ' ', u.lastname), u.email) AS company_name
    FROM users u
    LEFT JOIN institution_profile ip ON ip.id_owner = u.id
    WHERE u.level = 2
    ORDER BY company_name ASC, u.id ASC
    LIMIT 500
");
$businesses = $db->fetchAll();
$selectedOwnerId = (int)($_GET['owner_id'] ?? ($businesses[0]->id ?? 0));
$allowedOwnerIds = array_map(static fn($business): int => (int)$business->id, $businesses);

if ($selectedOwnerId <= 0 || !in_array($selectedOwnerId, $allowedOwnerIds, true)) {
    $selectedOwnerId = (int)($businesses[0]->id ?? 0);
}

$publicStoreUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/') . '/store?owner=' . $selectedOwnerId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    TranslationService::detectLocale();

    $selectedOwnerId = (int)($_POST['owner_id'] ?? $selectedOwnerId);
    if ($selectedOwnerId <= 0 || !in_array($selectedOwnerId, $allowedOwnerIds, true)) {
        MessageUtil::setMessage('Select a valid business before changing connectors.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors');
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $provider = trim((string)($_POST['provider'] ?? ''));

    if (!isset($providers[$provider])) {
        MessageUtil::setMessage('Invalid marketplace provider.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors?owner_id=' . $selectedOwnerId);
    }

    if ($action === 'save_connector') {
        if (!$repository->isReady()) {
            MessageUtil::setMessage('Install the marketplace connector SQL before saving tokens.', 'Error', 'error');
            LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors?owner_id=' . $selectedOwnerId);
        }

        $saved = $repository->upsert($selectedOwnerId, $provider, [
            'display_name' => $_POST['display_name'] ?? $providers[$provider],
            'store_url' => $_POST['store_url'] ?? '',
            'account_id' => $_POST['account_id'] ?? '',
            'shop_domain' => $_POST['shop_domain'] ?? '',
            'access_token' => $_POST['access_token'] ?? '',
            'refresh_token' => $_POST['refresh_token'] ?? '',
            'token_expires_at' => $_POST['token_expires_at'] ?? '',
            'is_active' => isset($_POST['is_active']),
            'public_store_url' => $publicStoreUrl,
        ]);

        MessageUtil::setMessage(
            $saved ? 'Marketplace connector saved.' : 'Marketplace connector could not be saved.',
            $saved ? 'Success' : 'Error',
            $saved ? 'success' : 'error'
        );
        LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors?owner_id=' . $selectedOwnerId);
    }

    if ($action === 'manual_sync') {
        $manualPayload = null;
        $manualPayloadJson = trim((string)($_POST['manual_payload'] ?? ''));

        if ($manualPayloadJson !== '') {
            $manualPayload = json_decode($manualPayloadJson, true);
            if (!is_array($manualPayload)) {
                MessageUtil::setMessage('Manual payload must be valid JSON.', 'Error', 'error');
                LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors?owner_id=' . $selectedOwnerId);
            }
        }

        $result = $syncService->sync($selectedOwnerId, $provider, $manualPayload);
        MessageUtil::setMessage(
            $result['message'],
            $result['success'] ? 'Success' : 'Error',
            $result['success'] ? 'success' : 'error'
        );
        LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors?owner_id=' . $selectedOwnerId);
    }
}

$connectors = [];
foreach ($repository->getByOwner($selectedOwnerId) as $connector) {
    $connectors[$connector->provider] = $connector;
}

echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'providers' => $providers,
    'connectors' => $connectors,
    'recentRuns' => $syncService->getRecentRuns($selectedOwnerId),
    'recentMappings' => $syncService->getRecentMappings($selectedOwnerId),
    'dbReady' => $repository->isReady(),
    'encryptionReady' => $repository->hasConfiguredEncryptionKey(),
    'publicStoreUrl' => $publicStoreUrl,
    'businesses' => $businesses,
    'selectedOwnerId' => $selectedOwnerId,
    'isLevel1MarketplaceAdmin' => true,
]);
