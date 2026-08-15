<?php

namespace App\Repositories;

class StorageContainerCategoryRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'storage_container_categories';
        $this->db = new Connection();
    }

    public function getByOwner(int $ownerId): array
    {
        try {
            $this->db->query('SELECT c.*, COUNT(sc.id) AS containers_count FROM storage_container_categories c LEFT JOIN storage_containers sc ON sc.id_category=c.id AND sc.id_owner=c.id_owner WHERE c.id_owner=:owner GROUP BY c.id ORDER BY c.name');
            $this->db->bind(':owner', $ownerId);
            return $this->db->fetchAll();
        } catch (\Throwable $error) {
            // A pending category migration must not turn the whole storage dashboard white.
            error_log('Storage categories unavailable: ' . $error->getMessage());
            return [];
        }
    }

    public function belongsToOwner(int $categoryId, int $ownerId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }
        try {
            return (bool)$this->getOne(['id'=>$categoryId, 'id_owner'=>$ownerId]);
        } catch (\Throwable $error) {
            error_log('Storage category ownership check unavailable: ' . $error->getMessage());
            return false;
        }
    }
}
