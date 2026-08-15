<?php

namespace App\Services;

use App\Repositories\GrowthHubRepository;
use App\Utils\LocationUtils;
use DateTimeImmutable;
use Exception;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Throwable;

class SearchConsoleImportService
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private GrowthHubRepository $repo;

    public function __construct()
    {
        $this->repo = new GrowthHubRepository();
    }

    public function importForSite(string $siteKey, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (($_ENV['GOOGLE_SEARCH_CONSOLE_ENABLED'] ?? 'false') !== 'true') {
            throw new Exception('Google Search Console import is disabled.');
        }

        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            throw new Exception('Growth Hub site is not configured.');
        }

        $propertyUrl = $this->propertyUrlForSite((string)$site->site_key, $site);
        if ($propertyUrl === '') {
            throw new Exception('Google Search Console property URL is missing.');
        }

        $dates = $this->dateRange($dateFrom, $dateTo);
        $client = $this->client();
        $service = new SearchConsole($client);

        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($dates['from']);
        $request->setEndDate($dates['to']);
        $request->setDimensions(['query', 'page']);
        $request->setRowLimit((int)($_ENV['GOOGLE_SEARCH_CONSOLE_ROW_LIMIT'] ?? 1000));

        $response = $service->searchanalytics->query($propertyUrl, $request);
        $rows = $response->getRows() ?: [];
        $imported = 0;
        $keywords = [];

        foreach ($rows as $row) {
            $keys = $row->getKeys() ?: [];
            $keyword = trim((string)($keys[0] ?? ''));
            if ($keyword === '') {
                continue;
            }

            $page = $keys[1] ?? null;
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $ctr = (float)$row->getCtr();
            $position = (float)$row->getPosition();

            $this->repo->createSearchConsoleSnapshot([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'property_url' => $propertyUrl,
                'domain' => $this->domainFromProperty($propertyUrl),
                'keyword_text' => $keyword,
                'page_url' => $page,
                'country' => null,
                'device' => null,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'average_position' => $position,
                'date_from' => $dates['from'],
                'date_to' => $dates['to'],
            ]);

            $priority = $this->priorityFromSearchConsole($clicks, $impressions, $position);
            $this->repo->createKeyword([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'keyword_text' => $keyword,
                'location' => null,
                'service_or_product' => null,
                'source' => 'google_search_console',
                'avg_monthly_searches' => $impressions,
                'competition' => null,
                'intent' => 'validated_search_console',
                'priority_score' => $priority,
                'notes' => 'Imported from Search Console. Clicks: ' . $clicks . ', impressions: ' . $impressions . ', avg position: ' . round($position, 2),
            ]);

            $keywords[$keyword] = true;
            $imported++;
        }

        $this->repo->upsertSearchConsoleImportStatus([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'property_url' => $propertyUrl,
            'domain' => $this->domainFromProperty($propertyUrl),
            'connection_status' => 'connected',
            'last_successful_import' => date('Y-m-d H:i:s'),
            'rows_imported' => $imported,
            'last_error' => null,
        ]);

        return [
            'site_key' => (string)$site->site_key,
            'property_url' => $propertyUrl,
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'rows_imported' => $imported,
            'keywords_seen' => count($keywords),
        ];
    }

    public function importRequiredProperties(?int $fallbackOwnerId = null): array
    {
        if (($_ENV['GOOGLE_SEARCH_CONSOLE_ENABLED'] ?? 'false') !== 'true') {
            throw new Exception('Google Search Console import is disabled.');
        }

        try {
            $client = $this->client();
            $service = new SearchConsole($client);
            $available = $this->availableProperties($service);
        } catch (Throwable $e) {
            return $this->markRequiredPropertiesUnavailable($fallbackOwnerId, $this->safeGoogleError($e));
        }

        $dates = $this->last28Days();
        $results = [];

        foreach ($this->requiredProperties() as $config) {
            $propertyUrl = $config['property_url'];
            $siteKey = $config['site_key'];
            $site = $this->repo->getSiteByKey($siteKey) ?: (object)[
                'id_owner' => (int)($fallbackOwnerId ?? 0),
                'site_key' => $siteKey,
            ];
            $domain = $this->domainFromProperty($propertyUrl);

            if (!isset($available[$propertyUrl])) {
                $message = 'Property is not available to the OAuth user: ' . $propertyUrl;
                $this->repo->upsertSearchConsoleImportStatus([
                    'id_owner' => (int)$site->id_owner,
                    'site_key' => $siteKey,
                    'property_url' => $propertyUrl,
                    'domain' => $domain,
                    'connection_status' => 'not_connected',
                    'last_successful_import' => null,
                    'rows_imported' => 0,
                    'last_error' => $message,
                ]);
                $results[] = [
                    'site_key' => $siteKey,
                    'property_url' => $propertyUrl,
                    'domain' => $domain,
                    'connected' => false,
                    'rows_imported' => 0,
                    'last_error' => $message,
                ];
                continue;
            }

            try {
                $summary = $this->importProperty($service, $site, $propertyUrl, $dates['from'], $dates['to']);
                $results[] = $summary + [
                    'connected' => true,
                    'last_error' => null,
                ];
            } catch (Throwable $e) {
                $message = $this->safeGoogleError($e);
                $this->repo->upsertSearchConsoleImportStatus([
                    'id_owner' => (int)$site->id_owner,
                    'site_key' => $siteKey,
                    'property_url' => $propertyUrl,
                    'domain' => $domain,
                    'connection_status' => 'not_connected',
                    'last_successful_import' => null,
                    'rows_imported' => 0,
                    'last_error' => $message,
                ]);
                $results[] = [
                    'site_key' => $siteKey,
                    'property_url' => $propertyUrl,
                    'domain' => $domain,
                    'connected' => false,
                    'rows_imported' => 0,
                    'last_error' => $message,
                ];
            }
        }

        return [
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'properties' => $results,
            'available_properties_count' => count($available),
        ];
    }

    private function markRequiredPropertiesUnavailable(?int $fallbackOwnerId, string $message): array
    {
        $results = [];

        foreach ($this->requiredProperties() as $config) {
            $propertyUrl = $config['property_url'];
            $siteKey = $config['site_key'];
            $site = $this->repo->getSiteByKey($siteKey) ?: (object)[
                'id_owner' => (int)($fallbackOwnerId ?? 0),
                'site_key' => $siteKey,
            ];
            $domain = $this->domainFromProperty($propertyUrl);

            $this->repo->upsertSearchConsoleImportStatus([
                'id_owner' => (int)$site->id_owner,
                'site_key' => $siteKey,
                'property_url' => $propertyUrl,
                'domain' => $domain,
                'connection_status' => 'not_connected',
                'last_successful_import' => null,
                'rows_imported' => 0,
                'last_error' => $message,
            ]);

            $results[] = [
                'site_key' => $siteKey,
                'property_url' => $propertyUrl,
                'domain' => $domain,
                'connected' => false,
                'rows_imported' => 0,
                'last_error' => $message,
            ];
        }

        return [
            'date_from' => null,
            'date_to' => null,
            'properties' => $results,
            'available_properties_count' => 0,
        ];
    }

    public function verifyRequiredProperties(): array
    {
        $service = new SearchConsole($this->client());
        $available = $this->availableProperties($service);
        $results = [];

        foreach ($this->requiredProperties() as $config) {
            $propertyUrl = $config['property_url'];
            $results[] = [
                'site_key' => $config['site_key'],
                'property_url' => $propertyUrl,
                'domain' => $this->domainFromProperty($propertyUrl),
                'connected' => isset($available[$propertyUrl]),
                'last_error' => isset($available[$propertyUrl]) ? null : 'Property is not available to the OAuth user: ' . $propertyUrl,
            ];
        }

        return [
            'properties' => $results,
            'available_properties_count' => count($available),
        ];
    }

    public function authorizationUrl(string $siteKey): string
    {
        $client = $this->oauthClient($siteKey);
        $state = bin2hex(random_bytes(16));
        $_SESSION['search_console_oauth_state'] = $state;
        $_SESSION['search_console_oauth_site_key'] = $siteKey;
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function handleOAuthCallback(array $query): array
    {
        $state = (string)($query['state'] ?? '');
        if ($state === '' || $state !== ($_SESSION['search_console_oauth_state'] ?? null)) {
            throw new Exception('Search Console OAuth state is invalid.');
        }

        $code = trim((string)($query['code'] ?? ''));
        if ($code === '') {
            throw new Exception('Search Console OAuth code is missing.');
        }

        $siteKey = (string)($_SESSION['search_console_oauth_site_key'] ?? ($_ENV['SEO_AGENT_DEFAULT_SITE_KEY'] ?? 'vnvevents'));
        $client = $this->oauthClient($siteKey);
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new Exception('Search Console OAuth failed: ' . ($token['error_description'] ?? $token['error']));
        }

        $existing = $this->readOAuthToken();
        if (empty($token['refresh_token']) && !empty($existing['refresh_token'])) {
            $token['refresh_token'] = $existing['refresh_token'];
        }

        $this->writeOAuthToken($token);
        unset($_SESSION['search_console_oauth_state'], $_SESSION['search_console_oauth_site_key']);

        return [
            'site_key' => $siteKey,
            'has_refresh_token' => !empty($token['refresh_token']),
        ];
    }

    private function client(): GoogleClient
    {
        if (($_ENV['GOOGLE_SEARCH_CONSOLE_AUTH_MODE'] ?? 'service_account') === 'oauth') {
            return $this->oauthClientWithToken();
        }

        $credentials = trim((string)($_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? ''));
        if ($credentials === '') {
            throw new Exception('GOOGLE_APPLICATION_CREDENTIALS is not configured.');
        }

        $path = $credentials;
        if (!preg_match('/^[A-Za-z]:\\\\|^\//', $path)) {
            $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $credentials);
        }

        if (!is_file($path)) {
            throw new Exception('Google service account JSON was not found at configured path.');
        }

        $client = new GoogleClient();
        $client->setAuthConfig($path);
        $client->setScopes([self::SCOPE]);

        return $client;
    }

    private function oauthClient(string $siteKey): GoogleClient
    {
        $clientId = trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_CLIENT_ID'] ?? $_ENV['GOOGLE_CLIENT_ID'] ?? ''));
        $clientSecret = trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'] ?? $_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new Exception('Google OAuth client id/secret are not configured.');
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($this->redirectUri($siteKey));
        $client->setScopes([self::SCOPE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    private function oauthClientWithToken(): GoogleClient
    {
        $siteKey = (string)($_ENV['SEO_AGENT_DEFAULT_SITE_KEY'] ?? 'vnvevents');
        $client = $this->oauthClient($siteKey);
        $token = $this->readOAuthToken();
        if (empty($token)) {
            throw new Exception('Search Console OAuth is not connected yet.');
        }

        if (empty($token['access_token']) && !empty($token['refresh_token'])) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            if (isset($newToken['error'])) {
                throw new Exception('Search Console OAuth refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']));
            }

            $newToken['refresh_token'] = $token['refresh_token'];
            $this->writeOAuthToken($newToken);
            $client->setAccessToken($newToken);

            return $client;
        }

        $client->setAccessToken($token);
        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken() ?: ($token['refresh_token'] ?? '');
            if ($refreshToken === '') {
                throw new Exception('Search Console OAuth refresh token is missing. Reconnect Google Search Console.');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($newToken['error'])) {
                throw new Exception('Search Console OAuth refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']));
            }

            $newToken['refresh_token'] = $refreshToken;
            $this->writeOAuthToken($newToken);
            $client->setAccessToken($newToken);
        }

        return $client;
    }

    private function redirectUri(string $siteKey): string
    {
        $base = rtrim((string)($_ENV['APP_URL'] ?? LocationUtils::getBasePath()), '/');
        return $base . '/panel/growth-hub/search-console-callback';
    }

    private function oauthTokenPath(): string
    {
        $path = trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_TOKEN_PATH'] ?? 'storage/private/google/search-console-oauth-token.json'));
        if (!preg_match('/^[A-Za-z]:\\\\|^\//', $path)) {
            $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }

    private function readOAuthToken(): array
    {
        $inlineToken = trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN'] ?? ''));
        if ($inlineToken !== '') {
            return ['refresh_token' => $inlineToken];
        }

        $path = $this->oauthTokenPath();
        if (!is_file($path)) {
            return [];
        }

        $token = json_decode((string)file_get_contents($path), true);
        return is_array($token) ? $token : [];
    }

    private function writeOAuthToken(array $token): void
    {
        $path = $this->oauthTokenPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new Exception('Could not create Google token storage directory.');
        }

        file_put_contents($path, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function propertyUrlForSite(string $siteKey, object $site): string
    {
        $siteEnvKey = 'GOOGLE_SEARCH_CONSOLE_PROPERTY_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $siteKey));
        $propertyUrl = trim((string)($_ENV[$siteEnvKey] ?? ''));
        if ($propertyUrl !== '') {
            return $propertyUrl;
        }

        $mapped = [
            'vnvevents' => 'sc-domain:vnvevents.com',
            'avomeal' => 'sc-domain:avomeal.com',
            'jonnysmedia' => 'sc-domain:jonnys.media',
        ];

        if (isset($mapped[$siteKey])) {
            return $mapped[$siteKey];
        }

        $fallback = trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_PROPERTY_URL'] ?? ''));
        if ($fallback !== '') {
            return $fallback;
        }

        $domain = trim((string)($site->domain ?? ''));
        if ($domain !== '') {
            return 'sc-domain:' . preg_replace('/^https?:\/\//', '', rtrim($domain, '/'));
        }

        return trim((string)($site->public_base_url ?? ''));
    }

    private function requiredProperties(): array
    {
        $raw = trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_REQUIRED_PROPERTIES'] ?? ''));
        if ($raw !== '') {
            $properties = [];
            foreach (explode(',', $raw) as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }

                if (str_starts_with($entry, 'sc-domain:') || str_starts_with($entry, 'http://') || str_starts_with($entry, 'https://')) {
                    $siteKey = $this->siteKeyFromProperty($entry);
                    $propertyUrl = $entry;
                } else {
                    [$siteKey, $propertyUrl] = array_pad(explode(':', $entry, 2), 2, '');
                }

                if ($propertyUrl === '') {
                    $propertyUrl = $siteKey;
                    $siteKey = $this->siteKeyFromProperty($propertyUrl);
                }

                $properties[] = [
                    'site_key' => trim($siteKey),
                    'property_url' => trim($propertyUrl),
                ];
            }

            if ($properties) {
                return $properties;
            }
        }

        return [
            ['site_key' => 'vnvevents', 'property_url' => trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_PROPERTY_VNVEVENTS'] ?? 'sc-domain:vnvevents.com'))],
            ['site_key' => 'avomeal', 'property_url' => trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_PROPERTY_AVOMEAL'] ?? 'sc-domain:avomeal.com'))],
            ['site_key' => 'jonnysmedia', 'property_url' => trim((string)($_ENV['GOOGLE_SEARCH_CONSOLE_PROPERTY_JONNYSMEDIA'] ?? 'sc-domain:jonnys.media'))],
        ];
    }

    private function availableProperties(SearchConsole $service): array
    {
        $response = $service->sites->listSites();
        $entries = $response->getSiteEntry() ?: [];
        $available = [];

        foreach ($entries as $entry) {
            $siteUrl = (string)$entry->getSiteUrl();
            if ($siteUrl !== '') {
                $available[$siteUrl] = true;
            }
        }

        return $available;
    }

    private function importProperty(SearchConsole $service, object $site, string $propertyUrl, string $dateFrom, string $dateTo): array
    {
        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($dateFrom);
        $request->setEndDate($dateTo);
        $request->setDimensions(['query', 'page']);
        $request->setRowLimit((int)($_ENV['GOOGLE_SEARCH_CONSOLE_ROW_LIMIT'] ?? 1000));

        $response = $service->searchanalytics->query($propertyUrl, $request);
        $rows = $response->getRows() ?: [];
        $imported = 0;
        $keywords = [];
        $domain = $this->domainFromProperty($propertyUrl);

        foreach ($rows as $row) {
            $keys = $row->getKeys() ?: [];
            $query = trim((string)($keys[0] ?? ''));
            if ($query === '') {
                continue;
            }

            $page = $keys[1] ?? null;
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $ctr = (float)$row->getCtr();
            $position = (float)$row->getPosition();

            $this->repo->createSearchConsoleSnapshot([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'property_url' => $propertyUrl,
                'domain' => $domain,
                'keyword_text' => $query,
                'page_url' => $page,
                'country' => null,
                'device' => null,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'average_position' => $position,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);

            $this->repo->createKeyword([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'keyword_text' => $query,
                'location' => null,
                'service_or_product' => null,
                'source' => 'google_search_console',
                'avg_monthly_searches' => $impressions,
                'competition' => null,
                'intent' => 'validated_search_console',
                'priority_score' => $this->priorityFromSearchConsole($clicks, $impressions, $position),
                'notes' => 'Imported from Search Console for ' . $domain . '. Clicks: ' . $clicks . ', impressions: ' . $impressions . ', avg position: ' . round($position, 2),
            ]);

            $keywords[$query] = true;
            $imported++;
        }

        $this->repo->upsertSearchConsoleImportStatus([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'property_url' => $propertyUrl,
            'domain' => $domain,
            'connection_status' => 'connected',
            'last_successful_import' => date('Y-m-d H:i:s'),
            'rows_imported' => $imported,
            'last_error' => null,
        ]);

        return [
            'site_key' => (string)$site->site_key,
            'property_url' => $propertyUrl,
            'domain' => $domain,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows_imported' => $imported,
            'keywords_seen' => count($keywords),
            'no_rows_returned' => $imported === 0,
        ];
    }

    private function last28Days(): array
    {
        $to = (new DateTimeImmutable('-1 day'))->format('Y-m-d');
        $from = (new DateTimeImmutable($to . ' -27 days'))->format('Y-m-d');

        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    private function domainFromProperty(string $propertyUrl): string
    {
        if (str_starts_with($propertyUrl, 'sc-domain:')) {
            return substr($propertyUrl, strlen('sc-domain:'));
        }

        $host = parse_url($propertyUrl, PHP_URL_HOST);
        return $host ? (string)$host : $propertyUrl;
    }

    private function siteKeyFromProperty(string $propertyUrl): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($this->domainFromProperty($propertyUrl))) ?: 'site';
    }

    private function safeGoogleError(Throwable $e): string
    {
        $message = $e->getMessage();
        $message = preg_replace('/ya29\\.[A-Za-z0-9_\\-.]+/', '[redacted_access_token]', $message);
        $message = preg_replace('/Authorization:\\s*Bearer\\s+[^\\s]+/i', 'Authorization: Bearer [redacted]', $message);
        $message = preg_replace('/client_secret=([^&\\s]+)/i', 'client_secret=[redacted]', $message);
        $message = preg_replace('/refresh_token=([^&\\s]+)/i', 'refresh_token=[redacted]', $message);

        return trim((string)$message);
    }

    private function dateRange(?string $dateFrom, ?string $dateTo): array
    {
        $to = $dateTo ?: (new DateTimeImmutable('-3 days'))->format('Y-m-d');
        $from = $dateFrom ?: (new DateTimeImmutable($to . ' -28 days'))->format('Y-m-d');

        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    private function priorityFromSearchConsole(int $clicks, int $impressions, float $position): float
    {
        $visibility = min(40, $impressions / 50);
        $traffic = min(30, $clicks * 2);
        $positionOpportunity = $position > 3 ? min(30, max(0, 35 - $position)) : 15;

        return round(max(10, min(100, $visibility + $traffic + $positionOpportunity)), 2);
    }
}
