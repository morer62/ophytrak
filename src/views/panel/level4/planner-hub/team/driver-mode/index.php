<?php

use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\CarrierPackageRepository;
use App\Repositories\DeliveryManifestRepository;
use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Services\UserWorkspaceContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$router = new Router();
ModuleGuardService::requireModule('store_delivery_tracking');

$router->get(function () {
    $user = LoginService::getSession();
    $teamContext = (int)$user->getLevel() === 4
        ? (new UserWorkspaceContextService())->getTeamContext($user)
        : ['selectedOwnerId' => (int)($user->getOwner() ?: $user->getId())];
    $ownerId = (int)($teamContext['selectedOwnerId'] ?? ($user->getOwner() ?: $user->getId()));
    $carrierRepo = new CarrierPackageRepository();
    $manifestRepo = new DeliveryManifestRepository();
    $isCarrierOrganization = $ownerId > 0 && $carrierRepo->isCarrier($ownerId);
    if($isCarrierOrganization && isset($_GET['export_manifest'])){
        $manifestId=max(0,(int)$_GET['export_manifest']);$items=$manifestRepo->getItems($manifestId,$ownerId,(int)$user->getLevel()===2?0:(int)$user->getId());if(!$items){http_response_code(404);exit('Manifest not found.');}
        $spreadsheet=new Spreadsheet();$sheet=$spreadsheet->getActiveSheet();$sheet->setTitle('Relatorio Export');$sheet->fromArray(['Recipient Name','Phone','Email','Address','Address Complement','City','State','Zip','Country','Delivery Instructions'],null,'A1');$row=2;foreach($items as $item){$sheet->fromArray([(string)($item->guest_name?:$item->guest_email),(string)$item->guest_phone,(string)$item->guest_email,(string)$item->shipping_address_1,(string)$item->shipping_address_2,(string)$item->shipping_city,(string)$item->shipping_state,(string)$item->shipping_zip,(string)$item->shipping_country,(string)$item->shipping_instructions],null,'A'.$row++);}$sheet->getStyle('A1:J1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B7A69');foreach(range('A','J')as$column)$sheet->getColumnDimension($column)->setAutoSize(true);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="ophytrack-manifest-'.$manifestId.'.xlsx"');header('Cache-Control: max-age=0');(new Xlsx($spreadsheet))->save('php://output');exit;
    }

    $storeTasks = $ownerId > 0
        ? (new StoreOrderTasksRepository())->getForAssignee($ownerId, (int)$user->getId())
        : [];

    $allDeliveryTasks = array_values(array_filter($storeTasks, static fn($task) => (string)($task->task_type ?? '') === StoreOrderTasksRepository::TYPE_DELIVERY));
    $deliveryTasks = [];
    $incidentTasks = [];
    $completedTasks = [];
    foreach ($allDeliveryTasks as $task) {
        $orderStatus = strtoupper((string)($task->order_status ?? ''));
        $taskStatus = strtoupper((string)($task->status ?? ''));
        if (in_array($orderStatus, ['DELIVERY_ATTEMPTED','RETURNED_TO_BUSINESS','RETURN_REQUESTED','RETURN_APPROVED'], true)) $incidentTasks[] = $task;
        elseif (in_array($orderStatus, ['DELIVERED','COMPLETED','CANCELLED','RETURNED','CLOSED'], true) || in_array($taskStatus, ['COMPLETED','CANCELED'], true)) $completedTasks[] = $task;
        else $deliveryTasks[] = $task;
    }

    // Keep every delivery assignment available to the scanner so it can explain
    // completed/cancelled assignments instead of reporting them as foreign QR codes.
    $assignedPackageTokens = [];
    foreach ($storeTasks as $task) {
        if ((string)($task->task_type ?? '') !== StoreOrderTasksRepository::TYPE_DELIVERY
            || empty($task->public_token)) {
            continue;
        }
        $assignedPackageTokens[(string)$task->public_token] = [
            'taskId' => (int)$task->id,
            'orderId' => (int)$task->id_store_order,
            'taskStatus' => (string)$task->status,
            'orderStatus' => (string)($task->order_status ?? ''),
        ];
    }

    $orderIds = array_map(static fn($task) => (int)$task->id_store_order, $allDeliveryTasks);
    $latestLocations = $orderIds ? (new StoreDeliveryLocationLogsRepository())->getLatestMapByOrders(array_unique($orderIds), $ownerId) : [];

    foreach ($allDeliveryTasks as $task) {
        $task->latest_location = $latestLocations[(int)$task->id_store_order] ?? null;
    }

    $readyToStartCount = 0;
    $onRouteCount = 0;
    foreach ($deliveryTasks as $task) {
        $status = (string)($task->order_status ?? '');
        if (in_array($status, ['READY_FOR_DELIVERY', 'REDELIVERY_SCHEDULED'], true)) {
            $readyToStartCount++;
        }
        if ($status === 'OUT_FOR_DELIVERY') {
            $onRouteCount++;
        }
    }

    $isCarrierOwner=(int)$user->getLevel()===2;$carrierPackages=$isCarrierOrganization?$carrierRepo->getForCarrier($ownerId,$isCarrierOwner?null:(int)$user->getId()):[];$carrierCancelledPackages=$isCarrierOrganization?$carrierRepo->getCancelledReturnsForCarrier($ownerId,$isCarrierOwner?null:(int)$user->getId()):[];$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$historyFrom=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['from']??''))?(string)$_GET['from']:(new \DateTimeImmutable($today))->modify('-30 days')->format('Y-m-d');$historyTo=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['to']??''))?(string)$_GET['to']:$today;if($historyFrom>$historyTo)[$historyFrom,$historyTo]=[$historyTo,$historyFrom];$manifests=$isCarrierOrganization?($isCarrierOwner?$manifestRepo->getForCarrier($ownerId):$manifestRepo->getForDriver($ownerId,(int)$user->getId())):[];$activeManifest=null;$manifestItems=[];foreach($manifests as $candidate){if(in_array((string)$candidate->status,['DRAFT','GENERATED','IN_PROGRESS'],true)){$activeManifest=$candidate;$manifestItems=$manifestRepo->getItems((int)$candidate->id,$ownerId,$isCarrierOwner?0:(int)$user->getId());break;}}
    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'teamContext' => $teamContext,
        'deliveryTasks' => $deliveryTasks,
        'readyToStartCount' => $readyToStartCount,
        'onRouteCount' => $onRouteCount,
        'incidentTasks' => $incidentTasks,
        'completedTasks' => $completedTasks,
        'allDeliveryTasks' => array_merge($deliveryTasks, $incidentTasks, $completedTasks),
        'assignedPackageTokens' => $assignedPackageTokens,
        'scannedPackageId' => max(0, (int)($_GET['scanned_package'] ?? 0)),
        'carrierStage' => in_array((string)($_GET['carrier_stage']??''),['collected','history','warehouse','route','cancelled','closed'],true)?(string)$_GET['carrier_stage']:'collected',
        'carrierView' => in_array((string)($_GET['view']??''),['deliveries','collection'],true)?(string)$_GET['view']:'deliveries',
        'carrierResult' => in_array((string)($_GET['result']??''),['open','completed','attempts','returns'],true)?(string)$_GET['result']:'open',
        'showCarrierDetails' => (string)($_GET['details']??'')==='1',
        'isCarrierOrganization' => $isCarrierOrganization,
        'isCarrierOwner' => $isCarrierOwner,
        'collectedTodayCount' => $isCarrierOrganization?$carrierRepo->countCollectedToday($ownerId,$isCarrierOwner?null:(int)$user->getId()):0,
        'carrierPackages' => $carrierPackages,
        'carrierCollectedPackages' => array_values(array_filter($carrierPackages,static fn($p)=>(string)$p->custody_status==='PICKED_UP')),
        'carrierCollectionHistory' => $isCarrierOrganization?$carrierRepo->getCollectionHistory($ownerId,$isCarrierOwner?null:(int)$user->getId(),$historyFrom,$historyTo):[],
        'historyFrom'=>$historyFrom,'historyTo'=>$historyTo,
        'carrierWarehousePackages' => array_values(array_filter($carrierPackages,static fn($p)=>in_array((string)$p->custody_status,['RECEIVED_AT_HUB','SORTED_AT_HUB'],true))),
        'carrierRoutePackages' => array_values(array_filter($carrierPackages,static fn($p)=>(string)$p->custody_status==='OUT_FOR_DELIVERY')),
        'carrierDeliveredPackages' => array_values(array_filter($carrierPackages,static fn($p)=>(string)$p->custody_status==='DELIVERED')),
        'carrierAttemptPackages' => array_values(array_filter($carrierPackages,static fn($p)=>in_array((string)$p->custody_status,['CUSTOMER_ABSENT','CUSTOMER_REJECTED'],true))),
        'carrierCancelledPackages' => $carrierCancelledPackages,
        'carrierPendingReturnPackages' => array_values(array_filter($carrierCancelledPackages,static fn($p)=>in_array((string)$p->custody_status,['CANCELLED_RETURN_PENDING','DELIVERY_CANCELLED'],true))),
        'carrierClosedPackages' => array_values(array_filter($carrierPackages,static fn($p)=>in_array((string)$p->custody_status,['DELIVERED','CUSTOMER_ABSENT','CUSTOMER_REJECTED'],true))),
        'deliveryManifests'=>$manifests,'activeManifest'=>$activeManifest,'manifestItems'=>$manifestItems,'manifestDbReady'=>$manifestRepo->isReady(),
    ]);
});

$router->post(function () {
    $user=LoginService::getSession();$context=(int)$user->getLevel()===4?(new UserWorkspaceContextService())->getTeamContext($user):['selectedOwnerId'=>(int)($user->getOwner()?:$user->getId())];$ownerId=(int)($context['selectedOwnerId']??($user->getOwner()?:$user->getId()));$repo=new CarrierPackageRepository();
    $isAjax=strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
    $respond=function(bool $ok,string $message,array $extra=[])use($isAjax):void{if($isAjax){header('Content-Type: application/json; charset=UTF-8');http_response_code($ok?200:422);echo json_encode(array_merge(['success'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_UNICODE);exit;}MessageUtil::setMessage($message);LocationUtils::reload();};
    if(!$repo->isCarrier($ownerId)){MessageUtil::setMessage('The selected workspace is not a carrier organization.');LocationUtils::reload();}
    $action=trim((string)($_POST['action']??''));
    if($action==='collection_batch_confirm'){$tokens=json_decode((string)($_POST['tokens']??'[]'),true);$handedBy=trim((string)($_POST['handed_by']??''));$signature=trim((string)($_POST['signature']??''));if(!is_array($tokens)||!count($tokens)||$handedBy===''||!str_starts_with($signature,'data:image/png;base64,'))$respond(false,'Agrega paquetes, identifica a quien los entrega y registra su firma.');$done=[];$errors=[];foreach(array_unique($tokens) as $token){[$ok,$message,$package]=$repo->claimByQr($ownerId,(int)$user->getId(),trim((string)$token),null,null,null,'COLLECTION_BATCH');if($ok)$done[]=['code'=>(string)($package->package_code??$token)];else $errors[]=$message;}$respond(count($done)>0,count($done).' paquetes confirmados'.($errors?' · '.count($errors).' no procesados':''),['packages'=>$done,'errors'=>$errors]);}
    if($action==='generate_manifest'){$manifestRepo=new DeliveryManifestRepository();[$ok,$message]=$manifestRepo->generate((int)($_POST['manifest_id']??0),$ownerId,(int)$user->getLevel()===2?0:(int)$user->getId());$respond($ok,$message);}
    if($action==='carrier_qr_preview'){
        [$ok,$message,$package]=$repo->previewClaimByQr($ownerId,trim((string)($_POST['qr_token']??'')));
        $respond($ok,$message,$package?['package'=>[
            'id'=>(int)$package->id,
            'code'=>(string)$package->package_code,
            'seller'=>(string)($package->seller_name??''),
            'recipient'=>trim((string)($package->guest_name??'')),
            'address'=>trim(implode(', ',array_filter([(string)($package->shipping_address_1??''),(string)($package->shipping_city??''),(string)($package->shipping_state??''),(string)($package->shipping_zip??'')]))),
            'status'=>(string)($package->custody_status??'WITH_SELLER'),
        ]]:[]);
    }
    if($action==='carrier_location'){$ok=$repo->recordLiveLocation((int)($_POST['package_id']??0),$ownerId,(int)$user->getId(),(float)($_POST['latitude']??0),(float)($_POST['longitude']??0),is_numeric($_POST['accuracy']??null)?(float)$_POST['accuracy']:null);$respond($ok,$ok?'Location updated.':'This package is not assigned to you for delivery.');}
    if($action==='carrier_manual_request'){
        [$ok,$message]=$repo->requestManualCustody($ownerId,(int)$user->getId(),trim((string)($_POST['package_code']??'')),trim((string)($_POST['notes']??'')));MessageUtil::setMessage($message);LocationUtils::reload();
    }
    if($action==='carrier_qr_claim'){
        $qrPhoto='';if(FileUtils::hasFile($_FILES,'qr_photo')){$file=$_FILES['qr_photo'];if(!in_array((string)($file['type']??''),['image/jpeg','image/png','image/webp'],true)||(int)($file['size']??0)>8*1024*1024){$message='QR evidence must be JPG, PNG or WEBP up to 8 MB.';if(strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'){header('Content-Type: application/json');http_response_code(422);echo json_encode(['success'=>false,'message'=>$message]);exit;}MessageUtil::setMessage($message);LocationUtils::reload();}$qrPhoto=FileUtils::saveFile($file,'store-carrier-evidence');}
        [$ok,$message,$package]=$repo->claimByQr($ownerId,(int)$user->getId(),trim((string)($_POST['qr_token']??'')),$qrPhoto,is_numeric($_POST['latitude']??null)?(float)$_POST['latitude']:null,is_numeric($_POST['longitude']??null)?(float)$_POST['longitude']:null);
        if(strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'){$updated=$ok?$repo->findBySecureToken(trim((string)($_POST['qr_token']??''))):null;$status=(string)($updated->custody_status??'');$listStage=in_array($status,['PICKED_UP'],true)?'collected':(in_array($status,['RECEIVED_AT_HUB','SORTED_AT_HUB'],true)?'warehouse':(in_array($status,['OUT_FOR_DELIVERY'],true)?'route':(in_array($status,['CANCELLED_RETURN_PENDING'],true)?'cancelled':'closed')));header('Content-Type: application/json');http_response_code($ok?200:422);echo json_encode(['success'=>$ok,'message'=>$message,'package_id'=>$package->id??null,'custody_status'=>$status,'list_stage'=>$listStage]);exit;}MessageUtil::setMessage($message);LocationUtils::reload();
    }
    if($action==='carrier_manual_secure_claim'){
        $photo='';if(FileUtils::hasFile($_FILES,'manual_claim_photo')){$file=$_FILES['manual_claim_photo'];if(!in_array((string)($file['type']??''),['image/jpeg','image/png','image/webp'],true)||(int)($file['size']??0)>8*1024*1024){$respond(false,'Evidence must be JPG, PNG or WEBP up to 8 MB.');}$photo=FileUtils::saveFile($file,'store-carrier-evidence');}
        if($photo===''){$respond(false,'Take a package photo to use the secure manual fallback.');}
        [$ok,$message,$package]=$repo->claimByQr($ownerId,(int)$user->getId(),trim((string)($_POST['secure_package_token']??'')),$photo,is_numeric($_POST['latitude']??null)?(float)$_POST['latitude']:null,is_numeric($_POST['longitude']??null)?(float)$_POST['longitude']:null,'MANUAL_SECURE_CODE');$respond($ok,$message,['package_id'=>$package->id??null]);
    }
    if($action==='carrier_advance'){
        $photo='';if(FileUtils::hasFile($_FILES,'evidence_photo')){$file=$_FILES['evidence_photo'];if(!in_array((string)($file['type']??''),['image/jpeg','image/png','image/webp'],true)||(int)($file['size']??0)>8*1024*1024){$respond(false,'Evidence must be JPG, PNG or WEBP up to 8 MB.');}$photo=FileUtils::saveFile($file,'store-carrier-evidence');}
        $stage=trim((string)($_POST['carrier_stage']??''));$notes=trim((string)($_POST['notes']??''));$meta=['receiver_type'=>trim((string)($_POST['receiver_type']??'')),'receiver_name'=>trim((string)($_POST['receiver_name']??'')),'document_type'=>trim((string)($_POST['document_type']??'')),'document_number'=>trim((string)($_POST['document_number']??'')),'failure_code'=>trim((string)($_POST['failure_code']??'')),'latitude'=>$_POST['latitude']??null,'longitude'=>$_POST['longitude']??null];
        if(in_array($stage,['customer_absent','customer_rejected','delivery_cancelled'],true)&&$notes===''){$respond(false,'Describe the delivery incident.');}
        if($stage==='delivered'&&($meta['receiver_name']===''||$meta['document_number']==='')){$respond(false,'Receiver name and document are required.');}
        [$ok,$message]=$repo->advance((int)($_POST['package_id']??0),$ownerId,(int)$user->getId(),$stage,$notes,$photo,$meta);$respond($ok,$message);
    }
    MessageUtil::setMessage('Invalid carrier operation.');LocationUtils::reload();
});

$router->run();
