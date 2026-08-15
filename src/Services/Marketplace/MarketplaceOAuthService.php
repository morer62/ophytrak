<?php
namespace App\Services\Marketplace;

final class MarketplaceOAuthService
{
    public function authorizationUrl(string $provider,string $redirectUri,string $state):string
    {
        return match($provider){
            'mercadolibre'=>'https://auth.mercadolivre.com.br/authorization?'.http_build_query(['response_type'=>'code','client_id'=>$_ENV['MERCADOLIBRE_CLIENT_ID']??'','redirect_uri'=>$redirectUri,'state'=>$state]),
            'tiktok_shop'=>(string)($_ENV['TIKTOK_SHOP_AUTH_URL']??'https://services.tiktokshop.com/open/authorize').'?'.http_build_query(['service_id'=>$_ENV['TIKTOK_SHOP_APP_KEY']??'','state'=>$state]),
            'shopee_br'=>$this->shopeeUrl($redirectUri,$state),
            default=>throw new \InvalidArgumentException('Unsupported provider'),
        };
    }
    public function exchange(string $provider,string $code,string $redirectUri):array
    {
        return match($provider){
            'mercadolibre'=>$this->form('https://api.mercadolibre.com/oauth/token',['grant_type'=>'authorization_code','client_id'=>$_ENV['MERCADOLIBRE_CLIENT_ID']??'','client_secret'=>$_ENV['MERCADOLIBRE_CLIENT_SECRET']??'','code'=>$code,'redirect_uri'=>$redirectUri]),
            'tiktok_shop'=>$this->getJson('https://auth.tiktok-shops.com/api/v2/token/get',['app_key'=>$_ENV['TIKTOK_SHOP_APP_KEY']??'','app_secret'=>$_ENV['TIKTOK_SHOP_APP_SECRET']??'','auth_code'=>$code,'grant_type'=>'authorized_code']),
            'shopee_br'=>$this->shopeeToken($code),
            default=>throw new \InvalidArgumentException('Unsupported provider'),
        };
    }
    private function shopeeUrl(string $redirect,string $state):string{$partner=(int)($_ENV['SHOPEE_PARTNER_ID']??0);$key=(string)($_ENV['SHOPEE_PARTNER_KEY']??'');$path='/api/v2/shop/auth_partner';$ts=time();$sign=hash_hmac('sha256',$partner.$path.$ts,$key);return(string)($_ENV['SHOPEE_API_BASE']??'https://partner.shopeemobile.com').$path.'?'.http_build_query(['partner_id'=>$partner,'timestamp'=>$ts,'sign'=>$sign,'redirect'=>$redirect,'state'=>$state]);}
    private function shopeeToken(string $code):array{$partner=(int)($_ENV['SHOPEE_PARTNER_ID']??0);$key=(string)($_ENV['SHOPEE_PARTNER_KEY']??'');$path='/api/v2/auth/token/get';$ts=time();$sign=hash_hmac('sha256',$partner.$path.$ts,$key);$url=(string)($_ENV['SHOPEE_API_BASE']??'https://partner.shopeemobile.com').$path.'?'.http_build_query(['partner_id'=>$partner,'timestamp'=>$ts,'sign'=>$sign]);return$this->jsonRequest('POST',$url,['code'=>$code,'partner_id'=>$partner]);}
    private function form(string $url,array $data):array{return$this->request($url,http_build_query($data),['Content-Type: application/x-www-form-urlencoded']);}
    private function getJson(string $url,array $data):array{return$this->jsonRequest('GET',$url.'?'.http_build_query($data),null);}
    private function jsonRequest(string $method,string $url,?array $data):array{return$this->request($url,$data===null?null:json_encode($data),['Content-Type: application/json'],$method);}
    private function request(string $url,?string $body,array $headers,string $method='POST'):array{$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);$json=json_decode((string)$raw,true);if($status<200||$status>=300||!is_array($json))throw new \RuntimeException('Authorization failed'.($error?': '.$error:''));return$json['data']??$json;}
}
