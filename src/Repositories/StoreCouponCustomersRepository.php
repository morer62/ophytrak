<?php
namespace App\Repositories;
class StoreCouponCustomersRepository extends StoreRepository {
    public function __construct(){ $this->table='store_coupon_customers';$this->db=new Connection(); }
    public function getByCoupon(int $id): array { return $this->getAllBy(['id_coupon'=>$id]); }
    public function isAllowedForCoupon(int $id,?int $userId,?string $email): bool { $sql="SELECT id FROM {$this->table} WHERE id_coupon=:coupon AND ((id_user IS NOT NULL AND id_user=:user) OR LOWER(email)=LOWER(:email)) LIMIT 1";$this->db->query($sql);$this->db->bind(':coupon',$id);$this->db->bind(':user',$userId);$this->db->bind(':email',(string)$email);return (bool)$this->db->fetchOne(); }
}
