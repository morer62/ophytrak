<?php

namespace App\Repositories;

class ClientAutoChargeConsentsRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'client_auto_charge_consents';
        $this->db = new Connection();
    }

    public function getActiveForMethod(int $businessId, int $clientId, int $methodId): ?object
    {
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE id_user_business = :business
              AND (id_client = :client OR user_id = :client)
              AND saved_payment_method_id = :method
              AND status = 'ACTIVE'
              AND revoked_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $this->db->bind(':business', $businessId);
        $this->db->bind(':client', $clientId);
        $this->db->bind(':method', $methodId);
        $row = $this->db->fetchOne();
        return $row ?: null;
    }

    public function getActiveForClientProvider(int $businessId, int $clientId, string $provider): ?object
    {
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE id_user_business = :business
              AND (id_client = :client OR user_id = :client)
              AND payment_provider = :provider
              AND status = 'ACTIVE'
              AND revoked_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $this->db->bind(':business', $businessId);
        $this->db->bind(':client', $clientId);
        $this->db->bind(':provider', $provider);
        $row = $this->db->fetchOne();
        return $row ?: null;
    }
}
