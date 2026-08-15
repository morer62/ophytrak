<?php
namespace App\Repositories;
class StoreProductsMealStylesRepository extends StoreRepository {
    public function __construct(){ $this->table='store_products_meal_styles';$this->db=new Connection(); }
    public function getProductsByMealStyle(string $style): array { $this->db->query("SELECT p.* FROM store_products p INNER JOIN {$this->table} r ON r.id_product=p.id WHERE r.meal_style=:type AND p.status='ACTIVE' AND p.is_public=1 ORDER BY p.name");$this->db->bind(':type',$style);return $this->db->fetchAll(); }
}
