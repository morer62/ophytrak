<?php

namespace App\Services;

use Throwable;

class DailyAutomationService
{
    private string $projectRoot;
    private string $logFile;
    private bool $echoOutput;
    private $lockHandle = null;

    public function __construct(?string $projectRoot = null, bool $echoOutput = false)
    {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 2);
        $this->echoOutput = $echoOutput;
        $this->logFile = $this->resolveLogFile();
    }

    public function run(bool $dryRun = false, string $trigger = 'cli'): array
    {
        $startedAt = microtime(true);
        $result = [
            'success' => true,
            'dry_run' => $dryRun,
            'trigger' => $trigger,
            'started_at' => date('c'),
            'finished_at' => null,
            'duration_seconds' => null,
            'log_file' => $this->logFile,
            'message' => null,
            'results' => [
                'autopay_renewals' => null,
                'payment_retries' => null,
                'backup' => null,
                'checks' => [],
            ],
        ];

        $this->log('=== Ophyra daily cron started (' . $trigger . ', dry_run=' . ($dryRun ? '1' : '0') . ') ===');

        if (!$this->envBool('CRON_DAILY_ENABLED', true)) {
            $result['message'] = 'CRON_DAILY_ENABLED is false. Nothing to do.';
            $this->log($result['message']);
            return $this->finish($result, $startedAt);
        }

        if (!$dryRun && !$this->acquireLock()) {
            $result['success'] = false;
            $result['message'] = 'Daily cron is already running.';
            $this->log($result['message']);
            return $this->finish($result, $startedAt);
        }

        try {
            if ($dryRun) {
                $result['results']['checks'] = $this->runDryRunChecks();
                $result['message'] = 'Daily cron dry run completed. No charges, retries, emails or state changes were executed.';
                $this->log($result['message']);
                return $this->finish($result, $startedAt);
            }

            if ($this->envBool('CRON_AUTOPAY_ENABLED', true) || $this->envBool('CRON_RETRY_FAILED_PAYMENTS', true)) {
                $autopay = new AutopayService();

                if ($this->envBool('CRON_AUTOPAY_ENABLED', true)) {
                    $this->log('Processing automatic renewals.');
                    $result['results']['autopay_renewals'] = $autopay->processAllRenewals();
                    $this->log('Autopay renewals: processed=' . (int)$result['results']['autopay_renewals']['processed']
                        . ', successful=' . (int)$result['results']['autopay_renewals']['successful']
                        . ', failed=' . (int)$result['results']['autopay_renewals']['failed']);
                } else {
                    $this->log('CRON_AUTOPAY_ENABLED is false. Skipping automatic renewals.');
                }

                if ($this->envBool('CRON_RETRY_FAILED_PAYMENTS', true)) {
                    $this->log('Processing failed payment retry queue.');
                    $result['results']['payment_retries'] = $autopay->processRetries();
                    $this->log('Payment retries: processed=' . (int)$result['results']['payment_retries']['processed']
                        . ', successful=' . (int)$result['results']['payment_retries']['successful']
                        . ', failed=' . (int)$result['results']['payment_retries']['failed']
                        . ', abandoned=' . (int)$result['results']['payment_retries']['abandoned']);
                } else {
                    $this->log('CRON_RETRY_FAILED_PAYMENTS is false. Skipping retry queue.');
                }
            }

            $this->log('Affiliate payouts are manual only. No affiliate payout automation is executed.');
            $this->log('Manual payment methods require admin review. No manual payment is auto-approved.');

            if ($this->envBool('CRON_BACKUP_ENABLED', true)) {
                $backupService = new DatabaseBackupService($this->projectRoot);
                $backupPath = $backupService->defaultBackupPath();
                $this->log('Creating database backup in configured secure path.');
                $backup = $backupService->createBackup($backupPath);
                $this->log('Backup created: ' . $backup['filename'] . ' (' . number_format(((int)$backup['size_bytes']) / 1024 / 1024, 2) . ' MB)');

                $emailTo = trim((string)($_ENV['DB_BACKUP_EMAIL_TO'] ?? 'jonny.dev2020@gmail.com'));
                if ($emailTo !== '') {
                    $emailResult = $backupService->emailBackup($backup, $emailTo);
                    $backup['email'] = $emailResult;
                    $this->log($emailResult['message']);
                } else {
                    $this->log('DB_BACKUP_EMAIL_TO is empty. Backup email skipped.');
                }

                $retentionDays = (int)($_ENV['DB_BACKUP_RETENTION_DAYS'] ?? 7);
                $deleted = $backupService->cleanupOldBackups($backupPath, $retentionDays);
                $backup['cleanup_deleted'] = $deleted;
                $backup['retention_days'] = $retentionDays;
                $this->log("Old backup cleanup completed. Deleted={$deleted}, retention_days={$retentionDays}.");

                unset($backup['path']);
                $result['results']['backup'] = $backup;
            } else {
                $this->log('CRON_BACKUP_ENABLED is false. Skipping database backup.');
            }

            $result['message'] = 'Daily cron completed.';
        } catch (Throwable $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
            $result['error'] = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ];
            $this->log('ERROR: ' . $e->getMessage());
            $this->log('TRACE: ' . $e->getTraceAsString());
        } finally {
            $this->releaseLock();
        }

        return $this->finish($result, $startedAt);
    }

    private function runDryRunChecks(): array
    {
        $backupService = new DatabaseBackupService($this->projectRoot);
        $backupPath = $backupService->defaultBackupPath();
        $logDir = dirname($this->logFile);

        return [
            'cron_daily_enabled' => $this->envBool('CRON_DAILY_ENABLED', true),
            'autopay_enabled' => $this->envBool('CRON_AUTOPAY_ENABLED', true),
            'retry_failed_payments_enabled' => $this->envBool('CRON_RETRY_FAILED_PAYMENTS', true),
            'backup_enabled' => $this->envBool('CRON_BACKUP_ENABLED', true),
            'backup_email_to_configured' => trim((string)($_ENV['DB_BACKUP_EMAIL_TO'] ?? 'jonny.dev2020@gmail.com')) !== '',
            'database_url_configured' => trim((string)($_ENV['DATABASE_URL'] ?? '')) !== '',
            'log_directory_writable' => is_dir($logDir) && is_writable($logDir),
            'backup_directory' => $backupPath,
            'backup_directory_ready_or_creatable' => is_dir($backupPath) ? is_writable($backupPath) : is_writable(dirname($backupPath)),
            'lock_file' => $this->lockPath(),
            'note' => 'Dry run did not charge, retry, create backups, send email or update records.',
        ];
    }

    private function finish(array $result, float $startedAt): array
    {
        $duration = round(microtime(true) - $startedAt, 2);
        $result['finished_at'] = date('c');
        $result['duration_seconds'] = $duration;
        $this->log('=== Ophyra daily cron finished in ' . $duration . 's with success=' . ($result['success'] ? '1' : '0') . ' ===');
        return $result;
    }

    private function resolveLogFile(): string
    {
        $logDir = $_ENV['CRON_LOG_PATH'] ?? ($this->projectRoot . DIRECTORY_SEPARATOR . '.logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        return rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ophyra-daily-cron-' . date('Y-m-d') . '.log';
    }

    private function envBool(string $key, bool $default = true): bool
    {
        $value = $_ENV[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }

    private function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND);
        if ($this->echoOutput) {
            echo $line;
        }
    }

    private function acquireLock(): bool
    {
        $path = $this->lockPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $this->lockHandle = fopen($path, 'c');
        if (!$this->lockHandle) {
            return false;
        }

        if (!flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        ftruncate($this->lockHandle, 0);
        fwrite($this->lockHandle, (string)getmypid());
        return true;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    private function lockPath(): string
    {
        return $_ENV['CRON_LOCK_PATH']
            ?? dirname($this->logFile) . DIRECTORY_SEPARATOR . 'ophyra-daily-cron.lock';
    }
}
