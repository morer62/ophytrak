<?php

namespace App\Repositories;

class OrdersClosurePaymentReceiptsRepository extends BaseRepository
{
    public function __construct()
    {
        $this->db = new Connection();
        $this->table = "orders_closure_payment_receipts";
    }

    public function getAllByOrder(int $orderId): array
    {
        return $this->getAllBy(["id_order" => $orderId]);
    }
}
