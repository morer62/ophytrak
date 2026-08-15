<?php

namespace App\Services;

use App\Repositories\NotificationsRepository;
use App\Repositories\OrdersPaymentsRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\UserRepository;
use App\Services\OrderCalculatorService;
use App\Services\EmailService;
use App\Services\TranslationService;

class PaymentNotificationService
{
    /**
     * Genera notificaciones de pagos para una orden específica
     */
    public static function generatePaymentNotifications(int $orderId): void
    {
        $notificationsRepo = new NotificationsRepository();
        $paymentRepo = new OrdersPaymentsRepository();
        $orderRepo = new OrdersRepository();
        
        // Obtener la orden
        $order = $orderRepo->getOne(["id" => $orderId]);
        if (!$order) {
            return;
        }
        
        // Obtener pagos de la orden
        $payments = $paymentRepo->getAllBy(["id_order" => $orderId]);
        
        // Calcular montos
        $amounts = OrderCalculatorService::calculateTotal($order);
        $firstPercent = $order->payment_split_percent_1 ?? 50;
        $secondPercent = $order->payment_split_percent_2 ?? 50;
        
        $firstPayment = round($amounts["total"] * $firstPercent / 100, 2);
        $secondPayment = round($amounts["total"] * $secondPercent / 100, 2);
        
        // Verificar pagos existentes
        $paymentStatus = 'pending_first';
        if (count($payments) > 0) {
            if ($order->payment_split_type == 2) {
                $paymentStatus = count($payments) === 1 ? 'pending_second' : 'complete';
            } elseif ($order->payment_split_type == 1) {
                $paymentStatus = 'complete';
            }
        }
        
        // Verificar si ya se generaron notificaciones de pagos para esta orden
        $existingPaymentNotifications = $notificationsRepo->getAllBy([
            'id_user' => $order->id_owner
        ]);
        
        $hasFirstPaymentNotification = false;
        $hasCompletePaymentNotification = false;
        
        foreach ($existingPaymentNotifications as $notification) {
            // Verificar si es una notificación de pago por el mensaje
            if (strpos($notification->mensaje, 'First Payment Received') !== false && 
                strpos($notification->mensaje, 'VNV341' . $orderId) !== false) {
                $hasFirstPaymentNotification = true;
            }
            if (strpos($notification->mensaje, 'Payment Complete') !== false && 
                strpos($notification->mensaje, 'VNV341' . $orderId) !== false) {
                $hasCompletePaymentNotification = true;
            }
        }
        
        // Generar URL pública de la orden para cliente/owner
        $orderToken = self::generateOrderToken((int)$order->id, (int)$order->id_client);
        $publicOrderUrl = ($_ENV["APP_URL"] ?? "vnv-venue") . "/order-access?token=" . $orderToken;
        
        // Generar notificación para primer pago
        if (count($payments) === 1 && $order->payment_split_type == 2 && !$hasFirstPaymentNotification) {
            // Notificación para el propietario
            $notificationsRepo->add([
                "id_user" => $order->id_owner,
                "mensaje" => "💰 First Payment Received - Payment #1 received for order #VNV341" . $orderId,
                "link" => $publicOrderUrl,
                "leido" => 0
            ]);
            
            // Notificación para el cliente
            $notificationsRepo->add([
                "id_user" => $order->id_client,
                "mensaje" => "✅ Payment Confirmed - Your first payment for order #VNV341" . $orderId . " has been received successfully.",
                "link" => $publicOrderUrl,
                "leido" => 0
            ]);
            
            NotificationService::sendToUsers(
                [$order->id_owner],
                '💰 First Payment Received',
                'Payment #1 received for order #VNV341' . $orderId
            );
            
            // Enviar email de confirmación de pago al cliente
            self::sendPaymentConfirmationEmail($order, 'first', $firstPayment);
        }
        
        // Generar notificación para pago completo
        if ($paymentStatus === 'complete' && !$hasCompletePaymentNotification) {
            // Notificación para el propietario
            $notificationsRepo->add([
                "id_user" => $order->id_owner,
                "mensaje" => "🎉 Payment Complete - All payments received for order #VNV341" . $orderId,
                "link" => $publicOrderUrl,
                "leido" => 0
            ]);
            
            // Notificación para el cliente
            $notificationsRepo->add([
                "id_user" => $order->id_client,
                "mensaje" => "🎉 Payment Complete - All payments for order #VNV341" . $orderId . " have been received successfully.",
                "link" => $publicOrderUrl,
                "leido" => 0
            ]);
            
            NotificationService::sendToUsers(
                [$order->id_owner],
                '🎉 Payment Complete',
                'All payments received for order #VNV341' . $orderId
            );
            
            // Enviar email de confirmación de pago completo al cliente
            self::sendPaymentConfirmationEmail($order, 'complete', $amounts["total"]);
        }
    }
    
    /**
     * Genera un token para acceso a la orden
     */
    private static function generateOrderToken(int $orderId, int $userId): string
    {
        $secret = $_ENV["VNV_SECRET_KEY"] ?? "mySuperSecretKey";
        $payload = [
            "order_id" => $orderId,
            "user_id" => $userId,
            "exp" => time() + 60 * 60 * 24 * 30, // 30 días
        ];
        $payload["hash"] = hash_hmac("sha256", json_encode([
            "order_id" => $payload["order_id"],
            "user_id" => $payload["user_id"],
            "exp" => $payload["exp"]
        ]), $secret);
        // Devolver el token en base64 simple (sin urlencode) porque
        // /public/order-access/index.php espera base64 directo en $_GET['token'].
        return base64_encode(json_encode($payload));
    }
    
    /**
     * Envía email de confirmación de pago al cliente
     */
    private static function sendPaymentConfirmationEmail($order, string $paymentType, float $amount): void
    {
        try {
            $userRepo = new UserRepository();
            // Usar getOneWithoutOwnership para evitar filtros de ownership
            $client = $userRepo->getOneWithoutOwnership(["id" => $order->id_client]);
            
            if (!$client || !$client->email) {
                error_log("Client email not found for payment confirmation - Order ID: " . $order->id);
                return;
            }
            
            // Usar el id_owner de la orden para las credenciales SMTP del owner (panel/planner-hub/settings/smtp)
            $emailService = new EmailService($order->id_owner);
            
            // Obtener el idioma del sistema del owner para el correo
            $owner = $userRepo->getOneWithoutOwnership(["id" => $order->id_owner]);
            // Acceder directamente a la propiedad system_language del objeto de BD
            $systemLanguage = ($owner && isset($owner->system_language) && !empty($owner->system_language)) ? $owner->system_language : 'en';
            
            // Establecer el locale para el correo según el system_language del owner
            TranslationService::setLocale($systemLanguage);
            
            // Determinar el tipo de pago y el mensaje
            $subject = "";
            $templateData = [];
            
            // Generar enlace público de la orden para el email
            $orderToken = self::generateOrderToken((int)$order->id, (int)$order->id_client);
            $orderPublicUrl = ($_ENV["APP_URL"] ?? "http://localhost/vnv-venue") . "/order-access?token=" . $orderToken;

            if ($paymentType === 'first') {
                $subject = "✅ " . TranslationService::trans('planner_hub.email_first_payment_confirmed', ['order_id' => 'VNV341' . $order->id]);
                $templateData = [
                    'orderId' => $order->id,
                    'paymentType' => TranslationService::trans('planner_hub.first_payment'),
                    'amount' => $amount,
                    'eventDate' => date("F j, Y", strtotime($order->event_date)),
                    'eventTime' => date("g:i A", strtotime($order->start_time)) . ' ' . TranslationService::trans('planner_hub.to') . ' ' . date("g:i A", strtotime($order->end_time)),
                    'location' => $order->address,
                    'orderUrl' => $orderPublicUrl,
                    'remainingMessage' => TranslationService::trans('planner_hub.email_second_payment_due'),
                    'locale' => $systemLanguage
                ];
            } else {
                $subject = "🎉 " . TranslationService::trans('planner_hub.email_payment_complete', ['order_id' => 'VNV341' . $order->id]);
                $templateData = [
                    'orderId' => $order->id,
                    'paymentType' => TranslationService::trans('planner_hub.full_payment'),
                    'amount' => $amount,
                    'eventDate' => date("F j, Y", strtotime($order->event_date)),
                    'eventTime' => date("g:i A", strtotime($order->start_time)) . ' ' . TranslationService::trans('planner_hub.to') . ' ' . date("g:i A", strtotime($order->end_time)),
                    'location' => $order->address,
                    'orderUrl' => $orderPublicUrl,
                    'remainingMessage' => TranslationService::trans('planner_hub.email_order_fully_paid'),
                    'locale' => $systemLanguage
                ];
            }
            
            $templatePath = \App\Utils\LocationUtils::getTemplatePath("emails/payment_confirmation.php");
            
            $emailResult = $emailService->sendTemplateEmail(
                $client->email,
                $subject,
                $templatePath,
                $templateData
            );
            
            if ($emailResult) {
                error_log("✅ Payment confirmation email sent successfully to: " . $client->email);
            } else {
                error_log("❌ Failed to send payment confirmation email to: " . $client->email . " - Debug: " . $emailService->getDebugInfo());
            }
            
        } catch (\Exception $e) {
            error_log("Error sending payment confirmation email: " . $e->getMessage());
        }
    }
}
