<?php

namespace App\Commands;

use App\Services\OphyraSeoService;

class SeoAudit extends BaseCommand
{
    public function getName(): string
    {
        return 'seo-audit';
    }

    public function handle(array $args): void
    {
        $appUrl = (string)($args['app-url'] ?? '');
        $seo = new OphyraSeoService();
        $entries = $seo->publicSitemapEntries($appUrl ?: null);
        $errors = [];
        $seenTitles = [];
        $seenDescriptions = [];

        foreach ($entries as $entry) {
            $route = (string)($entry['route'] ?? '');
            $canonical = (string)($entry['canonical'] ?? '');
            $title = trim((string)($entry['title'] ?? ''));
            $description = trim((string)($entry['description'] ?? ''));
            $robots = (string)($entry['robots'] ?? '');

            if ($canonical === '' || !preg_match('#^https://[^/]+(/.*)?$#', $canonical)) {
                $errors[] = "{$route}: invalid canonical {$canonical}";
            }

            $path = parse_url($canonical, PHP_URL_PATH) ?: '/';
            if (str_contains($path, '//')) {
                $errors[] = "{$route}: canonical has double slash path {$canonical}";
            }

            if (parse_url($canonical, PHP_URL_QUERY) || parse_url($canonical, PHP_URL_FRAGMENT)) {
                $errors[] = "{$route}: canonical must not include query or fragment";
            }

            if ($title === '') {
                $errors[] = "{$route}: missing title";
            }

            if ($description === '') {
                $errors[] = "{$route}: missing description";
            }

            if ($robots !== 'index, follow' && $robots !== 'noindex, follow') {
                $errors[] = "{$route}: unexpected robots value {$robots}";
            }

            if ($robots === 'index, follow') {
                $seenTitles[$title][] = $route;
                $seenDescriptions[$description][] = $route;
            }
        }

        foreach ($seenTitles as $title => $routes) {
            if ($title !== '' && count($routes) > 1) {
                $errors[] = 'Duplicate title "' . $title . '" on: ' . implode(', ', $routes);
            }
        }

        foreach ($seenDescriptions as $description => $routes) {
            if ($description !== '' && count($routes) > 1) {
                $errors[] = 'Duplicate description on: ' . implode(', ', $routes);
            }
        }

        echo "SEO audit entries: " . count($entries) . "\n";

        if ($errors === []) {
            echo "SEO audit passed.\n";
            return;
        }

        echo "SEO audit found " . count($errors) . " issue(s):\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    }
}
