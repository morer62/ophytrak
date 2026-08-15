<?php

use App\Repositories\AdminAccountActionsRepository;
use App\Repositories\UserRepository;
use App\Services\AddonBillingService;
use App\Services\CurrencyPricingService;
use App\Services\Level1MembershipOperationsService;
use App\Services\LoginService;
use App\Services\OphyraPricingService;
use App\Services\StripeService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->post(function () {
    $admin = LoginService::getSession();
    $adminId = (int)$admin->getId();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? $_POST['note'] ?? ''));
    $service = new Level1MembershipOperationsService();
    $customer = $service->getCustomerBilling($customerId);
    if (!$customer) {
        MessageUtil::setMessage('Customer not found.', 'Error', 'error');
        LocationUtils::redirectInternal('panel/module-manager');
    }

    if ($action === 'grant_courtesy') {
        $moduleSlug = trim((string)($_POST['module_slug'] ?? ''));
        $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
        if ($moduleSlug === '' || $expiresAt === '' || $reason === '') {
            MessageUtil::setMessage('Module, expiration date and reason are required.', 'Error', 'error');
            LocationUtils::reload();
        }
        $service->grantCourtesy($adminId, $customerId, $moduleSlug, $expiresAt, $reason);
        MessageUtil::setMessage('Courtesy module granted. Statement line will show Courtesy by Ophyra.');
        LocationUtils::redirectInternal('panel/customer-billing?customer_id=' . $customerId . '#statements');
    }

    if ($action === 'cancel_module_at_period_end') {
        $moduleSlug = trim((string)($_POST['module_slug'] ?? ''));
        $service->cancelModuleAtPeriodEnd($adminId, $customerId, $moduleSlug, $reason ?: 'Admin cancellation at period end');
        MessageUtil::setMessage('Module will remain active until the end of the current period.');
        LocationUtils::reload();
    }

    if ($action === 'reactivate_module_renewal') {
        $moduleSlug = trim((string)($_POST['module_slug'] ?? ''));
        $service->reactivateModuleRenewal($adminId, $customerId, $moduleSlug, $reason ?: 'Admin reactivated renewal');
        MessageUtil::setMessage('Module renewal reactivated.');
        LocationUtils::reload();
    }

    if ($action === 'send_payment_method_link') {
        (new AdminAccountActionsRepository())->add($adminId, $customerId, 'secure_payment_method_link_requested', $reason ?: 'Admin requested secure payment method link');
        MessageUtil::setMessage('Secure payment method link request recorded. Use the existing saved-card customer flow to collect tokenized payment data.');
        LocationUtils::reload();
    }

    if ($action === 'charge_now') {
        $amount = (float)($_POST['amount'] ?? $customer['amount_due']);
        if ($amount <= 0) {
            MessageUtil::setMessage('No payment due today.');
            LocationUtils::reload();
        }
        $card = $customer['cards'][0] ?? null;
        if (!$card || empty($card->token)) {
            MessageUtil::setMessage('No tokenized payment method is available.', 'Error', 'error');
            LocationUtils::reload();
        }
        $chargeId = (new StripeService())->createChargeV1((string)$card->token, $amount);
        if (!$chargeId) {
            (new AdminAccountActionsRepository())->add($adminId, $customerId, 'customer_billing_charge_failed', $reason, ['amount' => $amount]);
            MessageUtil::setMessage('Stripe charge failed. No membership was marked paid.', 'Error', 'error');
            LocationUtils::reload();
        }
        $newDue = date('Y-m-d', strtotime('+1 month'));
        (new UserRepository())->updateMembershipAndRegisterPayment(
            $customerId,
            $newDue,
            $amount,
            'Level 1 customer renewal charge ' . $chargeId,
            'level1_customer_renewal_' . $customerId . '_' . $chargeId,
            (new CurrencyPricingService())->createSnapshot($amount, OphyraPricingService::BASE_CURRENCY, OphyraPricingService::BASE_CURRENCY) + [
                'provider_type' => 'stripe',
                'payment_method' => 'admin_saved_card',
            ]
        );
        (new AdminAccountActionsRepository())->add($adminId, $customerId, 'customer_billing_charge_success', $reason, ['amount' => $amount, 'charge_id' => $chargeId]);
        MessageUtil::setMessage('Customer charged and membership renewed after gateway confirmation.');
        LocationUtils::reload();
    }

    if ($action === 'activate_paid_module') {
        $moduleSlug = trim((string)($_POST['module_slug'] ?? ''));
        $card = $customer['cards'][0] ?? null;
        if (!$card || empty($card->token)) {
            MessageUtil::setMessage('No tokenized payment method is available for paid activation.', 'Error', 'error');
            LocationUtils::reload();
        }
        $addonBilling = new AddonBillingService();
        $quote = $addonBilling->getAddonQuote($customerId, $moduleSlug, OphyraPricingService::BASE_CURRENCY);
        if (!$quote) {
            MessageUtil::setMessage('Invalid module selected.', 'Error', 'error');
            LocationUtils::reload();
        }
        $chargeId = (new StripeService())->createChargeV1((string)$card->token, (float)$quote['payment_amount']);
        if (!$chargeId) {
            (new AdminAccountActionsRepository())->add($adminId, $customerId, 'paid_module_charge_failed', $reason, ['module_slug' => $moduleSlug, 'amount' => $quote['payment_amount']]);
            MessageUtil::setMessage('Payment failed. Module was not activated.', 'Error', 'error');
            LocationUtils::reload();
        }
        $result = $addonBilling->activateAddonAfterPayment($customerId, $moduleSlug, (float)$quote['payment_amount'], 'level1_paid_module_' . $chargeId, $quote + ['provider_type' => 'stripe', 'payment_method' => 'admin_saved_card']);
        (new AdminAccountActionsRepository())->add($adminId, $customerId, 'paid_module_activated_by_admin', $reason, ['module_slug' => $moduleSlug, 'charge_id' => $chargeId, 'result' => $result]);
        MessageUtil::setMessage($result['success'] ? 'Paid module activated after confirmed charge.' : ($result['message'] ?? 'Module could not be activated.'));
        LocationUtils::reload();
    }

    MessageUtil::setMessage('Unsupported billing action.', 'Error', 'error');
    LocationUtils::reload();
});

$router->get(function () {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $service = new Level1MembershipOperationsService();
    $customer = $service->getCustomerBilling($customerId);
    if (!$customer) {
        return TemplateResponse::render(__DIR__ . '/index.twig', ['customer' => null]);
    }
    return TemplateResponse::render(__DIR__ . '/index.twig', ['customer' => $customer]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
