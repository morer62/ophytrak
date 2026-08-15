<?php

namespace App\Repositories;

use PDOException;

class BusinessMonthlySnapshotRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'business_monthly_snapshots';
        $this->db = new Connection();
    }

    public function getByOwnerPeriod(int $idOwner, int $year, int $month, string $locale = 'en'): ?object
    {
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE id_owner = :id_owner
              AND snapshot_year = :snapshot_year
              AND snapshot_month = :snapshot_month
              AND locale = :locale
            LIMIT 1
        ");
        $this->db->bind(':id_owner', $idOwner);
        $this->db->bind(':snapshot_year', $year);
        $this->db->bind(':snapshot_month', $month);
        $this->db->bind(':locale', $locale);

        $result = $this->db->fetchOne();
        return $result ?: null;
    }

    /** @return object[] */
    public function getByOwner(int $idOwner, int $limit = 12, ?string $locale = null): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE id_owner = :id_owner
        ";

        if ($locale !== null && $locale !== '') {
            $sql .= " AND locale = :locale";
        }

        $sql .= " ORDER BY snapshot_year DESC, snapshot_month DESC LIMIT " . (int)$limit;

        $this->db->query($sql);
        $this->db->bind(':id_owner', $idOwner);
        if ($locale !== null && $locale !== '') {
            $this->db->bind(':locale', $locale);
        }

        $rows = $this->db->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function addWithExplicitOwner(array $data): bool
    {
        try {
            if (isset($data['metrics_snapshot']) && is_array($data['metrics_snapshot'])) {
                $data['metrics_snapshot'] = json_encode($data['metrics_snapshot'], JSON_UNESCAPED_UNICODE);
            }

            $keys = array_keys($data);
            $columns = array_map(static fn($key) => "`{$key}`", $keys);
            $placeholders = array_map(static fn($key) => ":{$key}", $keys);

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $this->db->query($sql);
            foreach ($data as $key => $value) {
                $this->db->bind(":{$key}", $value);
            }
            $this->db->execute();

            return true;
        } catch (PDOException $e) {
            error_log('BusinessMonthlySnapshotRepository::addWithExplicitOwner(): ' . $e->getMessage());
            return false;
        }
    }
}
