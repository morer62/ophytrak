<?php

namespace App\Repositories;

class StoreDeliveryLocationLogsRepository extends StoreRepository
{
    public function __construct()
    {
        $this->table = 'store_delivery_location_logs';
        $this->db = new Connection();
    }

    public function addLocation(int $ownerId, int $orderId, ?int $taskId, int $userId, string $eventType, float $latitude, float $longitude, array $metadata = []): bool
    {
        $this->db->query("
            INSERT INTO {$this->table}
                (id_owner, id_store_order, id_store_order_task, id_user, event_type,
                 latitude, longitude, accuracy, platform, source, permission_status, device_id, context,
                 recorded_at, created_at)
            VALUES
                (:owner, :order, :task, :user, :event_type, :latitude, :longitude, :accuracy, :platform,
                 :source, :permission_status, :device_id, :context, NOW(), NOW())
        ");
        $this->db->bind(':owner', $ownerId);
        $this->db->bind(':order', $orderId);
        $this->db->bind(':task', $taskId);
        $this->db->bind(':user', $userId);
        $this->db->bind(':event_type', $eventType);
        $this->db->bind(':latitude', $latitude);
        $this->db->bind(':longitude', $longitude);
        $this->db->bind(':accuracy', isset($metadata['accuracy']) && is_numeric($metadata['accuracy']) ? (float)$metadata['accuracy'] : null);
        $this->db->bind(':platform', trim((string)($metadata['platform'] ?? 'web_mobile')) ?: 'web_mobile');
        $this->db->bind(':source', trim((string)($metadata['source'] ?? 'browser_geolocation')) ?: 'browser_geolocation');
        $this->db->bind(':permission_status', trim((string)($metadata['permission_status'] ?? 'granted')) ?: 'granted');
        $this->db->bind(':device_id', trim((string)($metadata['device_id'] ?? '')) ?: null);
        $this->db->bind(':context', trim((string)($metadata['context'] ?? 'store_delivery')) ?: 'store_delivery');
        $this->db->execute();
        return true;
    }

    public function getLatestByOrder(int $orderId, ?int $ownerId = null): ?object
    {
        $ownerSql = $ownerId !== null && $ownerId > 0 ? ' AND l.id_owner = :owner' : '';
        $this->db->query("
            SELECT l.*, u.name, u.lastname
            FROM {$this->table} l
            LEFT JOIN users u ON u.id = l.id_user
            WHERE l.id_store_order = :order
              {$ownerSql}
            ORDER BY l.recorded_at DESC, l.id DESC
            LIMIT 1
        ");
        $this->db->bind(':order', $orderId);
        if ($ownerId !== null && $ownerId > 0) {
            $this->db->bind(':owner', $ownerId);
        }
        return $this->db->fetchOne() ?: null;
    }

    public function getLatestMapByOrders(array $orderIds, ?int $ownerId = null): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) {
            return [];
        }

        $placeholders = [];
        foreach ($orderIds as $index => $orderId) {
            $placeholders[] = ':order' . $index;
        }

        $map = [];
        $ownerSql = $ownerId !== null && $ownerId > 0 ? ' AND l.id_owner = :owner' : '';
        $this->db->query("
            SELECT l.*, u.name, u.lastname
            FROM {$this->table} l
            LEFT JOIN users u ON u.id = l.id_user
            WHERE l.id_store_order IN (" . implode(',', $placeholders) . ")
              {$ownerSql}
            ORDER BY l.id_store_order ASC, l.recorded_at DESC, l.id DESC
        ");
        foreach ($orderIds as $index => $orderId) {
            $this->db->bind(':order' . $index, $orderId);
        }
        if ($ownerId !== null && $ownerId > 0) {
            $this->db->bind(':owner', $ownerId);
        }

        foreach ($this->db->fetchAll() as $row) {
            $orderId = (int)($row->id_store_order ?? 0);
            if ($orderId > 0 && !isset($map[$orderId])) {
                $map[$orderId] = $row;
            }
        }
        return $map;
    }
}
