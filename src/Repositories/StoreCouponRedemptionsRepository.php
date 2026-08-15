<?php
namespace App\Repositories;
class StoreCouponRedemptionsRepository extends StoreRepository {
    public function __construct(){ $this->table='store_coupon_redemptions';$this->db=new Connection(); }
    public function countByCoupon(int $id): int { return count($this->getAllBy(['id_coupon'=>$id])); }
    public function countByCouponAndCustomer(int $id,?int $userId,?string $email): int { $sql="SELECT COUNT(*) total FROM {$this->table} WHERE id_coupon=:coupon AND ((id_user IS NOT NULL AND id_user=:user) OR LOWER(email)=LOWER(:email))";$this->db->query($sql);$this->db->bind(':coupon',$id);$this->db->bind(':user',$userId);$this->db->bind(':email',(string)$email);return (int)($this->db->fetchOne()->total??0); }
}
