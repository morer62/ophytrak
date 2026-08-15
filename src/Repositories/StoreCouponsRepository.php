<?php
namespace App\Repositories;
class StoreCouponsRepository extends StoreRepository {
    public const STATUS_ACTIVE='ACTIVE'; public const STATUS_INACTIVE='INACTIVE'; public const SCOPE_GLOBAL='GLOBAL'; public const SCOPE_CUSTOMER='CUSTOMER'; public const TYPE_PERCENT='PERCENT'; public const TYPE_FIXED='FIXED'; public const PURCHASE_MODE_PAYG='PAYG'; public const PURCHASE_MODE_SUBSCRIPTION='SUBSCRIPTION';
    public function __construct(){ $this->table='store_coupons';$this->db=new Connection(); }
    public function normalizeCode(string $code): string { return strtoupper(trim($code)); }
    public function getByOwnerAndCode(int $owner,string $code): ?object { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND code=:code LIMIT 1");$this->db->bind(':owner',$owner);$this->db->bind(':code',$this->normalizeCode($code));$row=$this->db->fetchOne();return $row?:null; }
    public function getAllByOwner(int $owner,int $limit=500): array { return $this->getAllBy(['id_owner'=>$owner],[],$limit); }
    public function incrementTotalUsesAtomic(int $id): bool { $this->db->query("UPDATE {$this->table} SET total_uses=total_uses+1,updated_at=NOW() WHERE id=:id AND (max_total_uses=0 OR total_uses<max_total_uses)");$this->db->bind(':id',$id);$this->db->execute();return $this->db->rowCount()===1; }
}
