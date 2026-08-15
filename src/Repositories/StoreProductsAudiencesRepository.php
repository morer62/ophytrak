<?php
namespace App\Repositories;
class StoreProductsAudiencesRepository extends StoreRepository {
    public function __construct(){ $this->table='store_products_audiences';$this->db=new Connection(); }
    public function getProductsByAudience(string $audience): array { $this->db->query("SELECT p.* FROM store_products p INNER JOIN {$this->table} r ON r.id_product=p.id WHERE r.audience_type=:type AND p.status='ACTIVE' AND p.is_public=1 ORDER BY p.name");$this->db->bind(':type',$audience);return $this->db->fetchAll(); }
}
