<?php
namespace App\Repositories;
class StoreCartItemsRepository extends StoreRepository {
    public const PRICING_PAYG='PAYG'; public const PRICING_SUBSCRIPTION='SUBSCRIPTION';
    public function __construct(){ $this->table='store_cart_items';$this->db=new Connection(); }
    public function getByCart(int $id): array { return $this->getAllBy(['id_cart'=>$id]); }
    public function getDetailedByCart(int $id): array { $this->db->query("SELECT i.*,p.main_image,p.slug FROM {$this->table} i LEFT JOIN store_products p ON p.id=i.id_product WHERE i.id_cart=:id ORDER BY i.id");$this->db->bind(':id',$id);return $this->db->fetchAll(); }
    public function deleteByCart(int $id): bool { $this->db->query("DELETE FROM {$this->table} WHERE id_cart=:id");$this->db->bind(':id',$id);$this->db->execute();return true; }
}
