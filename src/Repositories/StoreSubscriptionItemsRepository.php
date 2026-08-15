<?php
namespace App\Repositories;
class StoreSubscriptionItemsRepository extends StoreRepository {
    public function __construct(){ $this->table='store_subscription_items';$this->db=new Connection(); }
    public function getBySubscription(int $id): array { return $this->getAllBy(['id_subscription'=>$id]); }
    public function getMealsCount(int $id): int { $this->db->query("SELECT COALESCE(SUM(quantity),0) total FROM {$this->table} WHERE id_subscription=:id");$this->db->bind(':id',$id);return (int)($this->db->fetchOne()->total??0); }
    public function replaceItems(int $subscription,array $items): bool { $owner=$this->ownerId();$this->db->query("DELETE FROM {$this->table} WHERE id_subscription=:id");$this->db->bind(':id',$subscription);$this->db->execute();foreach($items as $item){if(!$this->add(['id_owner'=>$owner,'id_subscription'=>$subscription,'id_product'=>(int)($item['id_product']??0),'product_name_snapshot'=>(string)($item['product_name_snapshot']??''),'quantity'=>max(1,(int)($item['quantity']??1))]))return false;}return true; }
}
