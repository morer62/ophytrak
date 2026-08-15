<?php

use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Repositories\SmtpCredentialsRepository;
use App\Services\EmailServiceFactory;

$router = new Router();

// GET: Display SMTP configurations
$router->get(function () {
    $session = LoginService::getSession();
    // Nivel 1, 2 y 3: solo las configuraciones SMTP de este usuario. Nivel 4: getOwner() (institución si aplica).
    $ownerId = in_array($session->getLevel(), [1, 2, 3], true) ? (int)$session->getId() : $session->getOwner();
    
    $smtpRepo = new SmtpCredentialsRepository();
    $smtpList = $smtpRepo->getAllByOwner($ownerId, 1, 50);
    
    $availableProviders = EmailServiceFactory::getAvailableProviders();
    
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "smtpConfigs" => $smtpList['data'],
        "total" => $smtpList['total'],
        "availableProviders" => $availableProviders
    ]);
});

// POST: Handle SMTP operations
$router->post(function () {
    $session = LoginService::getSession();
    $ownerId = in_array($session->getLevel(), [1, 2, 3], true) ? (int)$session->getId() : $session->getOwner();
    $smtpRepo = new SmtpCredentialsRepository();
    
    $action = $_POST['action'] ?? '';
    
    // =====================================================
    // ADD NEW SMTP CONFIGURATION
    // =====================================================
    if ($action === 'add') {
        $providerName = trim($_POST['provider_name'] ?? '');
        $providerType = $_POST['provider_type'] ?? 'custom';
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        $smtpPassword = $_POST['smtp_password'] ?? '';
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');
        $replyToEmail = trim($_POST['reply_to_email'] ?? '') ?: null;
        
        // Validations
        if (empty($providerName) || empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword) || empty($fromEmail) || empty($fromName)) {
            MessageUtil::setMessage("All required fields must be filled.", "Error", "error");
            LocationUtils::reload();
        }
        
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            MessageUtil::setMessage("Invalid from email address.", "Error", "error");
            LocationUtils::reload();
        }
        
        if ($smtpRepo->providerNameExists($ownerId, $providerName)) {
            MessageUtil::setMessage("A configuration with this name already exists.", "Error", "error");
            LocationUtils::reload();
        }
        
        // Test connection first
        $testResult = EmailServiceFactory::testSmtpConnection([
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_username' => $smtpUsername,
            'smtp_password' => $smtpPassword
        ]);
        
        $isVerified = $testResult['success'] ? 1 : 0;
        
        // Check if this should be the first/default config
        $existingConfigs = $smtpRepo->getAllByOwner($ownerId, 1, 1);
        $isDefault = $existingConfigs['total'] === 0 ? 1 : 0;
        
        $added = $smtpRepo->add([
            'id_owner' => $ownerId,
            'provider_name' => $providerName,
            'provider_type' => $providerType,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_username' => $smtpUsername,
            'smtp_password' => $smtpPassword,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to_email' => $replyToEmail,
            'is_active' => 1,
            'is_verified' => $isVerified,
            'is_default' => $isDefault
        ]);
        
        if ($added) {
            if ($isVerified) {
                MessageUtil::setMessage("✅ SMTP configuration added and verified successfully!");
            } else {
                MessageUtil::setMessage("⚠️ SMTP configuration added but connection test failed. Please verify your credentials.", "Warning", "warning");
            }
        } else {
            MessageUtil::setMessage("Failed to save SMTP configuration.", "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // TEST SMTP CONNECTION
    // =====================================================
    if ($action === 'test') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        
        if (!$smtpId) {
            MessageUtil::setMessage("Invalid SMTP ID.", "Error", "error");
            LocationUtils::reload();
        }
        
        $smtp = $smtpRepo->getById($smtpId, $ownerId);
        
        if (!$smtp) {
            MessageUtil::setMessage("SMTP configuration not found.", "Error", "error");
            LocationUtils::reload();
        }
        
        $testResult = EmailServiceFactory::testSmtpConnection([
            'smtp_host' => $smtp->smtp_host,
            'smtp_port' => $smtp->smtp_port,
            'smtp_encryption' => $smtp->smtp_encryption,
            'smtp_username' => $smtp->smtp_username,
            'smtp_password' => $smtp->smtp_password
        ]);
        
        if ($testResult['success']) {
            $smtpRepo->markAsVerified($smtpId, $ownerId);
            MessageUtil::setMessage("✅ " . $testResult['message']);
        } else {
            MessageUtil::setMessage("❌ " . $testResult['message'], "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // SEND TEST EMAIL
    // =====================================================
    if ($action === 'send_test') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        $testEmail = trim($_POST['test_email'] ?? '');
        
        if (!$smtpId || !$testEmail) {
            MessageUtil::setMessage("SMTP ID and test email are required.", "Error", "error");
            LocationUtils::reload();
        }
        
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            MessageUtil::setMessage("Invalid email address.", "Error", "error");
            LocationUtils::reload();
        }
        
        $result = EmailServiceFactory::sendTestEmail($ownerId, $testEmail);
        
        if ($result['success']) {
            MessageUtil::setMessage("✅ " . $result['message']);
        } else {
            MessageUtil::setMessage("❌ " . $result['message'], "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // SET AS DEFAULT
    // =====================================================
    if ($action === 'set_default') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        
        if ($smtpRepo->setAsDefault($smtpId, $ownerId)) {
            MessageUtil::setMessage("✅ Default SMTP updated successfully.");
        } else {
            MessageUtil::setMessage("Failed to update default SMTP.", "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // ACTIVATE / DEACTIVATE
    // =====================================================
    if ($action === 'activate') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        
        if ($smtpRepo->activate($smtpId, $ownerId)) {
            MessageUtil::setMessage("✅ SMTP configuration activated.");
        } else {
            MessageUtil::setMessage("Failed to activate SMTP.", "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    if ($action === 'deactivate') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        
        if ($smtpRepo->deactivate($smtpId, $ownerId)) {
            MessageUtil::setMessage("SMTP configuration deactivated.");
        } else {
            MessageUtil::setMessage("Failed to deactivate SMTP.", "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // DELETE
    // =====================================================
    if ($action === 'delete') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        
        if ($smtpRepo->deleteSmtp($smtpId, $ownerId)) {
            MessageUtil::setMessage("SMTP configuration deleted successfully.");
        } else {
            MessageUtil::setMessage("Failed to delete SMTP configuration.", "Error", "error");
        }
        
        LocationUtils::reload();
    }
    
    // =====================================================
    // UPDATE
    // =====================================================
    if ($action === 'update') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        $providerName = trim($_POST['provider_name'] ?? '');
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        $smtpPassword = $_POST['smtp_password'] ?? '';
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');
        $replyToEmail = trim($_POST['reply_to_email'] ?? '') ?: null;
        
        if (empty($providerName) || empty($smtpHost) || empty($smtpUsername) || empty($fromEmail) || empty($fromName)) {
            MessageUtil::setMessage("All required fields must be filled.", "Error", "error");
            LocationUtils::reload();
        }
        
        if ($smtpRepo->providerNameExists($ownerId, $providerName, $smtpId)) {
            MessageUtil::setMessage("Another configuration with this name already exists.", "Error", "error");
            LocationUtils::reload();
        }
        
        $updateData = [
            'provider_name' => $providerName,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_username' => $smtpUsername,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to_email' => $replyToEmail
        ];
        
        // Only update password if provided
        if (!empty($smtpPassword)) {
            $updateData['smtp_password'] = $smtpPassword;
        }
        
        $updated = $smtpRepo->update($updateData, [
            'id' => $smtpId,
            'id_owner' => $ownerId
        ]);
        
        if ($updated) {
            MessageUtil::setMessage("✅ SMTP configuration updated successfully.");
        } else {
            MessageUtil::setMessage("Failed to update SMTP configuration.", "Error", "error");
        }
        
        LocationUtils::reload();
    }
});

$router->run();
