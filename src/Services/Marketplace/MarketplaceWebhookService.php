<?php
namespace App\Services\Marketplace;

use App\Repositories\Connection;

final class MarketplaceWebhookService
{
    private Connection $db;
    public function __construct(){ $this->db=new Connection(); }
    public function receive(string $provider,string $raw,array $headers):array
    {
        if(!in_array($provider,['shopee_br','mercadolibre','tiktok_shop'],true))return[404,'Unknown provider'];
        $payload=json_decode($raw,true);if(!is_array($payload))return[400,'Invalid JSON'];
        $valid=$this->validSignature($provider,$raw,$headers,$payload);if(!$valid)return[401,'Invalid signature'];
        $eventId=$this->eventId($provider,$payload,$raw);$shopId=(string)($payload['shop_id']??$payload['user_id']??$payload['data']['shop_id']??'');$topic=(string)($payload['topic']??$payload['type']??$payload['code']??'unknown');
        $safeHeaders=array_intersect_key(array_change_key_case($headers,CASE_LOWER),array_flip(['content-type','user-agent','authorization','x-shopee-signature','x-request-id']));
        $this->db->query("INSERT IGNORE INTO marketplace_webhook_events(provider,external_event_id,external_shop_id,topic,signature_valid,headers_json,payload_json,status,available_at) VALUES(:provider,:event,:shop,:topic,1,:headers,:payload,'RECEIVED',NOW())");
        foreach(['provider'=>$provider,'event'=>$eventId,'shop'=>$shopId?:null,'topic'=>$topic,'headers'=>json_encode($safeHeaders),'payload'=>$raw]as$k=>$v)$this->db->bind(':'.$k,$v);$this->db->execute();return[200,''];
    }
    private function validSignature(string $provider,string $raw,array $headers,array $payload):bool{$h=array_change_key_case($headers,CASE_LOWER);if($provider==='mercadolibre')return isset($payload['resource'],$payload['topic'],$payload['user_id']);if($provider==='tiktok_shop'){$sig=(string)($h['authorization']??'');$secret=(string)($_ENV['TIKTOK_SHOP_APP_SECRET']??'');$key=(string)($_ENV['TIKTOK_SHOP_APP_KEY']??'');return $sig!==''&&$secret!==''&&hash_equals(strtolower($sig),hash_hmac('sha256',$key.$raw,$secret));}$sig=(string)($h['x-shopee-signature']??$h['authorization']??'');$secret=(string)($_ENV['SHOPEE_WEBHOOK_SECRET']??$_ENV['SHOPEE_PARTNER_KEY']??'');return $sig!==''&&$secret!==''&&hash_equals(strtolower($sig),hash_hmac('sha256',$raw,$secret));}
    private function eventId(string $provider,array $p,string $raw):string{return(string)($p['tts_notification_id']??$p['_id']??$p['notification_id']??$p['event_id']??hash('sha256',$provider.'|'.$raw));}
}
