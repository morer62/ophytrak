<?php
// Importar TranslationService para usar traducciones en el template
use App\Services\TranslationService;

// Establecer el locale ANTES de cualquier traducción
// Prioridad: locale pasado en datos > detectLocale (que usa el del owner)
if (isset($locale) && !empty($locale)) {
    TranslationService::setLocale($locale);
    // Forzar que se use este locale sin detectar de nuevo
    $_SESSION['locale'] = $locale;
} else {
    // Si no hay locale en datos, detectar (pero esto no debería pasar)
    TranslationService::detectLocale();
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(TranslationService::getCurrentLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_contract_signed_title')); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .success-message h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .order-details {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .order-details h3 {
            color: #495057;
            margin-top: 0;
            font-size: 20px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 500;
            color: #6c757d;
        }
        .detail-value {
            color: #495057;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white !important;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            margin: 20px 0;
            transition: transform 0.3s ease;
            border: none;
            font-size: 16px;
        }
        .cta-button:hover {
            transform: translateY(-2px);
        }
        .next-steps {
            background-color: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .next-steps h4 {
            color: #1976d2;
            margin-top: 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_contract_signed_header')); ?></h1>
            <p><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_contract_ready_payment')); ?></p>
        </div>
        
        <div class="content">
            <div class="success-message">
                <h2>🎉 <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_congratulations')); ?>!</h2>
                <p><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_contract_signed_message', ['order_id' => 'VNV341' . ($orderId ?? '')])); ?></p>
            </div>
            
            <div class="order-details">
                <h3>📋 <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_order_details')); ?></h3>
                
                <div class="detail-row">
                    <span class="detail-label"><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_order_id')); ?>:</span>
                    <span class="detail-value">VNV341<?php echo htmlspecialchars($orderId ?? ''); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_event_date')); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eventDate ?? ''); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_time')); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eventTime ?? ''); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_location')); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($location ?? ''); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_status')); ?>:</span>
                    <span class="detail-value" style="color: #28a745; font-weight: bold;">✅ <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_ready_payment')); ?></span>
                </div>
            </div>
            
            <div class="next-steps">
                <h4>🚀 <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_next_steps')); ?></h4>
                <p><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_contract_next_steps_message')); ?></p>
            </div>
            
            <div style="text-align: center;">
                <a href="<?php echo htmlspecialchars($orderUrl ?? '#'); ?>" class="cta-button">
                    💳 <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_proceed_payment')); ?>
                </a>
            </div>
            
            <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                <p style="margin: 0; font-size: 14px; color: #856404;">
                    <strong>📧 <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_important')); ?>:</strong> <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_keep_records')); ?>
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>VNV-Events</strong> - <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_contract_confirmation')); ?></p>
            <p><?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_thank_you')); ?></p>
            <p style="font-size: 12px; color: #adb5bd;">
                <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_auto_sent')); ?><br>
                <?php echo htmlspecialchars(TranslationService::trans('planner_hub.email_visit_us')); ?> <a href="https://ophyra.com/">ophyra.com</a>
            </p>
        </div>
    </div>
</body>
</html>

