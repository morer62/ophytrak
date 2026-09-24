<?php
use App\Repositories\OphytrackDriverPayoutRepository;
use App\Repositories\OphytrackPackageBillingRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Repositories\UserCardsRepository;
use App\Services\LoginService;
use App\Services\StripeService;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$session=LoginService::getSession();$userId=(int)$session->getId();$ownerId=(int)($session->getOwner()?:$userId);$isDriver=(int)$session->getLevel()===4;$repo=new OphytrackPackageBillingRepository();$payoutRepo=new OphytrackDriverPayoutRepository();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'submit_proof');
    try{
        if($isDriver){
            if($action!=='driver_review')throw new RuntimeException('This action is not available to delivery users.');
            $ok=$payoutRepo->review((int)($_POST['payout_id']??0),$userId,(string)($_POST['decision']??'')==='accept',trim((string)($_POST['review_notes']??'')));
            MessageUtil::setMessage($ok?'Payment review saved.':'This payout cannot be reviewed.');
        }elseif($action==='pay_platform_stripe'){
            $rows=$repo->pendingPlatformForOwner($ownerId);$ids=array_map(static fn($r)=>(int)$r->id,$rows);$amount=array_sum(array_map(static fn($r)=>(float)$r->amount_brl,$rows));
            if($amount<0.50)throw new RuntimeException('Stripe processes the accumulated OPHYTRACK balance once it reaches R$ 0,50.');
            $card=(new UserCardsRepository())->getMainCardByUserId($ownerId);if(!$card||empty($card->token))throw new RuntimeException('Add a saved payment card first.');
            $intent=(new StripeService())->createChargeV1((string)$card->token,$amount,'brl',['product'=>'ophytrack_package_usage','owner_id'=>(string)$ownerId,'charge_ids'=>implode(',',$ids)]);
            if(!$intent||!$repo->markPlatformPaid($ids,$ownerId,$userId,(string)$intent))throw new RuntimeException('Stripe did not confirm the OPHYTRACK balance payment.');
            MessageUtil::setMessage('OPHYTRACK balance paid successfully in BRL.');
        }elseif($action==='approve_proof'){
            $ok=$repo->approveProof((int)($_POST['charge_id']??0),$ownerId);MessageUtil::setMessage($ok?'Payment confirmed.':'This payment cannot be confirmed.');
        }else{
            $fileField=$action==='driver_submit_proof'?'driver_payment_proof':'payment_proof';$url='';if(FileUtils::hasFile($_FILES,$fileField)){$f=$_FILES[$fileField];if(!in_array((string)($f['type']??''),['image/jpeg','image/png','image/webp','application/pdf'],true)||(int)($f['size']??0)>8*1024*1024)throw new RuntimeException('Use JPG, PNG, WEBP or PDF up to 8 MB.');$url=FileUtils::saveFile($f,$action==='driver_submit_proof'?'ophytrack-driver-payments':'ophytrack-carrier-payments');}if($url==='')throw new RuntimeException('Attach the payment receipt.');$reference=trim((string)($_POST['payment_reference']??''));
            $ok=$action==='driver_submit_proof'?$payoutRepo->submitProof((int)($_POST['payout_id']??0),$ownerId,$userId,$url,$reference):$repo->submitProof((int)($_POST['charge_id']??0),$ownerId,$url,$reference);MessageUtil::setMessage($ok?'Payment proof submitted for review.':'This payment cannot be updated.');
        }
    }catch(Throwable $e){MessageUtil::setMessage($e->getMessage());}LocationUtils::reload();
}
$charges=$isDriver?[]:$repo->forOwner($ownerId);$providers=new PaymentProvidersRepository();foreach($charges as $charge){$charge->payee_has_online_payment=$charge->payee_owner_id?(bool)$providers->getActiveProviderForOwner((int)$charge->payee_owner_id):false;}
$driverPayouts=$isDriver?$payoutRepo->forDriver($userId):$payoutRepo->forOwner($ownerId);
echo TemplateResponse::render(__DIR__.'/index.twig',['dbReady'=>$repo->isReady()&&$payoutRepo->isReady(),'charges'=>$charges,'driverPayouts'=>$driverPayouts,'isDriver'=>$isDriver,'billingSummary'=>$isDriver?['platform_due'=>0,'carrier_due'=>0,'receivable'=>0,'overdue'=>0]:$repo->summary($ownerId),'ownerId'=>$ownerId,'userId'=>$userId,'packageFee'=>(float)($_ENV['OPHYTRACK_PACKAGE_FEE_BRL']??0),'driverPayout'=>(float)($_ENV['OPHYTRACK_DEFAULT_DRIVER_PAYOUT_BRL']??0),'cutoffDay'=>(int)($_ENV['OPHYTRACK_USAGE_CUTOFF_DAY']??15),'paymentDay'=>(int)($_ENV['OPHYTRACK_USAGE_PAYMENT_DAY']??1)]);
