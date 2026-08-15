<?php
namespace App\Services\Marketplace;

final class MercadoLibreProvider implements MarketplaceProviderInterface
{
    public function __construct(private object $connector, private MarketplaceHttpClient $http = new MarketplaceHttpClient()) {}
    public function provider(): string { return 'mercadolibre'; }
    private function get(string $path, array $query = []): array {
        $url='https://api.mercadolibre.com'.$path.($query?'?'.http_build_query($query):'');
        return $this->http->request('GET',$url,['Authorization: Bearer '.$this->connector->access_token])['data'];
    }
    public function testConnection(): array { $me=$this->get('/users/me'); return ['success'=>true,'shop_id'=>(string)($me['id']??''),'name'=>$me['nickname']??$me['first_name']??'Mercado Libre']; }
    public function fetchProducts(?string $cursor = null): array {
        $seller=(string)($this->connector->external_shop_id??$this->connector->account_id??'');
        if($seller==='')$seller=(string)($this->testConnection()['shop_id']??'');
        $offset=max(0,(int)$cursor);$search=$this->get('/users/'.$seller.'/items/search',['limit'=>50,'offset'=>$offset]);
        $ids=array_values(array_filter((array)($search['results']??[])));$items=[];
        if($ids){$batch=$this->get('/items',['ids'=>implode(',',$ids),'attributes'=>'id,title,subtitle,seller_custom_field,category_id,price,original_price,currency_id,available_quantity,sold_quantity,condition,permalink,thumbnail,pictures,attributes,variations,status,last_updated']);foreach($batch as $row){$item=$row['body']??null;if(is_array($item))$items[]=$this->normalizeProduct($item);}}
        $total=(int)($search['paging']['total']??count($items));$next=$offset+count($ids);
        return ['items'=>$items,'next_cursor'=>$next<$total?(string)$next:null,'raw_count'=>count($ids)];
    }
    public function fetchOrders(?string $cursor = null): array {
        $seller=(string)($this->connector->external_shop_id??$this->connector->account_id??'');if($seller==='')$seller=(string)($this->testConnection()['shop_id']??'');
        $from=$cursor?:gmdate('Y-m-d\TH:i:s.000-00:00',time()-86400*30);$res=$this->get('/orders/search',['seller'=>$seller,'order.date_last_updated.from'=>$from,'sort'=>'date_asc','limit'=>50]);$items=[];$latest=$from;
        foreach((array)($res['results']??[]) as $order){$shipment=[];if(!empty($order['shipping']['id'])){try{$shipment=$this->get('/shipments/'.rawurlencode((string)$order['shipping']['id']));}catch(\Throwable $ignored){}}$items[]=$this->normalizeOrder($order,$shipment);$latest=max($latest,(string)($order['last_updated']??$from));}
        return ['items'=>$items,'next_cursor'=>$latest,'raw_count'=>count($items)];
    }
    private function normalizeProduct(array $p): array { $pictures=array_map(fn($x)=>$x['secure_url']??$x['url']??null,(array)($p['pictures']??[]));return ['external_id'=>(string)$p['id'],'sku'=>(string)($p['seller_custom_field']??''),'name'=>(string)($p['title']??''),'description'=>(string)($p['subtitle']??''),'price'=>(float)($p['price']??0),'currency'=>(string)($p['currency_id']??'BRL'),'stock'=>(int)($p['available_quantity']??0),'status'=>(string)($p['status']??''),'condition'=>(string)($p['condition']??''),'main_image'=>$p['thumbnail']??($pictures[0]??null),'gallery'=>array_values(array_filter($pictures)),'variants'=>array_map(fn($v)=>['external_id'=>(string)($v['id']??''),'sku'=>(string)($v['seller_custom_field']??''),'name'=>implode(' / ',array_map(fn($a)=>$a['value_name']??'',(array)($v['attribute_combinations']??[]))),'price'=>(float)($v['price']??$p['price']??0),'stock'=>(int)($v['available_quantity']??0)],(array)($p['variations']??[])),'updated_at'=>$p['last_updated']??null,'raw'=>$p]; }
    private function normalizeOrder(array $o,array $s): array { $receiver=$s['receiver_address']??[];$buyer=$o['buyer']??[];return ['external_id'=>(string)$o['id'],'pack_id'=>isset($o['pack_id'])?(string)$o['pack_id']:null,'shipment_id'=>isset($o['shipping']['id'])?(string)$o['shipping']['id']:null,'status'=>(string)($o['status']??''),'payment_status'=>(string)($o['payments'][0]['status']??''),'currency'=>(string)($o['currency_id']??'BRL'),'total'=>(float)($o['total_amount']??0),'paid_amount'=>(float)($o['paid_amount']??0),'customer'=>['external_id'=>(string)($buyer['id']??''),'name'=>trim(($buyer['first_name']??'').' '.($buyer['last_name']??'')),'email'=>(string)($buyer['email']??''),'phone'=>(string)($receiver['receiver_phone']??'')],'shipping'=>['address1'=>(string)($receiver['address_line']??''),'address2'=>(string)($receiver['comment']??''),'city'=>(string)($receiver['city']['name']??''),'state'=>(string)($receiver['state']['name']??''),'zip'=>(string)($receiver['zip_code']??''),'country'=>(string)($receiver['country']['id']??'BR')],'items'=>array_map(fn($i)=>['external_id'=>(string)($i['item']['id']??''),'variant_id'=>isset($i['item']['variation_id'])?(string)$i['item']['variation_id']:null,'sku'=>(string)($i['item']['seller_sku']??$i['item']['seller_custom_field']??''),'name'=>(string)($i['item']['title']??''),'quantity'=>(int)($i['quantity']??1),'unit_price'=>(float)($i['unit_price']??0)],(array)($o['order_items']??[])),'updated_at'=>$o['last_updated']??null,'raw'=>['order'=>$o,'shipment'=>$s]]; }
}
