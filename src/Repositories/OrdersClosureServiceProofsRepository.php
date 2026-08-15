<?php

namespace App\Repositories;

class OrdersClosureServiceProofsRepository extends BaseRepository
{
    public function __construct()
    {
        $this->db = new Connection();
        $this->table = "orders_closure_service_proofs";
    }

    public function getAllByOrder(int $orderId): array
    {
        return $this->getAllBy(["id_order" => $orderId]);
    }

    public function getByService(int $orderId, int $serviceId): array
    {
        return $this->getAllBy([
            "id_order" => $orderId,
            "id_service" => $serviceId
        ]);
    }
}
