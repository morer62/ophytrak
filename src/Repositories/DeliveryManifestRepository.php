<?php

namespace App\Repositories;

class DeliveryManifestRepository extends StoreRepository
{
    public function __construct(){ $this->table='store_delivery_manifests';$this->db=new Connection(); }

    public function isReady():bool
    {
        try{$this->db->query("SELECT 1 FROM {$this->table} LIMIT 1");$this->db->fetchOne();return true;}catch(\Throwable $e){return false;}
    }

    public function getOrCreateDraft(int $carrierOwner,int $driverUser):?object
    {
        if(!$this->isReady())return null;
        $this->db->query("SELECT * FROM {$this->table} WHERE carrier_owner_id=:owner AND delivery_user_id=:driver AND status='DRAFT' ORDER BY id DESC LIMIT 1");$this->db->bind(':owner',$carrierOwner);$this->db->bind(':driver',$driverUser);$draft=$this->db->fetchOne();if($draft)return$draft;
        $code='MNF-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));
        $this->db->query("INSERT INTO {$this->table}(carrier_owner_id,delivery_user_id,manifest_code,status,created_by_user_id,created_at,updated_at) VALUES(:owner,:driver,:code,'DRAFT',:actor,NOW(),NOW())");$this->db->bind(':owner',$carrierOwner);$this->db->bind(':driver',$driverUser);$this->db->bind(':code',$code);$this->db->bind(':actor',$driverUser);$this->db->execute();$id=(int)$this->db->lastId();
        $this->db->query("SELECT * FROM {$this->table} WHERE id=:id");$this->db->bind(':id',$id);return$this->db->fetchOne()?:null;
    }

    public function addPackage(int $carrierOwner,int $driverUser,int $packageId):array
    {
        $draft=$this->getOrCreateDraft($carrierOwner,$driverUser);if(!$draft)return[false,'The manifest structure is not installed.',null];
        $this->db->query("SELECT id,custody_status FROM store_packages WHERE id=:package AND current_custodian_owner_id=:owner AND custody_status IN ('RECEIVED_AT_HUB','SORTED_AT_HUB') LIMIT 1");$this->db->bind(':package',$packageId);$this->db->bind(':owner',$carrierOwner);$package=$this->db->fetchOne();if(!$package)return[false,'This package is not ready to enter a delivery manifest.',null];
        $this->db->query("SELECT i.id,m.manifest_code,m.status FROM store_delivery_manifest_items i JOIN {$this->table} m ON m.id=i.id_manifest WHERE i.id_store_package=:package AND i.status='PENDING' AND m.status IN ('DRAFT','GENERATED','IN_PROGRESS') LIMIT 1");$this->db->bind(':package',$packageId);$existing=$this->db->fetchOne();if($existing)return[false,'This package already belongs to manifest '.$existing->manifest_code.'.',$draft];
        $this->db->query("SELECT COALESCE(MAX(stop_sequence),0)+1 AS next_stop FROM store_delivery_manifest_items WHERE id_manifest=:manifest");$this->db->bind(':manifest',(int)$draft->id);$sequence=(int)($this->db->fetchOne()->next_stop??1);
        $this->db->query("INSERT INTO store_delivery_manifest_items(id_manifest,id_store_package,stop_sequence,status,created_at,updated_at) VALUES(:manifest,:package,:sequence,'PENDING',NOW(),NOW())");$this->db->bind(':manifest',(int)$draft->id);$this->db->bind(':package',$packageId);$this->db->bind(':sequence',$sequence);$this->db->execute();
        $this->db->query("UPDATE {$this->table} SET package_count=(SELECT COUNT(*) FROM store_delivery_manifest_items WHERE id_manifest=:manifest_count AND status='PENDING'),updated_at=NOW() WHERE id=:manifest");$this->db->bind(':manifest_count',(int)$draft->id);$this->db->bind(':manifest',(int)$draft->id);$this->db->execute();
        $this->db->query("UPDATE store_packages SET current_custodian_user_id=:driver,custody_status='SORTED_AT_HUB',current_status='SORTED_AT_HUB',current_location_label='Added to delivery manifest',last_event_at=NOW(),updated_at=NOW() WHERE id=:package AND current_custodian_owner_id=:owner");$this->db->bind(':driver',$driverUser);$this->db->bind(':package',$packageId);$this->db->bind(':owner',$carrierOwner);$this->db->execute();
        return[true,'Package added to manifest '.$draft->manifest_code.'.',$draft];
    }

    public function getForDriver(int $carrierOwner,int $driverUser):array
    {
        if(!$this->isReady())return[];$this->db->query("SELECT m.*,SUM(i.status='PENDING') pending_count,SUM(i.status='DELIVERED') delivered_count,SUM(i.status IN ('FAILED','RETURNED')) incident_count FROM {$this->table} m LEFT JOIN store_delivery_manifest_items i ON i.id_manifest=m.id WHERE m.carrier_owner_id=:owner AND m.delivery_user_id=:driver GROUP BY m.id ORDER BY FIELD(m.status,'IN_PROGRESS','GENERATED','DRAFT','COMPLETED','CANCELLED'),m.created_at DESC");$this->db->bind(':owner',$carrierOwner);$this->db->bind(':driver',$driverUser);return$this->db->fetchAll();
    }

    public function getForCarrier(int $carrierOwner):array
    {
        if(!$this->isReady())return[];$this->db->query("SELECT m.*,u.name driver_name,u.lastname driver_lastname,SUM(i.status='PENDING') pending_count,SUM(i.status='DELIVERED') delivered_count,SUM(i.status IN ('FAILED','RETURNED')) incident_count FROM {$this->table} m LEFT JOIN users u ON u.id=m.delivery_user_id LEFT JOIN store_delivery_manifest_items i ON i.id_manifest=m.id WHERE m.carrier_owner_id=:owner GROUP BY m.id ORDER BY FIELD(m.status,'IN_PROGRESS','GENERATED','DRAFT','COMPLETED','CANCELLED'),m.created_at DESC");$this->db->bind(':owner',$carrierOwner);return$this->db->fetchAll();
    }

    public function getItems(int $manifestId,int $carrierOwner,int $driverUser=0):array
    {
        if(!$this->isReady())return[];$driverSql=$driverUser>0?' AND m.delivery_user_id=:driver':'';$this->db->query("SELECT i.*,p.package_code,p.id_store_order,p.custody_status,o.guest_name,o.guest_email,o.guest_phone,o.shipping_address_1,o.shipping_address_2,o.shipping_city,o.shipping_state,o.shipping_zip,o.shipping_country,o.shipping_instructions,o.public_token FROM store_delivery_manifest_items i JOIN {$this->table} m ON m.id=i.id_manifest JOIN store_packages p ON p.id=i.id_store_package JOIN store_orders o ON o.id=p.id_store_order AND o.id_owner=p.id_owner WHERE i.id_manifest=:manifest AND m.carrier_owner_id=:owner {$driverSql} ORDER BY i.stop_sequence,i.id");$this->db->bind(':manifest',$manifestId);$this->db->bind(':owner',$carrierOwner);if($driverUser>0)$this->db->bind(':driver',$driverUser);return$this->db->fetchAll();
    }

    public function generate(int $manifestId,int $carrierOwner,int $driverUser):array
    {
        $driverSql=$driverUser>0?' AND delivery_user_id=:driver':'';$this->db->query("SELECT * FROM {$this->table} WHERE id=:id AND carrier_owner_id=:owner {$driverSql} AND status='DRAFT' LIMIT 1");$this->db->bind(':id',$manifestId);$this->db->bind(':owner',$carrierOwner);if($driverUser>0)$this->db->bind(':driver',$driverUser);$manifest=$this->db->fetchOne();if(!$manifest)return[false,'Draft manifest not found.'];$assignedDriver=(int)$manifest->delivery_user_id;
        $items=$this->getItems($manifestId,$carrierOwner,$driverUser);if(!$items)return[false,'Scan at least one package before generating the manifest.'];
        usort($items,static fn($a,$b)=>strcmp(preg_replace('/\D/','',(string)$a->shipping_zip).strtolower((string)$a->shipping_address_1),preg_replace('/\D/','',(string)$b->shipping_zip).strtolower((string)$b->shipping_address_1)));
        foreach($items as $index=>$item){$this->db->query("UPDATE store_delivery_manifest_items SET stop_sequence=:sequence,updated_at=NOW() WHERE id=:id");$this->db->bind(':sequence',$index+1);$this->db->bind(':id',(int)$item->id);$this->db->execute();}
        $this->db->query("UPDATE {$this->table} SET status='GENERATED',package_count=:count,generated_at=NOW(),updated_at=NOW() WHERE id=:id");$this->db->bind(':count',count($items));$this->db->bind(':id',$manifestId);$this->db->execute();
        $this->db->query("UPDATE store_packages p JOIN store_delivery_manifest_items i ON i.id_store_package=p.id SET p.custody_status='OUT_FOR_DELIVERY',p.current_status='OUT_FOR_DELIVERY',p.current_custodian_user_id=:driver,p.current_location_label='Loaded on generated delivery manifest',p.last_event_at=NOW(),p.updated_at=NOW() WHERE i.id_manifest=:manifest AND i.status='PENDING'");$this->db->bind(':driver',$assignedDriver);$this->db->bind(':manifest',$manifestId);$this->db->execute();
        foreach($items as $item)(new StoreOrdersRepository())->updateStatus((int)$item->id_store_order,StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY);
        return[true,'Manifest generated with '.count($items).' packages.'];
    }

    public function recordOutcome(int $packageId,string $outcome,string $failureCode=''):void
    {
        if(!$this->isReady())return;$status=$outcome==='delivered'?'DELIVERED':'FAILED';$this->db->query("SELECT i.id_manifest FROM store_delivery_manifest_items i JOIN {$this->table} m ON m.id=i.id_manifest WHERE i.id_store_package=:package AND i.status='PENDING' AND m.status IN ('GENERATED','IN_PROGRESS') LIMIT 1");$this->db->bind(':package',$packageId);$item=$this->db->fetchOne();if(!$item)return;$manifestId=(int)$item->id_manifest;$this->db->query("UPDATE store_delivery_manifest_items SET status=:status,failure_code=:failure,completed_at=NOW(),updated_at=NOW() WHERE id_store_package=:package AND id_manifest=:manifest AND status='PENDING'");$this->db->bind(':status',$status);$this->db->bind(':failure',$failureCode?:null);$this->db->bind(':package',$packageId);$this->db->bind(':manifest',$manifestId);$this->db->execute();$this->db->query("UPDATE {$this->table} SET status=IF((SELECT COUNT(*) FROM store_delivery_manifest_items WHERE id_manifest=:manifest_pending AND status='PENDING')=0,'COMPLETED','IN_PROGRESS'),started_at=COALESCE(started_at,NOW()),completed_at=IF((SELECT COUNT(*) FROM store_delivery_manifest_items WHERE id_manifest=:manifest_complete AND status='PENDING')=0,NOW(),NULL),updated_at=NOW() WHERE id=:manifest");$this->db->bind(':manifest_pending',$manifestId);$this->db->bind(':manifest_complete',$manifestId);$this->db->bind(':manifest',$manifestId);$this->db->execute();
    }
}
