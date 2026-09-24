<?php

$root = dirname(__DIR__);
$viewRoots = [
    'src/views/panel/level2',
    'src/views/panel/level4',
    'src/views/panel/level5',
    'src/views/templates',
];
$keys = [];
$failures = [];

foreach ($viewRoots as $relativeRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $relativeRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'twig'], true)) continue;
        preg_match_all('/\btrans\(\s*[\'\"]([a-zA-Z0-9_.-]+)[\'\"]/', (string)file_get_contents($file->getPathname()), $matches);
        foreach ($matches[1] as $key) {
            // Prefixes ending in a separator are completed dynamically at runtime.
            if (!str_ends_with($key, '.') && !str_ends_with($key, '_')) $keys[$key] = true;
        }
    }
}

$hasTranslation = static function (array $translations, string $key): bool {
    foreach (explode('.', $key) as $part) {
        if (!is_array($translations) || !array_key_exists($part, $translations)) return false;
        $translations = $translations[$part];
    }
    return is_string($translations) && trim($translations) !== '' && $translations !== $key;
};

foreach (['en', 'es', 'pt', 'fr'] as $locale) {
    $translations = json_decode((string)file_get_contents($root . '/src/Languages/' . $locale . '.json'), true);
    if (!is_array($translations)) {
        $failures[] = $locale . '.json is invalid JSON.';
        continue;
    }
    foreach (array_keys($keys) as $key) {
        if (!$hasTranslation($translations, $key)) {
            $failures[] = $locale . ' is missing operational translation: ' . $key;
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Operational i18n contracts OK: ' . count($keys) . ' literal keys across en/es/pt/fr' . PHP_EOL;
