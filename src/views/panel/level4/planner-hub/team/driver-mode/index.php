<?php

use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\CarrierPackageRepository;
use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Services\UserWorkspaceContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

$router = new Router();
ModuleGuardService::requireModule('store_delivery_tracking');

$router->get(function () {
    $user = LoginService::getSession();
    $teamContext = (int)$user->getLevel() === 4
        ? (new UserWorkspaceContextService())->getTeamContext($user)
        : ['selectedOwnerId' => (int)($user->getOwner() ?: $user->getId())];
    $ownerId = (int)($teamContext['selectedOwnerId'] ?? ($user->getOwner() ?: $user->getId()));
    $carrierRepo = new CarrierPackageRepository();
    $isCarrierOrganization = $ownerId > 0 && $carrierRepo->isCarrier($ownerId);

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
        'isCarrierOrganization' => $isCarrierOrganization,
        'carrierPackages' => $isCarrierOrganization ? $carrierRepo->getForCarrier($ownerId, (int)$user->getId()) : [],
    ]);
});

$router->post(function () {
    $user=LoginService::getSession();$context=(int)$user->getLevel()===4?(new UserWorkspaceContextService())->getTeamContext($user):['selectedOwnerId'=>(int)($user->getOwner()?:$user->getId())];$ownerId=(int)($context['selectedOwnerId']??($user->getOwner()?:$user->getId()));$repo=new CarrierPackageRepository();
    $isAjax=strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
    $respond=function(bool $ok,string $message,array $extra=[])use($isAjax):void{if($isAjax){header('Content-Type: application/json; charset=UTF-8');http_response_code($ok?200:422);echo json_encode(array_merge(['success'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_UNICODE);exit;}MessageUtil::setMessage($message);LocationUtils::reload();};
    if(!$repo->isCarrier($ownerId)){MessageUtil::setMessage('The selected workspace is not a carrier organization.');LocationUtils::reload();}
    $action=trim((string)($_POST['action']??''));
    if($action==='carrier_location'){$ok=$repo->recordLiveLocation((int)($_POST['package_id']??0),$ownerId,(int)$user->getId(),(float)($_POST['latitude']??0),(float)($_POST['longitude']??0),is_numeric($_POST['accuracy']??null)?(float)$_POST['accuracy']:null);$respond($ok,$ok?'Location updated.':'This package is not assigned to you for delivery.');}
    if($action==='carrier_manual_request'){
        [$ok,$message]=$repo->requestManualCustody($ownerId,(int)$user->getId(),trim((string)($_POST['package_code']??'')),trim((string)($_POST['notes']??'')));MessageUtil::setMessage($message);LocationUtils::reload();
    }
    if($action==='carrier_qr_claim'){
        $qrPhoto='';if(FileUtils::hasFile($_FILES,'qr_photo')){$file=$_FILES['qr_photo'];if(!in_array((string)($file['type']??''),['image/jpeg','image/png','image/webp'],true)||(int)($file['size']??0)>8*1024*1024){$message='QR evidence must be JPG, PNG or WEBP up to 8 MB.';if(strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'){header('Content-Type: application/json');http_response_code(422);echo json_encode(['success'=>false,'message'=>$message]);exit;}MessageUtil::setMessage($message);LocationUtils::reload();}$qrPhoto=FileUtils::saveFile($file,'store-carrier-evidence');}
        if($qrPhoto===''){header('Content-Type: application/json');http_response_code(422);echo json_encode(['success'=>false,'message'=>'A live QR camera photo is required to accept custody.']);exit;}
        [$ok,$message,$package]=$repo->claimByQr($ownerId,(int)$user->getId(),trim((string)($_POST['qr_token']??'')),$qrPhoto,is_numeric($_POST['latitude']??null)?(float)$_POST['latitude']:null,is_numeric($_POST['longitude']??null)?(float)$_POST['longitude']:null);
        if(strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'){header('Content-Type: application/json');http_response_code($ok?200:422);echo json_encode(['success'=>$ok,'message'=>$message,'package_id'=>$package->id??null]);exit;}MessageUtil::setMessage($message);LocationUtils::reload();
    }
    if($action==='carrier_manual_secure_claim'){
        $photo='';if(FileUtils::hasFile($_FILES,'manual_claim_photo')){$file=$_FILES['manual_claim_photo'];if(!in_array((string)($file['type']??''),['image/jpeg','image/png','image/webp'],true)||(int)($file['size']??0)>8*1024*1024){$respond(false,'Evidence must be JPG, PNG or WEBP up to 8 MB.');}$photo=FileUtils::saveFile($file,'store-carrier-evidence');}
        if($photo===''){$respond(false,'Take a package photo to use the secure manual fallback.');}
        [$ok,$message,$package]=$repo->claimByQr($ownerId,(int)$user->getId(),trim((string)($_POST['secure_package_token']??'')),$photo,is_numeric($_POST['latitude']??null)?(float)$_POST['latitude']:null,is_numeric($_POST['longitude']??null)?(float)$_POST['longitude']:null,'MANUAL_SECURE_CODE');$respond($ok,$message,['package_id'=>$package->id??null]);
    }
    if($action==='carrier_advance'){
        $photo='';if(FileUtils::hasFile($_FILES,'evidence_photo')){$file=$_FILES['evidence_photo'];if(!in_array((string)($file['type']??''),['image/jpeg','image/png','image/webp'],true)||(int)($file['size']??0)>8*1024*1024){$respond(false,'Evidence must be JPG, PNG or WEBP up to 8 MB.');}$photo=FileUtils::saveFile($file,'store-carrier-evidence');}
        $stage=trim((string)($_POST['carrier_stage']??''));$notes=trim((string)($_POST['notes']??''));$meta=['receiver_type'=>trim((string)($_POST['receiver_type']??'')),'receiver_name'=>trim((string)($_POST['receiver_name']??'')),'document_type'=>trim((string)($_POST['document_type']??'')),'document_number'=>trim((string)($_POST['document_number']??'')),'latitude'=>$_POST['latitude']??null,'longitude'=>$_POST['longitude']??null];
        if(in_array($stage,['customer_absent','customer_rejected','delivery_cancelled'],true)&&$notes===''){$respond(false,'Describe the delivery incident.');}
        if($stage==='delivered'&&($meta['receiver_name']===''||$meta['document_number']==='')){$respond(false,'Receiver name and document are required.');}
        [$ok,$message]=$repo->advance((int)($_POST['package_id']??0),$ownerId,(int)$user->getId(),$stage,$notes,$photo,$meta);$respond($ok,$message);
    }
    MessageUtil::setMessage('Invalid carrier operation.');LocationUtils::reload();
});

$router->run();
