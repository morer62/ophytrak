<?php
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
use App\Repositories\CarrierPackageRepository;
use App\Services\CarrierLabelReference;

if(!preg_match('/host=(127\.0\.0\.1|localhost)(;|$)/',$_ENV['DATABASE_URL']??''))throw new RuntimeException('Local database only.');
$pdo=new PDO($_ENV['DATABASE_URL']);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$ids=[];$checks=0;$tag=bin2hex(random_bytes(6));
$assert=static function(bool $ok,string $message)use(&$checks){if(!$ok)throw new RuntimeException($message);$checks++;};
$user=static function(string $name,int $level,?int $owner=null)use($pdo,$tag,&$ids):int{
    $q=$pdo->prepare('INSERT INTO users(name,lastname,email,password,level,id_owner)VALUES(?,?,?,?,?,?)');
    $q->execute(['Collection QA',$name,'qa.collection.'.$tag.'.'.$name.'@example.test',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$level,$owner]);
    $id=(int)$pdo->lastInsertId();$ids[]=$id;return $id;
};
try{
    $carrier=$user('carrier',2);$driver=$user('driver',4,$carrier);$foreign=$user('foreign',2);$outsider=$user('outsider',4,$foreign);
    $q=$pdo->prepare("INSERT INTO institution_profile(id_owner,company_name,organization_type,payment_method_accepted)VALUES(?,?,'CARRIER','')");$q->execute([$carrier,'Collection QA '.$tag]);
    $repo=new CarrierPackageRepository();
    $labels=['BR265885228923I','BR268644790429G','BR267585120065C','BR2662880087196'];
    $proof=['handed_by'=>'Synthetic QA','signature'=>'synthetic-local-test'];
    foreach($labels as $label){
        $assert(CarrierLabelReference::parse($label)['provider']==='SHOPEE_BR','Shopee reference parser');
        [$ok,$message,$package]=$repo->collectScannedLabel($carrier,$driver,$label,$proof);$assert($ok,$message);
        $assert((int)$package->current_custodian_user_id===$driver,'Driver pickup assignment');
        $assert($package->custody_status==='PICKED_UP','Collection stage');
        $order=$pdo->query('SELECT * FROM store_orders WHERE id='.(int)$package->id_store_order)->fetch(PDO::FETCH_OBJ);
        $assert($order->external_shipment_id===$label && $order->payment_status==='PENDING' && (float)$order->total===0.0,'Logistics intake is not a paid sale');
        [$again,,$duplicate]=$repo->collectScannedLabel($carrier,$driver,$label,$proof);
        $assert($again && $duplicate->id===$package->id && $duplicate->custody_status==='PICKED_UP','Retry must not advance to warehouse');
    }
    $ml=json_encode(['id'=>'998877665544','sender_id'=>123456,'hash_code'=>'synthetic-not-a-real-label']);
    [$ok,$message,$market]=$repo->collectScannedLabel($carrier,$driver,$ml,$proof);$assert($ok,$message);
    $assert($repo->findForCarrierScan($carrier,$ml)->id===$market->id,'External label resolves for later stages');
    [$warehouse,$message]=$repo->claimByQr($carrier,$driver,$ml);$assert($warehouse,$message);
    $assert($repo->findForCarrierScan($carrier,$ml)->custody_status==='RECEIVED_AT_HUB','Same marketplace label reaches warehouse');
    $manifests=new App\Repositories\DeliveryManifestRepository();
    [$added,$message,$manifest]=$manifests->addPackage($carrier,$driver,(int)$market->id);$assert($added,$message);
    $q=$pdo->prepare("INSERT INTO store_package_carrier_assignments(id_store_package,carrier_owner_id,assigned_user_id,assigned_by_user_id,assignment_role,status,assigned_at)VALUES(?,?,?,?,'DELIVERY','IN_PROGRESS',NOW())");$q->execute([(int)$market->id,$carrier,$driver,$driver]);
    $assert($repo->findForCarrierScan($carrier,$ml)->id===$market->id,'Historical pickup/delivery assignments must not make one package ambiguous');
    [$generated]=$manifests->generate((int)$manifest->id,$carrier,$driver);$assert(!$generated,'An intake without a real recipient/address cannot become a delivery route');
    [$again]=$repo->collectScannedLabel($carrier,$driver,$ml,$proof);$assert(!$again,'Collection must not advance an existing warehouse package');
    $assert($repo->countCollectedToday($carrier,$driver)===5,'Five unique receipts counted');
    $assert(count($repo->getCollectionHistory($carrier,$driver))===5,'Five auditable collection events');
    $event=$pdo->query("SELECT metadata_json FROM store_package_events WHERE id_owner={$carrier} AND event_type='QR_CUSTODY_ACCEPTED' LIMIT 1")->fetchColumn();
    $assert(json_decode($event,true)['handover']['handed_by']==='Synthetic QA','Handover evidence retained');
    [$ok]=$repo->collectScannedLabel($carrier,$outsider,'BR123456789012Q',$proof);$assert(!$ok,'Other-company driver rejected');
    foreach(['{"id":123}', '{"id":[],"sender_id":1}', 'https://evil.example/anything?token=abc',str_repeat('X',4097)] as $bad)
        $assert(CarrierLabelReference::parse($bad)===null,'Invalid label rejected');
    $foreignToken=bin2hex(random_bytes(24));
    $q=$pdo->prepare("INSERT INTO store_orders(id_owner,guest_name,guest_email,public_token,total)VALUES(?,'Foreign QA','',?,0)");$q->execute([$foreign,$foreignToken]);$foreignOrder=(int)$pdo->lastInsertId();
    $q=$pdo->prepare("INSERT INTO store_packages(id_owner,id_store_order,package_code)VALUES(?,?,?)");$q->execute([$foreign,$foreignOrder,'QA-FOREIGN-'.$tag]);
    [$ok]=$repo->collectScannedLabel($carrier,$driver,$foreignToken,$proof);$assert(!$ok,'Secure token does not bypass unrelated seller boundary');
    $assert($pdo->query('SELECT custody_status FROM store_packages WHERE id_store_order='.$foreignOrder)->fetchColumn()==='WITH_SELLER','Foreign custody untouched');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM ophytrack_package_charges WHERE seller_owner_id={$carrier}")->fetchColumn()===0,'No automatic financial charge for minimal intake');
    echo "Carrier collection labels integration OK: {$checks} assertions\n";
}finally{
    // Only uniquely-created local fixture owners; never pre-existing business data.
    if($ids){$scope=implode(',',array_map('intval',$ids));
        $pdo->exec("DELETE FROM notifications WHERE id_user IN ({$scope})");
        $pdo->exec("DELETE i FROM store_delivery_manifest_items i JOIN store_delivery_manifests m ON m.id=i.id_manifest WHERE m.carrier_owner_id IN ({$scope})");
        $pdo->exec("DELETE FROM store_delivery_manifests WHERE carrier_owner_id IN ({$scope})");
        $pdo->exec("DELETE a FROM store_package_carrier_assignments a JOIN store_packages p ON p.id=a.id_store_package WHERE p.id_owner IN ({$scope})");
        $pdo->exec("DELETE e FROM store_package_events e JOIN store_packages p ON p.id=e.id_store_package WHERE p.id_owner IN ({$scope})");
        $pdo->exec("DELETE FROM store_packages WHERE id_owner IN ({$scope})");
        $pdo->exec("DELETE FROM store_orders WHERE id_owner IN ({$scope})");
        $pdo->exec("DELETE FROM institution_profile WHERE id_owner IN ({$scope})");
        $pdo->exec("DELETE FROM users WHERE id IN ({$scope})");
    }
}
