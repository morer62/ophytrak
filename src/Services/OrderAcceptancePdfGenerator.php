<?php

namespace App\Services;

use App\Repositories\OrdersRepository;
use App\Repositories\UserRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\OrdersAcceptanceContractTemplateRepository;
use App\Services\TranslationService;
use App\Utils\FileUtils;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;

class OrderAcceptancePdfGenerator
{
    /**
     * Generates the order acceptance PDF, uploads it, and returns the file URL and content hash for audit.
     * Hash is computed over the final PDF bytes (tamper-evident; DocuSign-style integrity).
     *
     * @return array{file_path: string, hash: string}
     */
    public static function generateAndSave(int $orderId, ?string $userTimestamp = null, ?string $signatureImagePath = null): array
    {
        $orderRepo = new OrdersRepository();
        $userRepo = new UserRepository();
        $institutionRepo = new InstitutionProfileRepository();

        $order = $orderRepo->getByIdWithoutOwnershipCheck($orderId);
        if ($order) {
            $order = (object)$order;
        }
        if (!$order) {
            throw new Exception("Order not found");
        }

        // Obtener el owner y su system_language para generar el PDF en el idioma correcto
        $owner = $userRepo->getOne(["id" => $order->id_owner]);
        $systemLanguage = $owner->system_language ?? 'en';
        
        // Establecer el idioma del sistema antes de generar el PDF
        TranslationService::setLocale($systemLanguage);

        $client = $userRepo->getOne(["id" => $order->id_client]);
        $institution = $institutionRepo->getByOwner($order->id_owner);
        $institution = $institution ? json_decode(json_encode($institution), true) : [];

        $ip = ($_SERVER["REMOTE_ADDR"] === '::1') ? '127.0.0.1' : ($_SERVER["REMOTE_ADDR"] ?? 'Unknown');
        $browser = $_SERVER["HTTP_USER_AGENT"] ?? 'Unknown';

        if ($userTimestamp) {
            $timestamp = date("F j, Y - g:i A", strtotime($userTimestamp));
        } else {
            $timestamp = date("F j, Y - g:i A");
        }

        $logoBase64 = '';
        $institutionName = $institution["name"] ?? "";
        $institutionAddress = $institution["address"] ?? "";
        $institutionPhone = $institution["phone"] ?? "";
        $institutionEmail = $institution["email"] ?? "";

        if (!empty($institution['logo_path'])) {
            $logoPath = $institution['logo_path'];
            if (strpos($logoPath, 'res.cloudinary.com') !== false && strpos($logoPath, 'http') === false) {
                $logoPath = 'https://' . ltrim($logoPath, '/');
            }
            try {
                $context = stream_context_create([
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    'http' => ['ignore_errors' => true, 'timeout' => 10]
                ]);
                $imageData = @file_get_contents($logoPath, false, $context);
                if ($imageData !== false) {
                    $imageInfo = @getimagesizefromstring($imageData);
                    if ($imageInfo) {
                        $mimeType = $imageInfo['mime'];
                        $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    }
                }
            } catch (\Exception $e) {
                error_log('Error loading logo for order acceptance PDF: ' . $e->getMessage());
            }
        }

        $clientName = $client ? (($client->name ?? '') . ' ' . ($client->lastname ?? '')) : 'N/A';
        $clientEmail = $client ? ($client->email ?? '') : '';
        $clientPhone = $client ? ($client->phone ?? '') : '';

        $templateRepo = new OrdersAcceptanceContractTemplateRepository();
        $template = $templateRepo->getOrCreateByOwner($order->id_owner);
        $acceptanceText = $template->content ?? "I hereby accept and confirm that I have received the order in accordance with the amount paid. I acknowledge that the services and items corresponding to Order #VNV-341" . $order->id . " have been received or are agreed as delivered as per the payment made.";
        
        $acceptanceText = str_replace('#ORDER_ID#', 'VNV-341' . $order->id, $acceptanceText);
        
        $acceptanceTextForPdf = $acceptanceText;
        $acceptanceTextForPdf = html_entity_decode($acceptanceTextForPdf, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $acceptanceTextForPdf = str_replace(['<p>', '</p>'], ['', '<br>'], $acceptanceTextForPdf);
        $acceptanceTextForPdf = str_replace(['<br>', '<br/>', '<br />'], '<br>', $acceptanceTextForPdf);
        $acceptanceTextForPdf = strip_tags($acceptanceTextForPdf, '<br>');
        $acceptanceTextForPdf = preg_replace('/\n\s*\n/', '<br><br>', $acceptanceTextForPdf);
        $acceptanceTextForPdf = trim($acceptanceTextForPdf);

        $signatureBase64 = '';
        if ($signatureImagePath) {
            try {
                $imageData = false;
                
                if (filter_var($signatureImagePath, FILTER_VALIDATE_URL)) {
                    $context = stream_context_create([
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                        'http' => ['ignore_errors' => true, 'timeout' => 10]
                    ]);
                    $imageData = @file_get_contents($signatureImagePath, false, $context);
                    if ($imageData === false) {
                        error_log('Failed to download signature image from URL: ' . $signatureImagePath);
                    }
                } elseif (file_exists($signatureImagePath)) {
                    $imageData = @file_get_contents($signatureImagePath);
                    if ($imageData === false) {
                        error_log('Failed to read signature image from local path: ' . $signatureImagePath);
                    }
                } else {
                    error_log('Signature image path does not exist and is not a valid URL: ' . $signatureImagePath);
                }
                
                if ($imageData !== false && strlen($imageData) > 0) {
                    $imageInfo = @getimagesizefromstring($imageData);
                    if ($imageInfo) {
                        $mimeType = $imageInfo['mime'];
                        $signatureBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                        error_log('Signature image successfully converted to base64. Size: ' . strlen($imageData) . ' bytes, MIME: ' . $mimeType);
                    } else {
                        error_log('Invalid image data - getimagesizefromstring returned false');
                    }
                } else {
                    error_log('No image data retrieved for signature path: ' . $signatureImagePath);
                }
            } catch (\Exception $e) {
                error_log('Error loading signature image for order acceptance PDF: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
        } else {
            error_log('No signature image path provided to OrderAcceptancePdfGenerator');
        }

        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { margin: 50px 50px; }
                body { font-family: Arial, sans-serif; color: #152026; font-size: 10px; margin: 0; padding: 0; }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                .company-info { color: #152026; font-size: 9px; }
                .company-name { font-weight: bold; font-size: 10px; }
                .invoice-info { text-align: right; color: #152026; font-size: 9px; }
                .invoice-number { font-weight: bold; font-size: 10px; }
                .hr-thick { height: 4px; background: #4c6b7d; margin: 15px 0; }
                .title { font-size: 18px; font-weight: bold; color: #152026; margin: 20px 0 10px; }
                .subtitle { font-size: 10px; color: #152026; margin-bottom: 15px; }
                .acceptance-box { margin: 20px 0; padding: 15px; border: 1px solid #d6dde3; background: #f8f9fa; line-height: 1.6; font-size: 11px; }
                .signature-block { margin-top: 25px; padding-top: 15px; border-top: 1px solid #d6dde3; }
                .signature-label { font-weight: bold; font-size: 10px; margin-bottom: 5px; }
                .meta { margin-top: 25px; font-size: 8px; color: #6b7a85; }
            </style>
        </head>
        <body>
            <div class="header">
                <div style="display: flex; align-items: center;">
                    ' . (!empty($logoBase64) ? '<img src="' . $logoBase64 . '" alt="Logo" style="height: 40px; margin-right: 15px;">' : '') . '
                    <div class="company-info">
                        <div class="company-name">' . htmlspecialchars($institutionName) . '</div>
                        <div>' . htmlspecialchars($institutionEmail) . ' | ' . htmlspecialchars($institutionPhone) . '</div>
                    </div>
                </div>
                <div class="invoice-info">
                    <div class="invoice-number">' . TranslationService::trans('planner_hub.pdf_order_acceptance') . ' #VNV-341' . (int)$order->id . '</div>
                    <div>' . TranslationService::trans('planner_hub.pdf_signed') . '</div>
                    <div>' . htmlspecialchars($timestamp) . '</div>
                </div>
            </div>

            <div class="hr-thick"></div>

            <div class="title">' . TranslationService::trans('planner_hub.pdf_order_receipt_acceptance') . '</div>
            <div class="subtitle">' . str_replace(':order_id', 'VNV-341' . (int)$order->id, TranslationService::trans('planner_hub.pdf_order_receipt_acceptance_subtitle')) . '</div>

            <div style="margin-bottom: 15px;">
                <div style="font-weight: bold; margin-bottom: 5px;">' . TranslationService::trans('planner_hub.pdf_client') . '</div>
                <div>' . htmlspecialchars($clientName) . '</div>
                <div>' . htmlspecialchars($clientEmail) . '</div>
                <div>' . htmlspecialchars($clientPhone) . '</div>
            </div>
            <div style="margin-bottom: 15px;">
                <div style="font-weight: bold; margin-bottom: 5px;">' . TranslationService::trans('planner_hub.pdf_event') . '</div>
                <div>' . date("F j, Y", strtotime($order->event_date)) . ' — ' . htmlspecialchars($order->address ?? '') . '</div>
            </div>

            <div class="acceptance-box">
                ' . $acceptanceTextForPdf . '
            </div>

            <div class="signature-block">
                <div class="signature-label">' . TranslationService::trans('planner_hub.pdf_signature_acceptance') . '</div>
                ' . (!empty($signatureBase64) ? 
                    '<div style="margin: 15px 0;">
                        <img src="' . $signatureBase64 . '" alt="' . TranslationService::trans('planner_hub.pdf_client_signature') . '" style="max-width: 300px; max-height: 100px; border: 1px solid #d6dde3; padding: 5px; background: white;">
                    </div>' : 
                    '<div style="font-family: \'Great Vibes\', cursive; font-size: 24px; color: #152026; margin: 15px 0; min-height: 50px;">
                        ' . TranslationService::trans('planner_hub.pdf_electronically_signed_client') . '
                    </div>') . '
                <div>IP: ' . htmlspecialchars($ip) . ' — ' . htmlspecialchars($timestamp) . '</div>
            </div>

            <div class="certificate" style="margin-top: 24px; padding: 12px; border: 1px solid #4c6b7d; background: #f0f4f8; font-size: 8px; color: #152026;">
                <div style="font-weight: bold; margin-bottom: 8px;">' . TranslationService::trans('planner_hub.pdf_signature_certificate') . '</div>
                <div>' . TranslationService::trans('planner_hub.pdf_document_id') . ': VNV-341' . (int)$order->id . ' | ' . TranslationService::trans('planner_hub.pdf_order_acceptance') . '</div>
                <div>' . TranslationService::trans('planner_hub.pdf_signer') . ': ' . htmlspecialchars($clientName) . ' | ' . htmlspecialchars($clientEmail) . '</div>
                <div>' . TranslationService::trans('planner_hub.pdf_signed_at') . ': ' . htmlspecialchars($timestamp) . ' (' . TranslationService::trans('planner_hub.pdf_signer_local_time') . ')</div>
                <div>' . TranslationService::trans('planner_hub.pdf_ip_address') . ': ' . htmlspecialchars($ip) . '</div>
                <div>' . TranslationService::trans('planner_hub.pdf_user_agent') . ': ' . htmlspecialchars(mb_substr($browser, 0, 200)) . '</div>
                <div style="margin-top: 6px;">' . TranslationService::trans('planner_hub.pdf_electronic_signature_disclaimer') . '</div>
            </div>

            <div class="meta">
                <hr style="height:1px; background:#d6dde3; border:0; margin:15px 0;"/>
                <div>' . TranslationService::trans('planner_hub.pdf_electronic_signature_legal') . '</div>
                <div>' . TranslationService::trans('planner_hub.pdf_browser') . ': ' . htmlspecialchars($browser) . '</div>
            </div>
        </body>
        </html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $content = $dompdf->output();
        $contentHash = hash('sha256', $content);
        $filePath = FileUtils::saveFileFromContent($content, 'documents_order_acceptance', 'pdf');

        return [
            'file_path' => $filePath,
            'hash'      => $contentHash,
        ];
    }
}
