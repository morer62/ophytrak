<?php

namespace App\Repositories;

class ClientSavedPaymentMethodsRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = 'client_saved_payment_methods';
        $this->db = new Connection();
    }

    public function getActiveForClient(int $businessId, int $clientId): array
    {
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE id_user_business = :business
              AND (id_client = :client OR user_id = :client)
              AND status = 'ACTIVE'
            ORDER BY is_default DESC, id DESC
        ");
        $this->db->bind(':business', $businessId);
        $this->db->bind(':client', $clientId);
        return $this->db->fetchAll();
    }

    public function getActiveForClientAcrossBusinesses(int $clientId, string $email = ''): array
    {
        $email = strtolower(trim($email));
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE status = 'ACTIVE'
              AND (
                    id_client = :client
                 OR user_id = :client
                 OR (:email <> '' AND LOWER(COALESCE(billing_email, '')) = :email)
              )
            ORDER BY is_default DESC, id DESC
        ");
        $this->db->bind(':client', $clientId);
        $this->db->bind(':email', $email);
        return $this->db->fetchAll();
    }

    public function deactivateForClient(int $id, int $clientId, string $email = ''): void
    {
        $email = strtolower(trim($email));
        $this->db->query("
            UPDATE {$this->table}
            SET status = 'INACTIVE', updated_at = :updated_at
            WHERE id = :id
              AND (
                    id_client = :client
                 OR user_id = :client
                 OR (:email <> '' AND LOWER(COALESCE(billing_email, '')) = :email)
              )
        ");
        $this->db->bind(':id', $id);
        $this->db->bind(':client', $clientId);
        $this->db->bind(':email', $email);
        $this->db->bind(':updated_at', date('Y-m-d H:i:s'));
        $this->db->execute();
    }

    public function setDefaultForClient(int $id, int $clientId, string $email = ''): void
    {
        $email = strtolower(trim($email));
        $this->db->query("
            SELECT id_user_business
            FROM {$this->table}
            WHERE id = :id
              AND status = 'ACTIVE'
              AND (
                    id_client = :client
                 OR user_id = :client
                 OR (:email <> '' AND LOWER(COALESCE(billing_email, '')) = :email)
              )
            LIMIT 1
        ");
        $this->db->bind(':id', $id);
        $this->db->bind(':client', $clientId);
        $this->db->bind(':email', $email);
        $row = $this->db->fetchOne();

        if (!$row) {
            return;
        }

        $businessId = (int)$row->id_user_business;
        $this->db->query("
            UPDATE {$this->table}
            SET is_default = 0, updated_at = :updated_at
            WHERE id_user_business = :business
              AND status = 'ACTIVE'
              AND (
                    id_client = :client
                 OR user_id = :client
                 OR (:email <> '' AND LOWER(COALESCE(billing_email, '')) = :email)
              )
        ");
        $this->db->bind(':business', $businessId);
        $this->db->bind(':client', $clientId);
        $this->db->bind(':email', $email);
        $this->db->bind(':updated_at', date('Y-m-d H:i:s'));
        $this->db->execute();

        $this->db->query("
            UPDATE {$this->table}
            SET is_default = 1, updated_at = :updated_at
            WHERE id = :id
        ");
        $this->db->bind(':id', $id);
        $this->db->bind(':updated_at', date('Y-m-d H:i:s'));
        $this->db->execute();
    }

    public function getActiveByIdForBusiness(int $id, int $businessId): ?object
    {
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE id = :id
              AND id_user_business = :business
              AND status = 'ACTIVE'
            LIMIT 1
        ");
        $this->db->bind(':id', $id);
        $this->db->bind(':business', $businessId);
        $row = $this->db->fetchOne();
        return $row ?: null;
    }

    public function findDuplicate(int $businessId, int $clientId, string $provider, ?string $customerId, ?string $methodId): ?object
    {
        $this->db->query("
            SELECT *
            FROM {$this->table}
            WHERE id_user_business = :business
              AND (id_client = :client OR user_id = :client)
              AND payment_provider = :provider
              AND COALESCE(provider_customer_id, '') = COALESCE(:customer, '')
              AND COALESCE(provider_payment_method_id, '') = COALESCE(:method, '')
            LIMIT 1
        ");
        $this->db->bind(':business', $businessId);
        $this->db->bind(':client', $clientId);
        $this->db->bind(':provider', $provider);
        $this->db->bind(':customer', $customerId);
        $this->db->bind(':method', $methodId);
        $row = $this->db->fetchOne();
        return $row ?: null;
    }
}
