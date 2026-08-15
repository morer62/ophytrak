<?php

use App\Repositories\MarketplaceConnectorsRepository;
use App\Services\LoginService;
use App\Services\MarketplaceSyncService;
use App\Services\Marketplace\MarketplaceOAuthService;
use App\Services\Marketplace\MarketplaceSandboxService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$user = LoginService::getSession();
$ownerId = (int)($user->getOwner() ?: $user->getId());
$repository = new MarketplaceConnectorsRepository();
$syncService = new MarketplaceSyncService($repository);
$providers = MarketplaceSyncService::PROVIDERS;
$publicStoreUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/') . '/store?owner=' . $ownerId;
$connectorUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/') . '/panel/planner-hub/marketplace-connectors';

if (isset($_GET['marketplace_callback'])) {
    $provider=trim((string)($_GET['provider']??''));$state=(string)($_GET['state']??'');$expected=(string)($_SESSION['marketplace_oauth_state'][$provider]??'');
    if($expected===''||!hash_equals($expected,$state)){MessageUtil::setMessage('The marketplace authorization state is invalid or expired.','Error','error');LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors');}
    try{$code=(string)($_GET['code']??$_GET['auth_code']??'');$token=(new MarketplaceOAuthService())->exchange($provider,$code,$connectorUrl.'?marketplace_callback=1&provider='.$provider);if(isset($_GET['shop_id']))$token['shop_id']=(string)$_GET['shop_id'];$saved=$repository->saveAuthorization($ownerId,$provider,$token);MessageUtil::setMessage($saved?'Marketplace connected successfully.':'The authorization was received but could not be saved.',$saved?'Success':'Error',$saved?'success':'error');}catch(\Throwable $e){MessageUtil::setMessage($e->getMessage(),'Error','error');}
    unset($_SESSION['marketplace_oauth_state'][$provider]);LocationUtils::redirectInternal('panel/planner-hub/marketplace-connectors');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    TranslationService::detectLocale();

    $action = trim((string)($_POST['action'] ?? ''));
    $provider = trim((string)($_POST['provider'] ?? ''));

    if (!isset($providers[$provider])) {
        MessageUtil::setMessage('Invalid marketplace provider.', 'Error', 'error');
        LocationUtils::reload();
    }

    if ($action === 'save_connector') {
        if (!$repository->isReady()) {
            MessageUtil::setMessage('Install the marketplace connector SQL before saving tokens.', 'Error', 'error');
            LocationUtils::reload();
        }

        $saved = $repository->upsert($ownerId, $provider, [
            'display_name' => $_POST['display_name'] ?? $providers[$provider],
            'store_url' => $_POST['store_url'] ?? '',
            'account_id' => $_POST['account_id'] ?? '',
            'external_shop_id' => $_POST['external_shop_id'] ?? $_POST['account_id'] ?? '',
            'shop_cipher' => $_POST['shop_cipher'] ?? '',
            'country_code' => $_POST['country_code'] ?? 'BR',
            'currency_code' => $_POST['currency_code'] ?? 'BRL',
            'shop_domain' => $_POST['shop_domain'] ?? '',
            'access_token' => $_POST['access_token'] ?? '',
            'refresh_token' => $_POST['refresh_token'] ?? '',
            'token_expires_at' => $_POST['token_expires_at'] ?? '',
            'is_active' => isset($_POST['is_active']),
            'sync_products' => isset($_POST['sync_products']),
            'sync_orders' => isset($_POST['sync_orders']),
            'sync_inventory' => isset($_POST['sync_inventory']),
            'inventory_source' => $_POST['inventory_source'] ?? 'MARKETPLACE',
            'price_source' => $_POST['price_source'] ?? 'MARKETPLACE',
            'public_store_url' => $publicStoreUrl,
        ]);

        MessageUtil::setMessage(
            $saved ? 'Marketplace connector saved.' : 'Marketplace connector could not be saved.',
            $saved ? 'Success' : 'Error',
            $saved ? 'success' : 'error'
        );
        LocationUtils::reload();
    }

    if($action==='connect_provider'){
        if(!$repository->getByOwnerAndProvider($ownerId,$provider)){MessageUtil::setMessage('Save the connector settings before authorizing the store.','Error','error');LocationUtils::reload();}
        $state=bin2hex(random_bytes(24));$_SESSION['marketplace_oauth_state'][$provider]=$state;
        try{$url=(new MarketplaceOAuthService())->authorizationUrl($provider,$connectorUrl.'?marketplace_callback=1&provider='.$provider,$state);header('Location: '.$url,true,302);exit;}catch(\Throwable $e){MessageUtil::setMessage($e->getMessage(),'Error','error');LocationUtils::reload();}
    }

    if($action==='sandbox_sync'){
        $result=(new MarketplaceSandboxService())->run($ownerId,$provider);MessageUtil::setMessage($result['message'],$result['success']?'Success':'Error',$result['success']?'success':'error');LocationUtils::reload();
    }

    if ($action === 'manual_sync') {
        $manualPayload = null;
        $manualPayloadJson = trim((string)($_POST['manual_payload'] ?? ''));

        if ($manualPayloadJson !== '') {
            $manualPayload = json_decode($manualPayloadJson, true);
            if (!is_array($manualPayload)) {
                MessageUtil::setMessage('Manual payload must be valid JSON.', 'Error', 'error');
                LocationUtils::reload();
            }
        }

        $result = $syncService->sync($ownerId, $provider, $manualPayload);
        MessageUtil::setMessage(
            $result['message'],
            $result['success'] ? 'Success' : 'Error',
            $result['success'] ? 'success' : 'error'
        );
        LocationUtils::reload();
    }
}

$connectors = [];
foreach ($repository->getByOwner($ownerId) as $connector) {
    $connectors[$connector->provider] = $connector;
}

echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'providers' => $providers,
    'connectors' => $connectors,
    'recentRuns' => $syncService->getRecentRuns($ownerId),
    'recentMappings' => $syncService->getRecentMappings($ownerId),
    'dbReady' => $repository->isReady(),
    'encryptionReady' => $repository->hasConfiguredEncryptionKey(),
    'publicStoreUrl' => $publicStoreUrl,
    'sandboxEnabled' => strtolower((string)($_ENV['ENVIRONMENT'] ?? 'prod')) !== 'prod' || str_contains(strtolower((string)($_ENV['APP_URL'] ?? '')), 'localhost') || str_contains((string)($_ENV['APP_URL'] ?? ''), '127.0.0.1'),
]);
