<?php

namespace App\Repositories;

class OrdersTeamTaskPhotosRepository extends BaseRepository
{
    public function __construct() {
        $this->db = new Connection();
        $this->table = "orders_team_task_photos";
    }

    public function getByTask(int $taskId): array
    {
        $this->db->query("SELECT * FROM `{$this->table}` WHERE `id_task` = :taskId ORDER BY `uploaded_at` DESC");
        $this->db->bind(":taskId", $taskId);
        return $this->db->fetchAll();
    }

    public function deleteByTask(int $taskId): bool
    {
        $this->db->query("DELETE FROM `{$this->table}` WHERE `id_task` = :taskId");
        $this->db->bind(":taskId", $taskId);
        return $this->db->execute();
    }
}
