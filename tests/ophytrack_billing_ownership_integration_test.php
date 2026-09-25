<?php

require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/../')->load();

use App\Repositories\Connection;

$db=new Connection();$failures=[];
$db->query("SELECT dp.id,dp.seller_owner_id,dp.carrier_owner_id,dp.payer_owner_id,sp.logistics_mode FROM ophytrack_driver_payouts dp JOIN store_packages sp ON sp.id=dp.id_store_package ORDER BY dp.id DESC LIMIT 500");
foreach($db->fetchAll() as $row){
    if($row->carrier_owner_id===null&&(int)$row->payer_owner_id!==(int)$row->seller_owner_id)$failures[]='Internal driver payout #'.$row->id.' is not payable by its seller.';
    if($row->carrier_owner_id!==null&&((int)$row->carrier_owner_id<=0||(int)$row->payer_owner_id!==(int)$row->carrier_owner_id))$failures[]='External driver payout #'.$row->id.' is not payable by its carrier.';
}
$db->query("SELECT c.id,c.charge_kind,c.status,c.seller_owner_id,c.carrier_owner_id,c.payer_owner_id,c.payee_owner_id,sp.logistics_mode FROM ophytrack_package_charges c JOIN store_packages sp ON sp.id=c.id_store_package WHERE c.status<>'VOID' ORDER BY c.id DESC LIMIT 1000");
foreach($db->fetchAll() as $row){
    if($row->charge_kind==='PLATFORM_USAGE'&&(int)$row->payer_owner_id!==(int)$row->seller_owner_id)$failures[]='Platform charge #'.$row->id.' is not payable by its seller.';
    if($row->charge_kind==='CARRIER_SERVICE'&&((int)$row->carrier_owner_id<=0||(int)$row->payer_owner_id!==(int)$row->seller_owner_id||(int)$row->payee_owner_id!==(int)$row->carrier_owner_id))$failures[]='Carrier charge #'.$row->id.' does not match external custody.';
}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "OPHYTRACK billing ownership integration OK\n";
