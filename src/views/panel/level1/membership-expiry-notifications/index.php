<?php

use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\NotificationsRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$userRepo = new UserRepository();

// Procesar envío de notificaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notifications') {
    $selectedUsers = $_POST['selected_users'] ?? [];
    $notificationType = $_POST['notification_type'] ?? 'expired';
    
    if (empty($selectedUsers)) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.please_select_at_least_one_user_notify'));
        LocationUtils::reload();
    }
    
    $ownerId = LoginService::getSession() ? LoginService::getSession()->getOwner() : null;
    $emailService = new EmailService($ownerId);
    $notificationsRepo = new NotificationsRepository();
    $successCount = 0;
    $errorCount = 0;
    TranslationService::detectLocale();
    
    foreach ($selectedUsers as $userId) {
        try {
            $user = $userRepo->getOne(["id" => $userId]);
            if (!$user || !$user->email) {
                $errorCount++;
                continue;
            }
            
            // Crear notificación en la base de datos
            $notificationMessage = $notificationType === 'expired' 
                ? "⚠️ " . TranslationService::trans('planner_hub.your_ophyra_membership_expired')
                : "⏰ " . TranslationService::trans('planner_hub.your_ophyra_membership_expire_soon');
            
            // Construir URL correcta basada en el nivel del usuario
            $appUrl = rtrim($_ENV["APP_URL"] ?? "http://localhost/vnv-venue", '/');
            $membershipUrl = $appUrl . "/panel/membership/manage";
            
            $notificationsRepo->add([
                "id_user" => $userId,
                "mensaje" => $notificationMessage,
                "link" => $membershipUrl,
                "leido" => 0
            ]);
            
            // Enviar email
            $subject = $notificationType === 'expired' 
                ? TranslationService::trans('planner_hub.membership_expired_action_required')
                : TranslationService::trans('planner_hub.membership_expiring_soon_renew_now');
            
            $templateData = [
                'userName' => $user->name . ' ' . $user->lastname,
                'membershipType' => $notificationType,
                'expiryDate' => $user->membership_due_date ? date("F j, Y", strtotime($user->membership_due_date)) : 'N/A',
                'renewalUrl' => $membershipUrl,
                'isExpired' => $notificationType === 'expired'
            ];
            
            $templatePath = \App\Utils\LocationUtils::getTemplatePath("emails/membership_expiry_notification.php");
            
            $emailResult = $emailService->sendTemplateEmail(
                $user->email,
                $subject,
                $templatePath,
                $templateData
            );
            
            if ($emailResult) {
                $successCount++;
                error_log("✅ Membership expiry notification sent successfully to: " . $user->email);
            } else {
                $errorCount++;
                error_log("❌ Failed to send membership expiry notification to: " . $user->email);
            }
            
        } catch (\Exception $e) {
            $errorCount++;
            error_log("Error sending membership expiry notification to user $userId: " . $e->getMessage());
        }
    }
    
    if ($successCount > 0) {
        $message = str_replace('{count}', $successCount, TranslationService::trans('planner_hub.successfully_sent_notifications'));
        if ($errorCount > 0) {
            $message .= " $errorCount " . TranslationService::trans('planner_hub.failed');
        }
        MessageUtil::setMessage($message);
    } else {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.failed_send_notifications'));
    }
    
    LocationUtils::reload();
}

// Obtener parámetros de filtro y paginación
$searchTerm = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10; // Usuarios por página
$offset = ($page - 1) * $perPage;

// Obtener usuarios con membresías vencidas o próximas a vencer
$expiredUsers = $userRepo->getExpiredMembershipUsers($searchTerm, $perPage, $offset);
$expiringSoonUsers = $userRepo->getExpiringSoonMembershipUsers($searchTerm, $perPage, $offset);

// Obtener totales para paginación
$expiredTotal = $userRepo->getExpiredMembershipUsersCount($searchTerm);
$expiringSoonTotal = $userRepo->getExpiringSoonMembershipUsersCount($searchTerm);

// Calcular páginas totales
$expiredTotalPages = ceil($expiredTotal / $perPage);
$expiringSoonTotalPages = ceil($expiringSoonTotal / $perPage);

// Combinar y organizar por tipo de usuario
$allUsers = [
    'expired' => $expiredUsers,
    'expiring_soon' => $expiringSoonUsers
];

// Mostrar la vista
echo TemplateResponse::render(__DIR__ . "/index.twig", [
    "users" => $allUsers,
    "expiredCount" => $expiredTotal,
    "expiringSoonCount" => $expiringSoonTotal,
    "searchTerm" => $searchTerm,
    "currentPage" => $page,
    "expiredTotalPages" => $expiredTotalPages,
    "expiringSoonTotalPages" => $expiringSoonTotalPages,
    "perPage" => $perPage
]);
