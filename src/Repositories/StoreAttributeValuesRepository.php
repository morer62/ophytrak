<?php
namespace App\Repositories;
class StoreAttributeValuesRepository extends StoreRepository {
    public const STATUS_ACTIVE='ACTIVE'; public const STATUS_INACTIVE='INACTIVE';
    public function __construct(){ $this->table='store_attribute_values'; $this->db=new Connection(); }
    public function getByAttribute(int $id,?int $ownerId=null): array { return $ownerId ? $this->getByOwnerAndAttribute($ownerId,$id) : $this->getAllBy(['id_attribute'=>$id]); }
    public function getActiveByAttribute(int $id,?int $ownerId=null): array { return $ownerId ? $this->getActiveByOwnerAndAttribute($ownerId,$id) : $this->getAllBy(['id_attribute'=>$id,'status'=>self::STATUS_ACTIVE]); }
    public function getByOwnerAndAttribute(int $ownerId,int $attributeId): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND id_attribute=:attribute ORDER BY sort_order,value");$this->db->bind(':owner',$ownerId);$this->db->bind(':attribute',$attributeId);return $this->db->fetchAll(); }
    public function getActiveByOwnerAndAttribute(int $ownerId,int $attributeId): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND id_attribute=:attribute AND status=:status ORDER BY sort_order,value");$this->db->bind(':owner',$ownerId);$this->db->bind(':attribute',$attributeId);$this->db->bind(':status',self::STATUS_ACTIVE);return $this->db->fetchAll(); }
    public function getActiveGroupedByOwner(int $ownerId): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND status=:status ORDER BY id_attribute,sort_order,value");$this->db->bind(':owner',$ownerId);$this->db->bind(':status',self::STATUS_ACTIVE);$rows=$this->db->fetchAll();$grouped=[];foreach($rows as $row){$attributeId=(int)($row->id_attribute??0);if($attributeId<=0){continue;}if(!isset($grouped[$attributeId])){$grouped[$attributeId]=[];}$grouped[$attributeId][]=$row;}return $grouped; }
    public function generateUniqueSlug(int $attributeId,string $value,?int $excludeId=null,?int $ownerId=null): string { return $this->uniqueSlug($this->table,$value,$excludeId,$ownerId,['id_attribute'=>$attributeId]); }
}
