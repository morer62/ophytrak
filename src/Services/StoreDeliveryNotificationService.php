<?php

namespace App\Services;

use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\StoreOrdersRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\UserRepository;

class StoreDeliveryNotificationService
{
    public static function notify(int $ownerId, int $orderId, string $status, string $actorName = 'Operations team'): void
    {
        try {
            $ordersRepo = new StoreOrdersRepository();
            $workflowRepo = new StoreOrderWorkflowRepository();
            $locationRepo = new StoreDeliveryLocationLogsRepository();
            $userRepo = new UserRepository();

            $order = $ordersRepo->getById($orderId);
            if (!$order || (int)($order->id_owner ?? 0) !== $ownerId) {
                return;
            }

            $workflow = $workflowRepo->getByOrder($orderId);
            $latestLocation = $locationRepo->getLatestByOrder($orderId);
            $owner = $userRepo->getOneWithoutOwnership(['id' => $ownerId]);
            $client = !empty($order->id_user) ? $userRepo->getOneWithoutOwnership(['id' => (int)$order->id_user]) : null;

            $clientEmail = trim((string)($order->guest_email ?? ''));
            if ($clientEmail === '' && $client && !empty($client->email)) {
                $clientEmail = (string)$client->email;
            }
            $adminEmail = $owner && !empty($owner->email) ? (string)$owner->email : '';
            if ($clientEmail === '' && $adminEmail === '') {
                return;
            }

            $publicUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/') . '/store/order-access?token=' . urlencode((string)$order->public_token);
            $statusKey = strtolower($status);
            $isDelivered = $statusKey === 'delivered';
            $messages = [
                'delivered' => ['Your order was delivered', 'was marked delivered'],
                'out_for_delivery' => ['Your order is out for delivery', 'was marked as sent'],
                'cancellation_requested' => ['Cancellation requested', 'received a cancellation request'],
                'cancellation_rejected' => ['Cancellation was not approved', 'will continue from its previous stage'],
                'cancellation_resend' => ['Replacement shipment approved', 'will be prepared for reshipment'],
                'cancellation_refunded' => ['Cancellation and refund approved', 'was cancelled and marked refunded'],
            ];
            [$headline, $eventText] = $messages[$statusKey] ?? ['Your order was updated', 'was updated'];
            $adminSubject = 'Store order #' . $orderId . ': ' . $headline;
            $notes = trim((string)($workflow->delivery_notes ?? ''));
            $safeNotes = $notes !== ''
                ? nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'))
                : $headline . '.';

            $body = '<h2>' . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . '</h2>'
                . '<p>Store order #' . $orderId . ' ' . htmlspecialchars($eventText, ENT_QUOTES, 'UTF-8') . ' by ' . htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p><strong>Message:</strong><br>' . $safeNotes . '</p>'
                . '<p><a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '">View order, payment status and delivery tracking</a></p>';

            if ($latestLocation) {
                $mapsUrl = 'https://www.google.com/maps?q=' . rawurlencode((string)$latestLocation->latitude . ',' . (string)$latestLocation->longitude);
                $body .= '<p><a href="' . htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') . '">' . ($isDelivered ? 'View delivery location' : 'View dispatch location') . '</a></p>';
            }

            if ($workflow && !empty($workflow->delivery_photo_url)) {
                $photoUrl = (string)$workflow->delivery_photo_url;
                $body .= '<p><img src="' . htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Delivery proof" style="max-width:520px;width:100%;border-radius:14px;border:1px solid #e5e7eb;"></p>';
            }

            $email = new EmailService($ownerId);
            if ($clientEmail !== '') {
                $email->sendSimpleEmail($clientEmail, $headline . ' - order #' . $orderId, $body, true);
            }
            if ($adminEmail !== '' && strtolower($adminEmail) !== strtolower($clientEmail)) {
                $email->sendSimpleEmail($adminEmail, $adminSubject, $body, true);
            }
        } catch (\Throwable $e) {
            error_log('Store delivery notification failed: ' . $e->getMessage());
        }
    }
}
