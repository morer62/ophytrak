<?php
namespace App\Repositories;
class StoreProductVariationValuesRepository extends StoreRepository {
    public function __construct(){ $this->table='store_product_variation_values';$this->db=new Connection(); }
    public function deleteByVariation(int $id): bool { $this->db->query("DELETE FROM {$this->table} WHERE id_variation=:id");$this->db->bind(':id',$id);$this->db->execute();return true; }
    public function getByVariation(int $id): array { return $this->getAllBy(['id_variation'=>$id]); }
}
