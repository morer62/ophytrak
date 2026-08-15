<?php

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseBackupService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 2);
    }

    public function createBackup(?string $backupPath = null): array
    {
        $backupPath = $backupPath ?: $this->defaultBackupPath();
        $this->ensureBackupDirectory($backupPath);

        $pdo = $this->createPdo();
        $databaseName = $this->resolveDatabaseName();
        $timestamp = date('Ymd_His');
        $plainPath = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "ophyra_{$databaseName}_{$timestamp}.sql";
        $gzPath = $plainPath . '.gz';

        $handle = gzopen($gzPath, 'wb9');
        if (!$handle) {
            throw new RuntimeException('Could not open backup file for writing.');
        }

        try {
            $this->write($handle, "-- Ophyra database backup\n");
            $this->write($handle, "-- Database: {$databaseName}\n");
            $this->write($handle, "-- Created at: " . date('Y-m-d H:i:s') . "\n\n");
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            $this->write($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($this->listTables($pdo) as $table) {
                $this->dumpTable($pdo, $handle, $table);
            }

            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($handle);
        }

        return [
            'success' => true,
            'path' => $gzPath,
            'filename' => basename($gzPath),
            'size_bytes' => filesize($gzPath) ?: 0,
            'database' => $databaseName,
        ];
    }

    public function emailBackup(array $backup, string $to): array
    {
        $path = (string)($backup['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Backup file was not found for email.');
        }

        $maxMb = max(1, (int)($_ENV['DB_BACKUP_EMAIL_MAX_ATTACHMENT_MB'] ?? 20));
        $maxBytes = $maxMb * 1024 * 1024;
        $sizeBytes = (int)($backup['size_bytes'] ?? filesize($path));
        $subject = 'Ophyra daily database backup - ' . date('Y-m-d');
        $body = '<p>Daily Ophyra database backup completed.</p>'
            . '<p><strong>Database:</strong> ' . htmlspecialchars((string)($backup['database'] ?? 'unknown')) . '</p>'
            . '<p><strong>File:</strong> ' . htmlspecialchars((string)($backup['filename'] ?? basename($path))) . '</p>'
            . '<p><strong>Size:</strong> ' . number_format($sizeBytes / 1024 / 1024, 2) . ' MB</p>';

        $email = EmailServiceFactory::createDefault();
        if ($sizeBytes <= $maxBytes) {
            $sent = $email->sendEmailWithAttachment($to, $subject, $body, [[
                'path' => $path,
                'name' => basename($path),
            ]], true);

            return [
                'sent' => $sent,
                'attached' => true,
                'message' => $sent ? 'Backup email sent with attachment.' : 'Backup email failed.',
            ];
        }

        $body .= '<p>The file was not attached because it is larger than the configured email limit.</p>'
            . '<p>The backup remains stored in the secure backup folder configured on the server.</p>';
        $sent = $email->sendSimpleEmail($to, $subject, $body, true);

        return [
            'sent' => $sent,
            'attached' => false,
            'message' => $sent ? 'Backup notice sent without attachment due to size.' : 'Backup notice email failed.',
        ];
    }

    public function cleanupOldBackups(string $backupPath, int $retentionDays): int
    {
        $retentionDays = max(1, $retentionDays);
        if (!is_dir($backupPath)) {
            return 0;
        }

        $deleted = 0;
        $cutoff = time() - ($retentionDays * 86400);
        foreach (glob(rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ophyra_*.sql.gz') ?: [] as $file) {
            if (is_file($file) && filemtime($file) !== false && filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function defaultBackupPath(): string
    {
        return $_ENV['DB_BACKUP_PATH']
            ?? dirname($this->projectRoot) . DIRECTORY_SEPARATOR . 'ophyra-db-backups';
    }

    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $this->write($handle, "\n-- Table: {$table}\n");
        $this->write($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");

        $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_ASSOC);
        $createSql = $create['Create Table'] ?? array_values($create)[1] ?? '';
        if ($createSql === '') {
            throw new RuntimeException("Could not read CREATE TABLE for {$table}.");
        }
        $this->write($handle, $createSql . ";\n\n");

        $stmt = $pdo->query("SELECT * FROM {$quotedTable}");
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta = $stmt->getColumnMeta($i);
            $columns[] = $this->quoteIdentifier((string)$meta['name']);
        }

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $values = array_map(fn($value) => $this->sqlValue($pdo, $value), $row);
            $this->write(
                $handle,
                "INSERT INTO {$quotedTable} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
            );
        }
    }

    private function createPdo(): PDO
    {
        $databaseUrl = (string)($_ENV['DATABASE_URL'] ?? '');
        if ($databaseUrl === '') {
            throw new RuntimeException('DATABASE_URL is not configured.');
        }

        $urlParts = str_contains($databaseUrl, '://') ? parse_url($databaseUrl) : false;
        if (is_array($urlParts) && isset($urlParts['scheme']) && $urlParts['scheme'] === 'mysql') {
            $host = $urlParts['host'] ?? 'localhost';
            $port = isset($urlParts['port']) ? ';port=' . (int)$urlParts['port'] : '';
            $db = isset($urlParts['path']) ? ltrim($urlParts['path'], '/') : '';
            $dsn = "mysql:host={$host}{$port};dbname={$db};charset=utf8mb4";
            $user = urldecode((string)($urlParts['user'] ?? ''));
            $pass = urldecode((string)($urlParts['pass'] ?? ''));
            return $this->pdo($dsn, $user, $pass);
        }

        $user = $_ENV['DB_USER'] ?? $_ENV['DATABASE_USER'] ?? null;
        $pass = $_ENV['DB_PASSWORD'] ?? $_ENV['DATABASE_PASSWORD'] ?? null;
        return $user !== null ? $this->pdo($databaseUrl, (string)$user, (string)$pass) : $this->pdo($databaseUrl);
    }

    private function pdo(string $dsn, ?string $user = null, ?string $pass = null): PDO
    {
        try {
            $pdo = $user === null ? new PDO($dsn) : new PDO($dsn, $user, $pass ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET NAMES 'utf8mb4'");
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Could not connect to database for backup: ' . $e->getMessage());
        }
    }

    private function listTables(PDO $pdo): array
    {
        $rows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $tables = [];
        foreach ($rows as $row) {
            if (($row[1] ?? '') === 'BASE TABLE') {
                $tables[] = (string)$row[0];
            }
        }
        return $tables;
    }

    private function resolveDatabaseName(): string
    {
        $databaseUrl = (string)($_ENV['DATABASE_URL'] ?? 'ophyra');
        if (preg_match('/dbname=([^;]+)/', $databaseUrl, $matches)) {
            return preg_replace('/[^A-Za-z0-9_-]+/', '_', $matches[1]) ?: 'ophyra';
        }

        $parts = parse_url($databaseUrl);
        if (is_array($parts) && !empty($parts['path'])) {
            return preg_replace('/[^A-Za-z0-9_-]+/', '_', ltrim($parts['path'], '/')) ?: 'ophyra';
        }

        return 'ophyra';
    }

    private function ensureBackupDirectory(string $backupPath): void
    {
        if (!is_dir($backupPath) && !mkdir($backupPath, 0750, true) && !is_dir($backupPath)) {
            throw new RuntimeException('Could not create backup directory.');
        }

        $htaccess = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function sqlValue(PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return $pdo->quote((string)$value);
    }

    private function write($handle, string $content): void
    {
        gzwrite($handle, $content);
    }
}
