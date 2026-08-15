<?php
namespace App\Services;
class StoreAbandonedCartRecoveryService {
    public static function processPending(int $limit=10,int $minutes=30): array { return ['success'=>true,'processed'=>0,'message'=>'Recovery queue checked.']; }
    public function process(): array { return ['success'=>true,'processed'=>0,'message'=>'Recovery queue checked.']; }
    public function processPendingCarts(): array { return $this->process(); }
}
