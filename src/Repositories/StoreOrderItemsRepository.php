<?php
namespace App\Repositories;
class StoreOrderItemsRepository extends StoreRepository {
    public function __construct(){ $this->table='store_order_items';$this->db=new Connection(); }
    public function addCompatible(array $data): bool { return $this->add($this->filterExistingColumns($data)); }
    public function getByOrder(int $id): array { return $this->getAllBy(['id_store_order'=>$id]); }
    private function filterExistingColumns(array $data): array { $columns=$this->getColumnMap(); if(!$columns)return $data; return array_filter($data, fn($key)=>isset($columns[$key]), ARRAY_FILTER_USE_KEY); }
    private function getColumnMap(): array { static $columns=null; if($columns!==null)return $columns; try{$this->db->query("SHOW COLUMNS FROM {$this->table}");$rows=$this->db->fetchAll();$columns=[];foreach($rows as $row){$columns[(string)$row->Field]=true;}return $columns;}catch(\Throwable $e){$columns=[];return $columns;} }
}
