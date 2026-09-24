<?php

$root = dirname(__DIR__);
$failures = [];

$assertContains = static function (string $file, string $needle, string $message) use ($root, &$failures): void {
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $file);
    if ($contents === false || !str_contains($contents, $needle)) {
        $failures[] = $message;
    }
};

$assertNotContains = static function (string $file, string $needle, string $message) use ($root, &$failures): void {
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $file);
    if ($contents !== false && str_contains($contents, $needle)) {
        $failures[] = $message;
    }
};

$assertContains('src/Repositories/StoreUserRolesRepository.php', 'user_institutions', 'Store team lookup must include multi-company relationships.');
$assertContains('src/views/panel/level4/planner-hub/team/driver-mode/index.twig', 'Html5Qrcode', 'Driver Mode must expose the package QR scanner.');
$assertContains('src/views/panel/level4/planner-hub/team/driver-mode/index.twig', 'data-public-token', 'Driver Mode must match QR tokens to assigned tasks.');
$assertContains('src/views/panel/level4/planner-hub/team/my-work/index.twig', 'print-package-tag', 'Preparation tasks must be able to print a package tag.');
$assertContains('src/views/public/commerce/store/home/index.php', "\$_GET['category']", 'The existing public Store must accept category filters.');
$assertContains('src/views/public/commerce/store/home/index.php', "\$_GET['product']", 'The existing public Store must accept product focus.');
$assertContains('src/Repositories/StorageItemRepository.php', 'c.name LIKE :term', 'Warehouse search must include container names.');
$assertNotContains('src/views/panel/level2/planner-hub/store/products/home/index.twig', "path('product/", 'Level 2 must not link to a missing public product route.');
$assertNotContains('src/views/panel/level2/planner-hub/store/categories/home/index.twig', "path('product-category/", 'Level 2 must not link to a missing public category route.');
$assertNotContains('src/views/panel/level5/chat/index.php', 'Location: index.php', 'Level 5 chat must redirect through the application route.');
$assertContains('src/Services/StoreManualOrderService.php', "\$paymentMode === 'manual_proof'", 'Manual proof payments must be validated by the backend.');
$assertContains('src/Services/StoreManualOrderService.php', 'UPLOAD_ERR_OK', 'A manual payment proof must be a successful upload before an order is created.');
$assertContains('src/views/panel/shared/store/manual-order-form.twig', "document.getElementById('paymentProof').required = requiresProof", 'The order wizard must require a receipt only for manual proof payments.');

foreach (glob($root . '/src/Languages/*.json') as $languageFile) {
    json_decode((string)file_get_contents($languageFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $failures[] = basename($languageFile) . ' is not valid JSON.';
    }
}

require_once $root . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader($root . '/src/views'));
foreach (['path', 'trans', 'asset', 'asset_for', 'url', 'csrf_token'] as $functionName) {
    $twig->addFunction(new Twig\TwigFunction($functionName, static fn (...$arguments) => ''));
}
$changedTemplates = [
    'panel/level1/planner-hub/store/categories/home/index.twig',
    'panel/level1/planner-hub/store/products/details/index.twig',
    'panel/level1/planner-hub/store/products/home/index.twig',
    'panel/level2/planner-hub/store/categories/home/index.twig',
    'panel/level2/planner-hub/store/products/details/index.twig',
    'panel/level2/planner-hub/store/products/home/index.twig',
    'panel/level2/planner-hub/store/orders/home/index.twig',
    'panel/level2/planner-hub/store/orders/home/compact-table.twig',
    'panel/shared/store/manual-order-form.twig',
    'panel/level4/planner-hub/team/driver-mode/index.twig',
    'panel/level4/planner-hub/team/my-work/index.twig',
    'panel/level5/planner-hub/orders/orders/index.twig',
    'panel/level5/store/orders/home/index.twig',
    'public/commerce/store/home/index.twig',
    'templates/layout/sidebars/5.twig',
];
foreach ($changedTemplates as $template) {
    try {
        $source = (string)file_get_contents($root . '/src/views/' . $template);
        $twig->parse($twig->tokenize(new Twig\Source($source, $template)));
    } catch (Throwable $exception) {
        $failures[] = $template . ' has invalid Twig syntax: ' . $exception->getMessage();
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Store logistics contracts OK" . PHP_EOL;
