<?php

namespace App\Repositories;

class OrdersServiceTasksRepository extends BaseRepository
{

    public function __construct() {
        $this->db = new Connection();
        $this->table = "orders_service_tasks";
    }

    public function getAllWithoutOwner(array $conditions): array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $whereClauses[] = "`$key` = ?";
            $params[] = $value;
        }

        $whereSQL = implode(" AND ", $whereClauses);
        $query = "SELECT * FROM `$this->table` WHERE $whereSQL";

        $this->db->query($query);
        
        // Bind parameters
        foreach ($params as $index => $value) {
            $this->db->bind($index + 1, $value);
        }
        
        return $this->db->fetchAll();
    }

    public function addWithExplicitOwner(array $data): bool
    {
        try {
            $fields = array_keys($data);
            $placeholders = array_map(fn($field) => ":$field", $fields);
            $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->query($sql);
            foreach ($data as $field => $value) {
                $this->db->bind(":$field", $value);
            }
            $this->db->execute();
            return true;
        } catch (\Exception $e) {
            error_log("Error in OrdersServiceTasksRepository::addWithExplicitOwner: " . $e->getMessage());
            return false;
        }
    }
}
