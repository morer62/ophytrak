<?php

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Services\OphyraPricingService;
use App\Services\ProductProfileService;

$failures = [];
$originalProduct = $_ENV['APP_PRODUCT'] ?? null;
$originalPrice = $_ENV['STORE_LOGISTICS_PRICE_BRL'] ?? null;

$_ENV['APP_PRODUCT'] = 'ophytrack';
unset($_ENV['STORE_LOGISTICS_PRICE_BRL']);

$pricing = new OphyraPricingService();
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.';
    }
};

$assertSame('BRL', ProductProfileService::billingCurrency(), 'OPHYTRACK must declare BRL as its billing currency.');
$assertSame('BRL', ProductProfileService::operationalCurrency(), 'OPHYTRACK Store and logistics operations must use BRL.');
$assertSame('R$', ProductProfileService::currencySymbol(), 'OPHYTRACK monetary amounts must use the Brazilian-real symbol.');
$assertSame(['store_delivery_tracking'], ProductProfileService::allowedAddonSlugs(), 'OPHYTRACK must sell Store + Logistics as its single launch plan.');
$assertSame(true, ProductProfileService::includedWithStoreLogistics('inventory_storage'), 'Warehouse must be included with Store + Logistics.');
$assertSame(true, ProductProfileService::includedWithStoreLogistics('marketplace_connectors'), 'Marketplace connectors must be included with Store + Logistics.');
$assertSame('BRL', $pricing->getDefaultCurrency(), 'OPHYTRACK default billing currency must be BRL.');
$assertSame(['BRL'], $pricing->allowedOphyraPaymentCurrencies(), 'OPHYTRACK must expose only BRL for membership payments.');
$assertSame('BRL', $pricing->normalizePaymentCurrency('EUR'), 'A manipulated EUR payment request must resolve to BRL.');
$assertSame('BRL', $pricing->normalizePaymentCurrency('USD'), 'A manipulated USD payment request must resolve to BRL.');
$assertSame(169.0, $pricing->getModulePrice('store_logistics', 'BRL'), 'The launch BRL price must remain available when deployment configuration is absent.');
$assertSame(75.0, $pricing->getModulePrice('inventory_storage', 'BRL'), 'The inventory BRL launch price must remain available.');
$assertSame(59.0, $pricing->getModulePrice('marketplace_connectors', 'BRL'), 'The connectors BRL launch price must remain available.');

$cardsTemplate = (string)file_get_contents($root . '/src/views/panel/level2/cards/index.twig');
$stripeService = (string)file_get_contents($root . '/src/Services/StripeService.php');
$assertSame(false, str_contains($cardsTemplate, 'stripe.createToken('), 'Level 2 card setup must not use legacy Stripe card tokens.');
$assertSame(true, str_contains($cardsTemplate, 'stripe.confirmCardSetup('), 'Level 2 card setup must confirm a SetupIntent.');
$assertSame(true, str_contains($stripeService, 'paymentIntents->create('), 'Saved membership cards must be charged with PaymentIntents.');
$cardsController = (string)file_get_contents($root . '/src/views/panel/level2/cards/index.php');
$assertSame(true, str_contains($cardsController, '"billing_zip" => $verifiedSetup["billing_zip"]'), 'Saved Stripe cards must satisfy the existing non-null billing_zip column.');

foreach ([
    'src/Services/StoreManualOrderService.php',
    'src/views/public/commerce/store/checkout/index.php',
    'src/views/public/commerce/store/order-access/index.php',
    'src/views/panel/level2/planner-hub/settings/payment-providers/index.php',
] as $operationalFile) {
    $source = (string)file_get_contents($root . '/' . $operationalFile);
    $assertSame(false, (bool)preg_match('/[\'\"](?:USD|EUR|GBP)[\'\"]/', $source), $operationalFile . ' must not persist or submit a non-BRL currency.');
}

$loader = new Twig\Loader\FilesystemLoader($root . '/src/views');
$twig = new Twig\Environment($loader);
foreach (['path', 'trans', 'asset', 'asset_for', 'url', 'csrf_token'] as $functionName) {
    $twig->addFunction(new Twig\TwigFunction($functionName, static fn (...$arguments) => ''));
}
$twig->addFilter(new Twig\TwigFilter('json_decode', static fn ($value) => is_string($value) ? (json_decode($value, true) ?: []) : $value));
foreach ([
    'panel/level2/cards/index.twig',
    'panel/level2/home/index.twig',
    'panel/level2/membership/manage/index.twig',
    'panel/level2/membership/modules/review/index.twig',
    'panel/level2/planner-hub/no-access/index.twig',
    'panel/shared/store/manual-order-form.twig',
    'panel/shared/store/sales-report.twig',
    'panel/shared/store/order-history.twig',
    'panel/level2/planner-hub/store/orders/home/index.twig',
    'panel/level2/planner-hub/store/orders/home/compact-table.twig',
    'panel/level2/planner-hub/store/products/home/index.twig',
    'panel/level2/planner-hub/store/products/details/index.twig',
    'panel/level2/planner-hub/store/payments/home/index.twig',
    'panel/level2/planner-hub/settings/payment-providers/index.twig',
    'public/commerce/store/home/index.twig',
    'public/commerce/store/cart/index.twig',
    'public/commerce/store/checkout/index.twig',
    'public/commerce/store/order-access/index.twig',
    'public/planner-hub/index.twig',
] as $template) {
    try {
        $source = (string)file_get_contents($root . '/src/views/' . $template);
        $twig->parse($twig->tokenize(new Twig\Source($source, $template)));
    } catch (Throwable $exception) {
        $failures[] = $template . ' has invalid Twig syntax: ' . $exception->getMessage();
    }
}

if ($originalProduct === null) unset($_ENV['APP_PRODUCT']); else $_ENV['APP_PRODUCT'] = $originalProduct;
if ($originalPrice === null) unset($_ENV['STORE_LOGISTICS_PRICE_BRL']); else $_ENV['STORE_LOGISTICS_PRICE_BRL'] = $originalPrice;

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "OPHYTRACK BRL billing contracts OK" . PHP_EOL;
