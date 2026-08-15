<?php

require __DIR__ . '/../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../')->load();

$appUrl = strtolower((string)($_ENV['APP_URL'] ?? ''));
if (!str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')) {
    fwrite(STDERR, "Certification reset is restricted to localhost.\n");
    exit(2);
}

$code = trim((string)($argv[1] ?? ''));
if ($code === '') exit(3);
$db = new App\Repositories\Connection();
$db->query('SELECT id,id_store_order FROM store_packages WHERE package_code=:code LIMIT 1');
$db->bind(':code', $code);
$package = $db->fetchOne();
if (!$package) exit(4);
$db->query('DELETE FROM store_package_custody_requests WHERE id_store_package=:id');
$db->bind(':id', (int)$package->id); $db->execute();
$db->query('DELETE FROM store_package_carrier_assignments WHERE id_store_package=:id');
$db->bind(':id', (int)$package->id); $db->execute();
$db->query("UPDATE store_packages SET current_custodian_owner_id=NULL,current_custodian_user_id=NULL,custody_status='WITH_SELLER',current_status='CREATED',logistics_mode='INTERNAL',current_location_label='With seller',custody_started_at=NULL,updated_at=NOW() WHERE id=:id");
$db->bind(':id', (int)$package->id); $db->execute();
$db->query("UPDATE store_orders SET status='CONFIRMED',payment_status='PAID',return_notes=NULL,return_requested_at=NULL,return_admin_message=NULL,return_decision_at=NULL,return_closed_at=NULL,updated_at=NOW() WHERE id=:id");
$db->bind(':id', (int)$package->id_store_order); $db->execute();
echo (int)$package->id, PHP_EOL;
