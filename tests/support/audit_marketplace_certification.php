<?php
require __DIR__ . '/../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../')->load();
$db = new App\Repositories\Connection();
$db->query("SELECT o.id,o.order_source,o.external_order_id,o.status,o.payment_status,COUNT(i.id) item_count,COUNT(p.id) package_count FROM store_orders o LEFT JOIN store_order_items i ON i.id_store_order=o.id LEFT JOIN store_packages p ON p.id_store_order=o.id AND p.id_owner=o.id_owner WHERE o.id_owner=:owner AND o.order_source<>'OPHYRA' GROUP BY o.id ORDER BY o.id");
$db->bind(':owner', (int)($argv[1] ?? 0));
echo json_encode($db->fetchAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
