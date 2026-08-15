<?php

namespace App\Repositories;

class TipsRepository extends BaseRepository
{
    protected string $table = "tips";

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function getActiveTips()
    {
        return $this->getAllBy(["is_active" => 1]);
    }

    /** Listar tips por owner (niveles 1,2,3: su id; nivel 4: getOwner()) */
    public function getAllByOwner(int $ownerId): array
    {
        $this->db->query("SELECT * FROM `{$this->table}` WHERE `id_owner` = :id_owner ORDER BY id DESC");
        $this->db->bind(":id_owner", $ownerId);
        return $this->db->fetchAll();
    }

    public function getOneByIdAndOwner(int $id, int $ownerId): ?object
    {
        $this->db->query("SELECT * FROM `{$this->table}` WHERE `id` = :id AND `id_owner` = :id_owner LIMIT 1");
        $this->db->bind(":id", $id);
        $this->db->bind(":id_owner", $ownerId);
        $result = $this->db->fetchOne();
        return $result ? (object)$result : null;
    }

    public function addWithExplicitOwner(array $data): bool
    {
        try {
            $fields = array_keys($data);
            $placeholders = array_map(fn($f) => ":$f", $fields);
            $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->query($sql);
            foreach ($data as $field => $value) {
                $this->db->bind(":$field", $value);
            }
            $this->db->execute();
            return true;
        } catch (\Exception $e) {
            error_log("TipsRepository::addWithExplicitOwner: " . $e->getMessage());
            return false;
        }
    }
}

