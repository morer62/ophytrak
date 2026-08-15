<?php
namespace App\Repositories;
class StoreAttributesRepository extends StoreRepository {
    public const STATUS_ACTIVE='ACTIVE'; public const STATUS_INACTIVE='INACTIVE';
    public function __construct(){ $this->table='store_attributes'; $this->db=new Connection(); }
    public function getActive(?int $ownerId=null): array { return $ownerId ? $this->getActiveByOwner($ownerId) : $this->getAllBy(['status'=>self::STATUS_ACTIVE]); }
    public function getActiveByOwner(int $ownerId): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND status=:status ORDER BY name");$this->db->bind(':owner',$ownerId);$this->db->bind(':status',self::STATUS_ACTIVE);return $this->db->fetchAll(); }
    public function generateUniqueSlug(string $value, ?int $excludeId=null, ?int $ownerId=null): string { return $this->uniqueSlug($this->table,$value,$excludeId,$ownerId); }
}
