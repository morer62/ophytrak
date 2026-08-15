<?php

namespace App\Repositories;

class UserAiUsageRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'user_ai_usage';
        $this->db = new Connection();
    }

    public function getForUserAndPeriod(int $idUser, string $period): ?object
    {
        $this->db->query("SELECT * FROM `{$this->table}` WHERE `id_user` = :id_user AND `period` = :period LIMIT 1");
        $this->db->bind(':id_user', $idUser);
        $this->db->bind(':period', $period);
        $result = $this->db->fetchOne();
        return ($result && $result !== false) ? $result : null;
    }

    public function addTokens(int $idUser, string $period, int $tokens): bool
    {
        $row = $this->getForUserAndPeriod($idUser, $period);
        if ($row) {
            $newUsed = (int) $row->tokens_used + $tokens;
            $this->db->query("UPDATE `{$this->table}` SET `tokens_used` = :tokens_used WHERE `id_user` = :id_user AND `period` = :period");
            $this->db->bind(':tokens_used', $newUsed);
            $this->db->bind(':id_user', $idUser);
            $this->db->bind(':period', $period);
            $this->db->execute();
            return true;
        }
        $this->db->query("INSERT INTO `{$this->table}` (id_user, period, tokens_used) VALUES (:id_user, :period, :tokens_used)");
        $this->db->bind(':id_user', $idUser);
        $this->db->bind(':period', $period);
        $this->db->bind(':tokens_used', $tokens);
        $this->db->execute();
        return true;
    }

    public function getOrCreate(int $idUser, string $period): object
    {
        $row = $this->getForUserAndPeriod($idUser, $period);
        if ($row) {
            return $row;
        }
        $this->addTokens($idUser, $period, 0);
        $row = $this->getForUserAndPeriod($idUser, $period);
        return $row ?? (object) ['tokens_used' => 0, 'period' => $period];
    }
}
