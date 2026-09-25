<?php

use App\Services\LoginService;
use App\Repositories\OphytrackDriverPayoutRepository;
use App\Repositories\CarrierPackageRepository;
use App\Services\UserWorkspaceContextService;
use App\Utils\TemplateResponse;
use App\Utils\Router;

$router = new Router();

$router->get(function (): string {
    $user = LoginService::getSession();
    $zone=new DateTimeZone('America/Sao_Paulo');$today=new DateTimeImmutable('today',$zone);$period=(string)($_GET['period']??'today');if(!in_array($period,['today','yesterday','current_fortnight','previous_fortnight'],true))$period='today';
    $day=(int)$today->format('j');$currentStart=$day<=15?$today->modify('first day of this month'):$today->setDate((int)$today->format('Y'),(int)$today->format('m'),16);$currentEnd=$day<=15?$today->setDate((int)$today->format('Y'),(int)$today->format('m'),15):$today->modify('last day of this month');if($day>15){$previousStart=$today->modify('first day of this month');$previousEnd=$today->setDate((int)$today->format('Y'),(int)$today->format('m'),15);}else{$previousMonth=$today->modify('first day of previous month');$previousStart=$previousMonth->setDate((int)$previousMonth->format('Y'),(int)$previousMonth->format('m'),16);$previousEnd=$previousMonth->modify('last day of this month');}$periods=['today'=>[$today,$today],'yesterday'=>[$today->modify('-1 day'),$today->modify('-1 day')],'current_fortnight'=>[$currentStart,$currentEnd],'previous_fortnight'=>[$previousStart,$previousEnd]];[$from,$to]=$periods[$period];
    $services=['amazon'=>'Amazon','avulso'=>'Avulso (remesa)','mercado_flex'=>'Mercado Envíos Flex','mercado_flex_turbo'=>'Mercado Envíos Flex Turbo','shopee'=>'Shopee','shopee_turbo'=>'Shopee Turbo','tracken_web'=>'Tracken web','vapt_magalu'=>'Vapt Magalu','vtex'=>'Vtex'];$selected=array_values(array_intersect(array_keys($services),(array)($_GET['services']??[])));$normalize=static function($row):string{$value=strtolower(trim((string)($row->order_source??'')).' '.trim((string)($row->payer_name??'')));if(str_contains($value,'amazon'))return'amazon';if(str_contains($value,'mercado')&&str_contains($value,'turbo'))return'mercado_flex_turbo';if(str_contains($value,'mercado'))return'mercado_flex';if((str_contains($value,'shopee')||str_contains($value,'shoppe'))&&str_contains($value,'turbo'))return'shopee_turbo';if(str_contains($value,'shopee')||str_contains($value,'shoppe'))return'shopee';if(str_contains($value,'magalu')||str_contains($value,'vapt'))return'vapt_magalu';if(str_contains($value,'vtex'))return'vtex';if(str_contains($value,'ophyra')||str_contains($value,'tracken'))return'tracken_web';return'avulso';};
    $payouts=(new OphytrackDriverPayoutRepository())->earningsForDriver((int)$user->getId(),$from->format('Y-m-d'),$to->format('Y-m-d'));foreach($payouts as $payout)$payout->service_key=$normalize($payout);if($selected)$payouts=array_values(array_filter($payouts,static fn($p)=>in_array($p->service_key,$selected,true)));$deliveryTotal=array_reduce($payouts,static fn($sum,$p)=>$sum+(float)$p->amount_brl,0.0);$credits=array_reduce($payouts,static fn($sum,$p)=>$sum+(in_array((string)$p->status,['PENDING','PROOF_SUBMITTED','ACCEPTED'],true)?(float)$p->amount_brl:0),0.0);$debits=array_reduce($payouts,static fn($sum,$p)=>$sum+((string)$p->status==='REJECTED'?(float)$p->amount_brl:0),0.0);
    $context=(new UserWorkspaceContextService())->getTeamContext($user);$owner=(int)($context['selectedOwnerId']??$user->getOwner());$collections=(new CarrierPackageRepository())->getCollectionHistory($owner,(int)$user->getId(),$from->format('Y-m-d'),$to->format('Y-m-d'));$collectionTotal=count($collections)*(float)($_ENV['OPHYTRACK_DEFAULT_COLLECTION_PAYOUT_BRL']??0);
    return TemplateResponse::render(__DIR__.'/index.twig',['driverName'=>trim($user->getName().' '.$user->getLastname()),'driverCode'=>$user->getId(),'services'=>$services,'selectedServices'=>$selected,'period'=>$period,'periods'=>$periods,'from'=>$from,'to'=>$to,'deliveryCount'=>count($payouts),'deliveryTotal'=>$deliveryTotal,'collectionCount'=>count($collections),'collectionTotal'=>$collectionTotal,'credits'=>$credits+$collectionTotal,'debits'=>$debits,'receivable'=>$credits+$collectionTotal-$debits,'queriedAt'=>new DateTimeImmutable('now',$zone)]);
});

$router->run();
