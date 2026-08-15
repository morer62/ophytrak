<?php
namespace App\Repositories;
class StoreProductsAttributesRepository extends StoreRepository {
    public function __construct(){ $this->table='store_products_attributes'; $this->db=new Connection(); }
    public function getAllByOwnerAndProduct(int $ownerId,int $productId): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner AND id_product=:product");$this->db->bind(':owner',$ownerId);$this->db->bind(':product',$productId);return $this->db->fetchAll(); }
    public function deleteByProduct(int $id): bool { $this->db->query("DELETE FROM {$this->table} WHERE id_product=:id");$this->db->bind(':id',$id);$this->db->execute();return true; }
}
