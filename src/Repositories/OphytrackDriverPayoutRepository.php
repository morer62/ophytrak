<?php
namespace App\Repositories;

class OphytrackDriverPayoutRepository extends StoreRepository
{
    public function __construct(){ $this->table='ophytrack_driver_payouts';$this->db=new Connection(); }
    public function isReady():bool{$this->db->query("SELECT COUNT(*) total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table");$this->db->bind(':table',$this->table);return(int)($this->db->fetchOne()->total??0)===1;}
    public function recordDelivered(int $packageId,int $deliveryUserId,?int $carrierOwner=null):bool
    {
        if(!$this->isReady()||$deliveryUserId<=0)return false;
        $this->db->query("SELECT p.*,e.id event_id FROM store_packages p JOIN store_package_events e ON e.id_store_package=p.id AND e.status_to='DELIVERED' WHERE p.id=:package AND e.id_user=:user ORDER BY e.id DESC LIMIT 1");$this->db->bind(':package',$packageId);$this->db->bind(':user',$deliveryUserId);$p=$this->db->fetchOne();if(!$p)return false;
        $payer=$carrierOwner&&$carrierOwner>0?$carrierOwner:(int)$p->id_owner;$amount=(float)($_ENV['OPHYTRACK_DEFAULT_DRIVER_PAYOUT_BRL']??0);if($amount<=0)return true;
        $this->db->query("INSERT INTO {$this->table} (id_store_package,id_store_order,id_store_package_event,seller_owner_id,carrier_owner_id,payer_owner_id,delivery_user_id,amount_brl,currency,status,created_at,updated_at) VALUES (:package,:order,:event,:seller,:carrier,:payer,:driver,:amount,'BRL','PENDING',NOW(),NOW()) ON DUPLICATE KEY UPDATE delivery_user_id=VALUES(delivery_user_id),payer_owner_id=VALUES(payer_owner_id),carrier_owner_id=VALUES(carrier_owner_id),amount_brl=IF(status='ACCEPTED',amount_brl,VALUES(amount_brl)),updated_at=NOW()");foreach([':package'=>$packageId,':order'=>(int)$p->id_store_order,':event'=>(int)$p->event_id,':seller'=>(int)$p->id_owner,':carrier'=>$carrierOwner,':payer'=>$payer,':driver'=>$deliveryUserId,':amount'=>$amount] as $k=>$v)$this->db->bind($k,$v);$this->db->execute();return true;
    }
    public function forOwner(int $owner):array{if(!$this->isReady())return[];$this->db->query("SELECT p.*,u.name driver_name,u.lastname driver_lastname,sp.package_code FROM {$this->table} p JOIN users u ON u.id=p.delivery_user_id JOIN store_packages sp ON sp.id=p.id_store_package WHERE p.payer_owner_id=:owner ORDER BY p.created_at DESC");$this->db->bind(':owner',$owner);return$this->db->fetchAll();}
    public function forDriver(int $user):array{if(!$this->isReady())return[];$this->db->query("SELECT p.*,sp.package_code,ip.company_name payer_name FROM {$this->table} p JOIN store_packages sp ON sp.id=p.id_store_package LEFT JOIN institution_profile ip ON ip.id_owner=p.payer_owner_id WHERE p.delivery_user_id=:user ORDER BY p.created_at DESC");$this->db->bind(':user',$user);return$this->db->fetchAll();}
    public function earningsForDriver(int $user,string $from,string $to):array
    {
        if(!$this->isReady())return[];
        $this->db->query("SELECT p.*,sp.package_code,COALESCE(NULLIF(o.order_source,''),'OPHYRA') order_source,ip.company_name payer_name,CONVERT_TZ(p.created_at,'+00:00','-03:00') earned_at FROM {$this->table} p JOIN store_packages sp ON sp.id=p.id_store_package JOIN store_orders o ON o.id=p.id_store_order LEFT JOIN institution_profile ip ON ip.id_owner=p.payer_owner_id WHERE p.delivery_user_id=:user AND DATE(CONVERT_TZ(p.created_at,'+00:00','-03:00')) BETWEEN :from_date AND :to_date AND p.status<>'VOID' ORDER BY p.created_at DESC");
        $this->db->bind(':user',$user);$this->db->bind(':from_date',$from);$this->db->bind(':to_date',$to);return$this->db->fetchAll();
    }
    public function submitProof(int $id,int $payer,int $actor,string $url,string $reference):bool{$this->db->query("UPDATE {$this->table} SET status='PROOF_SUBMITTED',payment_proof_url=:url,payment_reference=:reference,payment_method='manual_proof',proof_submitted_by_user_id=:actor,proof_submitted_at=NOW(),updated_at=NOW() WHERE id=:id AND payer_owner_id=:payer AND status IN ('PENDING','REJECTED')");foreach([':url'=>$url,':reference'=>$reference?:null,':actor'=>$actor,':id'=>$id,':payer'=>$payer] as $k=>$v)$this->db->bind($k,$v);$this->db->execute();return$this->db->rowCount()>0;}
    public function review(int $id,int $driver,bool $accept,string $notes=''):bool{$this->db->query("UPDATE {$this->table} SET status=:status,reviewed_by_user_id=:driver,review_notes=:notes,reviewed_at=NOW(),updated_at=NOW() WHERE id=:id AND delivery_user_id=:driver_scope AND status='PROOF_SUBMITTED'");$this->db->bind(':status',$accept?'ACCEPTED':'REJECTED');$this->db->bind(':driver',$driver);$this->db->bind(':notes',$notes?:null);$this->db->bind(':id',$id);$this->db->bind(':driver_scope',$driver);$this->db->execute();return$this->db->rowCount()>0;}
}
