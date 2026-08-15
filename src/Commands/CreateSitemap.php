<?php

namespace App\Commands;

use App\Services\OphyraSeoService;
use App\Utils\LocationUtils;

class CreateSitemap extends BaseCommand
{

    public function getName(): string
    {
        return 'create-sitemap';
    }

    public function handle(array $args): void
    {
        $fileLocation = LocationUtils::getRootLocation();
        $fileLocation .= '/public/sitemap.xml';

        $appUrl = (string)($args['app-url'] ?? null);
        $seo = new OphyraSeoService();
        file_put_contents($fileLocation, $seo->sitemapXml($appUrl ?: null));
        echo "Sitemap generated at {$fileLocation}\n";
    }

}
