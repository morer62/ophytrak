<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

use App\Services\DatabaseBackupService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This cron must be run from CLI.\n";
    exit(1);
}

$service = new DatabaseBackupService($root);
$backupPath = $service->defaultBackupPath();
$backup = $service->createBackup($backupPath);
$emailTo = trim((string)($_ENV['DB_BACKUP_EMAIL_TO'] ?? 'jonny.dev2020@gmail.com'));
$result = $emailTo !== '' ? $service->emailBackup($backup, $emailTo) : ['sent' => false, 'message' => 'DB_BACKUP_EMAIL_TO is empty.'];
$deleted = $service->cleanupOldBackups($backupPath, (int)($_ENV['DB_BACKUP_RETENTION_DAYS'] ?? 7));

echo '[' . date('Y-m-d H:i:s') . '] Backup: ' . $backup['filename'] . PHP_EOL;
echo '[' . date('Y-m-d H:i:s') . '] Email: ' . $result['message'] . PHP_EOL;
echo '[' . date('Y-m-d H:i:s') . '] Old backups deleted: ' . $deleted . PHP_EOL;

exit(($result['sent'] ?? false) ? 0 : 1);
