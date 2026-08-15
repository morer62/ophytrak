<?php

namespace App\Repositories;

class BusinessEvaluatorSettingsRepository extends BaseRepository
{
    public const BUSINESS_SIZE_SMALL = 'small';
    public const BUSINESS_SIZE_MEDIUM = 'medium';
    public const BUSINESS_SIZE_LARGE = 'large';

    public function __construct()
    {
        $this->table = 'business_evaluator_settings';
        $this->db = new Connection();
    }

    public function getByOwner(int $idOwner): ?object
    {
        $this->db->query("SELECT * FROM `{$this->table}` WHERE `id_owner` = :id_owner LIMIT 1");
        $this->db->bind(':id_owner', $idOwner);
        $result = $this->db->fetchOne();
        return ($result && $result !== false) ? $result : null;
    }

    /**
     * Inserta o actualiza la configuración del evaluador para un owner.
     * Campos permitidos: business_size, social_media (array→json), location, recommendations_enabled, preferred_language.
     */
    public function upsert(int $idOwner, array $data): bool
    {
        $allowed = ['business_size', 'social_media', 'location', 'recommendations_enabled', 'preferred_language'];
        $data = array_intersect_key($data, array_flip($allowed));
        if (empty($data)) {
            return true;
        }

        $existing = $this->getByOwner($idOwner);
        if ($existing) {
            if (isset($data['social_media']) && is_array($data['social_media'])) {
                $data['social_media'] = json_encode($data['social_media']);
            }
            return $this->update($data, ['id_owner' => $idOwner]);
        }

        $data['id_owner'] = $idOwner;
        if (isset($data['social_media']) && is_array($data['social_media'])) {
            $data['social_media'] = json_encode($data['social_media']);
        }
        return $this->addWithExplicitOwner($data);
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
                $this->db->bind(":{$key}", $value);
            }
            $this->db->execute();
            return true;
        } catch (\PDOException $e) {
            error_log("BusinessEvaluatorSettingsRepository::addWithExplicitOwner: " . $e->getMessage());
            return false;
        }
    }
}
