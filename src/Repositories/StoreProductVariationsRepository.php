<?php
namespace App\Repositories;
class StoreProductVariationsRepository extends StoreRepository {
    public function __construct(){ $this->table='store_product_variations'; $this->db=new Connection(); }
    public function getByProductAndId(int $productId,int $id): ?object { return $this->getOne(['id'=>$id,'id_product'=>$productId]); }
    public function getEffectivePrice(object $variation): float { $promo=(float)($variation->promo_price??0); return $promo>0?$promo:(float)($variation->price??0); }
    public function getDetailedByProduct(int $id,?int $ownerId=null): array { $rows=$ownerId?$this->getAllByOwnerAndProduct($ownerId,$id):$this->getAllBy(['id_product'=>$id]);$values=new StoreProductVariationValuesRepository();foreach($rows as $row)$row->attribute_pairs=$values->getByVariation((int)$row->id);return $rows; }
    public function getAllByOwnerAndProduct(int $ownerId,int $productId): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND id_product=:product ORDER BY sort_order,id");$this->db->bind(':owner',$ownerId);$this->db->bind(':product',$productId);return $this->db->fetchAll(); }
    public function deleteByProduct(int $id): bool { $values=new StoreProductVariationValuesRepository();foreach($this->getAllBy(['id_product'=>$id]) as $variation)$values->deleteByVariation((int)$variation->id);$this->db->query("DELETE FROM {$this->table} WHERE id_product=:id");$this->db->bind(':id',$id);$this->db->execute();return true; }
}
