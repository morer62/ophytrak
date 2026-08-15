<?php

namespace App\Repositories;

class StorageContainerRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = "storage_containers";
        $this->db = new Connection();
    }

    public function getDetailedByOwner(int $ownerId): array
    {
        try {
            $this->db->query('SELECT sc.*, scc.name AS category_name FROM storage_containers sc LEFT JOIN storage_container_categories scc ON scc.id=sc.id_category AND scc.id_owner=sc.id_owner WHERE sc.id_owner=:owner ORDER BY sc.name');
            $this->db->bind(':owner', $ownerId);
            return $this->db->fetchAll();
        } catch (\Throwable $error) {
            // Preserve access to containers while a category schema deployment is pending.
            error_log('Storage container categories unavailable: ' . $error->getMessage());
            $this->db->query('SELECT sc.*, NULL AS category_name FROM storage_containers sc WHERE sc.id_owner=:owner ORDER BY sc.name');
            $this->db->bind(':owner', $ownerId);
            return $this->db->fetchAll();
        }
    }
}
