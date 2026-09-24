<?php

namespace App\Repositories;

class CarrierPackageRepository extends StoreRepository
{
    public function __construct(){ $this->table='store_packages';$this->db=new Connection(); }

    public function isCarrier(int $ownerId): bool
    {
        $this->db->query("SELECT id FROM institution_profile WHERE id_owner=:owner AND organization_type='CARRIER' LIMIT 1");
        $this->db->bind(':owner',$ownerId); return (bool)$this->db->fetchOne();
    }

    public function findBySecureToken(string $token): ?object
    {
        $this->db->query("SELECT p.*,o.public_token,o.guest_name,o.guest_email,o.guest_phone,o.shipping_address_1,o.shipping_address_2,o.shipping_city,o.shipping_state,o.shipping_zip,o.shipping_country,o.status AS order_status,ip.company_name AS seller_name FROM store_packages p JOIN store_orders o ON o.id=p.id_store_order AND o.id_owner=p.id_owner LEFT JOIN institution_profile ip ON ip.id_owner=p.id_owner WHERE o.public_token=:token LIMIT 1");
        $this->db->bind(':token',trim($token)); return $this->db->fetchOne() ?: null;
    }

    public function findByCode(string $code): ?object
    {
        $this->db->query("SELECT p.*,o.guest_name,o.guest_email,o.status AS order_status,ip.company_name AS seller_name FROM store_packages p JOIN store_orders o ON o.id=p.id_store_order AND o.id_owner=p.id_owner LEFT JOIN institution_profile ip ON ip.id_owner=p.id_owner WHERE UPPER(p.package_code)=UPPER(:code) LIMIT 1");
        $this->db->bind(':code',trim($code)); return $this->db->fetchOne() ?: null;
    }

    public function requestManualCustody(int $carrierOwner,int $userId,string $code,string $notes=''): array
    {
        return [false,'Custody can only be accepted by scanning the secure package QR.'];
        /* Legacy manual flow intentionally disabled for OPHYTRACK billing safety.
        if(!$this->isCarrier($carrierOwner)) return [false,'The selected workspace is not a carrier organization.'];
        $package=$this->findByCode($code); if(!$package)return[false,'Package not found. Check the complete label identifier.'];
        if((int)$package->id_owner===$carrierOwner)return[false,'This package already belongs to your own business.'];
        $this->db->query("SELECT id FROM store_package_custody_requests WHERE id_store_package=:package AND carrier_owner_id=:carrier AND status='PENDING' LIMIT 1");$this->db->bind(':package',(int)$package->id);$this->db->bind(':carrier',$carrierOwner);
        if($this->db->fetchOne())return[true,'A custody request is already awaiting seller approval.'];
        $this->db->query("INSERT INTO store_package_custody_requests (id_store_package,seller_owner_id,carrier_owner_id,requested_by_user_id,status,request_method,request_notes,created_at,updated_at) VALUES (:package,:seller,:carrier,:user,'PENDING','MANUAL_CODE',:notes,NOW(),NOW())");
        $this->db->bind(':package',(int)$package->id);$this->db->bind(':seller',(int)$package->id_owner);$this->db->bind(':carrier',$carrierOwner);$this->db->bind(':user',$userId);$this->db->bind(':notes',trim($notes)?:null);$this->db->execute();
        $this->event($package,$carrierOwner,$userId,'CUSTODY_REQUESTED','WITH_SELLER','Manual code request awaiting seller approval',$notes);
        $this->notify((int)$package->id_owner,'Carrier custody request for '.$package->package_code,'panel/planner-hub/store/orders/home');
        return[true,'Custody request sent to the seller.']; */
    }

    public function claimByQr(int $carrierOwner,int $userId,string $token,?string $photoUrl=null,?float $lat=null,?float $lng=null): array
    {
        if(!$this->isCarrier($carrierOwner))return[false,'The selected workspace is not a carrier organization.',null];
        $package=$this->findBySecureToken($token);if(!$package)return[false,'The QR is invalid or no longer identifies a package.',null];
        if(!(new CarrierRelationshipRepository())->isAssociated((int)$package->id_owner,$carrierOwner))return[false,'This carrier is not authorized by the seller for this package.',null];
        if(in_array((string)$package->custody_status,['DELIVERED','CLOSED'],true))return[false,'This package is already closed or delivered.',null];
        if((int)($package->current_custodian_owner_id??0)===$carrierOwner){
            if((string)$package->custody_status==='PICKED_UP'){
                $this->db->query("UPDATE store_packages SET current_custodian_user_id=:user,custody_status='RECEIVED_AT_HUB',current_status='RECEIVED_AT_HUB',current_location_label='Received at carrier warehouse by QR',last_event_at=NOW(),updated_at=NOW() WHERE id=:id AND current_custodian_owner_id=:carrier");$this->db->bind(':user',$userId);$this->db->bind(':id',(int)$package->id);$this->db->bind(':carrier',$carrierOwner);$this->db->execute();$this->event($package,$carrierOwner,$userId,'QR_WAREHOUSE_RECEIVED','RECEIVED_AT_HUB','Received at carrier warehouse by QR','',['photo_url'=>$photoUrl,'latitude'=>$lat,'longitude'=>$lng]);return[true,'Package received physically at the carrier warehouse.',$package];
            }
            if(in_array((string)$package->custody_status,['RECEIVED_AT_HUB','SORTED_AT_HUB'],true)){
                $this->db->query("UPDATE store_packages SET current_custodian_user_id=:user,custody_status='OUT_FOR_DELIVERY',current_status='OUT_FOR_DELIVERY',current_location_label='Accepted by delivery driver through QR',last_event_at=NOW(),updated_at=NOW() WHERE id=:id AND current_custodian_owner_id=:carrier");$this->db->bind(':user',$userId);$this->db->bind(':id',(int)$package->id);$this->db->bind(':carrier',$carrierOwner);$this->db->execute();$this->assign((int)$package->id,$carrierOwner,$userId,$userId,'DELIVERY');$this->event($package,$carrierOwner,$userId,'QR_DRIVER_ACCEPTED','OUT_FOR_DELIVERY','Accepted by delivery driver through QR','',['photo_url'=>$photoUrl,'latitude'=>$lat,'longitude'=>$lng]);(new StoreOrdersRepository())->updateStatus((int)$package->id_store_order,StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY);return[true,'Package assigned to the delivery driver and moved out for delivery.',$package];
            }
            return[false,'This QR has already been processed for the current package stage.',null];
        }
        if((int)($package->current_custodian_owner_id??0)>0 && (int)$package->current_custodian_owner_id!==(int)$package->id_owner && (int)$package->current_custodian_owner_id!==$carrierOwner)return[false,'This package is under another carrier custody.',null];
        $this->db->query("UPDATE store_packages SET current_custodian_owner_id=:carrier,current_custodian_user_id=:user,custody_status='PICKED_UP',logistics_mode='EXTERNAL_CARRIER',current_location_label='With carrier pickup team',custody_started_at=COALESCE(custody_started_at,NOW()),last_event_at=NOW(),updated_at=NOW() WHERE id=:id");
        $this->db->bind(':carrier',$carrierOwner);$this->db->bind(':user',$userId);$this->db->bind(':id',(int)$package->id);$this->db->execute();
        $this->assign((int)$package->id,$carrierOwner,$userId,$userId,'PICKUP');
        (new OphytrackPackageBillingRepository())->recordForCustody($package,$carrierOwner);
        $meta=['photo_url'=>$photoUrl,'latitude'=>$lat,'longitude'=>$lng,'method'=>'SECURE_QR'];
        $this->event($package,$carrierOwner,$userId,'QR_CUSTODY_ACCEPTED','PICKED_UP','Carrier accepted custody through secure QR scan','', $meta);
        $this->notify((int)$package->id_owner,'Carrier picked up '.$package->package_code.' by secure QR scan','panel/planner-hub/store/orders/home?package='.urlencode((string)$package->package_code));
        return[true,'Package added to your carrier workspace.',$package];
    }

    public function pendingForSeller(int $sellerOwner): array
    {
        $this->db->query("SELECT r.*,p.package_code,o.id AS order_id,ip.company_name AS carrier_name,u.name,u.lastname,u.email FROM store_package_custody_requests r JOIN store_packages p ON p.id=r.id_store_package JOIN store_orders o ON o.id=p.id_store_order LEFT JOIN institution_profile ip ON ip.id_owner=r.carrier_owner_id LEFT JOIN users u ON u.id=r.requested_by_user_id WHERE r.seller_owner_id=:owner AND r.status='PENDING' ORDER BY r.created_at DESC");$this->db->bind(':owner',$sellerOwner);return $this->db->fetchAll();
    }

    public function getCarrierOrganizations(): array
    {
        $this->db->query("SELECT id_owner,company_name,city,state,country FROM institution_profile WHERE organization_type='CARRIER' ORDER BY company_name");return $this->db->fetchAll();
    }

    public function sellerAssignCarrier(int $packageId,int $sellerOwner,int $carrierOwner,int $userId): array
    {
        return[false,'Carrier assignment is completed only when an authorized carrier scans the secure QR.'];
        /* Direct carrier selection is intentionally disabled to prevent charging the wrong organization.
        if(!$this->isCarrier($carrierOwner))return[false,'Select a valid carrier organization.'];
        if(!(new CarrierRelationshipRepository())->isAssociated($sellerOwner,$carrierOwner))return[false,'Associate this carrier with the seller before assigning packages.'];
        $this->db->query("SELECT * FROM store_packages WHERE id=:id AND id_owner=:seller LIMIT 1");$this->db->bind(':id',$packageId);$this->db->bind(':seller',$sellerOwner);$p=$this->db->fetchOne();if(!$p)return[false,'Package not found in this seller workspace.'];
        if(in_array((string)$p->custody_status,['DELIVERED','CLOSED'],true))return[false,'A delivered or closed package cannot be assigned.'];
        $this->db->query("UPDATE store_packages SET current_custodian_owner_id=:carrier,current_custodian_user_id=NULL,custody_status='PICKUP_ASSIGNED',logistics_mode='EXTERNAL_CARRIER',current_location_label='Awaiting carrier pickup',custody_started_at=NOW(),last_event_at=NOW(),updated_at=NOW() WHERE id=:id");$this->db->bind(':carrier',$carrierOwner);$this->db->bind(':id',$packageId);$this->db->execute();
        $this->event($p,$carrierOwner,$userId,'CARRIER_ASSIGNED_BY_SELLER','PICKUP_ASSIGNED','Awaiting carrier pickup','Seller pre-authorized this carrier.',['carrier_owner_id'=>$carrierOwner]);return[true,'Carrier assigned. Its employee can now scan the secure QR and collect the package.']; */
    }

    public function hasExternalCarrierForOrder(int $sellerOwner, int $orderId): bool
    {
        $this->db->query("SELECT id FROM store_packages WHERE id_owner=:seller AND id_store_order=:order AND logistics_mode='EXTERNAL_CARRIER' AND current_custodian_owner_id IS NOT NULL AND current_custodian_owner_id<>:seller_compare LIMIT 1");
        $this->db->bind(':seller',$sellerOwner);$this->db->bind(':order',$orderId);$this->db->bind(':seller_compare',$sellerOwner);
        return (bool)$this->db->fetchOne();
    }

    public function sellerUseOwnTeam(int $packageId,int $sellerOwner,int $userId): bool
    {
        $this->db->query("SELECT * FROM store_packages WHERE id=:id AND id_owner=:seller LIMIT 1");$this->db->bind(':id',$packageId);$this->db->bind(':seller',$sellerOwner);$p=$this->db->fetchOne();if(!$p)return false;
        $this->db->query("UPDATE store_packages SET current_custodian_owner_id=:seller,current_custodian_user_id=NULL,logistics_mode='SELF_DELIVERY',current_location_label=IF(custody_status='PICKUP_ASSIGNED','With seller',current_location_label),custody_status=IF(custody_status='PICKUP_ASSIGNED','WITH_SELLER',custody_status),updated_at=NOW() WHERE id=:id");$this->db->bind(':seller',$sellerOwner);$this->db->bind(':id',$packageId);$this->db->execute();
        $this->db->query("UPDATE store_package_carrier_assignments SET status='CANCELLED',updated_at=NOW() WHERE id_store_package=:package AND status NOT IN ('COMPLETED','CANCELLED')");$this->db->bind(':package',$packageId);$this->db->execute();
        (new OphytrackPackageBillingRepository())->recordForOwnTeam($p);
        return true;
    }

    public function sellerAwaitCarrierQr(int $packageId,int $sellerOwner): bool
    {
        $this->db->query("UPDATE store_packages SET current_custodian_owner_id=:seller,current_custodian_user_id=NULL,logistics_mode='INTERNAL',custody_status='WITH_SELLER',current_location_label='Awaiting authorized carrier QR scan',updated_at=NOW() WHERE id=:id AND id_owner=:seller_scope AND custody_status NOT IN ('DELIVERED','CLOSED')");
        $this->db->bind(':seller',$sellerOwner);$this->db->bind(':id',$packageId);$this->db->bind(':seller_scope',$sellerOwner);$this->db->execute();return$this->db->rowCount()>0;
    }

    public function decideRequest(int $requestId,int $sellerOwner,int $decider,bool $approve): array
    {
        $this->db->query("SELECT r.*,p.package_code,p.id_owner,p.id_store_order,p.custody_status FROM store_package_custody_requests r JOIN store_packages p ON p.id=r.id_store_package WHERE r.id=:id AND r.seller_owner_id=:seller AND r.status='PENDING' LIMIT 1");$this->db->bind(':id',$requestId);$this->db->bind(':seller',$sellerOwner);$r=$this->db->fetchOne();if(!$r)return[false,'Request not found or already resolved.'];
        $status=$approve?'APPROVED':'REJECTED';$this->db->query("UPDATE store_package_custody_requests SET status=:status,decided_by_user_id=:user,decided_at=NOW(),updated_at=NOW() WHERE id=:id");$this->db->bind(':status',$status);$this->db->bind(':user',$decider);$this->db->bind(':id',$requestId);$this->db->execute();
        if($approve){$this->db->query("UPDATE store_packages SET current_custodian_owner_id=:carrier,current_custodian_user_id=:user,custody_status='PICKUP_ASSIGNED',logistics_mode='EXTERNAL_CARRIER',current_location_label='Awaiting carrier pickup',custody_started_at=NOW(),last_event_at=NOW(),updated_at=NOW() WHERE id=:id");$this->db->bind(':carrier',(int)$r->carrier_owner_id);$this->db->bind(':user',(int)$r->requested_by_user_id);$this->db->bind(':id',(int)$r->id_store_package);$this->db->execute();$this->assign((int)$r->id_store_package,(int)$r->carrier_owner_id,(int)$r->requested_by_user_id,$decider,'PICKUP');}
        $this->event($r,(int)$r->carrier_owner_id,$decider,$approve?'CUSTODY_APPROVED':'CUSTODY_REJECTED',$approve?'PICKUP_ASSIGNED':'WITH_SELLER',$approve?'Seller approved carrier custody':'Seller rejected carrier custody');$this->notify((int)$r->requested_by_user_id,($approve?'Custody approved for ':'Custody rejected for ').$r->package_code,'panel/planner-hub/team/driver-mode');return[true,$approve?'Carrier custody approved.':'Carrier request rejected.'];
    }

    public function getForCarrier(int $carrierOwner,?int $userId=null): array
    {
        $userSql=$userId?' AND (p.current_custodian_user_id=:user OR a.assigned_user_id=:user)':'';
        $this->db->query("SELECT DISTINCT p.*,o.guest_name,o.guest_email,o.guest_phone,o.shipping_address_1,o.shipping_address_2,o.shipping_city,o.shipping_state,o.shipping_zip,o.shipping_country,o.status AS order_status,ip.company_name AS seller_name,a.assignment_role,a.status AS assignment_status FROM store_packages p JOIN store_orders o ON o.id=p.id_store_order AND o.id_owner=p.id_owner LEFT JOIN institution_profile ip ON ip.id_owner=p.id_owner LEFT JOIN store_package_carrier_assignments a ON a.id_store_package=p.id AND a.carrier_owner_id=:carrier AND a.status<>'CANCELLED' WHERE p.current_custodian_owner_id=:carrier {$userSql} ORDER BY p.last_event_at DESC");$this->db->bind(':carrier',$carrierOwner);if($userId)$this->db->bind(':user',$userId);return$this->db->fetchAll();
    }

    public function assignEmployee(int $packageId,int $carrierOwner,int $employeeId,int $assignedBy,string $role): array
    {
        $role=strtoupper($role);if(!in_array($role,['PICKUP','HUB_RECEIVING','SORTING','DELIVERY','SUPERVISOR'],true))return[false,'Invalid carrier role.'];
        if(!(new StoreUserRolesRepository())->userBelongsToOwner($carrierOwner,$employeeId))return[false,'That employee does not belong to this carrier.'];
        $this->db->query("SELECT * FROM store_packages WHERE id=:id AND current_custodian_owner_id=:carrier LIMIT 1");$this->db->bind(':id',$packageId);$this->db->bind(':carrier',$carrierOwner);$p=$this->db->fetchOne();if(!$p)return[false,'Package is not under this carrier custody.'];
        $this->db->query("UPDATE store_package_carrier_assignments SET status='CANCELLED',updated_at=NOW() WHERE id_store_package=:package AND carrier_owner_id=:carrier AND status<>'COMPLETED'");$this->db->bind(':package',$packageId);$this->db->bind(':carrier',$carrierOwner);$this->db->execute();$this->assign($packageId,$carrierOwner,$employeeId,$assignedBy,$role);
        $this->db->query("UPDATE store_packages SET current_custodian_user_id=:user,updated_at=NOW() WHERE id=:id");$this->db->bind(':user',$employeeId);$this->db->bind(':id',$packageId);$this->db->execute();$this->event($p,$carrierOwner,$assignedBy,'CARRIER_EMPLOYEE_ASSIGNED',(string)$p->custody_status,(string)$p->current_location_label,'Assigned employee #'.$employeeId.' as '.$role,['assigned_user_id'=>$employeeId,'role'=>$role]);return[true,'Carrier employee assigned.'];
    }

    public function advance(int $packageId,int $carrierOwner,int $userId,string $action,string $notes='',?string $photo=null,array $meta=[]): array
    {
        $map=['picked_up'=>['PICKED_UP','With carrier pickup team'],'received_hub'=>['RECEIVED_AT_HUB','At carrier warehouse'],'sorted_hub'=>['SORTED_AT_HUB','Sorted by delivery region'],'out_for_delivery'=>['OUT_FOR_DELIVERY','With carrier delivery employee'],'customer_absent'=>['CUSTOMER_ABSENT','Delivery incident: customer absent'],'customer_rejected'=>['CUSTOMER_REJECTED','Delivery incident: customer rejected package'],'delivery_cancelled'=>['DELIVERY_CANCELLED','Delivery incident: cancelled'],'delivered'=>['DELIVERED','Delivered to customer']];if(!isset($map[$action]))return[false,'Invalid carrier action.'];
        $this->db->query("SELECT * FROM store_packages WHERE id=:id AND current_custodian_owner_id=:carrier LIMIT 1");$this->db->bind(':id',$packageId);$this->db->bind(':carrier',$carrierOwner);$p=$this->db->fetchOne();if(!$p)return[false,'Package is not under this carrier custody.'];
        $allowed=['PICKUP_ASSIGNED'=>['picked_up'],'WITH_SELLER'=>['picked_up'],'PICKED_UP'=>['received_hub'],'RECEIVED_AT_HUB'=>['sorted_hub'],'SORTED_AT_HUB'=>['out_for_delivery'],'CUSTOMER_ABSENT'=>['received_hub','out_for_delivery'],'CUSTOMER_REJECTED'=>['received_hub'],'DELIVERY_CANCELLED'=>['received_hub'],'OUT_FOR_DELIVERY'=>['delivered','customer_absent','customer_rejected','delivery_cancelled']];
        if(!in_array($action,$allowed[(string)$p->custody_status]??[],true))return[false,'This step is not allowed from the current package stage.'];
        [$status,$location]=$map[$action];if(!$photo)return[false,'A package or delivery evidence photo is required.'];
        $this->db->query("UPDATE store_packages SET current_custodian_user_id=:user,custody_status=:status,current_status=:status,current_location_label=:location,last_event_at=NOW(),updated_at=NOW() WHERE id=:id");$this->db->bind(':user',$userId);$this->db->bind(':status',$status);$this->db->bind(':location',$location);$this->db->bind(':id',$packageId);$this->db->execute();
        $meta['photo_url']=$photo;$this->event($p,$carrierOwner,$userId,strtoupper($action),$status,$location,$notes,$meta);
        if($action==='out_for_delivery')(new StoreOrdersRepository())->updateStatus((int)$p->id_store_order,StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY);
        if($action==='customer_absent')(new StoreOrdersRepository())->updateStatus((int)$p->id_store_order,StoreOrdersRepository::STATUS_DELIVERY_ATTEMPTED);
        if(in_array($action,['customer_rejected','delivery_cancelled'],true))(new StoreOrdersRepository())->updateStatus((int)$p->id_store_order,StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS);
        if($action==='delivered')(new StoreOrdersRepository())->updateStatus((int)$p->id_store_order,StoreOrdersRepository::STATUS_DELIVERED);
        return[true,'Package workflow updated.'];
    }

    private function assign(int $package,int $carrier,int $user,int $by,string $role): void {$this->db->query("INSERT INTO store_package_carrier_assignments (id_store_package,carrier_owner_id,assigned_user_id,assigned_by_user_id,assignment_role,status,assigned_at,created_at,updated_at) VALUES (:package,:carrier,:user,:by,:role,'IN_PROGRESS',NOW(),NOW(),NOW())");$this->db->bind(':package',$package);$this->db->bind(':carrier',$carrier);$this->db->bind(':user',$user);$this->db->bind(':by',$by);$this->db->bind(':role',$role);$this->db->execute();}
    private function notify(int $userId,string $message,string $link): void {try{$this->db->query("INSERT INTO notifications (id_user,mensaje,link,leido,timestamp) VALUES (:user,:message,:link,'NO',NOW())");$this->db->bind(':user',$userId);$this->db->bind(':message',mb_substr($message,0,120));$this->db->bind(':link',$link);$this->db->execute();}catch(\Throwable $e){error_log('Carrier notification failed: '.$e->getMessage());}}
    private function event(object $p,int $carrier,int $user,string $type,string $to,string $location,string $notes='',array $meta=[]): void {$this->db->query("INSERT INTO store_package_events (id_owner,id_store_package,id_store_order,id_user,event_type,status_from,status_to,location_label,notes,metadata_json,created_at) VALUES (:owner,:package,:order,:user,:type,:from,:to,:location,:notes,:meta,NOW())");$this->db->bind(':owner',(int)($p->id_owner??0));$this->db->bind(':package',(int)($p->id_store_package??$p->id));$this->db->bind(':order',(int)$p->id_store_order);$this->db->bind(':user',$user);$this->db->bind(':type',$type);$this->db->bind(':from',(string)($p->custody_status??''));$this->db->bind(':to',$to);$this->db->bind(':location',$location);$this->db->bind(':notes',$notes?:null);$this->db->bind(':meta',$meta?json_encode($meta,JSON_UNESCAPED_UNICODE):null);$this->db->execute();}
}
