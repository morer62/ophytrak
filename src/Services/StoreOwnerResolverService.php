<?php
namespace App\Services;
use App\Repositories\Connection;
class StoreOwnerResolverService {
    public static function resolve(?int $requestedOwner=null): int {
        if($requestedOwner&&$requestedOwner>0)return $requestedOwner;
        foreach(['STORE_OWNER_ID','DEFAULT_OWNER_ID'] as $key){$value=(int)($_ENV[$key]??0);if($value>0)return $value;}
        $db=new Connection();$db->query("SELECT id FROM users WHERE level IN ('1','2') ORDER BY CASE WHEN level='1' THEN 0 ELSE 1 END,id LIMIT 1");$row=$db->fetchOne();return (int)($row->id??0);
    }
}
