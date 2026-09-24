<?php

namespace App\Services;

use App\Repositories\StoreOrderItemsRepository;
use App\Repositories\StoreOrdersRepository;
use App\Repositories\StorePaymentsRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Repositories\StoreProductsRepository;
use App\Repositories\StoreProductVariationsRepository;
use App\Repositories\UserCardsRepository;
use App\Repositories\UserRepository;
use App\Utils\FileUtils;

class StoreManualOrderService
{
    public function getProductsForOwner(int $ownerId): array
    {
        $productsRepo = new StoreProductsRepository();
        $variationsRepo = new StoreProductVariationsRepository();
        $products = $productsRepo->getScopedByOwner($ownerId, 500);

        foreach ($products as $product) {
            $product->effective_price = $productsRepo->getEffectivePrice($product);
            $product->variations = $variationsRepo->getDetailedByProduct((int)$product->id, $ownerId);
            foreach ($product->variations as $variation) {
                $variation->effective_price = $variationsRepo->getEffectivePrice($variation);
            }
        }

        return $products;
    }

    public function getClients(int $ownerId): array
    {
        return (new UserRepository())->getActiveClientsByOwner($ownerId);
    }

    public function createFromRequest(int $ownerId, array $post, array $files = [], ?int $actorId = null): array
    {
        $customerName = trim((string)($post['guest_name'] ?? ''));
        $customerEmail = strtolower(trim((string)($post['guest_email'] ?? '')));
        $customerPhone = trim((string)($post['guest_phone'] ?? ''));
        $clientId = (int)($post['id_user'] ?? 0);
        $paymentMode = trim((string)($post['payment_mode'] ?? 'send_link'));
        $allowedPaymentModes = ['send_link', 'saved_card', 'manual_proof', 'mark_paid'];
        if (!in_array($paymentMode, $allowedPaymentModes, true)) {
            return ['success' => false, 'message' => 'Select a valid payment option.'];
        }
        $notes = trim((string)($post['notes'] ?? ''));
        $shippingInstructions = trim((string)($post['shipping_instructions'] ?? ''));
        if ($shippingInstructions !== '') {
            $notes = trim($notes . "\n\nDelivery instructions: " . $shippingInstructions);
        }
        $discount = max(0, round((float)($post['discount'] ?? 0), 2));
        $deliveryFee = max(0, round((float)($post['delivery_fee'] ?? 0), 2));
        $feeType = in_array((string)($post['fee_type'] ?? 'none'), ['none', 'percentage', 'fixed'], true)
            ? (string)$post['fee_type'] : 'none';
        $feeValue = max(0, round((float)($post['fee_value'] ?? 0), 4));
        if ($feeType === 'percentage') {
            $feeValue = min(100, $feeValue);
        }
        $feeLabel = trim((string)($post['fee_label'] ?? '')) ?: 'Tax / processing fee';

        if ($ownerId <= 0) {
            return ['success' => false, 'message' => 'No business workspace was selected.'];
        }
        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Add a valid customer email.'];
        }

        $userRepo = new UserRepository();
        $client = null;
        if ($clientId > 0) {
            $client = $userRepo->getActiveClientByOwnerAndId($ownerId, $clientId);
            if (!$client) {
                return ['success' => false, 'message' => 'The selected customer does not belong to this business workspace.'];
            }
            // A selected customer is authoritative; do not permit a crafted POST
            // to link that id to another tenant's name or email.
            $customerEmail = strtolower(trim((string)$client->email));
            $customerName = trim((string)($client->name ?? '') . ' ' . (string)($client->lastname ?? ''));
            $customerPhone = trim((string)($client->phone ?? $customerPhone));
        } else {
            $client = $userRepo->getActiveClientByOwnerAndEmail($ownerId, $customerEmail);
            $clientId = $client ? (int)$client->id : 0;
        }
        if ($paymentMode === 'send_link' && !$this->hasConnectedOnlinePaymentProvider($ownerId)) {
            return [
                'success' => false,
                'message' => 'Connect and verify Stripe, Square or PayPal before sending a payment link. Otherwise use a manual payment option.'
            ];
        }
        if ($customerName === '' && $client) {
            $customerName = trim((string)($client->name ?? '') . ' ' . (string)($client->lastname ?? ''));
        }
        if ($customerName === '') {
            $customerName = 'Store customer';
        }

        $items = $this->normalizeItems($ownerId, $post);
        if (!$items) {
            return ['success' => false, 'message' => 'Add at least one valid product to the order.'];
        }
        $stockItems = array_map(static fn(array $item) => (object)$item, $items);
        $requiresImmediateStock = in_array($paymentMode, ['manual_proof', 'mark_paid', 'saved_card'], true);
        if ($requiresImmediateStock && !(new StoreProductsRepository())->hasStockForItems($stockItems)) {
            return ['success' => false, 'message' => 'One or more selected products do not have enough stock for this order.'];
        }

        $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
        $feeBase = max(0, round($subtotal + $deliveryFee - $discount, 2));
        $feeAmount = $feeType === 'percentage'
            ? round($feeBase * $feeValue / 100, 2)
            : ($feeType === 'fixed' ? round($feeValue, 2) : 0.0);
        $total = max(0, round($feeBase + $feeAmount, 2));
        if ($total <= 0) {
            return ['success' => false, 'message' => 'The order total must be greater than zero.'];
        }

        $ordersRepo = new StoreOrdersRepository();
        $paidManually = in_array($paymentMode, ['manual_proof', 'mark_paid'], true);
        $orderCreated = $ordersRepo->addCompatible([
            'id_owner' => $ownerId,
            'id_user' => $clientId > 0 ? $clientId : null,
            'id_cart' => null,
            'public_token' => $ordersRepo->generatePublicToken(),
            'guest_name' => $customerName,
            'guest_email' => $customerEmail,
            'guest_phone' => $customerPhone,
            'city' => trim((string)($post['shipping_city'] ?? '')),
            'audience_type' => null,
            'meal_style' => null,
            'pricing_mode' => StoreOrdersRepository::PRICING_PAYG,
            'items_count' => count($items),
            'meals_count' => array_sum(array_column($items, 'quantity')),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'manual_fee_type' => $feeType,
            'manual_fee_value' => $feeValue,
            'manual_fee_amount' => $feeAmount,
            'manual_fee_label' => $feeLabel,
            'total' => $total,
            'currency' => ProductProfileService::operationalCurrency(),
            'payment_status' => $paidManually ? StoreOrdersRepository::PAYMENT_PAID : StoreOrdersRepository::PAYMENT_PENDING,
            'status' => $paidManually ? StoreOrdersRepository::STATUS_IN_PREPARATION : StoreOrdersRepository::STATUS_NEW,
            'notes' => $notes,
            'shipping_address_1' => trim((string)($post['shipping_address_1'] ?? '')),
            'shipping_address_2' => trim((string)($post['shipping_address_2'] ?? '')),
            'shipping_city' => trim((string)($post['shipping_city'] ?? '')),
            'shipping_state' => trim((string)($post['shipping_state'] ?? '')),
            'shipping_zip' => trim((string)($post['shipping_zip'] ?? '')),
            'shipping_country' => trim((string)($post['shipping_country'] ?? 'US')),
            'shipping_place_id' => trim((string)($post['shipping_place_id'] ?? '')),
            'shipping_latitude' => ($post['shipping_latitude'] ?? '') !== '' ? (float)$post['shipping_latitude'] : null,
            'shipping_longitude' => ($post['shipping_longitude'] ?? '') !== '' ? (float)$post['shipping_longitude'] : null,
            'shipping_instructions' => $shippingInstructions,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if (!$orderCreated) {
            return ['success' => false, 'message' => 'The Store order could not be created.'];
        }

        $orderId = (int)$ordersRepo->getLastId();
        $itemsRepo = new StoreOrderItemsRepository();
        $itemsSaved = true;
        foreach ($items as $item) {
            $itemsSaved = $itemsRepo->addCompatible([
                'id_owner' => $ownerId,
                'id_store_order' => $orderId,
                'id_product' => $item['id_product'],
                'id_product_variation' => $item['id_product_variation'] ?: null,
                'product_name_snapshot' => $item['product_name_snapshot'],
                'variation_name_snapshot' => $item['variation_name_snapshot'],
                'unit_price' => $item['unit_price'],
                'pricing_mode' => StoreOrdersRepository::PRICING_PAYG,
                'quantity' => $item['quantity'],
                'line_total' => $item['line_total']
            ]) && $itemsSaved;
        }

        if (!$itemsSaved) {
            $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_CANCELLED);
            return ['success' => false, 'message' => 'The Store order was created but one or more products could not be saved. The order was cancelled for review.'];
        }

        $paymentResult = $this->recordPaymentIntent($ownerId, $orderId, $clientId, $customerName, $customerEmail, $total, $paymentMode, $files, $post, $actorId);
        if (!($paymentResult['success'] ?? false)) {
            if ($paymentMode === 'saved_card') {
                $ordersRepo->markAsFailed($orderId);
                $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_CANCELLED);
                (new StorePaymentsRepository())->addCompatible([
                    'id_owner' => $ownerId,
                    'id_store_order' => $orderId,
                    'id_user' => $clientId > 0 ? $clientId : null,
                    'payment_method' => 'saved_card',
                    'payment_type' => StorePaymentsRepository::TYPE_FULL,
                    'amount' => $total,
                    'currency' => ProductProfileService::operationalCurrency(),
                    'status' => StoreOrdersRepository::PAYMENT_FAILED,
                    'payer_name' => $customerName,
                    'payer_email' => $customerEmail,
                    'payment_source' => 'admin_saved_card',
                    'notes' => (string)($paymentResult['message'] ?? 'Saved card payment failed.'),
                    'created_by' => $actorId,
                ]);
            }
            return ['success' => false, 'message' => (string)($paymentResult['message'] ?? 'Payment could not be recorded.')];
        }
        $order = $ordersRepo->getById($orderId);
        if ($paymentMode === 'send_link') {
            $this->sendPaymentLink($ownerId, $customerEmail, $customerName, $order);
        } else {
            (new StoreProductsRepository())->decrementStockForItems($stockItems);
            $this->sendOrderConfirmation($ownerId, $customerEmail, $customerName, $order, $paymentMode);
        }

        return [
            'success' => true,
            'message' => (string)$paymentResult['message'],
            'order_id' => $orderId,
            'public_url' => $this->publicOrderUrl($order),
        ];
    }

    public function importCsv(int $ownerId, array $file, ?int $actorId = null): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            return ['success' => false, 'message' => 'Upload a CSV file first.'];
        }

        $handle = fopen((string)$file['tmp_name'], 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'The CSV file could not be opened.'];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['success' => false, 'message' => 'The CSV file is empty.'];
        }

        $headers = array_map(static fn($h) => trim(strtolower((string)$h)), $headers);
        $groups = [];
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            if (!$data) {
                continue;
            }
            $key = trim((string)($data['order_key'] ?? ''));
            if ($key === '') {
                $key = strtolower(trim((string)($data['customer_email'] ?? ''))) . '|' . trim((string)($data['shipping_address_1'] ?? '')) . '|' . count($groups);
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'base' => $data,
                    'product_id' => [],
                    'variation_id' => [],
                    'quantity' => [],
                ];
            }
            $groups[$key]['product_id'][] = $data['product_id'] ?? 0;
            $groups[$key]['variation_id'][] = $data['variation_id'] ?? 0;
            $groups[$key]['quantity'][] = $data['quantity'] ?? 1;
        }
        fclose($handle);

        $created = 0;
        $errors = [];

        foreach ($groups as $group) {
            $data = $group['base'];
            $result = $this->createFromRequest($ownerId, [
                'guest_name' => $data['customer_name'] ?? '',
                'guest_email' => $data['customer_email'] ?? '',
                'guest_phone' => $data['customer_phone'] ?? '',
                'shipping_address_1' => $data['shipping_address_1'] ?? '',
                'shipping_city' => $data['shipping_city'] ?? '',
                'shipping_state' => $data['shipping_state'] ?? '',
                'shipping_zip' => $data['shipping_zip'] ?? '',
                'shipping_country' => $data['shipping_country'] ?? 'US',
                'payment_mode' => $data['payment_mode'] ?? 'send_link',
                'notes' => $data['notes'] ?? '',
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'product_id' => $group['product_id'],
                'variation_id' => $group['variation_id'],
                'quantity' => $group['quantity'],
            ], [], $actorId);

            if ($result['success'] ?? false) {
                $created++;
            } else {
                $errors[] = ($data['customer_email'] ?? 'row') . ': ' . ($result['message'] ?? 'failed');
            }
        }

        return [
            'success' => $created > 0,
            'message' => $created . ' Store order(s) imported.' . ($errors ? ' Issues: ' . implode(' | ', array_slice($errors, 0, 5)) : ''),
        ];
    }

    public function csvTemplate(): string
    {
        return "order_key,customer_name,customer_email,customer_phone,shipping_address_1,shipping_city,shipping_state,shipping_zip,shipping_country,product_id,variation_id,quantity,payment_mode,delivery_fee,discount,notes\nA1001,Jane Doe,jane@example.com,555-0000,123 Main St,Miami,FL,33130,US,1,,2,send_link,0,0,Manual order note\nA1001,Jane Doe,jane@example.com,555-0000,123 Main St,Miami,FL,33130,US,2,,1,send_link,0,0,Second item same order\n";
    }

    public function resendPaymentLink(int $ownerId, int $orderId): array
    {
        if (!$this->hasConnectedOnlinePaymentProvider($ownerId)) {
            return ['success' => false, 'message' => 'Connect and verify Stripe, Square or PayPal before sending a payment link.'];
        }
        $order = (new StoreOrdersRepository())->getById($orderId);
        if (!$order || (int)($order->id_owner ?? 0) !== $ownerId) {
            return ['success' => false, 'message' => 'Store order not found in this workspace.'];
        }
        $email = trim((string)($order->guest_email ?? ''));
        if ($email === '') {
            return ['success' => false, 'message' => 'This order does not have a customer email.'];
        }
        $this->sendPaymentLink($ownerId, $email, (string)($order->guest_name ?? 'there'), $order);
        return ['success' => true, 'message' => 'Payment link sent again.'];
    }

    private function normalizeItems(int $ownerId, array $post): array
    {
        $productIds = (array)($post['product_id'] ?? []);
        $variationIds = (array)($post['variation_id'] ?? []);
        $quantities = (array)($post['quantity'] ?? []);
        $productsRepo = new StoreProductsRepository();
        $variationsRepo = new StoreProductVariationsRepository();
        $items = [];

        foreach ($productIds as $index => $productId) {
            $productId = (int)$productId;
            $variationId = (int)($variationIds[$index] ?? 0);
            $quantity = max(1, (int)($quantities[$index] ?? 1));
            if ($productId <= 0) {
                continue;
            }

            $product = $productsRepo->getOneByOwner(['id' => $productId], $ownerId);
            if (!$product) {
                continue;
            }

            $unitPrice = $productsRepo->getEffectivePrice($product);
            $variationName = null;
            if ($variationId > 0) {
                $variation = $variationsRepo->getOneByOwner(['id' => $variationId, 'id_product' => $productId], $ownerId);
                if (!$variation) {
                    continue;
                }
                $unitPrice = $variationsRepo->getEffectivePrice($variation);
                $variationName = (string)($variation->name ?? '');
            }

            $items[] = [
                'id_product' => $productId,
                'id_product_variation' => $variationId,
                'product_name_snapshot' => (string)($product->name ?? ('Product #' . $productId)),
                'variation_name_snapshot' => $variationName,
                'unit_price' => round($unitPrice, 2),
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        }

        return $items;
    }

    private function recordPaymentIntent(int $ownerId, int $orderId, int $userId, string $name, string $email, float $total, string $paymentMode, array $files, array $post, ?int $actorId): array
    {
        $paymentsRepo = new StorePaymentsRepository();
        $proofUrl = '';
        if ($paymentMode === 'manual_proof' && !empty($files['payment_proof']['name'] ?? '')) {
            $proofUrl = FileUtils::saveFile($files['payment_proof'], 'store-manual-payments');
        }

        if ($paymentMode === 'saved_card') {
            $charge = $this->chargeSavedCard($ownerId, $userId, $total, $email, 'Manual Store order #' . $orderId);
            if (!($charge['success'] ?? false)) {
                return ['success' => false, 'message' => (string)($charge['message'] ?? 'Saved card payment failed.')];
            }
            $paymentsRepo->addCompatible([
                'id_owner' => $ownerId,
                'id_store_order' => $orderId,
                'id_user' => $userId > 0 ? $userId : null,
                'payment_method' => (string)($charge['provider'] ?? 'saved_card'),
                'payment_type' => StorePaymentsRepository::TYPE_FULL,
                'external_payment_id' => $charge['payment_id'] ?? null,
                'external_reference' => $charge['reference'] ?? null,
                'amount' => $total,
                'currency' => ProductProfileService::operationalCurrency(),
                'status' => StorePaymentsRepository::STATUS_PAID,
                'payer_name' => $name,
                'payer_email' => $email,
                'raw_response' => $charge['raw'] ?? null,
                'payment_source' => 'admin_saved_card',
                'notes' => trim((string)($post['payment_notes'] ?? '')),
                'created_by' => $actorId,
                'paid_at' => date('Y-m-d H:i:s')
            ]);
            $ordersRepo = new StoreOrdersRepository();
            $ordersRepo->markAsPaid($orderId);
            $ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_IN_PREPARATION);
            return ['success' => true, 'message' => TranslationService::trans('store_manual.order_created_card_charged')];
        }

        if ($paymentMode === 'manual_proof' || $paymentMode === 'mark_paid') {
            $paymentsRepo->addCompatible([
                'id_owner' => $ownerId,
                'id_store_order' => $orderId,
                'id_user' => $userId > 0 ? $userId : null,
                'payment_method' => $paymentMode === 'manual_proof' ? 'manual_proof' : 'manual',
                'payment_type' => StorePaymentsRepository::TYPE_FULL,
                'external_reference' => trim((string)($post['payment_reference'] ?? '')),
                'amount' => $total,
                'currency' => ProductProfileService::operationalCurrency(),
                'status' => StorePaymentsRepository::STATUS_PAID,
                'payer_name' => $name,
                'payer_email' => $email,
                'proof_url' => $proofUrl,
                'payment_source' => 'admin_manual_order',
                'notes' => trim((string)($post['payment_notes'] ?? '')),
                'created_by' => $actorId,
                'paid_at' => date('Y-m-d H:i:s')
            ]);
            return ['success' => true, 'message' => TranslationService::trans('store_manual.order_created_marked_paid')];
        }

        $paymentsRepo->addCompatible([
            'id_owner' => $ownerId,
            'id_store_order' => $orderId,
            'id_user' => $userId > 0 ? $userId : null,
            'payment_method' => 'payment_link',
            'payment_type' => StorePaymentsRepository::TYPE_FULL,
            'amount' => $total,
            'currency' => ProductProfileService::operationalCurrency(),
            'status' => StorePaymentsRepository::STATUS_PENDING,
            'payer_name' => $name,
            'payer_email' => $email,
            'payment_source' => 'admin_payment_link',
            'notes' => trim((string)($post['payment_notes'] ?? '')),
            'created_by' => $actorId,
        ]);

        return ['success' => true, 'message' => TranslationService::trans('store_manual.order_created_link_sent')];
    }

    private function chargeSavedCard(int $ownerId, int $userId, float $total, string $email, string $note): array
    {
        if ($userId <= 0) {
            return ['success' => false, 'message' => 'Select an existing client with a saved card.'];
        }
        $card = (new UserCardsRepository())->getMainCardByUserId($userId);
        if (!$card || empty($card->token)) {
            return ['success' => false, 'message' => 'The selected client has no default saved card.'];
        }
        $provider = (new PaymentProvidersRepository())->getActiveProviderForOwner($ownerId);
        $providerType = strtolower((string)($provider->provider_type ?? ''));
        if (!$provider || !in_array($providerType, ['square', 'stripe'], true)) {
            return ['success' => false, 'message' => 'No active Square or Stripe provider is configured for this business.'];
        }

        $amountCents = (int)round($total * 100);
        if ($providerType === 'stripe') {
            return $this->chargeStripeStoredCard($provider, (string)$card->token, $amountCents, $email, $note);
        }

        return $this->chargeSquareStoredCard($provider, (string)$card->token, $amountCents, $email, $note);
    }

    private function chargeStripeStoredCard(object $provider, string $customerToken, int $amountCents, string $email, string $note): array
    {
        try {
            \Stripe\Stripe::setApiKey((string)$provider->api_key);
            $charge = \Stripe\Charge::create([
                'amount' => $amountCents,
                'currency' => strtolower(ProductProfileService::operationalCurrency()),
                'customer' => $customerToken,
                'receipt_email' => $email,
                'description' => $note
            ]);
            return [
                'success' => true,
                'provider' => 'stripe',
                'currency' => ProductProfileService::operationalCurrency(),
                'payment_id' => $charge->id ?? null,
                'reference' => $charge->balance_transaction ?? null,
                'raw' => json_encode($charge),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function chargeSquareStoredCard(object $provider, string $cardId, int $amountCents, string $email, string $note): array
    {
        $accessToken = trim((string)($provider->api_key ?? ''));
        $locationId = trim((string)($provider->location_id ?? ''));
        if ($accessToken === '' || $locationId === '' || $cardId === '') {
            return ['success' => false, 'message' => 'Square stored card payment cannot be processed.'];
        }
        $base = strtolower((string)($provider->environment ?? 'sandbox')) === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
        $payload = [
            'source_id' => $cardId,
            'idempotency_key' => bin2hex(random_bytes(16)),
            'location_id' => $locationId,
            'amount_money' => [
                'amount' => $amountCents,
                'currency' => ProductProfileService::operationalCurrency()
            ],
            'autocomplete' => true,
            'buyer_email_address' => $email,
            'note' => $note
        ];
        $ch = curl_init($base . '/v2/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Square-Version: 2024-12-18',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 45
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) {
            return ['success' => false, 'message' => 'Square connection error.', 'raw' => $curlError];
        }
        $data = json_decode((string)$response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($data['payment']['id'])) {
            return [
                'success' => true,
                'provider' => 'square',
                'currency' => ProductProfileService::operationalCurrency(),
                'payment_id' => $data['payment']['id'],
                'reference' => $data['payment']['receipt_number'] ?? null,
                'raw' => $response
            ];
        }
        return ['success' => false, 'message' => $data['errors'][0]['detail'] ?? 'Square payment failed.', 'raw' => $response];
    }

    private function sendPaymentLink(int $ownerId, string $email, string $name, ?object $order): void
    {
        if (!$order || $email === '') {
            return;
        }
        $url = $this->publicOrderUrl($order);
        $businessName = $this->businessDisplayName($ownerId);
        $orderSummary = $this->orderEmailSummary($order);
        $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
        $body = '<p>Hello ' . $safeName . ',</p>'
            . '<p><strong>' . htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8') . '</strong> created a new Store order for you in Ophyra. Payment is now available.</p>'
            . $orderSummary
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:700;">View and pay order</a></p>'
            . '<p>You can use the same link to review preparation, delivery tracking and proof of delivery when available.</p>';

        try {
            // Order-created and payment-activated notices are Ophyra transactional mail.
            (new EmailService(null))->sendSimpleEmail($email, 'Your Store order payment link', $body);
        } catch (\Throwable $e) {
            error_log('Store payment link email failed: ' . $e->getMessage());
        }
    }

    private function sendOrderConfirmation(int $ownerId, string $email, string $name, ?object $order, string $paymentMode): void
    {
        if (!$order || $email === '') {
            return;
        }
        $url = $this->publicOrderUrl($order);
        $businessName = $this->businessDisplayName($ownerId);
        $orderSummary = $this->orderEmailSummary($order);
        $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
        $paymentLabel = $paymentMode === 'saved_card'
            ? 'charged to your saved card'
            : 'marked as paid by the business team';
        $body = '<p>Hello ' . $safeName . ',</p>'
            . '<p><strong>' . htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8') . '</strong> created your Store order and it was ' . htmlspecialchars($paymentLabel, ENT_QUOTES, 'UTF-8') . '.</p>'
            . $orderSummary
            . '<p>The team will continue from preparation into delivery. You can review status, tracking and proof of delivery from this secure link:</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:700;">View order status</a></p>';

        try {
            // This confirmation must not depend on the business having configured SMTP.
            (new EmailService(null))->sendSimpleEmail($email, 'Your Store order is confirmed', $body);
        } catch (\Throwable $e) {
            error_log('Store order confirmation email failed: ' . $e->getMessage());
        }
    }

    private function publicOrderUrl(?object $order): string
    {
        $base = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        return $base . '/store/order-access?token=' . urlencode((string)($order->public_token ?? ''));
    }

    public function hasConnectedOnlinePaymentProvider(int $ownerId): bool
    {
        $provider = (new PaymentProvidersRepository())->getActiveProviderForOwner($ownerId);
        if (!$provider || empty($provider->is_verified)) {
            return false;
        }

        return in_array(strtolower((string)($provider->provider_type ?? '')), ['stripe', 'square', 'paypal'], true);
    }

    public function hasConnectedSavedCardProvider(int $ownerId): bool
    {
        $provider = (new PaymentProvidersRepository())->getActiveProviderForOwner($ownerId);
        if (!$provider || empty($provider->is_verified)) {
            return false;
        }

        return in_array(strtolower((string)($provider->provider_type ?? '')), ['stripe', 'square'], true);
    }

    private function businessDisplayName(int $ownerId): string
    {
        $owner = (new UserRepository())->getOne(['id' => $ownerId]);
        $name = $owner ? trim((string)($owner->name ?? '') . ' ' . (string)($owner->lastname ?? '')) : '';
        return $name !== '' ? $name : 'Your business';
    }

    private function orderEmailSummary(object $order): string
    {
        $orderId = (int)($order->id ?? 0);
        $currency = htmlspecialchars(ProductProfileService::operationalCurrency(), ENT_QUOTES, 'UTF-8');
        $total = number_format((float)($order->total ?? 0), 2);
        return '<p><strong>Order:</strong> #' . $orderId . '<br><strong>Total:</strong> ' . $currency . ' ' . $total . '</p>';
    }
}
