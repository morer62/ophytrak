<?php
namespace App\Services;

/** Decodes label data only. Never follows a scanned URL or grants another tenant access. */
final class CarrierLabelReference
{
    public static function parse(string $raw): ?array
    {
        $raw=trim($raw);
        if($raw===''||strlen($raw)>4096)return null;
        if(preg_match('/^BR[0-9]{10,16}[A-Z0-9]?$/i',$raw))
            return ['kind'=>'external','provider'=>'SHOPEE_BR','reference'=>strtoupper($raw)];
        if($raw[0]==='{'){
            $data=json_decode($raw,true);
            // Mercado Livre FLEX labels carry shipment id plus sender/security metadata.
            if(!is_array($data)||!isset($data['id'])||!is_scalar($data['id'])
                ||(!isset($data['sender_id'])&&!isset($data['hash_code'])&&!isset($data['security_digit'])))return null;
            $id=(string)$data['id'];
            return preg_match('/^[0-9]{6,25}$/D',$id)
                ?['kind'=>'external','provider'=>'MERCADOLIBRE','reference'=>$id]:null;
        }
        if(preg_match('/^[A-Za-z0-9_-]{1,160}$/D',$raw))
            return ['kind'=>'internal','provider'=>'','reference'=>$raw];
        $url=parse_url($raw);
        if(!is_array($url)||($url['path']??'')!=='/store/order-access')return null;
        parse_str($url['query']??'', $query);
        $token=$query['token']??null;
        return is_string($token)&&preg_match('/^[A-Za-z0-9_-]{1,160}$/D',$token)
            ?['kind'=>'internal','provider'=>'','reference'=>$token]:null;
    }
}
