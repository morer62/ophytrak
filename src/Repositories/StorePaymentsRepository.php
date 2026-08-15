<?php
namespace App\Repositories;
class StorePaymentsRepository extends StoreRepository {
    public const TYPE_FULL='FULL'; public const TYPE_PARTIAL='PARTIAL'; public const TYPE_SUBSCRIPTION_INITIAL='SUBSCRIPTION_INITIAL'; public const TYPE_RECOVERY='RECOVERY'; public const STATUS_PENDING='PENDING'; public const STATUS_PAID='PAID'; public const STATUS_FAILED='FAILED'; public const STATUS_REFUNDED='REFUNDED';
    public function __construct(){ $this->table='store_payments';$this->db=new Connection(); }
    public function getAllByOwner(int $owner,int $limit=300): array { $this->db->query("SELECT * FROM {$this->table} WHERE id_owner=:owner ORDER BY created_at DESC LIMIT {$limit}");$this->db->bind(':owner',$owner);return $this->db->fetchAll(); }
    public function getLatestByOrder(int $orderId, ?int $ownerId = null): ?object { $sql="SELECT * FROM {$this->table} WHERE id_store_order=:order";if($ownerId!==null&&$ownerId>0){$sql.=" AND id_owner=:owner";}$sql.=" ORDER BY id DESC LIMIT 1";$this->db->query($sql);$this->db->bind(':order',$orderId);if($ownerId!==null&&$ownerId>0){$this->db->bind(':owner',$ownerId);}$row=$this->db->fetchOne();return $row?:null; }
    public function getPaidTotalByOrder(int $orderId, int $ownerId): float { $this->db->query("SELECT COALESCE(SUM(CASE WHEN status='PAID' THEN amount WHEN status='REFUNDED' THEN -amount ELSE 0 END),0) total FROM {$this->table} WHERE id_store_order=:order AND id_owner=:owner");$this->db->bind(':order',$orderId);$this->db->bind(':owner',$ownerId);$row=$this->db->fetchOne();return max(0.0,(float)($row->total??0)); }

    public function addCompatible(array $data): bool
    {
        return $this->add($this->filterExistingColumns($data));
    }

    public function updateCompatible(array $data, array $criteria): bool
    {
        return $this->update($this->filterExistingColumns($data), $criteria);
    }

    private function filterExistingColumns(array $data): array
    {
        $columns = $this->getColumnMap();
        if (!$columns) {
            return $data;
        }

        return array_filter(
            $data,
            fn($key) => isset($columns[$key]),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function getColumnMap(): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        try {
            $this->db->query("SHOW COLUMNS FROM {$this->table}");
            $rows = $this->db->fetchAll();
            $columns = [];
            foreach ($rows as $row) {
                $columns[(string)$row->Field] = true;
            }
            return $columns;
        } catch (\Throwable $e) {
            $columns = [];
            return $columns;
        }
    }
}
