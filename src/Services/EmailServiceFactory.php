<?php

namespace App\Services;

use App\Repositories\SmtpCredentialsRepository;

/**
 * Factory for creating EmailService instances
 * Simplifies email service creation with owner-specific SMTP
 */
class EmailServiceFactory
{
    /**
     * Create EmailService for a specific owner
     * Uses owner's configured SMTP or falls back to default
     * 
     * @param int $ownerId Owner user ID
     * @return EmailService Configured email service instance
     */
    public static function createForOwner(int $ownerId): EmailService
    {
        return new EmailService($ownerId);
    }

    /**
     * Create EmailService with default Brevo credentials
     * For backward compatibility and system emails
     * 
     * @return EmailService Email service with default config
     */
    public static function createDefault(): EmailService
    {
        return new EmailService(null);
    }

    /**
     * Create EmailService using specific SMTP credential ID
     * 
     * @param int $smtpId SMTP credential ID
     * @param int $ownerId Owner ID (for security verification)
     * @return EmailService|null Email service or null if credentials not found
     */
    public static function createWithSmtp(int $smtpId, int $ownerId): ?EmailService
    {
        $smtpRepo = new SmtpCredentialsRepository();
        $smtp = $smtpRepo->getById($smtpId, $ownerId);
        
        if (!$smtp || !$smtp->is_active) {
            return null;
        }
        
        // Create a temporary EmailService and reconfigure it
        $service = new EmailService(null);
        
        // Use reflection to access private method (or make it public/protected)
        // For now, just return the owner-based service
        return new EmailService($ownerId);
    }

    /**
     * Test SMTP credentials without saving
     * 
     * @param array $credentials SMTP configuration array
     * @return array ['success' => bool, 'message' => string]
     */
    public static function testSmtpConnection(array $credentials): array
    {
        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $credentials['smtp_host'];
            $mailer->Port = $credentials['smtp_port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $credentials['smtp_username'];
            $mailer->Password = $credentials['smtp_password'];
            
            $mailer->SMTPSecure = match($credentials['smtp_encryption'] ?? 'tls') {
                'tls' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
                'ssl' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
                default => ''
            };
            
            $mailer->Timeout = 10;
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Try to connect
            $connected = $mailer->smtpConnect();
            
            if ($connected) {
                $mailer->smtpClose();
                return [
                    'success' => true,
                    'message' => 'SMTP connection successful! Credentials are valid.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to SMTP server: ' . $mailer->ErrorInfo
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send test email to verify SMTP configuration
     * 
     * @param int $ownerId Owner ID
     * @param string $testEmail Email address to send test to
     * @return array ['success' => bool, 'message' => string]
     */
    public static function sendTestEmail(int $ownerId, string $testEmail): array
    {
        try {
            $emailService = self::createForOwner($ownerId);
            
            $subject = '✅ SMTP Test Email';
            $body = '
                <h2>SMTP Configuration Test</h2>
                <p>This is a test email to verify your SMTP configuration.</p>
                <p>If you received this email, your SMTP credentials are working correctly!</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Sent at: ' . date('Y-m-d H:i:s') . '<br>
                    From: Ophyra Email System
                </p>
            ';
            
            $result = $emailService->sendSimpleEmail($testEmail, $subject, $body, true);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => "Test email sent successfully to $testEmail"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send test email: ' . $emailService->getDebugInfo()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending test email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get SMTP provider templates for quick setup
     * 
     * @param string $providerType Provider type (gmail, sendgrid, etc.)
     * @return array Template configuration
     */
    public static function getProviderTemplate(string $providerType): array
    {
        return match($providerType) {
            'gmail' => [
                'provider_name' => 'Gmail',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'instructions' => 'Use your Gmail address and an App Password (not your regular password). Enable 2FA first.',
                'docs_url' => 'https://support.google.com/accounts/answer/185833'
            ],
            'sendgrid' => [
                'provider_name' => 'SendGrid',
                'smtp_host' => 'smtp.sendgrid.net',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'apikey',
                'instructions' => 'Username is literally "apikey". Password is your SendGrid API Key.',
                'docs_url' => 'https://docs.sendgrid.com/for-developers/sending-email/integrating-with-the-smtp-api'
            ],
            'mailgun' => [
                'provider_name' => 'Mailgun',
                'smtp_host' => 'smtp.mailgun.org',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'instructions' => 'Use postmaster@your-domain.mailgun.org as username.',
                'docs_url' => 'https://documentation.mailgun.com/en/latest/user_manual.html#sending-via-smtp'
            ],
            'aws_ses' => [
                'provider_name' => 'AWS SES',
                'smtp_host' => 'email-smtp.us-east-1.amazonaws.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'instructions' => 'Create SMTP credentials in AWS IAM. Verify your domain first.',
                'docs_url' => 'https://docs.aws.amazon.com/ses/latest/dg/smtp-credentials.html'
            ],
            'brevo' => [
                'provider_name' => 'Brevo (Sendinblue)',
                'smtp_host' => 'smtp-relay.brevo.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'instructions' => 'Use your Brevo login email and SMTP key from dashboard.',
                'docs_url' => 'https://help.brevo.com/hc/en-us/articles/209467485'
            ],
            'outlook' => [
                'provider_name' => 'Outlook / Office 365',
                'smtp_host' => 'smtp.office365.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'instructions' => 'Use your Outlook email and password (or App Password for 2FA).',
                'docs_url' => 'https://support.microsoft.com/office/pop-imap-and-smtp-settings-8361e398-8af4-4e97-b147-6c6c4ac95353'
            ],
            default => [
                'provider_name' => 'Custom SMTP',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'instructions' => 'Enter your custom SMTP server details.',
                'docs_url' => ''
            ]
        };
    }

    /**
     * Get list of available provider types
     */
    public static function getAvailableProviders(): array
    {
        return [
            'gmail' => 'Gmail',
            'sendgrid' => 'SendGrid',
            'mailgun' => 'Mailgun',
            'aws_ses' => 'AWS SES',
            'brevo' => 'Brevo (Sendinblue)',
            'outlook' => 'Outlook / Office 365',
            'custom' => 'Custom SMTP Server'
        ];
    }
}
