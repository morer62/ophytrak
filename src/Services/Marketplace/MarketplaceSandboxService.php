<?php
namespace App\Services\Marketplace;

use App\Repositories\MarketplaceConnectorsRepository;

final class MarketplaceSandboxService
{
    public function run(int $owner,string $provider):array
    {
        $isLocal=str_contains(strtolower((string)($_ENV['APP_URL']??'')),'localhost')||str_contains((string)($_ENV['APP_URL']??''),'127.0.0.1');
        if(strtolower((string)($_ENV['ENVIRONMENT']??'prod'))==='prod'&&!$isLocal)return['success'=>false,'message'=>'Marketplace sandbox fixtures are disabled in production.'];
        $repo=new MarketplaceConnectorsRepository();$connector=$repo->getCredentialsForSync($owner,$provider);
        if(!$connector)return['success'=>false,'message'=>'Save the connector before running its sandbox fixture.'];
        $labels=['shopee_br'=>'Shopee Brasil','mercadolibre'=>'Mercado Libre','tiktok_shop'=>'TikTok Shop'];if(!isset($labels[$provider]))return['success'=>false,'message'=>'Unsupported sandbox provider.'];
        $prefix=['shopee_br'=>'SHP','mercadolibre'=>'MLB','tiktok_shop'=>'TTS'][$provider];$stamp='SANDBOX-'.date('Ymd');
        $product=['external_id'=>$prefix.'-PRODUCT-'.$stamp,'sku'=>$prefix.'-SKU-001','name'=>$labels[$provider].' Sandbox Product','description'=>'Ophyra certified sandbox synchronization fixture.','price'=>49.90,'currency'=>'BRL','stock'=>25,'status'=>'ACTIVE','main_image'=>null,'gallery'=>[],'variants'=>[['external_id'=>$prefix.'-VARIANT-'.$stamp,'sku'=>$prefix.'-SKU-BLUE','name'=>'Azul / M','price'=>54.90,'stock'=>10]],'updated_at'=>date('Y-m-d H:i:s'),'raw'=>['sandbox'=>true,'provider'=>$provider]];
        $order=['external_id'=>$prefix.'-ORDER-'.$stamp,'pack_id'=>$prefix.'-PACK-'.$stamp,'shipment_id'=>$prefix.'-SHIP-'.$stamp,'status'=>'PAID','payment_status'=>'APPROVED','currency'=>'BRL','total'=>109.80,'paid_amount'=>109.80,'customer'=>['external_id'=>$prefix.'-BUYER-1','name'=>$labels[$provider].' Sandbox Customer','email'=>strtolower($prefix).'.sandbox.customer@example.test','phone'=>'+5511999999999'],'shipping'=>['address1'=>'Avenida Paulista, 1000','address2'=>'Apto 10','city'=>'Sao Paulo','state'=>'SP','zip'=>'01310-100','country'=>'BR'],'items'=>[['external_id'=>$product['external_id'],'variant_id'=>$product['variants'][0]['external_id'],'sku'=>$product['variants'][0]['sku'],'name'=>$product['name'].' Azul / M','quantity'=>2,'unit_price'=>54.90]],'updated_at'=>date('Y-m-d H:i:s'),'raw'=>['sandbox'=>true,'provider'=>$provider]];
        $import=new MarketplaceImportService();$products=$import->importProducts($owner,$connector,$provider,[$product]);$orders=$import->importOrders($owner,$connector,$provider,[$order]);
        return['success'=>$products===1&&$orders===1,'message'=>sprintf('%s sandbox synchronized: %d product, %d order.',$labels[$provider],$products,$orders)];
    }
}
