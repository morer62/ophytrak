<?php

namespace App\Repositories;

class OphytrackDriverProfileRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'ophytrack_driver_profiles';
        $this->db = new Connection();
    }

    public function findByUser(int $userId): ?object
    {
        $this->db->query('SELECT * FROM ophytrack_driver_profiles WHERE id_user = :id_user LIMIT 1');
        $this->db->bind(':id_user', $userId);
        return $this->db->fetchOne() ?: null;
    }

    public function saveForUser(int $userId, array $data): bool
    {
        $allowed = [
            'profile_photo_url', 'bank_account_name', 'document_type',
            'document_number', 'bank_name', 'bank_agency',
            'bank_account_type', 'bank_account_number', 'pix_key',
        ];
        $clean = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string)$data[$field]);
                $clean[$field] = $value === '' ? null : $value;
            }
        }

        $existing = $this->findByUser($userId);
        if ($existing) {
            $clean['updated_at'] = date('Y-m-d H:i:s');
            return $this->update($clean, ['id_user' => $userId]);
        }

        return $this->add(array_merge($clean, [
            'id_user' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
    }
}
