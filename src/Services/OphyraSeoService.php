<?php

namespace App\Services;

final class OphyraSeoService
{
    private const BRAND = 'Ophyra';
    private const DEFAULT_TITLE = 'Ophyra | Service Business Operations Software';
    private const DEFAULT_DESCRIPTION = 'Run clients, teams, service orders, contracts, payments, products, fulfillment, delivery, inventory and reports from one modular workspace for service companies.';
    private const DEFAULT_KEYWORDS = 'service business operations software, service company software, modular operations platform, client management software, service order management, team operations platform, business operations software, fulfillment management software, logistics management software, inventory tracking software';
    private const DEFAULT_IMAGE = 'assets/images/planner-hub-logo-positive.png';
    private const MODULE_SCHEMA = [
        'base_profile' => [
            'name' => 'Base Profile',
            'description' => 'Free starter business profile and basic Ophyra workspace.',
            'url' => '/',
        ],
        'service_operations' => [
            'name' => 'Service Operations',
            'description' => 'CRM, clients, service orders, contracts, team execution, payroll basics, communication and reports.',
            'url' => '/modules/service-operations',
        ],
        'store_logistics' => [
            'name' => 'Store + Logistics',
            'description' => 'Products, online store orders, fulfillment, delivery status, tracking and basic inventory. Coming soon.',
            'url' => '/modules/store-logistics',
        ],
        'advanced_storage_qr_inventory' => [
            'name' => 'Advanced Storage / QR Inventory',
            'description' => 'Containers, physical items, QR labels, storage locations and advanced inventory operations.',
            'url' => '/modules/advanced-storage-qr-inventory',
        ],
        'ai_advisor' => [
            'name' => 'AI Advisor',
            'description' => 'Ideas, summaries, recommendations, content support and operational guidance.',
            'url' => '/modules/ai-advisor',
        ],
        'ticket_sales_rsvp' => [
            'name' => 'Ticket Sales + RSVP',
            'description' => 'Event registrations, RSVP and ticket sales workflows.',
            'url' => '/modules/ticket-sales-rsvp',
        ],
        'marketplace_connectors' => [
            'name' => 'Marketplace Connectors',
            'description' => 'Marketplace connection layer for supported commerce channels and operational mapping.',
            'url' => '/modules/marketplace-connectors',
        ],
        'custom_domain_seo_page_builder' => [
            'name' => 'Custom Domain + SEO Page Builder',
            'description' => 'Future custom-domain, SEO and page builder layer.',
            'url' => '/',
            'coming_soon' => true,
        ],
    ];

    public function baseUrl(): string
    {
        return $this->normalizeBaseUrl((string)($_ENV['APP_URL'] ?? 'https://ophyra.com'));
    }

    public function publicSitemapEntries(?string $appUrl = null): array
    {
        $appUrl = $this->normalizeBaseUrl($appUrl ?? $this->baseUrl());
        $entries = $this->corePages($appUrl);
        $landing = new OphyraLandingPageService();
        $legacyModuleRoutes = [
            'modules/orders-operations',
            'modules/store-delivery-tracking',
            'modules/inventory-storage',
        ];

        foreach ($landing->getModulePages($appUrl) as $page) {
            if (in_array((string)$page['route'], $legacyModuleRoutes, true)) {
                continue;
            }

            $entries[] = $this->entry($page['route'], $page['title'], $page['description'], 'weekly', '0.82', 'index, follow', $appUrl);
        }

        foreach ($landing->getIndustryPages($appUrl) as $page) {
            $entries[] = $this->entry($page['route'], $page['title'], $page['description'], 'weekly', '0.78', 'index, follow', $appUrl);
        }

        return $this->uniqueEntries($entries);
    }

    public function sitemapXml(?string $appUrl = null): string
    {
        $entries = $this->publicSitemapEntries($appUrl);
        $today = date('Y-m-d');
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            if (($entry['robots'] ?? 'index, follow') !== 'index, follow') {
                continue;
            }

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars($entry['canonical'], ENT_XML1) . '</loc>';
            $xml[] = '    <lastmod>' . htmlspecialchars($entry['lastmod'] ?? $today, ENT_XML1) . '</lastmod>';
            $xml[] = '    <changefreq>' . htmlspecialchars($entry['changefreq'] ?? 'weekly', ENT_XML1) . '</changefreq>';
            $xml[] = '    <priority>' . htmlspecialchars($entry['priority'] ?? '0.5', ENT_XML1) . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    public function robotsTxt(?string $appUrl = null): string
    {
        $appUrl = $this->normalizeBaseUrl($appUrl ?? $this->baseUrl());

        return implode("\n", [
            'User-agent: *',
            'Disallow: /panel/',
            'Disallow: /app/',
            'Disallow: /api/',
            'Disallow: /admin/',
            'Disallow: /private/',
            'Disallow: /dashboard/',
            'Disallow: /account/',
            'Disallow: /profile/',
            'Disallow: /client-portal/',
            'Disallow: /team-view/',
            'Disallow: /membership/',
            'Disallow: /memberships/',
            'Disallow: /subscriptions-manager/',
            'Disallow: /module-manager/',
            'Disallow: /payments/',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /billing',
            'Disallow: /payment',
            'Disallow: /order-access/',
            'Disallow: /commerce/order-access/',
            'Disallow: /tickets/purchase/',
            'Disallow: /commerce/tickets/purchase/',
            'Disallow: /storage/private/',
            'Disallow: /webhook/',
            'Disallow: /cron/',
            'Disallow: /reset-password',
            'Disallow: /update-password',
            'Disallow: /forgot_password',
            'Disallow: /login',
            'Disallow: /logout',
            '',
            'Sitemap: ' . $appUrl . '/sitemap.xml',
        ]) . "\n";
    }

    public function seoForRoute(string $route, ?string $appUrl = null): array
    {
        $appUrl = $this->normalizeBaseUrl($appUrl ?? $this->baseUrl());
        $route = $this->normalizeRoute($route);
        $entry = $this->entryForRoute($route, $appUrl);

        return [
            'title' => $entry['title'],
            'description' => $entry['description'],
            'keywords' => $entry['keywords'] ?? self::DEFAULT_KEYWORDS,
            'author' => self::BRAND,
            'canonical' => $entry['canonical'],
            'og_title' => $entry['title'],
            'og_description' => $entry['description'],
            'og_image' => $entry['image'],
            'twitter_title' => $entry['title'],
            'twitter_description' => $entry['description'],
            'robots' => $entry['robots'],
        ];
    }

    public function schemaJsonListForRoute(string $route, ?string $appUrl = null, ?array $seo = null, mixed $pageSchema = null): array
    {
        $appUrl = $this->normalizeBaseUrl($appUrl ?? $this->baseUrl());
        $seo = $seo ?? $this->seoForRoute($route, $appUrl);
        $schemas = [];

        $globalSchema = $this->globalSchema($appUrl);
        if ($globalSchema) {
            $schemas[] = $globalSchema;
        }

        if (is_array($pageSchema) && $pageSchema !== []) {
            $schemas[] = $pageSchema;
        } else {
            $schemas[] = $this->routeSpecificPageSchema($route, $appUrl, $seo);
        }

        return $schemas;
    }

    private function corePages(string $appUrl): array
    {
        return [
            $this->entry('', self::DEFAULT_TITLE, self::DEFAULT_DESCRIPTION, 'weekly', '1.0', 'index, follow', $appUrl),
            $this->entry('forum', 'Ophyra Community | Business Operations Help and System Guides', 'Find Ophyra guides, system tips, business operations answers and community discussions for service, commerce, logistics and fulfillment teams.', 'daily', '0.84', 'index, follow', $appUrl),
            $this->entry('support', 'Ophyra Support | Get Help With Your Business Workspace', 'Contact Ophyra support for account, modules, billing, setup, workspace and platform questions.', 'monthly', '0.58', 'index, follow', $appUrl),
            $this->entry('terms_and_conditions', 'Ophyra Terms and Conditions', 'Review the terms and conditions for using Ophyra business operations software and related platform services.', 'yearly', '0.10', 'noindex, follow', $appUrl),
            $this->entry('terms-and-conditions', 'Ophyra Terms and Conditions', 'Review the terms and conditions for using Ophyra business operations software and related platform services.', 'yearly', '0.32', 'index, follow', $appUrl),
            $this->entry('privacy-policy', 'Ophyra Privacy Policy', 'How Ophyra collects, uses, protects and processes personal data for users in the United States and internationally.', 'yearly', '0.30', 'index, follow', $appUrl),
            $this->entry('cookie-policy', 'Ophyra Cookie Policy', 'Cookie and similar technology policy for Ophyra public pages and business workspaces.', 'yearly', '0.28', 'index, follow', $appUrl),
            $this->entry('data-processing-notice', 'Ophyra Data Processing Notice', 'International data processing and transfer notice for Ophyra users.', 'yearly', '0.28', 'index, follow', $appUrl),
            $this->entry('affiliates', 'Ophyra Partner Program | Refer Business Operators and Earn Commission', 'Refer business owners to Ophyra and earn commission on eligible paid modules after approved customers complete confirmed payments.', 'monthly', '0.55', 'index, follow', $appUrl),
            $this->entry('signup', 'Create Your Ophyra Business Account | Start Free', 'Create your free Ophyra business account, set up your public profile and activate the operations workspace your business needs.', 'monthly', '0.52', 'index, follow', $appUrl),
            $this->entry('login', 'Ophyra Login', 'Sign in to your Ophyra workspace.', 'monthly', '0.10', 'noindex, follow', $appUrl),
        ];
    }

    private function entryForRoute(string $route, string $appUrl): array
    {
        foreach ($this->publicSitemapEntries($appUrl) as $entry) {
            if ($this->normalizeRoute($entry['route']) === $route) {
                return $entry;
            }
        }

        $title = self::DEFAULT_TITLE;
        $description = self::DEFAULT_DESCRIPTION;
        $robots = $this->isIndexableRoute($route) ? 'index, follow' : 'noindex, nofollow';

        return $this->entry($route, $title, $description, 'weekly', '0.5', $robots, $appUrl);
    }

    private function entry(string $route, string $title, string $description, string $changefreq, string $priority, string $robots = 'index, follow', ?string $appUrl = null): array
    {
        $route = $this->normalizeRoute($route);
        $appUrl = $this->normalizeBaseUrl($appUrl ?? $this->baseUrl());

        return [
            'route' => $route,
            'title' => $title,
            'description' => $description,
            'canonical' => $this->canonicalForRoute($route, $appUrl),
            'image' => $appUrl . '/' . self::DEFAULT_IMAGE,
            'keywords' => self::DEFAULT_KEYWORDS,
            'changefreq' => $changefreq,
            'priority' => $priority,
            'robots' => $robots,
        ];
    }

    private function uniqueEntries(array $entries): array
    {
        $unique = [];

        foreach ($entries as $entry) {
            $unique[$entry['canonical']] = $entry;
        }

        return array_values($unique);
    }

    private function globalSchema(string $appUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $appUrl . '/#organization',
                    'name' => self::BRAND,
                    'url' => $appUrl . '/',
                    'logo' => $appUrl . '/assets/images/planner-hub-logo-positive.png',
                    'description' => self::DEFAULT_DESCRIPTION,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $appUrl . '/#website',
                    'name' => self::BRAND,
                    'url' => $appUrl . '/',
                    'publisher' => [
                        '@id' => $appUrl . '/#organization',
                    ],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    '@id' => $appUrl . '/#software',
                    'name' => self::BRAND,
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'url' => $appUrl . '/',
                    'description' => self::DEFAULT_DESCRIPTION,
                    'applicationSubCategory' => 'Service business operations software',
                    'featureList' => [
                        'Client intake and CRM',
                        'Service orders and operational workflows',
                        'Team coordination and payroll basics',
                        'Contracts, payments and billing context',
                        'Inventory, fulfillment and delivery visibility',
                        'Modular add-ons for growing service businesses',
                    ],
                    'audience' => [
                        '@type' => 'BusinessAudience',
                        'audienceType' => 'service companies, consultants, agencies, venues, logistics teams, retailers and project-based operators',
                    ],
                    'provider' => [
                        '@id' => $appUrl . '/#organization',
                    ],
                    'offers' => $this->softwareOfferCatalog($appUrl),
                ],
            ],
        ];
    }

    private function softwareOfferCatalog(string $appUrl): array
    {
        $pricing = new OphyraPricingService();
        $currency = $pricing->baseCurrency();
        $offers = [];

        foreach (self::MODULE_SCHEMA as $slug => $module) {
            if (!empty($module['coming_soon'])) {
                $offers[] = [
                    '@type' => 'Offer',
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'url' => $this->absoluteUrl($appUrl, $module['url']),
                    'availability' => 'https://schema.org/PreOrder',
                ];
                continue;
            }

            $price = $pricing->getModulePrice($slug, $currency);
            if ($price === null) {
                continue;
            }

            $offers[] = [
                '@type' => 'Offer',
                'name' => $module['name'],
                'description' => $module['description'],
                'url' => $this->absoluteUrl($appUrl, $module['url']),
                'price' => number_format((float)$price, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
                'priceSpecification' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => number_format((float)$price, 2, '.', ''),
                    'priceCurrency' => $currency,
                    'billingDuration' => 'P1M',
                    'unitText' => 'month',
                ],
            ];
        }

        return [
            '@type' => 'OfferCatalog',
            'name' => 'Ophyra service business module catalog',
            'itemListElement' => $offers,
        ];
    }

    private function routeSpecificPageSchema(string $route, string $appUrl, array $seo): array
    {
        $schema = $this->webPageSchema($route, $appUrl, $seo);
        $route = $this->normalizeRoute($route);
        $canonical = $seo['canonical'] ?? ($appUrl . '/' . $route);
        $canonicalId = rtrim($canonical, '/');

        if ($route === '') {
            $schema['@graph'][] = [
                '@type' => 'ItemList',
                '@id' => $appUrl . '/#service-business-modules',
                'name' => 'Ophyra modules for service business operations',
                'description' => 'Core and optional modules for client intake, service orders, teams, payments, inventory, logistics, tickets and marketplace workflows.',
                'itemListElement' => $this->moduleItemList($appUrl),
            ];
            $schema['@graph'][] = [
                '@type' => 'FAQPage',
                '@id' => $appUrl . '/#service-business-faq',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name' => 'Is Ophyra only for event businesses?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'No. Ophyra is built as service business operations software for companies that manage clients, orders, teams, fulfillment, inventory, logistics, payments and recurring operational work.',
                        ],
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'Can a business start free and add modules later?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Yes. Businesses can start with the base workspace and activate paid modules only when the operation needs deeper control.',
                        ],
                    ],
                ],
            ];
        }

        if ($route === 'affiliates') {
            $schema['@graph'][] = [
                '@type' => 'Service',
                '@id' => $canonicalId . '#partner-program',
                'name' => 'Ophyra Partner and Affiliate Program',
                'serviceType' => 'Partner program for service business software referrals',
                'provider' => [
                    '@id' => $appUrl . '/#organization',
                ],
                'audience' => [
                    '@type' => 'BusinessAudience',
                    'audienceType' => 'consultants, agencies, creators, operators and advisors serving service businesses',
                ],
                'description' => 'A partner and affiliate program for people who help service businesses adopt better operations software, client workflows, modules and workspace structure.',
                'url' => $canonical,
            ];
        }

        return $schema;
    }

    private function moduleItemList(string $appUrl): array
    {
        $items = [];
        $position = 1;

        foreach (self::MODULE_SCHEMA as $module) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'item' => [
                    '@type' => 'SoftwareApplication',
                    '@id' => $appUrl . ($module['url'] ?? '/') . '#software-module',
                    'name' => $module['name'],
                    'applicationCategory' => 'BusinessApplication',
                    'applicationSubCategory' => 'Service business operations software module',
                    'operatingSystem' => 'Web',
                    'url' => $appUrl . ($module['url'] ?? '/'),
                    'description' => $module['description'],
                    'isPartOf' => [
                        '@id' => $appUrl . '/#software',
                    ],
                ],
            ];
        }

        return $items;
    }

    private function webPageSchema(string $route, string $appUrl, array $seo): array
    {
        $canonical = $seo['canonical'] ?? ($appUrl . '/' . $this->normalizeRoute($route));
        $name = $seo['title'] ?? self::DEFAULT_TITLE;

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => $this->schemaTypeForRoute($route),
                    '@id' => rtrim($canonical, '/') . '#webpage',
                    'url' => $canonical,
                    'name' => $name,
                    'description' => $seo['description'] ?? self::DEFAULT_DESCRIPTION,
                    'isPartOf' => [
                        '@id' => $appUrl . '/#website',
                    ],
                    'about' => [
                        '@id' => $appUrl . '/#organization',
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => rtrim($canonical, '/') . '#breadcrumb',
                    'itemListElement' => $this->breadcrumbItems($route, $appUrl, $name, $canonical),
                ],
            ],
        ];
    }

    private function breadcrumbItems(string $route, string $appUrl, string $name, string $canonical): array
    {
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => self::BRAND,
                'item' => $appUrl . '/',
            ],
        ];

        if ($this->normalizeRoute($route) !== '') {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => preg_replace('/\s+\|\s+Ophyra.*/', '', $name) ?: $name,
                'item' => $canonical,
            ];
        }

        return $items;
    }

    private function schemaTypeForRoute(string $route): string
    {
        $route = $this->normalizeRoute($route);

        if ($route === 'forum') {
            return 'CollectionPage';
        }

        if ($route === 'support') {
            return 'ContactPage';
        }

        if ($route === 'terms_and_conditions') {
            return 'WebPage';
        }

        return 'WebPage';
    }

    private function isIndexableRoute(string $route): bool
    {
        $route = $this->normalizeRoute($route);
        $blockedPrefixes = [
            'panel',
            'app',
            'api',
            'admin',
            'dashboard',
            'account',
            'profile',
            'client-portal',
            'team-view',
            'membership',
            'memberships',
            'subscriptions-manager',
            'module-manager',
            'payments',
            'checkout',
            'cart',
            'billing',
            'payment',
            'order-access',
            'commerce/order-access',
            'tickets/purchase',
            'commerce/tickets/purchase',
            'storage/private',
            'webhook',
            'cron',
            'reset-password',
            'update-password',
            'forgot_password',
            'logout',
            'login',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if ($route === $prefix || str_starts_with($route, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    private function normalizeRoute(string $route): string
    {
        $route = trim($route, "/ \t\n\r\0\x0B");

        if ($route === 'planner-hub') {
            return '';
        }

        return $route;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl) ?: 'https://ophyra.com';
        if (!preg_match('#^https?://#i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }

        $parts = parse_url($baseUrl);
        $host = strtolower((string)($parts['host'] ?? 'ophyra.com'));
        $scheme = ($host === 'localhost' || str_starts_with($host, '127.'))
            ? strtolower((string)($parts['scheme'] ?? 'http'))
            : 'https';
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = trim((string)($parts['path'] ?? ''), '/');
        $path = $path !== '' ? '/' . preg_replace('#/+#', '/', $path) : '';

        return rtrim($scheme . '://' . $host . $port . $path, '/');
    }

    private function canonicalForRoute(string $route, string $appUrl): string
    {
        $route = $this->normalizeRoute($route);
        if ($route === '') {
            return $appUrl . '/';
        }

        return $appUrl . preg_replace('#/+#', '/', '/' . trim($route, '/'));
    }

    private function absoluteUrl(string $appUrl, string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return $appUrl . '/';
        }

        return $appUrl . preg_replace('#/+#', '/', '/' . trim($path, '/'));
    }
}
