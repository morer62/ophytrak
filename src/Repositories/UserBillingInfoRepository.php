<?php

namespace App\Repositories; 

class UserBillingInfoRepository extends BaseRepository
{
    public function __construct()
    {
        $this->table = "user_billing_info";
        $this->db = new Connection();
    }

    public function getByUserId(int $userId): ?array
    {
        $this->db->query("SELECT * FROM user_billing_info WHERE user_id = :user_id LIMIT 1");
        $this->db->bind(":user_id", $userId);
        $result = $this->db->fetchOne();
        return $result ? (array) $result : null;
    }
    public function upsert(int $userId, array $data): void
    {
        $existing = $this->getByUserId($userId);
    
        if ($existing) {
            $this->db->query("UPDATE user_billing_info 
                SET billing_address_1 = :address, 
                    billing_city = :city, 
                    billing_state = :state, 
                    billing_zip = :zip 
                WHERE user_id = :user_id");
        } else {
            $this->db->query("INSERT INTO user_billing_info 
                (billing_address_1, billing_city, billing_state, billing_zip, user_id) 
                VALUES (:address, :city, :state, :zip, :user_id)");
        }
    
        $this->db->bind(":address", $data['billing_address_1']);
        $this->db->bind(":city", $data['billing_city']);
        $this->db->bind(":state", $data['billing_state']);
        $this->db->bind(":zip", $data['billing_zip']);
        $this->db->bind(":user_id", $userId);
        $this->db->execute();
    }
}
