<?php

use App\Repositories\StoreOrdersRepository;
use App\Repositories\StorePaymentsRepository;
use App\Repositories\StoreProductsRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StoreDeliveryLocationLogsRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\Payment\PayPalProvider;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

function getStoreOrderByTokenOrRedirect(string $token): object
{
    if ($token === '') {
        MessageUtil::setMessage("Order token not found.");
        LocationUtils::redirectInternal("store");
    }

    $ordersRepo = new StoreOrdersRepository();
    $order = $ordersRepo->getByPublicToken($token);

    if (!$order) {
        MessageUtil::setMessage("Order not found.");
        LocationUtils::redirectInternal("store");
    }

    $order = $ordersRepo->getFullOrderDetails((int)$order->id);

    if (!$order) {
        MessageUtil::setMessage("Order not found.");
        LocationUtils::redirectInternal("store");
    }

    return $order;
}

function getStoreOrderPaymentProvider(object $order): ?object
{
    $providerRepo = new PaymentProvidersRepository();
    $ownerId = (int)($order->id_owner ?? 0);
    if ($ownerId <= 0) {
        return null;
    }

    $provider = $providerRepo->getActiveProviderForOwner($ownerId);
    if (!$provider || empty($provider->is_verified) || !in_array($provider->provider_type, ['stripe', 'square', 'paypal'], true)) {
        return null;
    }

    return $provider;
}

function storeOrderChargeIsPaid(object $charge): bool
{
    if (!empty($charge->paid)) {
        return true;
    }

    $status = strtoupper((string)($charge->status ?? ''));
    return in_array($status, ['COMPLETED', 'SUCCEEDED', 'CAPTURED'], true);
}

function safeStorePaymentRawResponse(object $charge): ?string
{
    try {
        $raw = $charge->raw ?? $charge;
        $encoded = json_encode($raw, JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $encoded ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}

$router->get(function () {

    $token = trim($_GET['token'] ?? '');
    $userRepo = new UserRepository();
    $workflowRepo = new StoreOrderWorkflowRepository();
    $locationRepo = new StoreDeliveryLocationLogsRepository();
    $profileRepo = new InstitutionProfileRepository();
    $order = getStoreOrderByTokenOrRedirect($token);
    $workflow = $workflowRepo->getByOrder((int)$order->id);
    $preparationResponsible = !empty($workflow->kitchen_user_id)
        ? $userRepo->getOneWithoutOwnership(['id' => (int)$workflow->kitchen_user_id], ['id', 'name', 'lastname'])
        : null;
    $deliveryResponsible = !empty($workflow->delivery_user_id)
        ? $userRepo->getOneWithoutOwnership(['id' => (int)$workflow->delivery_user_id], ['id', 'name', 'lastname'])
        : null;
    $activeProvider = getStoreOrderPaymentProvider($order);
    $currencyCode = strtoupper((string)($order->currency ?? ($activeProvider->currency ?? 'USD')));

    $existingUser = null;
    if (!empty($order->guest_email)) {
        $existingUser = $userRepo->getOne(['email' => $order->guest_email]);
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "order" => $order,
        "token" => $token,
        "existingUser" => $existingUser,
        "workflow" => $workflow,
        "preparationResponsible" => $preparationResponsible,
        "deliveryResponsible" => $deliveryResponsible,
        "latestLocation" => $locationRepo->getLatestByOrder((int)$order->id),
        "sellerProfile" => $profileRepo->getByOwner((int)$order->id_owner),
        "paymentReady" => (bool)$activeProvider,
        "activeProviderType" => $activeProvider->provider_type ?? '',
        "stripePublishableKey" => ($activeProvider && $activeProvider->provider_type === 'stripe') ? ($activeProvider->public_key ?? '') : '',
        "squareApplicationId" => ($activeProvider && $activeProvider->provider_type === 'square') ? ($activeProvider->public_key ?? '') : '',
        "squareLocationId" => ($activeProvider && $activeProvider->provider_type === 'square') ? ($activeProvider->location_id ?? '') : '',
        "squareEnvironment" => ($activeProvider && $activeProvider->provider_type === 'square') ? ($activeProvider->environment ?? 'sandbox') : 'sandbox',
        "paypalClientId" => ($activeProvider && $activeProvider->provider_type === 'paypal') ? ($activeProvider->api_key ?? '') : '',
        "currencyCode" => $currencyCode,
        "baseUrl" => rtrim((string)($_ENV['APP_URL'] ?? ''), '/')
    ]);
});

$router->post(function () {
    $token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
    $action = trim((string)($_POST['action'] ?? 'pay_store_order'));
    $order = getStoreOrderByTokenOrRedirect($token);
    $activeProvider = getStoreOrderPaymentProvider($order);

    if (!$activeProvider) {
        if ($action === 'create_paypal_order') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Payment provider is not configured for this store.']);
            exit;
        }

        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "order" => $order,
            "token" => $token,
            "existingUser" => null,
            "workflow" => null,
            "latestLocation" => null,
            "sellerProfile" => (new InstitutionProfileRepository())->getByOwner((int)$order->id_owner),
            "paymentReady" => false,
            "paymentError" => "Payment provider is not configured for this store.",
            "activeProviderType" => "",
            "currencyCode" => strtoupper((string)($order->currency ?? 'USD')),
            "baseUrl" => rtrim((string)($_ENV['APP_URL'] ?? ''), '/')
        ]);
    }

    if ((string)$order->payment_status === StoreOrdersRepository::PAYMENT_PAID) {
        LocationUtils::redirectInternal('store/order-access?token=' . urlencode($token));
    }

    $amount = round((float)($order->total ?? 0), 2);
    if ($amount <= 0) {
        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "order" => $order,
            "token" => $token,
            "existingUser" => null,
            "workflow" => null,
            "latestLocation" => null,
            "sellerProfile" => (new InstitutionProfileRepository())->getByOwner((int)$order->id_owner),
            "paymentReady" => false,
            "paymentError" => "This order does not have a payable amount.",
            "activeProviderType" => $activeProvider->provider_type ?? "",
            "currencyCode" => strtoupper((string)($order->currency ?? ($activeProvider->currency ?? 'USD'))),
            "baseUrl" => rtrim((string)($_ENV['APP_URL'] ?? ''), '/')
        ]);
    }

    if ($action === 'create_paypal_order') {
        header('Content-Type: application/json');
        if ($activeProvider->provider_type !== 'paypal') {
            echo json_encode(['success' => false, 'error' => 'PayPal is not configured for this store.']);
            exit;
        }

        $provider = PaymentProviderFactory::create($activeProvider);
        if (!$provider instanceof PayPalProvider) {
            echo json_encode(['success' => false, 'error' => 'Invalid PayPal provider.']);
            exit;
        }

        $baseUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        $paypalOrder = $provider->createOrder($amount, [
            'description' => 'Store order #' . (int)$order->id,
            'return_url' => $baseUrl . '/store/order-access?token=' . urlencode($token),
            'cancel_url' => $baseUrl . '/store/order-access?token=' . urlencode($token),
            'brand_name' => 'Ophyra Store',
        ]);

        if (!$paypalOrder) {
            echo json_encode(['success' => false, 'error' => 'Could not create PayPal order.']);
            exit;
        }

        echo json_encode(['success' => true, 'orderId' => $paypalOrder->id]);
        exit;
    }

    $customerToken = trim((string)($_POST['customer_token'] ?? ''));
    $customerEmail = strtolower(trim((string)($_POST['customer_email'] ?? $order->guest_email ?? '')));
    $payerName = trim((string)($_POST['customer_name'] ?? $order->guest_name ?? ''));

    if ($customerToken === '' || $customerEmail === '') {
        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "order" => $order,
            "token" => $token,
            "existingUser" => null,
            "workflow" => null,
            "latestLocation" => null,
            "sellerProfile" => (new InstitutionProfileRepository())->getByOwner((int)$order->id_owner),
            "paymentReady" => true,
            "paymentError" => "Missing payment data. Please enter your email and payment details.",
            "activeProviderType" => $activeProvider->provider_type ?? "",
            "stripePublishableKey" => ($activeProvider->provider_type === 'stripe') ? ($activeProvider->public_key ?? '') : '',
            "squareApplicationId" => ($activeProvider->provider_type === 'square') ? ($activeProvider->public_key ?? '') : '',
            "squareLocationId" => ($activeProvider->provider_type === 'square') ? ($activeProvider->location_id ?? '') : '',
            "squareEnvironment" => ($activeProvider->provider_type === 'square') ? ($activeProvider->environment ?? 'sandbox') : 'sandbox',
            "paypalClientId" => ($activeProvider->provider_type === 'paypal') ? ($activeProvider->api_key ?? '') : '',
            "currencyCode" => strtoupper((string)($order->currency ?? ($activeProvider->currency ?? 'USD'))),
            "baseUrl" => rtrim((string)($_ENV['APP_URL'] ?? ''), '/')
        ]);
    }

    $provider = PaymentProviderFactory::create($activeProvider);
    $charge = $provider->chargeCustomer($customerToken, $amount, [
        'description' => 'Store order #' . (int)$order->id,
        'note' => 'Store order #' . (int)$order->id,
        'reference_id' => 'STORE-' . (int)$order->id,
        'customer_email' => $customerEmail,
    ]);

    if (!$charge || !storeOrderChargeIsPaid($charge)) {
        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "order" => $order,
            "token" => $token,
            "existingUser" => null,
            "workflow" => null,
            "latestLocation" => null,
            "sellerProfile" => (new InstitutionProfileRepository())->getByOwner((int)$order->id_owner),
            "paymentReady" => true,
            "paymentError" => "Payment could not be processed. Please try again.",
            "activeProviderType" => $activeProvider->provider_type ?? "",
            "stripePublishableKey" => ($activeProvider->provider_type === 'stripe') ? ($activeProvider->public_key ?? '') : '',
            "squareApplicationId" => ($activeProvider->provider_type === 'square') ? ($activeProvider->public_key ?? '') : '',
            "squareLocationId" => ($activeProvider->provider_type === 'square') ? ($activeProvider->location_id ?? '') : '',
            "squareEnvironment" => ($activeProvider->provider_type === 'square') ? ($activeProvider->environment ?? 'sandbox') : 'sandbox',
            "paypalClientId" => ($activeProvider->provider_type === 'paypal') ? ($activeProvider->api_key ?? '') : '',
            "currencyCode" => strtoupper((string)($order->currency ?? ($activeProvider->currency ?? 'USD'))),
            "baseUrl" => rtrim((string)($_ENV['APP_URL'] ?? ''), '/')
        ]);
    }

    $currencyCode = strtoupper((string)($charge->currency ?? $order->currency ?? $activeProvider->currency ?? 'USD'));
    $paymentsRepo = new StorePaymentsRepository();
    $paymentsRepo->addCompatible([
        'id_owner' => (int)$order->id_owner,
        'id_store_order' => (int)$order->id,
        'id_user' => !empty($order->id_user) ? (int)$order->id_user : null,
        'payment_method' => $activeProvider->provider_type,
        'payment_type' => StorePaymentsRepository::TYPE_FULL,
        'external_payment_id' => $charge->id ?? null,
        'external_reference' => 'STORE-' . (int)$order->id,
        'amount' => $amount,
        'currency' => $currencyCode,
        'status' => StorePaymentsRepository::STATUS_PAID,
        'payer_name' => $payerName ?: null,
        'payer_email' => $customerEmail ?: null,
        'raw_response' => safeStorePaymentRawResponse($charge),
        'paid_at' => date('Y-m-d H:i:s'),
        'base_amount' => $amount,
        'base_currency' => $currencyCode,
        'display_amount' => $amount,
        'display_currency' => $currencyCode,
        'payment_amount' => $amount,
        'payment_currency' => $currencyCode,
        'exchange_rate' => 1,
        'exchange_rate_source' => 'direct',
        'provider_type' => $activeProvider->provider_type,
        'payment_provider_id' => (int)($activeProvider->id ?? 0),
        'id_user_business' => (int)$order->id_owner,
        'site_key' => (string)($order->site_key ?? ''),
    ]);

    $ordersRepo = new StoreOrdersRepository();
    $ordersRepo->markAsPaid((int)$order->id);
    if (in_array((string)$order->status, [StoreOrdersRepository::STATUS_NEW, StoreOrdersRepository::STATUS_CONFIRMED], true)) {
        $ordersRepo->updateStatus((int)$order->id, StoreOrdersRepository::STATUS_PROCESSING);
    }

    try {
        (new StoreProductsRepository())->decrementStockForItems($order->items ?? []);
    } catch (\Throwable $e) {
        error_log('Store order public payment stock decrement failed: ' . $e->getMessage());
    }

    LocationUtils::redirectInternal('store/order-access?token=' . urlencode($token) . '&paid=1');
});

$router->run();
