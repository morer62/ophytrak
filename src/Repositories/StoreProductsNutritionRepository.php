<?php
namespace App\Repositories;
class StoreProductsNutritionRepository extends StoreRepository {
    public function __construct(){ $this->table='store_products_nutrition'; $this->db=new Connection(); }
    public function getByProduct(int $id): ?object { return $this->getOne(['id_product'=>$id]); }
}
