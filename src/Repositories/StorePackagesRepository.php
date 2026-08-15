<?php

namespace App\Repositories;

use App\Services\LoginService;

class StorePackagesRepository extends StoreRepository
{
    public function __construct()
    {
        $this->table = 'store_packages';
        $this->db = new Connection();
    }

    public function ensurePrimary(int $ownerId, int $orderId, string $status = 'CREATED'): ?object
    {
        try {
            $this->db->query("SELECT * FROM store_packages WHERE id_owner=:owner AND id_store_order=:order AND package_sequence=1 LIMIT 1");
            $this->db->bind(':owner', $ownerId);
            $this->db->bind(':order', $orderId);
            $existing = $this->db->fetchOne();
            if ($existing) return $existing;

            $code = sprintf('OPH-%d-%d-01', $ownerId, $orderId);
            $location = $this->locationForStatus($status);
            $this->db->query("INSERT INTO store_packages (id_owner,id_store_order,package_sequence,package_code,current_status,current_location_label,last_event_at,created_at,updated_at) VALUES (:owner,:order,1,:code,:status,:location,NOW(),NOW(),NOW())");
            $this->db->bind(':owner', $ownerId); $this->db->bind(':order', $orderId); $this->db->bind(':code', $code);
            $this->db->bind(':status', $status); $this->db->bind(':location', $location); $this->db->execute();
            return $this->ensurePrimary($ownerId, $orderId, $status);
        } catch (\Throwable $e) {
            error_log('Package tracking unavailable: ' . $e->getMessage());
            return null;
        }
    }

    public function ensureForOrder(int $ownerId, int $orderId, string $status = 'CREATED'): ?object
    {
        return $this->ensurePrimary($ownerId, $orderId, $status);
    }

    public function recordStatus(int $ownerId, int $orderId, string $from, string $to, ?int $userId = null, string $notes = ''): bool
    {
        if ($userId === null) {
            try { $userId = (int)LoginService::getSession()->getId() ?: null; } catch (\Throwable $e) { $userId = null; }
        }
        $package = $this->ensurePrimary($ownerId, $orderId, $from ?: $to);
        if (!$package || $from === $to) return (bool)$package;
        try {
            $location = $this->locationForStatus($to);
            $this->db->query("INSERT INTO store_package_events (id_owner,id_store_package,id_store_order,id_user,event_type,status_from,status_to,location_label,notes,created_at) VALUES (:owner,:package,:order,:user,'STATUS_CHANGED',:from,:to,:location,:notes,NOW())");
            $this->db->bind(':owner',$ownerId); $this->db->bind(':package',(int)$package->id); $this->db->bind(':order',$orderId);
            $this->db->bind(':user',$userId); $this->db->bind(':from',$from ?: null); $this->db->bind(':to',$to);
            $this->db->bind(':location',$location); $this->db->bind(':notes',$notes ?: null); $this->db->execute();
            $this->db->query("UPDATE store_packages SET current_status=:status,current_location_label=:location,last_event_at=NOW(),updated_at=NOW() WHERE id=:id AND id_owner=:owner");
            $this->db->bind(':status',$to); $this->db->bind(':location',$location); $this->db->bind(':id',(int)$package->id); $this->db->bind(':owner',$ownerId);
            return (bool)$this->db->execute();
        } catch (\Throwable $e) {
            error_log('Package event could not be recorded: ' . $e->getMessage());
            return false;
        }
    }

    public function search(int $ownerId, string $identifier): ?object
    {
        $identifier = trim($identifier);
        if ($identifier === '') return null;
        $numeric = preg_replace('/\D+/', '', $identifier);
        $this->db->query("SELECT p.*,o.guest_name,o.guest_email,o.guest_phone,o.payment_status,o.total,o.shipping_address_1,o.shipping_address_2,o.shipping_city,o.shipping_state,o.shipping_zip,o.shipping_country,o.shipping_instructions,o.public_token,o.created_at AS order_created_at FROM store_packages p INNER JOIN store_orders o ON o.id=p.id_store_order AND o.id_owner=p.id_owner WHERE p.id_owner=:owner AND (UPPER(p.package_code)=UPPER(:code) OR p.id_store_order=:order) ORDER BY p.package_sequence ASC LIMIT 1");
        $this->db->bind(':owner',$ownerId); $this->db->bind(':code',$identifier); $this->db->bind(':order',$numeric !== '' ? (int)$numeric : 0);
        $row = $this->db->fetchOne();
        if (!$row) return null;
        $this->db->query("SELECT e.*,u.name,u.lastname,u.email FROM store_package_events e LEFT JOIN users u ON u.id=e.id_user WHERE e.id_owner=:owner AND e.id_store_package=:package ORDER BY e.created_at DESC,e.id DESC LIMIT 100");
        $this->db->bind(':owner',$ownerId); $this->db->bind(':package',(int)$row->id);
        $row->events = $this->db->fetchAll();
        $this->db->query("SELECT l.*,u.name,u.lastname FROM store_delivery_location_logs l LEFT JOIN users u ON u.id=l.id_user WHERE l.id_owner=:owner AND l.id_store_order=:order ORDER BY l.recorded_at DESC,l.id DESC LIMIT 1");
        $this->db->bind(':owner',$ownerId); $this->db->bind(':order',(int)$row->id_store_order);
        $row->latest_location = $this->db->fetchOne() ?: null;
        return $row;
    }

    public function getPrimaryMap(int $ownerId, array $orderIds): array
    {
        $map=[];
        foreach ($orderIds as $orderId) {
            $package=$this->ensurePrimary($ownerId,(int)$orderId,'CREATED');
            if ($package) $map[(int)$orderId]=$package;
        }
        return $map;
    }

    public function locationForStatus(string $status): string
    {
        $status = strtoupper($status);
        if (in_array($status,['NEW','CONFIRMED','PROCESSING','IN_PREPARATION'],true)) return 'At business / preparation area';
        if (in_array($status,['READY','READY_FOR_DELIVERY'],true)) return 'At business / ready for pickup';
        if (in_array($status,['OUT_FOR_DELIVERY','DELIVERY_ATTEMPTED','REDELIVERY_SCHEDULED'],true)) return 'With delivery team';
        if ($status === 'RETURNED_TO_BUSINESS') return 'Returned to business';
        if (in_array($status,['DELIVERED','COMPLETED'],true)) return 'Delivered to customer';
        if (str_starts_with($status,'RETURN')) return 'Return workflow';
        if (in_array($status,['CANCELLED','CLOSED'],true)) return 'Closed';
        return 'Status pending confirmation';
    }
}
