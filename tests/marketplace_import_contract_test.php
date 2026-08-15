<?php
require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/../')->load();
if (getenv('DATABASE_URL')) $_ENV['DATABASE_URL'] = getenv('DATABASE_URL');
use App\Repositories\Connection;
use App\Services\Marketplace\MarketplaceImportService;

$db=new Connection();$owner=909090;
$db->query("INSERT INTO marketplace_connectors(id_owner,provider,display_name,account_id,external_shop_id,status,sync_status,created_at,updated_at) VALUES(:owner,'mercadolibre','Contract test','ML-TEST','ML-TEST','ACTIVE','NEVER',NOW(),NOW())");$db->bind(':owner',$owner);$db->execute();$connector=(object)['id'=>(int)$db->lastId(),'external_shop_id'=>'ML-TEST'];
$product=['external_id'=>'MLB-P-1','sku'=>'SKU-1','name'=>'Produto de teste','price'=>19.9,'currency'=>'BRL','stock'=>8,'status'=>'active','variants'=>[['external_id'=>'MLB-V-1','sku'=>'SKU-1-A','name'=>'Azul','price'=>21.9,'stock'=>3]]];
$order=['external_id'=>'MLB-O-1','status'=>'paid','payment_status'=>'approved','currency'=>'BRL','total'=>43.8,'paid_amount'=>43.8,'customer'=>['name'=>'Cliente Teste','email'=>'marketplace-contract@example.test','phone'=>'551100000000'],'shipping'=>['address1'=>'Rua Teste 1','city'=>'Sao Paulo','state'=>'SP','zip'=>'01000-000','country'=>'BR'],'items'=>[['external_id'=>'MLB-P-1','variant_id'=>'MLB-V-1','sku'=>'SKU-1-A','name'=>'Produto de teste Azul','quantity'=>2,'unit_price'=>21.9]]];
$service=new MarketplaceImportService();
if($service->importProducts($owner,$connector,'mercadolibre',[$product])!==1)throw new RuntimeException('First product import failed');
if($service->importOrders($owner,$connector,'mercadolibre',[$order])!==1)throw new RuntimeException('First order import failed');
$service->importProducts($owner,$connector,'mercadolibre',[$product]);$service->importOrders($owner,$connector,'mercadolibre',[$order]);
$db->query("SELECT COUNT(*) total FROM marketplace_resource_mappings WHERE id_owner=:owner AND resource_type='PRODUCT'");$db->bind(':owner',$owner);if((int)$db->fetchOne()->total!==1)throw new RuntimeException('Product mapping is not idempotent');
$db->query("SELECT COUNT(*) total FROM store_orders WHERE id_owner=:owner AND external_order_id='MLB-O-1'");$db->bind(':owner',$owner);if((int)$db->fetchOne()->total!==1)throw new RuntimeException('Order import is not idempotent');
$db->query("DELETE FROM store_package_events WHERE id_owner=:owner; DELETE FROM store_packages WHERE id_owner=:owner; DELETE FROM store_order_items WHERE id_owner=:owner; DELETE FROM store_orders WHERE id_owner=:owner; DELETE FROM store_product_variations WHERE id_owner=:owner; DELETE FROM store_products WHERE id_owner=:owner; DELETE FROM marketplace_resource_mappings WHERE id_owner=:owner; DELETE FROM marketplace_connectors WHERE id_owner=:owner");$db->bind(':owner',$owner);$db->execute();
echo "Marketplace import contract: OK\n";
