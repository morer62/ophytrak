<?php

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$guard = (string)file_get_contents($root . '/src/Services/ModuleGuardService.php');
$controller = (string)file_get_contents($root . '/src/views/panel/level4/planner-hub/no-access/index.php');
$templatePath = 'panel/level4/planner-hub/no-access/index.twig';
$template = (string)file_get_contents($root . '/src/views/' . $templatePath);

$assert(
    str_contains($guard, 'UserWorkspaceContextService')
        && str_contains($guard, "teamContext['selectedOwnerId']"),
    'Level 4 module checks must use the selected company owner.'
);
$assert(
    str_contains($controller, 'isCompanyModuleLock')
        && str_contains($controller, 'canAccessAnyRouteModule'),
    'The Level 4 no-access controller must distinguish a company module lock from a role lock.'
);
$assert(
    str_contains($template, 'company_module_locked_reason')
        && !str_contains($template, 'membership/pay'),
    'A team member must receive the company activation explanation without a company payment link.'
);

$loader = new Twig\Loader\FilesystemLoader($root . '/src/views');
$twig = new Twig\Environment($loader);
foreach (['path', 'trans', 'asset', 'asset_for', 'url', 'csrf_token'] as $functionName) {
    $twig->addFunction(new Twig\TwigFunction($functionName, static fn (...$arguments) => ''));
}

try {
    $twig->parse($twig->tokenize(new Twig\Source($template, $templatePath)));
} catch (Throwable $exception) {
    $failures[] = $templatePath . ' has invalid Twig syntax: ' . $exception->getMessage();
}

foreach (['pt', 'pr', 'en', 'es', 'fr'] as $locale) {
    $json = json_decode((string)file_get_contents($root . '/src/Languages/' . $locale . '.json'), true);
    $assert(is_array($json), $locale . '.json must remain valid JSON.');
    foreach (['company_activation_required', 'company_module_locked', 'company_module_locked_reason', 'selected_company', 'back_to_work'] as $key) {
        $assert(
            isset($json['level4_access_center'][$key]) && trim((string)$json['level4_access_center'][$key]) !== '',
            $locale . '.json is missing level4_access_center.' . $key . '.'
        );
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Level 4 selected-company module access contracts OK" . PHP_EOL;
