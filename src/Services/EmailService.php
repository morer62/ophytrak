<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Repositories\SmtpCredentialsRepository;
use App\Repositories\NotificationsRepository;

class EmailService
{
    private PHPMailer $mailer;
    private string $fromEmail;
    private string $fromName;
    private ?int $smtpCredentialId = null;

    /** When true, no SMTP is configured for the owner; we create notifications instead of sending. */
    private bool $noSmtpConfigured = false;

    /** Owner ID to create notification when no SMTP is configured (owner is the notification recipient). */
    private ?int $ownerIdForNotification = null;

    /**
     * @param int|null $ownerId Owner ID to load SMTP credentials from database
     *                          If null, uses default Brevo credentials (backward compatibility)
     */
    public function __construct(?int $ownerId = null)
    {
        $this->mailer = new PHPMailer(true);
        $this->ownerIdForNotification = $ownerId;

        // Try to load owner-specific SMTP credentials if ownerId is provided
        $smtpConfig = null;
        if ($ownerId !== null) {
            $smtpRepo = new SmtpCredentialsRepository();
            $smtpConfig = $smtpRepo->getDefaultByOwner($ownerId);
            
            if (!$smtpConfig || !$smtpConfig->is_active) {
                $smtpConfig = null;
                // A global SMTP configuration may still be used when explicitly enabled.
            }
        }
        
        if ($smtpConfig) {
            // Use owner's SMTP credentials
            $this->smtpCredentialId = $smtpConfig->id;
            $this->fromEmail = $smtpConfig->from_email;
            $this->fromName = $smtpConfig->from_name;
            $this->configureSMTP($smtpConfig);
        } elseif (filter_var((string)($_ENV['MAIL_ENABLED'] ?? 'true'), FILTER_VALIDATE_BOOLEAN) === false) {
            $this->noSmtpConfigured = true;
            $this->fromEmail = (string)($_ENV['MAIL_FROM_EMAIL'] ?? 'no-reply@localhost');
            $this->fromName = (string)($_ENV['MAIL_FROM_NAME'] ?? 'Ophyra');
        } else {
            // Fallback to .env SMTP credentials when no owner SMTP is configured
            $this->fromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'info@vnvevents.com';
            $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'VNV_Events';
            $this->configureSMTP(null);
            // Ensure we're using SMTP, not PHP mail()
            $this->noSmtpConfigured = false;
        }
    }

    /**
     * Create an in-app notification when email could not be sent because no SMTP is configured.
     */
    private function createNoSmtpNotification(string $context = ''): void
    {
        if ($this->ownerIdForNotification === null) {
            return;
        }
        try {
            $notificationsRepo = new NotificationsRepository();
            $message = 'Email could not be sent: no SMTP configuration set up. Please configure your email (SMTP) in Settings to send emails.';
            if ($context !== '') {
                $message .= ' Context: ' . $context;
            }
            $smtpSettingsUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/panel/planner-hub/settings/smtp';
            $notificationsRepo->add([
                'id_user' => $this->ownerIdForNotification,
                'mensaje' => $message,
                'link' => $smtpSettingsUrl,
                'leido' => 0,
            ]);
        } catch (\Throwable $e) {
            error_log('Failed to create no-SMTP notification: ' . $e->getMessage());
        }
    }

    /**
     * Configure SMTP settings
     * @param object|null $smtpConfig SMTP credentials from database or null for default
     */
    private function configureSMTP(?object $smtpConfig = null): void
    {
        if ($this->noSmtpConfigured) {
            return;
        }
        try {
            // Force SMTP mode (don't use PHP mail() function)
            $this->mailer->isSMTP();
            $this->mailer->SMTPAuth = true;
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Timeout = 15;
            $this->mailer->SMTPKeepAlive = false;
            $this->mailer->SMTPDebug = 0;
            // Ensure we're not using PHP mail() function
            $this->mailer->Mailer = 'smtp';
            
            if ($smtpConfig) {
                // Use owner's SMTP configuration
                $this->mailer->Host = $smtpConfig->smtp_host;
                $this->mailer->Port = $smtpConfig->smtp_port;
                $this->mailer->Username = $smtpConfig->smtp_username;
                $this->mailer->Password = $smtpConfig->smtp_password;
                
                // Set encryption
                $this->mailer->SMTPSecure = match($smtpConfig->smtp_encryption) {
                    'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                    'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                    default => ''
                };
                
                $this->fromEmail = $smtpConfig->from_email;
                $this->fromName = $smtpConfig->from_name;
            } else {
                // Use SMTP credentials from .env file
                $this->mailer->Host = $_ENV['MAIL_HOST'] ?? 'smtp-relay.brevo.com';
                $this->mailer->Port = (int)($_ENV['MAIL_PORT'] ?? 2525);
                $this->mailer->Username = $_ENV['MAIL_USERNAME'] ?? '92dd67001@smtp-brevo.com';
                $this->mailer->Password = $_ENV['MAIL_PASSWORD'] ?? '6qQhvfXZ2mzMECLs';
                
                // Set encryption from .env
                $encryption = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'tls');
                $this->mailer->SMTPSecure = match($encryption) {
                    'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                    'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                    default => PHPMailer::ENCRYPTION_STARTTLS
                };
                
                // Set from email and name from .env
                $this->fromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'info@vnvevents.com';
                $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'VNV_Events';
            }
            
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ];
            
            $this->mailer->setFrom($this->fromEmail, $this->fromName);

        } catch (Exception $e) {
            throw new Exception("Failed to configure email service: " . $e->getMessage());
        }
    }

    private function trySendFallback(callable $sendAction): bool
    {
        try {
            return $sendAction();
        } catch (Exception $e) {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = 465;
            $this->mailer->Timeout = 15;
            try {
                return $sendAction();
            } catch (Exception $ex) {
                throw new Exception($this->mailer->ErrorInfo ?: $ex->getMessage());
            }
        }
    }

    public function sendSimpleEmail(string $to, string $subject, string $body, bool $isHTML = true): bool
    {
        if ($this->noSmtpConfigured) {
            $this->createNoSmtpNotification("To: {$to}, Subject: {$subject}");
            return false;
        }
        $result = $this->trySendFallback(function () use ($to, $subject, $body, $isHTML) {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $body;
            $this->mailer->isHTML($isHTML);

            if (!$this->mailer->send()) {
                throw new Exception($this->mailer->ErrorInfo);
            }

            return true;
        });
        
        // Update usage stats if using custom SMTP
        if ($result && $this->smtpCredentialId) {
            $this->updateUsageStats();
        }
        
        return $result;
    }
    
    /**
     * Update SMTP usage statistics
     */
    private function updateUsageStats(): void
    {
        try {
            $smtpRepo = new SmtpCredentialsRepository();
            $smtpRepo->incrementEmailCount($this->smtpCredentialId);
        } catch (\Exception $e) {
            // Don't fail email send if stats update fails
            error_log("Failed to update SMTP stats: " . $e->getMessage());
        }
    }

    public function sendTemplateEmail(string $to, string $subject, string $templatePath, array $data = []): bool
    {
        if ($this->noSmtpConfigured) {
            $this->createNoSmtpNotification("To: {$to}, Subject: {$subject}");
            return false;
        }
        try {
            $body = $this->renderTemplate($templatePath, $data);
            return $this->sendSimpleEmail($to, $subject, $body, true);
        } catch (Exception $e) {
            throw new Exception("Template email failed for $to: " . $e->getMessage());
        }
    }

    public function sendEmailWithAttachment(string $to, string $subject, string $body, array $attachments = [], bool $isHTML = true): bool
    {
        if ($this->noSmtpConfigured) {
            $this->createNoSmtpNotification("To: {$to}, Subject: {$subject}");
            return false;
        }
        return $this->trySendFallback(function () use ($to, $subject, $body, $attachments, $isHTML) {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $body;
            $this->mailer->isHTML($isHTML);

            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $this->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }

            if (!$this->mailer->send()) {
                throw new Exception($this->mailer->ErrorInfo);
            }

            return true;
        });
    }

    public function sendBulkEmail(array $recipients, string $subject, string $body, bool $isHTML = true): array
    {
        if ($this->noSmtpConfigured) {
            $this->createNoSmtpNotification("Bulk email, Subject: {$subject}, Recipients: " . count($recipients));
            return array_fill_keys(
                array_map(fn($r) => is_array($r) ? $r['email'] : $r, $recipients),
                false
            );
        }
        $results = [];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $name  = is_array($recipient) ? ($recipient['name'] ?? '') : '';

            try {
                $this->mailer->clearAddresses();
                $this->mailer->clearAttachments();

                $this->mailer->addAddress($email, $name);
                $this->mailer->Subject = $subject;
                $this->mailer->Body    = $body;
                $this->mailer->isHTML($isHTML);

                if (!$this->mailer->send()) {
                    throw new Exception($this->mailer->ErrorInfo);
                }

                $results[$email] = true;
            } catch (Exception $e) {
                $results[$email] = false;
            }
        }

        return $results;
    }

    private function renderTemplate(string $templatePath, array $data = []): string
    {
        if (!file_exists($templatePath)) {
            throw new Exception("Template not found: $templatePath");
        }

        extract($data);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    public function getDebugInfo(): string
    {
        if ($this->noSmtpConfigured) {
            return 'No SMTP configuration set up for this account.';
        }
        return $this->mailer->ErrorInfo ?? '';
    }

    public function testSMTPConnection(): bool
    {
        if ($this->noSmtpConfigured) {
            return false;
        }
        try {
            return $this->mailer->smtpConnect([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}
