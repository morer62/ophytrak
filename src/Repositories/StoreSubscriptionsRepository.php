<?php
namespace App\Repositories;
class StoreSubscriptionsRepository extends StoreRepository {
    public const STATUS_ACTIVE='ACTIVE'; public const STATUS_PAUSED='PAUSED'; public const STATUS_CANCELLED='CANCELLED';
    public function __construct(){ $this->table='store_subscriptions';$this->db=new Connection(); }
    public function getActiveByUser(int $id): ?object { return $this->getOne(['id_user'=>$id,'status'=>self::STATUS_ACTIVE]); }
    public function getActiveByEmail(string $email): ?object { return $this->getOne(['email'=>$email,'status'=>self::STATUS_ACTIVE]); }
    public function assignUser(int $id,int $user): bool { return $this->update(['id_user'=>$user,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function registerCharge(int $id,string $date,string $nextDate): bool { return $this->update(['last_charge_date'=>$date,'next_charge_date'=>$nextDate,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function updateMealsCount(int $id,int $count): bool { return $this->update(['meals_count'=>$count,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function pause(int $id): bool { return $this->update(['status'=>self::STATUS_PAUSED,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function resume(int $id): bool { return $this->update(['status'=>self::STATUS_ACTIVE,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
    public function activate(int $id): bool { return $this->resume($id); }
    public function archive(int $id): bool { return $this->update(['archive'=>1,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$id]); }
}
