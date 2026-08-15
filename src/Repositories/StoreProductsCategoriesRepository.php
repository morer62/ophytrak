<?php
namespace App\Repositories;
class StoreProductsCategoriesRepository extends StoreRepository {
    public function __construct(){ $this->table='store_products_categories'; $this->db=new Connection(); }
    public function deleteByProduct(int $id): bool { return $this->deleteWhere('id_product',$id); }
    public function getCategoryIdsByProduct(int $id): array { return array_map(fn($row)=>(int)$row->id_category,$this->getAllBy(['id_product'=>$id])); }
    public function getProductIdsByCategorySlug(int $ownerId, string $slug): array {
        $this->db->query("SELECT DISTINCT spc.id_product FROM {$this->table} spc INNER JOIN store_categories sc ON sc.id=spc.id_category WHERE sc.id_owner=:owner_id AND sc.slug=:slug AND sc.status='ACTIVE'");
        $this->db->bind(':owner_id',$ownerId);
        $this->db->bind(':slug',$slug);
        return array_map(static fn($row)=>(int)$row->id_product,$this->db->fetchAll());
    }
    private function deleteWhere(string $field,int $value): bool { $this->db->query("DELETE FROM {$this->table} WHERE {$field}=:value");$this->db->bind(':value',$value);$this->db->execute();return true; }
}
