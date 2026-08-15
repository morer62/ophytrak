<?php

require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/..')->safeLoad();

use App\Repositories\StoreProductsRepository;

$pdo = new PDO($_ENV['DATABASE_URL']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ownerId = 1450;
$productId = $orderId = 0;
try {
    $stmt=$pdo->prepare("INSERT INTO store_products (id_owner,name,price,stock_quantity,status,is_public) VALUES (:owner,'QA Restock Idempotency',1.00,7,'DRAFT',0)");$stmt->execute([':owner'=>$ownerId]);$productId=(int)$pdo->lastInsertId();
    $stmt=$pdo->prepare("INSERT INTO store_orders (id_owner,guest_name,guest_email,status,payment_status,total) VALUES (:owner,'QA Restock','qa.restock@example.test','RETURN_APPROVED','PAID',2.00)");$stmt->execute([':owner'=>$ownerId]);$orderId=(int)$pdo->lastInsertId();
    $stmt=$pdo->prepare("INSERT INTO store_order_items (id_owner,id_store_order,id_product,product_name_snapshot,unit_price,quantity,line_total) VALUES (:owner,:order_id,:product,'QA Restock Idempotency',1.00,2,2.00)");$stmt->execute([':owner'=>$ownerId,':order_id'=>$orderId,':product'=>$productId]);
    $repo=new StoreProductsRepository();
    if(!$repo->restoreStockForReturnedOrder($ownerId,$orderId,$ownerId) || !$repo->restoreStockForReturnedOrder($ownerId,$orderId,$ownerId)) throw new RuntimeException('Stock restore returned false.');
    $stock=(int)$pdo->query("SELECT stock_quantity FROM store_products WHERE id={$productId}")->fetchColumn();
    $markers=(int)$pdo->query("SELECT COUNT(*) FROM store_order_stock_returns WHERE id_owner={$ownerId} AND id_store_order={$orderId}")->fetchColumn();
    if($stock!==9 || $markers!==1) throw new RuntimeException("Expected stock 9 and one marker; got {$stock} and {$markers}.");
    echo "Store return stock idempotency OK\n";
} finally {
    if($orderId){
        if($pdo->query("SHOW TABLES LIKE 'store_order_stock_returns'")->fetchColumn())$pdo->exec("DELETE FROM store_order_stock_returns WHERE id_store_order={$orderId}");
        $pdo->exec("DELETE FROM store_order_items WHERE id_store_order={$orderId}");$pdo->exec("DELETE FROM store_orders WHERE id={$orderId}");
    }
    if($productId)$pdo->exec("DELETE FROM store_products WHERE id={$productId}");
}
