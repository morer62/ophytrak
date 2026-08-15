<?php
namespace App\Services;

use App\Entity\User;
use App\Repositories\UserRepository;
use App\Repositories\UserRolesRepository;
use App\Utils\LocationUtils;


class NotificationService
{
    public static function sendExpoNotification(string $expoToken, string $title, string $body, array $data = []): void
    {
        $expoToken = trim($expoToken);
        if (!preg_match('/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_\\-]+\\]$/', $expoToken)) {
            error_log('[EXPO] Invalid Expo token format. Notification skipped.');
            return;
        }

        $payload = json_encode([
            "to" => $expoToken,
            "sound" => "default",
            "title" => $title,
            "body" => $body,
            "data" => $data,
        ]);

        $ch = curl_init("https://exp.host/--/api/v2/push/send");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: application/json",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        if ($response === false) {
            error_log('[EXPO] Push request failed: ' . curl_error($ch));
            curl_close($ch);
            return;
        }
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        if ($statusCode >= 400 || (isset($decoded['data']['status']) && $decoded['data']['status'] === 'error')) {
            error_log('[EXPO] Push rejected. HTTP ' . $statusCode . ' Response: ' . substr((string)$response, 0, 500));
        }
    }


    /**
     * Permite enviar una notificación a múltiples usuarios según su ID
     * Solo se envía si el usuario tiene un expo_token válido.
     */
    public static function sendToUsers(array $userIds, string $title, string $body): void
    {
        $userRepo = new UserRepository();

        foreach ($userIds as $id) {
            $user = $userRepo->getOneWithoutOwnership(['id' => $id]);
            if ($user && $user->expo_token) {
                self::sendExpoNotification($user->expo_token, $title, $body);
            }
        }
    }
}
