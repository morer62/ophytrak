<?php

namespace App\Repositories;

class DailyRecommendationRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'daily_recommendations';
        $this->db = new Connection();
    }

    public function getByOwnerAndDate(int $idOwner, string $recommendationDate, string $locale = 'es', string $type = 'daily_insight'): ?object
    {
        $this->db->query("
            SELECT *
            FROM `{$this->table}`
            WHERE `id_owner` = :id_owner
              AND `recommendation_date` = :recommendation_date
              AND `locale` = :locale
              AND `recommendation_type` = :recommendation_type
            LIMIT 1
        ");
        $this->db->bind(':id_owner', $idOwner);
        $this->db->bind(':recommendation_date', $recommendationDate);
        $this->db->bind(':locale', $locale);
        $this->db->bind(':recommendation_type', $type);
        $result = $this->db->fetchOne();
        return ($result && $result !== false) ? $result : null;
    }

    /** @return object[] */
    public function getByOwner(int $idOwner, int $limit = 30, ?string $locale = null, ?string $type = null): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `id_owner` = :id_owner";
        $params = [':id_owner' => $idOwner];
        if ($locale !== null && $locale !== '') {
            $sql .= " AND `locale` = :locale";
            $params[':locale'] = $locale;
        }
        if ($type !== null && $type !== '') {
            $sql .= " AND `recommendation_type` = :recommendation_type";
            $params[':recommendation_type'] = $type;
        }
        $sql .= " ORDER BY recommendation_date DESC LIMIT " . (int) $limit;
        $this->db->query($sql);
        foreach ($params as $k => $v) {
            $this->db->bind($k, $v);
        }
        $rows = $this->db->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function addWithExplicitOwner(array $data): bool
    {
        try {
            $keys = array_keys($data);
            $columns = array_map(fn($k) => "`{$k}`", $keys);
            $placeholders = array_map(fn($k) => ":{$k}", $keys);
            $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->query($sql);
            foreach ($data as $key => $value) {
                $bindValue = ($key === 'metrics_snapshot' && is_array($value)) ? json_encode($value) : $value;
                $this->db->bind(":{$key}", $bindValue);
            }
            $this->db->execute();
            return true;
        } catch (\PDOException $e) {
            error_log("DailyRecommendationRepository::addWithExplicitOwner: " . $e->getMessage());
            return false;
        }
    }

    public function refreshById(int $id, array $data): bool
    {
        try {
            $this->db->query("
                UPDATE `{$this->table}`
                SET metrics_snapshot = :metrics_snapshot,
                    recommendation_text = :recommendation_text,
                    status = :status,
                    module_slug = :module_slug,
                    action_label = :action_label,
                    action_url = :action_url,
                    source_type = 'internal_data'
                WHERE id = :id
                LIMIT 1
            ");
            $this->db->bind(':metrics_snapshot', json_encode($data['metrics_snapshot'] ?? []));
            $this->db->bind(':recommendation_text', $data['recommendation_text'] ?? '');
            $this->db->bind(':status', $data['status'] ?? 'healthy');
            $this->db->bind(':module_slug', $data['module_slug'] ?? null);
            $this->db->bind(':action_label', $data['action_label'] ?? null);
            $this->db->bind(':action_url', $data['action_url'] ?? null);
            $this->db->bind(':id', $id);
            $this->db->execute();
            return true;
        } catch (\PDOException $e) {
            error_log("DailyRecommendationRepository::refreshById: " . $e->getMessage());
            return false;
        }
    }
}
