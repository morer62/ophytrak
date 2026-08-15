<?php

namespace App\Repositories;
 

class UserCardsRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = "user_cards";
        $this->db = new Connection();
    }

    public function getByUserId(int $userId): array
    {
        $this->db->query("SELECT * FROM {$this->table} WHERE id_user = :id_user");
        $this->db->bind(":id_user", $userId);
        return $this->db->fetchAll();
    }

    public function getMainCardByUserId(int $userId): ?object
    {
        $this->db->query("SELECT * FROM {$this->table} WHERE id_user = :id_user AND main_card = 'yes' LIMIT 1");
        $this->db->bind(":id_user", $userId);
        $result = $this->db->fetchOne();
        // fetchOne() puede devolver false cuando no hay resultados, convertir a null
        return ($result === false) ? null : $result;
    }

    public function deleteCard(int $cardId): void
    {
        $this->db->query("DELETE FROM user_cards WHERE id = :id");
        $this->db->bind(":id", $cardId);
        $this->db->execute();
    }

    public function deleteCardForUser(int $userId, int $cardId): void
    {
        $this->db->query("DELETE FROM {$this->table} WHERE id = :id AND id_user = :id_user");
        $this->db->bind(":id", $cardId);
        $this->db->bind(":id_user", $userId);
        $this->db->execute();
    }

    public function ensureMainCard(int $userId): void
    {
        $this->db->query("SELECT id FROM {$this->table} WHERE id_user = :id_user AND main_card = 'yes' LIMIT 1");
        $this->db->bind(":id_user", $userId);
        $mainCard = $this->db->fetchOne();

        if ($mainCard !== false) {
            return;
        }

        $this->db->query("SELECT id FROM {$this->table} WHERE id_user = :id_user ORDER BY id DESC LIMIT 1");
        $this->db->bind(":id_user", $userId);
        $fallbackCard = $this->db->fetchOne();

        if ($fallbackCard === false) {
            return;
        }

        $this->setMainCard($userId, (int) $fallbackCard->id);
    }

    public function countCards(int $userId): int
    {
        $this->db->query("SELECT * FROM user_cards WHERE id_user = :id_user");
        $this->db->bind(":id_user", $userId);
        return $this->db->count();
    }

    public function setMainCard(int $userId, int $cardId): void
    {
        $this->db->query("UPDATE {$this->table} SET main_card = 'no' WHERE id_user = :id_user");
        $this->db->bind(":id_user", $userId);
        $this->db->execute();

        $this->db->query("UPDATE {$this->table} SET main_card = 'yes' WHERE id = :id AND id_user = :id_user");
        $this->db->bind(":id", $cardId);
        $this->db->bind(":id_user", $userId);
        $this->db->execute();
    }
}
