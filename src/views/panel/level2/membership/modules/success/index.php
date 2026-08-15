<?php

use App\Repositories\Connection;
use App\Services\LoginService;
use App\Services\OphyraPricingService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;

$user = LoginService::getSession();
$billingOwnerId = (int)($user->getOwner() ?: $user->getId());
$paymentId = (int)($_GET['payment_id'] ?? 0);
$moduleSlug = (new OphyraPricingService())->legacySlug((string)($_GET['module_slug'] ?? ''));

if ($paymentId <= 0 || $moduleSlug === '') {
    MessageUtil::setMessage('Payment confirmation was not found.', 'Error', 'error');
    LocationUtils::redirectInternal('panel/membership/manage');
}

$db = new Connection();
$db->query("
    SELECT pa.*, m.name module_name, um.status module_status, um.renewal_at module_renewal_at, um.billing_status module_billing_status
    FROM payments_all pa
    LEFT JOIN modules m ON m.slug = pa.module_slug
    LEFT JOIN user_modules um ON um.id_user = pa.user_id AND um.module_slug = pa.module_slug
    WHERE pa.id = :payment_id
      AND pa.user_id = :user_id
      AND pa.concept = 'OphyraAddon'
      AND pa.module_slug = :module_slug
    LIMIT 1
");
$db->bind(':payment_id', $paymentId);
$db->bind(':user_id', $billingOwnerId);
$db->bind(':module_slug', $moduleSlug);
$payment = $db->fetchOne();

if (!$payment) {
    MessageUtil::setMessage('Payment confirmation was not found.', 'Error', 'error');
    LocationUtils::redirectInternal('panel/membership/manage');
}

echo TemplateResponse::render(__DIR__ . '/index.twig', [
    'payment' => $payment,
    'moduleSlug' => $moduleSlug,
    'paymentCurrency' => (string)($payment->payment_currency ?? 'USD'),
    'chargedAmountLabel' => (new OphyraPricingService())->format(
        (float)($payment->payment_amount ?? $payment->total ?? 0),
        (string)($payment->payment_currency ?? 'USD')
    ),
]);
