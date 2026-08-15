<?php
namespace App\Repositories;
class StoreCartsRepository extends StoreRepository {
    public const STATUS_ACTIVE='ACTIVE'; public const STATUS_ABANDONED='ABANDONED'; public const STATUS_CONVERTED='CONVERTED'; public const STATUS_EXPIRED='EXPIRED';
    public const PRICING_PAYG='PAYG'; public const PRICING_SUBSCRIPTION='SUBSCRIPTION'; public const PRICING_QUOTE='QUOTE';
    public function __construct(){ $this->table='store_carts';$this->db=new Connection(); }
    public function generateToken(): string { return bin2hex(random_bytes(24)); }
    public function getBySessionToken(string $token): ?object { return $this->getTokenRow('session_token',$token); }
    public function getByRecoveryToken(string $token): ?object { return $this->getTokenRow('recovery_token',$token); }
    public function markAsConverted(int $id): bool { return $this->update(['status'=>self::STATUS_CONVERTED,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function markRecoveryEmailSent(int $id): bool { return $this->update(['abandoned_email_sent'=>1,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function getDetailedCart(int $id): ?object { $cart=$this->getOne(['id'=>$id]);if($cart)$cart->items=(new StoreCartItemsRepository())->getDetailedByCart($id);return $cart; }
    public function getAbandonedByOwner(int $ownerId,int $limit=200): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND status IN ('ACTIVE','ABANDONED') ORDER BY last_activity_at DESC LIMIT {$limit}");$this->db->bind(':owner',$ownerId);return $this->db->fetchAll(); }
    private function getTokenRow(string $field,string $token): ?object { if($token==='')return null;$this->db->query("SELECT * FROM {$this->table} WHERE {$field}=:token LIMIT 1");$this->db->bind(':token',$token);$row=$this->db->fetchOne();return $row?:null; }
}
