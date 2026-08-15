<?php

namespace App\Repositories;

class AdminAccountActionsRepository
{
    private Connection $db;

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function add(int $adminId, int $userId, string $actionType, ?string $note = null, array $metadata = []): bool
    {
        $this->db->query("
            INSERT INTO admin_account_actions
            (id_admin, id_user, action_type, note, metadata_json, created_at)
            VALUES
            (:admin_id, :user_id, :action_type, :note, :metadata_json, NOW())
        ");
        $this->db->bind(':admin_id', $adminId);
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':action_type', $actionType);
        $this->db->bind(':note', $note);
        $this->db->bind(':metadata_json', $metadata ? json_encode($metadata) : null);
        $this->db->execute();

        return true;
    }

    public function getByUserId(int $userId, int $limit = 20): array
    {
        $this->db->query("
            SELECT actions.*, CONCAT(COALESCE(admin.name, ''), ' ', COALESCE(admin.lastname, '')) AS admin_name
            FROM admin_account_actions actions
            LEFT JOIN users admin ON admin.id = actions.id_admin
            WHERE actions.id_user = :user_id
            ORDER BY actions.created_at DESC
            LIMIT " . max(1, $limit) . "
        ");
        $this->db->bind(':user_id', $userId);

        return $this->db->fetchAll();
    }
}
