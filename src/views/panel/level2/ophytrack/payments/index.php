<?php
use App\Repositories\OphytrackPackageBillingRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Services\LoginService;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$session=LoginService::getSession();$ownerId=(int)($session->getOwner()?:$session->getId());$repo=new OphytrackPackageBillingRepository();
if($_SERVER['REQUEST_METHOD']==='POST'){$id=(int)($_POST['charge_id']??0);$action=(string)($_POST['action']??'submit_proof');try{if($action==='approve_proof'){$ok=$repo->approveProof($id,$ownerId);MessageUtil::setMessage($ok?'Payment confirmed.':'This payment cannot be confirmed.');}else{$reference=trim((string)($_POST['payment_reference']??''));$url='';if(FileUtils::hasFile($_FILES,'payment_proof')){$f=$_FILES['payment_proof'];if(!in_array((string)($f['type']??''),['image/jpeg','image/png','image/webp','application/pdf'],true)||(int)($f['size']??0)>8*1024*1024)throw new RuntimeException('Use JPG, PNG, WEBP or PDF up to 8 MB.');$url=FileUtils::saveFile($f,'ophytrack-carrier-payments');}if($url==='')throw new RuntimeException('Attach the payment receipt.');$ok=$repo->submitProof($id,$ownerId,$url,$reference);MessageUtil::setMessage($ok?'Payment proof submitted for carrier review.':'This charge cannot be updated.');}}catch(Throwable $e){MessageUtil::setMessage($e->getMessage());}LocationUtils::reload();}
$charges=$repo->forOwner($ownerId);$providers=new PaymentProvidersRepository();foreach($charges as $charge){$charge->payee_has_online_payment=$charge->payee_owner_id?(bool)$providers->getActiveProviderForOwner((int)$charge->payee_owner_id):false;}
echo TemplateResponse::render(__DIR__.'/index.twig',['dbReady'=>$repo->isReady(),'charges'=>$charges,'billingSummary'=>$repo->summary($ownerId),'ownerId'=>$ownerId,'packageFee'=>(float)($_ENV['OPHYTRACK_PACKAGE_FEE_BRL']??0),'cutoffDay'=>(int)($_ENV['OPHYTRACK_USAGE_CUTOFF_DAY']??15),'paymentDay'=>(int)($_ENV['OPHYTRACK_USAGE_PAYMENT_DAY']??1)]);
