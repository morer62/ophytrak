<?php
use App\Services\Marketplace\MarketplaceWebhookService;

header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'method_not_allowed']);return;}
$provider=strtolower(trim((string)($_GET['provider']??'')));
[$status,$message]=(new MarketplaceWebhookService())->receive($provider,(string)file_get_contents('php://input'),function_exists('getallheaders')?(array)getallheaders():[]);
http_response_code($status);echo $message===''?'':json_encode(['error'=>$message]);
