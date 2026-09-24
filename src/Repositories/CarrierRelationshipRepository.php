<?php

namespace App\Repositories;

class CarrierRelationshipRepository extends StoreRepository
{
    public function __construct()
    {
        $this->table = 'store_carrier_relationships';
        $this->db = new Connection();
    }

    public function isReady(): bool
    {
        $this->db->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table");
        $this->db->bind(':table', $this->table);
        return (int)($this->db->fetchOne()->total ?? 0) === 1;
    }

    public function getAssociated(int $sellerOwnerId): array
    {
        if (!$this->isReady()) return [];
        $this->db->query("SELECT r.carrier_owner_id,r.created_at,ip.company_name,ip.city,ip.state,ip.country
            FROM {$this->table} r
            INNER JOIN institution_profile ip ON ip.id_owner=r.carrier_owner_id AND ip.organization_type='CARRIER'
            WHERE r.seller_owner_id=:seller AND r.status='ACTIVE'
            ORDER BY ip.company_name");
        $this->db->bind(':seller', $sellerOwnerId);
        return $this->db->fetchAll();
    }

    public function getAvailable(int $sellerOwnerId): array
    {
        if (!$this->isReady()) return [];
        $this->db->query("SELECT ip.id_owner,ip.company_name,ip.city,ip.state,ip.country
            FROM institution_profile ip
            WHERE ip.organization_type='CARRIER' AND ip.id_owner<>:seller
              AND NOT EXISTS (SELECT 1 FROM {$this->table} r WHERE r.seller_owner_id=:seller_relation AND r.carrier_owner_id=ip.id_owner AND r.status='ACTIVE')
            ORDER BY ip.company_name");
        $this->db->bind(':seller', $sellerOwnerId);
        $this->db->bind(':seller_relation', $sellerOwnerId);
        return $this->db->fetchAll();
    }

    public function getSellersForCarrier(int $carrierOwnerId): array
    {
        if (!$this->isReady()) return [];
        $this->db->query("SELECT r.seller_owner_id,r.created_at,ip.company_name,ip.city,ip.state,ip.country,u.email
            FROM {$this->table} r
            INNER JOIN institution_profile ip ON ip.id_owner=r.seller_owner_id
            LEFT JOIN users u ON u.id=r.seller_owner_id
            WHERE r.carrier_owner_id=:carrier AND r.status='ACTIVE'
            ORDER BY ip.company_name");
        $this->db->bind(':carrier', $carrierOwnerId);
        return $this->db->fetchAll();
    }

    public function isAssociated(int $sellerOwnerId, int $carrierOwnerId): bool
    {
        if (!$this->isReady()) return false;
        $this->db->query("SELECT id FROM {$this->table} WHERE seller_owner_id=:seller AND carrier_owner_id=:carrier AND status='ACTIVE' LIMIT 1");
        $this->db->bind(':seller', $sellerOwnerId);
        $this->db->bind(':carrier', $carrierOwnerId);
        return (bool)$this->db->fetchOne();
    }

    public function addRelationship(int $sellerOwnerId, int $carrierOwnerId, int $actorId): bool
    {
        if (!$this->isReady() || $sellerOwnerId <= 0 || $carrierOwnerId <= 0 || $sellerOwnerId === $carrierOwnerId) return false;
        $carrier = new CarrierPackageRepository();
        if (!$carrier->isCarrier($carrierOwnerId)) return false;
        $this->db->query("INSERT INTO {$this->table} (seller_owner_id,carrier_owner_id,status,created_by_user_id,created_at,updated_at)
            VALUES (:seller,:carrier,'ACTIVE',:actor,NOW(),NOW())
            ON DUPLICATE KEY UPDATE status='ACTIVE',created_by_user_id=:actor_update,updated_at=NOW()");
        $this->db->bind(':seller', $sellerOwnerId);
        $this->db->bind(':carrier', $carrierOwnerId);
        $this->db->bind(':actor', $actorId);
        $this->db->bind(':actor_update', $actorId);
        $this->db->execute();
        return true;
    }

    public function removeRelationship(int $sellerOwnerId, int $carrierOwnerId): bool
    {
        if (!$this->isReady()) return false;
        $this->db->query("UPDATE {$this->table} SET status='INACTIVE',updated_at=NOW() WHERE seller_owner_id=:seller AND carrier_owner_id=:carrier AND status='ACTIVE'");
        $this->db->bind(':seller', $sellerOwnerId);
        $this->db->bind(':carrier', $carrierOwnerId);
        $this->db->execute();
        return $this->db->rowCount() > 0;
    }
}
