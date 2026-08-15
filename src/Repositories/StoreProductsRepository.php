<?php
namespace App\Repositories;
class StoreProductsRepository extends StoreRepository {
    public const PRODUCT_TYPE_FIXED='FIXED'; public const PRODUCT_TYPE_VARIABLE='VARIABLE';
    public const STATUS_ACTIVE='ACTIVE'; public const STATUS_INACTIVE='INACTIVE'; public const STATUS_DRAFT='DRAFT';
    public function __construct(){ $this->table='store_products'; $this->db=new Connection(); }
    public function normalizeProductType(string $type): string { return strtoupper($type)===self::PRODUCT_TYPE_VARIABLE?self::PRODUCT_TYPE_VARIABLE:self::PRODUCT_TYPE_FIXED; }
    public function getEffectivePrice(object $product): float { $promo=(float)($product->promo_price??0); return $promo>0?$promo:(float)($product->price??0); }
    public function getPlainPriceForStorage(array $data): float { return round((float)($data['price']??0),2); }
    public function generateUniqueSlug(string $value,?int $excludeId=null,?int $ownerId=null): string { return $this->uniqueSlug($this->table,$value,$excludeId,$ownerId); }
    public function skuExists(string $sku,?int $excludeProductId=null,?int $ownerId=null): bool { $owner=$this->ownerId($ownerId);$productSql="SELECT id FROM {$this->table} WHERE sku=:product_sku";$variationSql="SELECT id FROM store_product_variations WHERE sku=:variation_sku";if($owner>0){$productSql.=" AND id_owner=:product_owner";$variationSql.=" AND id_owner=:variation_owner";}if($excludeProductId){$productSql.=" AND id<>:product_id";$variationSql.=" AND id_product<>:variation_product_id";}$this->db->query("({$productSql}) UNION ALL ({$variationSql}) LIMIT 1");$this->db->bind(':product_sku',$sku);$this->db->bind(':variation_sku',$sku);if($owner>0){$this->db->bind(':product_owner',$owner);$this->db->bind(':variation_owner',$owner);}if($excludeProductId){$this->db->bind(':product_id',$excludeProductId);$this->db->bind(':variation_product_id',$excludeProductId);}return (bool)$this->db->fetchOne(); }
    public function getPublicById(int $id,?int $ownerId=null): ?object { $sql="SELECT * FROM {$this->table} WHERE id=:id AND status='ACTIVE' AND is_public=1";$owner=$this->ownerId($ownerId);if($owner>0)$sql.=" AND id_owner=:owner";$sql.=" LIMIT 1";$this->db->query($sql);$this->db->bind(':id',$id);if($owner>0)$this->db->bind(':owner',$owner);$row=$this->db->fetchOne();return $row?:null; }
    public function getPublic(?int $ownerId=null): array { $sql="SELECT * FROM {$this->table} WHERE status='ACTIVE' AND is_public=1";$owner=$this->ownerId($ownerId);if($owner>0)$sql.=" AND id_owner=:owner";$sql.=" ORDER BY is_featured DESC,name";$this->db->query($sql);if($owner>0)$this->db->bind(':owner',$owner);return $this->db->fetchAll(); }
    public function getPublicActiveProducts(int $limit=500,?int $ownerId=null): array { return array_slice($this->getPublic($ownerId),0,$limit); }
    public function getFullProductDetails(int $id,?int $ownerId=null): ?object { $product=$ownerId?$this->getOneByOwner(['id'=>$id],$ownerId):$this->getOne(['id'=>$id]);if(!$product)return null;$attrs=new StoreProductsAttributesRepository();$vars=new StoreProductVariationsRepository();$product->attributes=$ownerId?$attrs->getAllByOwnerAndProduct($ownerId,$id):$attrs->getAllBy(['id_product'=>$id]);$product->variations=$vars->getDetailedByProduct($id,$ownerId);return $product; }
    public function saveProductWithRelations(array $data,array $categoryIds=[],array $attributes=[],array $variations=[]): int { $data['id_owner']=$data['id_owner']??$this->ownerId();if(!$this->add($data))return 0;$id=$this->getLastId();$this->replaceRelations($id,$categoryIds,$attributes,$variations);return $id; }
    public function updateProductWithRelations(int $id,array $data,array $categoryIds=[],array $attributes=[],array $variations=[]): bool { if(!$this->update($data,['id'=>$id]))return false;$this->replaceRelations($id,$categoryIds,$attributes,$variations);return true; }
    public function decrementStockForItems(array $items): bool { foreach($items as $item){$qty=(int)$item->quantity;$variation=(int)($item->id_product_variation??0);if($variation>0){$sql="UPDATE store_product_variations SET stock_quantity=stock_quantity-:qty WHERE id=:id AND stock_quantity>=:qty";}else{$sql="UPDATE store_products SET stock_quantity=stock_quantity-:qty WHERE id=:id AND stock_quantity>=:qty";}$this->db->query($sql);$this->db->bind(':qty',$qty);$this->db->bind(':id',$variation?:((int)$item->id_product));$this->db->execute();if($this->db->rowCount()!==1)return false;}return true; }
    public function hasStockForItems(array $items): bool { foreach($items as $item){$qty=(int)$item->quantity;$variation=(int)($item->id_product_variation??0);$table=$variation>0?'store_product_variations':'store_products';$id=$variation?:((int)$item->id_product);$this->db->query("SELECT stock_quantity FROM {$table} WHERE id=:id LIMIT 1");$this->db->bind(':id',$id);$row=$this->db->fetchOne();if(!$row||(int)$row->stock_quantity<$qty)return false;}return true; }
    public function restoreStockForReturnedOrder(int $ownerId, int $orderId, int $restoredBy): bool {
        if ($ownerId <= 0 || $orderId <= 0) return false;
        try {
            $this->db->beginTransaction();
            $this->db->query("INSERT IGNORE INTO store_order_stock_returns (id_owner,id_store_order,restored_by) VALUES (:owner,:order_id,:restored_by)");
            $this->db->bind(':owner',$ownerId);$this->db->bind(':order_id',$orderId);$this->db->bind(':restored_by',$restoredBy);$this->db->execute();
            if ($this->db->rowCount() === 0) { $this->db->commit(); return true; }
            $this->db->query("SELECT id_product,id_product_variation,quantity FROM store_order_items WHERE id_owner=:owner AND id_store_order=:order_id");
            $this->db->bind(':owner',$ownerId);$this->db->bind(':order_id',$orderId);$items=$this->db->fetchAll();
            foreach ($items as $item) {
                $variation=(int)($item->id_product_variation??0);$qty=max(0,(int)$item->quantity);
                if ($qty === 0) continue;
                $table=$variation>0?'store_product_variations':'store_products';$id=$variation?:((int)$item->id_product);
                $this->db->query("UPDATE {$table} SET stock_quantity=stock_quantity+:qty WHERE id=:id AND id_owner=:owner");
                $this->db->bind(':qty',$qty);$this->db->bind(':id',$id);$this->db->bind(':owner',$ownerId);$this->db->execute();
                if ($this->db->rowCount() !== 1) throw new \RuntimeException('Unable to restore returned Store stock item.');
            }
            $this->db->commit(); return true;
        } catch (\Throwable $e) {
            try { $this->db->rollback(); } catch (\Throwable $ignored) {}
            error_log('Store stock return failed: '.$e->getMessage()); return false;
        }
    }
    private function replaceRelations(int $id,array $categories,array $attributes,array $variations): void { $product=$this->getOne(['id'=>$id]);$owner=(int)($product->id_owner??$this->ownerId());$cats=new StoreProductsCategoriesRepository();$attrs=new StoreProductsAttributesRepository();$vars=new StoreProductVariationsRepository();$variationValues=new StoreProductVariationValuesRepository();$cats->deleteByProduct($id);$attrs->deleteByProduct($id);$vars->deleteByProduct($id);foreach($categories as $category){$cats->add(['id_owner'=>$owner,'id_product'=>$id,'id_category'=>(int)$category]);}foreach($attributes as $attributeKey=>$attribute){$valueId=(int)(is_array($attribute)?($attribute['id_attribute_value']??$attribute['id']??0):$attribute);$attributeId=(int)(is_array($attribute)?($attribute['id_attribute']??0):$attributeKey);if($attributeId<=0&&$valueId>0){$this->db->query("SELECT id_attribute FROM store_attribute_values WHERE id=:id LIMIT 1");$this->db->bind(':id',$valueId);$attributeId=(int)($this->db->fetchOne()->id_attribute??0);}if($attributeId>0&&$valueId>0)$attrs->add(['id_owner'=>$owner,'id_product'=>$id,'id_attribute'=>$attributeId,'id_attribute_value'=>$valueId]);}foreach($variations as $variation){if(!is_array($variation))continue;$pairs=$variation['attribute_pairs']??[];unset($variation['attribute_pairs']);$variation['id_owner']=$owner;$variation['id_product']=$id;if(!$vars->add($variation))continue;$variationId=$vars->getLastId();foreach($pairs as $pair){$attributeId=(int)($pair['id_attribute']??0);$valueId=(int)($pair['id_attribute_value']??0);if($attributeId>0&&$valueId>0)$variationValues->add(['id_owner'=>$owner,'id_variation'=>$variationId,'id_attribute'=>$attributeId,'id_attribute_value'=>$valueId]);}} }
}
