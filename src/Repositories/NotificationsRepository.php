<?php

namespace App\Repositories;

use App\Repositories\Connection;
use App\Utils\LocationUtils;
use Exception;

class NotificationsRepository extends BaseRepository
{
    protected string $table = "notifications";

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function getByUser(int $userId): array
    {
        $notifications = $this->getAllBy(['id_user' => $userId]);
        $this->normalizeLinks($notifications);
        
        // Ordenar por timestamp descendente (más recientes primero)
        usort($notifications, function($a, $b) {
            return strtotime($b->timestamp) - strtotime($a->timestamp);
        });
        
        return $notifications;
    }

    public function getUnreadByUser(int $userId): array
    {
        $notifications = $this->getAllBy(['id_user' => $userId, 'leido' => 0]);
        $this->normalizeLinks($notifications);
        
        // Ordenar por timestamp descendente (más recientes primero)
        usort($notifications, function($a, $b) {
            return strtotime($b->timestamp) - strtotime($a->timestamp);
        });
        
        return $notifications;
    }

    public function markAsRead(int $notificationId): bool
    {
        error_log("DEBUG: NotificationsRepository::markAsRead() - ID: " . $notificationId);
        
        $result = $this->update(['leido' => 1], ['id' => $notificationId]);
        
        error_log("DEBUG: NotificationsRepository::markAsRead() - Resultado: " . ($result ? 'TRUE' : 'FALSE'));
        
        return $result;
    }

    public function markAllAsRead(int $userId): bool
    {
        try {
            $sql = "UPDATE notifications SET leido = 1 WHERE id_user = :user_id AND leido = 0";
            $this->db->query($sql);
            $this->db->bind(":user_id", $userId);
            $this->db->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    public function getUnreadCount(int $userId): int
    {
        $notifications = $this->getAllBy(['id_user' => $userId, 'leido' => 0]);
        return count($notifications);
    }
    
    public function getAllNotifications(): array
    {
        $notifications = $this->getAllBy([]);
        $this->normalizeLinks($notifications);
        
        // Ordenar por timestamp descendente (más recientes primero)
        usort($notifications, function($a, $b) {
            return strtotime($b->timestamp) - strtotime($a->timestamp);
        });
        
        return $notifications;
    }

    private function normalizeLinks(array $notifications): void
    {
        foreach ($notifications as $notification) {
            $message = (string)($notification->mensaje ?? '');
            if (preg_match('/^Carrier picked up (.+) by secure QR scan$/i', $message, $match)) {
                $notification->mensaje = 'La transportadora recolectó el paquete '.$match[1].' mediante escaneo QR seguro.';
            }
            $link = trim((string)($notification->link ?? ''));
            if ($link === '' || str_starts_with($link, '#')) {
                continue;
            }
            if (preg_match('~^(?:https?:)?//~i', $link)) {
                continue;
            }
            // Notification producers historically saved links as both
            // "panel/..." and "/panel/...". A relative value opened from
            // /panel/notifications became /panel/panel/... and returned 404.
            $notification->link = LocationUtils::pathFor(ltrim($link, '/'));
        }
    }
}
