<?php

namespace App\Repositories;

class UserAgentTokensRepository extends BaseRepository
{
    public const DEFAULT_TOKEN_LIMIT = 50000;

    public function __construct()
    {
        $this->table = 'user_agent_tokens';
        $this->db = new Connection();
    }

    public function getByUserId(int $userId): ?object
    {
        $this->db->query("SELECT * FROM `{$this->table}` WHERE `id_user` = :id_user LIMIT 1");
        $this->db->bind(':id_user', $userId);
        $result = $this->db->fetchOne();
        return ($result && $result !== false) ? $result : null;
    }

    /**
     * Obtiene o crea el registro de tokens para el usuario. Si reset_at es pasado, reinicia tokens_used.
     */
    public function getOrCreate(int $userId): object
    {
        $row = $this->getByUserId($userId);
        if ($row !== null) {
            $this->maybeResetUsage($userId, $row);
            return $this->getByUserId($userId);
        }
        $resetAt = date('Y-m-t');
        $this->db->query("INSERT INTO `{$this->table}` (id_user, token_limit, tokens_used, reset_at) VALUES (:id_user, :token_limit, 0, :reset_at)");
        $this->db->bind(':id_user', $userId);
        $this->db->bind(':token_limit', self::DEFAULT_TOKEN_LIMIT);
        $this->db->bind(':reset_at', $resetAt);
        $this->db->execute();
        $row = $this->getByUserId($userId);
        return $row;
    }

    private function maybeResetUsage(int $userId, object $row): void
    {
        $resetAt = $row->reset_at ?? null;
        if ($resetAt === null) {
            return;
        }
        if (strtotime($resetAt) < strtotime('today')) {
            $nextReset = date('Y-m-t');
            $this->db->query("UPDATE `{$this->table}` SET tokens_used = 0, reset_at = :reset_at WHERE id_user = :id_user");
            $this->db->bind(':reset_at', $nextReset);
            $this->db->bind(':id_user', $userId);
            $this->db->execute();
        }
    }

    public function getRemaining(int $userId): int
    {
        $row = $this->getOrCreate($userId);
        $row = $this->getByUserId($userId);
        $limit = (int) ($row->token_limit ?? self::DEFAULT_TOKEN_LIMIT);
        $used = (int) ($row->tokens_used ?? 0);
        return max(0, $limit - $used);
    }

    public function addUsage(int $userId, int $tokens): void
    {
        $this->getOrCreate($userId);
        $this->db->query("UPDATE `{$this->table}` SET tokens_used = tokens_used + :tokens WHERE id_user = :id_user");
        $this->db->bind(':tokens', $tokens);
        $this->db->bind(':id_user', $userId);
        $this->db->execute();
    }

    public function getTokenLimit(int $userId): int
    {
        $row = $this->getOrCreate($userId);
        $row = $this->getByUserId($userId);
        return (int) ($row->token_limit ?? self::DEFAULT_TOKEN_LIMIT);
    }
}
