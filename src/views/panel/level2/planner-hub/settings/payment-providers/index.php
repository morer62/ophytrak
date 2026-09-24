<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\ErrorLogging;
use App\Repositories\PaymentProvidersRepository;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\ProductProfileService;

$router = new Router();

// GET: Display Payment Provider configurations
$router->get(function () {
    $session = LoginService::getSession();
    // Nivel 1, 2 y 3: solo los proveedores de este usuario. Nivel 4: getOwner() (institución si aplica).
    $ownerId = in_array($session->getLevel(), [1, 2, 3], true) ? (int)$session->getId() : $session->getOwner();
    
    $paymentRepo = new PaymentProvidersRepository();
    $providersList = $paymentRepo->getAllByOwner($ownerId, 1, 50);
    
    $availableProviders = PaymentProviderFactory::getAvailableProviders();
    $providerNames = PaymentProviderFactory::getProviderNames();
    
    // Get active provider for test payment section
    $activeProvider = $paymentRepo->getActiveByOwner($ownerId);
    $activeProviderData = null;
    
    if (!empty($activeProvider)) {
        $activeProviderData = $activeProvider[0]; // Get first active provider
    }
    
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "providers" => $providersList['data'] ?? [],
        "total" => $providersList['total'] ?? 0,
        "availableProviders" => $availableProviders,
        "providerNames" => $providerNames,
        "activeProvider" => $activeProviderData
    ]);
});

// POST: Handle Payment Provider operations
$router->post(function () {
    $session = LoginService::getSession();
    $ownerId = in_array($session->getLevel(), [1, 2, 3], true) ? (int)$session->getId() : $session->getOwner();
    $paymentRepo = new PaymentProvidersRepository();
    
    $action = $_POST['action'] ?? '';
    
    // =====================================================
    // ADD NEW PAYMENT PROVIDER
    // =====================================================
    if ($action === 'add') {
        $providerType = $_POST['provider_type'] ?? '';
        $providerName = trim($_POST['provider_name'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        $apiSecret = trim($_POST['api_secret'] ?? '');
        $publicKey = trim($_POST['public_key'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $environment = $_POST['environment'] ?? 'sandbox';
        $currency = ProductProfileService::operationalCurrency();
        $merchantEmail = trim($_POST['merchant_email'] ?? '') ?: null;
        $locationId = trim($_POST['location_id'] ?? '') ?: null;
        
        // Debug logging to see what's being received
        $logFile = LocationUtils::getRootLocation() . '/.logs/app_error_' . date('Y-m-d') . '.log';
        $logDir = LocationUtils::getRootLocation() . '/.logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        error_log("\n[ADD PROVIDER DEBUG] Provider: {$providerType}, api_key length: " . strlen($apiKey) . ", public_key length: " . strlen($publicKey) . ", location_id length: " . strlen($locationId ?? ''), 3, $logFile);
        
        TranslationService::detectLocale();
        // Validations
        if (empty($providerType) || empty($providerName)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_type_name_required'), "Error", "error");
            LocationUtils::reload();
        }
        
        if (!in_array($providerType, ['stripe', 'square', 'paypal'])) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_provider_type'), "Error", "error");
            LocationUtils::reload();
        }
        
        // Provider-specific validations
        $requiredFields = PaymentProviderFactory::getRequiredFields($providerType);
        foreach ($requiredFields as $field) {
            if ($field === 'api_key' && empty($apiKey)) {
                error_log("\n[ADD PROVIDER ERROR] api_key is empty for {$providerType}", 3, $logFile);
                MessageUtil::setMessage(str_replace('{provider}', $providerType, TranslationService::trans('planner_hub.api_key_required_for')), "Error", "error");
                LocationUtils::reload();
            }
            if ($field === 'api_secret' && empty($apiSecret) && $providerType === 'paypal') {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.api_secret_required_paypal'), "Error", "error");
                LocationUtils::reload();
            }
            if ($field === 'public_key' && empty($publicKey) && in_array($providerType, ['stripe', 'square'])) {
                error_log("\n[ADD PROVIDER ERROR] public_key is empty for {$providerType}", 3, $logFile);
                MessageUtil::setMessage(str_replace('{provider}', $providerType, TranslationService::trans('planner_hub.public_key_required_for')), "Error", "error");
                LocationUtils::reload();
            }
            if ($field === 'location_id' && empty($locationId) && $providerType === 'square') {
                error_log("\n[ADD PROVIDER ERROR] location_id is empty for Square", 3, $logFile);
                MessageUtil::setMessage(TranslationService::trans('planner_hub.location_id_required_square'), "Error", "error");
                LocationUtils::reload();
            }
        }
        
        // Check if provider name already exists
        if ($paymentRepo->providerNameExists($ownerId, $providerType, $providerName)) {
            MessageUtil::setMessage(str_replace('{provider}', $providerType, TranslationService::trans('planner_hub.config_name_exists')), "Error", "error");
            LocationUtils::reload();
        }
        
        // Test credentials first
        $testCredentials = (object)[
            'provider_type' => $providerType,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'public_key' => $publicKey,
            'webhook_secret' => $webhookSecret,
            'environment' => $environment,
            'currency' => $currency,
            'merchant_email' => $merchantEmail,
            'location_id' => $locationId
        ];
        
        error_log("\n[ADD PROVIDER DEBUG] Testing credentials - api_key length: " . strlen($apiKey) . ", location_id: " . ($locationId ?? 'NULL'), 3, $logFile);
        
        $isVerified = false;
        try {
            $testProvider = PaymentProviderFactory::create($testCredentials);
            $isVerified = $testProvider->validateCredentials();
            error_log("\n[ADD PROVIDER DEBUG] Validation result: " . ($isVerified ? 'SUCCESS' : 'FAILED'), 3, $logFile);
        } catch (\Exception $e) {
            ErrorLogging::log($e);
            error_log("\n[ADD PROVIDER ERROR] Exception during validation: " . $e->getMessage(), 3, $logFile);
        }
        
        // Check if this should be the first/default config
        $existingConfigs = $paymentRepo->getAllByOwner($ownerId, 1, 1);
        $isDefault = $existingConfigs['total'] === 0 ? 1 : 0;
        
        // IMPORTANT: If activating this provider, deactivate all others
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($isActive) {
            // Deactivate all other providers for this owner
            $paymentRepo->deactivateAllByOwner($ownerId);
        }
        
        $added = $paymentRepo->add([
            'id_owner' => $ownerId,
            'provider_type' => $providerType,
            'provider_name' => $providerName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'public_key' => $publicKey,
            'webhook_secret' => $webhookSecret,
            'environment' => $environment,
            'currency' => $currency,
            'merchant_email' => $merchantEmail,
            'location_id' => $locationId,
            'is_active' => $isActive,
            'is_verified' => $isVerified ? 1 : 0,
            'is_default' => $isDefault
        ]);
        
        if ($added) {
            if ($isVerified) {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_added_verified'));
            } else {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_added_verify_credentials'), "Warning", "warning");
            }
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_save_provider'), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // TEST CREDENTIALS
    // =====================================================
    if ($action === 'test') {
        TranslationService::detectLocale();
        $providerId = (int)($_POST['provider_id'] ?? 0);
        
        if (!$providerId) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_provider_id'), "Error", "error");
            LocationUtils::reload();
        }
        
        $provider = $paymentRepo->getById($providerId, $ownerId);
        
        if (!$provider) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.payment_provider_not_found'), "Error", "error");
            LocationUtils::reload();
        }
        
        try {
            $paymentProvider = PaymentProviderFactory::create($provider);
            $isValid = $paymentProvider->validateCredentials();
            
            if ($isValid) {
                $paymentRepo->markAsVerified($providerId, $ownerId);
                MessageUtil::setMessage(TranslationService::trans('planner_hub.credentials_valid_working'));
            } else {
                $paymentRepo->markAsUnverified($providerId, $ownerId);
                MessageUtil::setMessage(TranslationService::trans('planner_hub.credentials_test_failed'), "Error", "error");
            }
        } catch (\Exception $e) {
            ErrorLogging::log($e);
            $paymentRepo->markAsUnverified($providerId, $ownerId);
            MessageUtil::setMessage(str_replace('{error}', $e->getMessage(), TranslationService::trans('planner_hub.test_failed')), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // SET AS DEFAULT
    // =====================================================
    if ($action === 'set_default') {
        TranslationService::detectLocale();
        $providerId = (int)($_POST['provider_id'] ?? 0);
        
        if ($paymentRepo->setAsDefault($providerId, $ownerId)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.default_provider_updated'));
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_update_default_provider'), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // ACTIVATE / DEACTIVATE
    // =====================================================
    if ($action === 'activate') {
        TranslationService::detectLocale();
        $providerId = (int)($_POST['provider_id'] ?? 0);
        
        $paymentRepo->deactivateAllByOwner($ownerId);
        
        if ($paymentRepo->activate($providerId, $ownerId)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_activated_others_deactivated'));
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_activate_provider'), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    if ($action === 'deactivate') {
        TranslationService::detectLocale();
        $providerId = (int)($_POST['provider_id'] ?? 0);
        
        if ($paymentRepo->deactivate($providerId, $ownerId)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_deactivated'));
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_deactivate_provider'), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // DELETE
    // =====================================================
    if ($action === 'delete') {
        TranslationService::detectLocale();
        $providerId = (int)($_POST['provider_id'] ?? 0);
        
        if ($paymentRepo->deleteProvider($providerId, $ownerId)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_deleted_successfully'));
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_delete_provider'), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // UPDATE
    // =====================================================
    if ($action === 'update') {
        TranslationService::detectLocale();
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $providerName = trim($_POST['provider_name'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        $apiSecret = trim($_POST['api_secret'] ?? '');
        $publicKey = trim($_POST['public_key'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $environment = $_POST['environment'] ?? 'sandbox';
        $currency = ProductProfileService::operationalCurrency();
        $merchantEmail = trim($_POST['merchant_email'] ?? '') ?: null;
        $locationId = trim($_POST['location_id'] ?? '') ?: null;
        
        if (empty($providerName)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_name_required'), "Error", "error");
            LocationUtils::reload();
        }
        
        $provider = $paymentRepo->getById($providerId, $ownerId);
        if (!$provider) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.payment_provider_not_found'), "Error", "error");
            LocationUtils::reload();
        }
        
        if ($paymentRepo->providerNameExists($ownerId, $provider->provider_type, $providerName, $providerId)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.another_config_exists_name'), "Error", "error");
            LocationUtils::reload();
        }
        
        $updateData = [
            'provider_name' => $providerName,
            'environment' => $environment,
            'currency' => $currency
        ];
        
        // Only update optional fields if provided
        if (!empty($merchantEmail)) {
            $updateData['merchant_email'] = $merchantEmail;
        }
        
        if (!empty($locationId)) {
            $updateData['location_id'] = $locationId;
        }
        
        // Only update credentials if provided
        if (!empty($apiKey)) {
            $updateData['api_key'] = $apiKey;
        }
        if (!empty($apiSecret)) {
            $updateData['api_secret'] = $apiSecret;
        }
        if (!empty($publicKey)) {
            $updateData['public_key'] = $publicKey;
        }
        if (!empty($webhookSecret)) {
            $updateData['webhook_secret'] = $webhookSecret;
        }
        
        $updated = $paymentRepo->update($updateData, [
            'id' => $providerId,
            'id_owner' => $ownerId
        ]);
        
        if ($updated) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.provider_updated_successfully'));
        } else {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_update_provider_config'), "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // TEST PAYMENT
    // =====================================================
    if ($action === 'test_payment') {
        TranslationService::detectLocale();
        $amount = floatval($_POST['amount'] ?? 0);
        $token = trim($_POST['token'] ?? '');
        $description = trim($_POST['description'] ?? 'Test Payment');
        
        if (empty($token)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.payment_token_required'), "Error", "error");
            LocationUtils::reload();
        }
        
        $activeProvider = $paymentRepo->getActiveByOwner($ownerId);
        if (empty($activeProvider)) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.no_active_provider_found'), "Error", "error");
            LocationUtils::reload();
        }
        
        $provider = $activeProvider[0];
        
        try {
            $paymentProvider = PaymentProviderFactory::create($provider);
            $minAmount = $paymentProvider->getMinimumAmount();
            
            if ($amount < $minAmount) {
                MessageUtil::setMessage(str_replace(['{amount}', '{currency}'], [$minAmount, $provider->currency], TranslationService::trans('planner_hub.minimum_amount_is')), "Error", "error");
                LocationUtils::reload();
            }
        } catch (\Exception $e) {
            if ($amount < 0.50) {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.minimum_amount_default'), "Error", "error");
                LocationUtils::reload();
            }
        }
        
        try {
            $paymentProvider = PaymentProviderFactory::create($provider);
            
            if ($provider->provider_type === 'paypal') {
                $result = $paymentProvider->chargeCustomer($token, $amount, [
                    'description' => $description,
                    'test' => true
                ]);
            } else {
                $result = $paymentProvider->chargeCustomer($token, $amount, [
                    'description' => $description,
                    'test' => true
                ]);
            }
            
            if ($result) {
                $paymentRepo->updateLastUsed($provider->id);
                MessageUtil::setMessage(str_replace('{id}', $result->id, TranslationService::trans('planner_hub.test_payment_success')), "Success", "success");
            } else {
                MessageUtil::setMessage(TranslationService::trans('planner_hub.test_payment_failed_check_credentials'), "Error", "error");
            }
        } catch (\Exception $e) {
            ErrorLogging::log($e);
            MessageUtil::setMessage(str_replace('{error}', $e->getMessage(), TranslationService::trans('planner_hub.test_payment_error')), "Error", "error");
        }
        
        LocationUtils::reload();
    }
});

$router->run();
