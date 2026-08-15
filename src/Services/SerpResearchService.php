<?php

namespace App\Services;

use App\Repositories\GrowthHubRepository;
use App\Services\Serp\DataForSEOSerpProvider;
use App\Services\Serp\SERPProviderInterface;
use Exception;
use Throwable;

class SerpResearchService
{
    private GrowthHubRepository $repo;
    private SERPProviderInterface $provider;

    public function __construct(?SERPProviderInterface $provider = null)
    {
        $this->repo = new GrowthHubRepository();
        $this->provider = $provider ?: $this->providerFromEnv();
    }

    public function research(string $siteKey, string $keyword, ?string $location = null, ?string $language = null, ?string $device = null, ?int $depth = null): array
    {
        if (($_ENV['SERP_ENABLED'] ?? 'false') !== 'true') {
            throw new Exception('SERP research is disabled.');
        }

        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            throw new Exception('Growth Hub site is not configured.');
        }

        $this->enforceDailyLimit((int)$site->id_owner, (string)$site->site_key);

        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new Exception('SERP keyword is required.');
        }

        $location = trim((string)($location ?: ($_ENV['SERP_DEFAULT_LOCATION'] ?? 'Miami, Florida, United States')));
        $language = trim((string)($language ?: ($_ENV['SERP_DEFAULT_LANGUAGE'] ?? 'en')));
        $device = trim((string)($device ?: ($_ENV['SERP_DEFAULT_DEVICE'] ?? 'desktop')));
        $depth = max(1, min(10, (int)($depth ?: ($_ENV['SERP_RESEARCH_DEPTH'] ?? 5))));
        $providerName = (string)($_ENV['SERP_PROVIDER'] ?? 'dataforseo');

        try {
            $created = $this->provider->createTask([
                'keyword' => $keyword,
                'location_name' => $location,
                'language_code' => $language,
                'device' => $device,
                'depth' => $depth,
            ]);
            $taskId = $this->taskIdFromResponse($created);
            if ($taskId === '') {
                throw new Exception('DataForSEO did not return a task id.');
            }

            $this->repo->upsertSerpImportStatus([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'provider' => $providerName,
                'keyword_text' => $keyword,
                'location_name' => $location,
                'device' => $device,
                'connection_status' => 'task_created',
                'provider_task_id' => $taskId,
                'rows_saved' => 0,
                'last_error' => null,
            ]);

            $fetched = $this->fetchWithPolling($taskId);
            $saved = $this->saveResult($site, $keyword, $location, $language, $device, $depth, $taskId, $providerName, $fetched);

            $this->repo->upsertSerpImportStatus([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'provider' => $providerName,
                'keyword_text' => $keyword,
                'location_name' => $location,
                'device' => $device,
                'connection_status' => 'results_fetched',
                'provider_task_id' => $taskId,
                'last_successful_fetch' => date('Y-m-d H:i:s'),
                'rows_saved' => $saved['rows_saved'],
                'last_error' => null,
            ]);

            return [
                'connected' => true,
                'task_created' => true,
                'results_fetched' => true,
                'rows_saved' => $saved['rows_saved'],
                'snapshot_id' => $saved['snapshot_id'],
                'task_id' => $taskId,
            ];
        } catch (Throwable $e) {
            $message = $this->sanitizeError($e->getMessage());
            $this->repo->upsertSerpImportStatus([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'provider' => $providerName,
                'keyword_text' => $keyword,
                'location_name' => $location,
                'device' => $device,
                'connection_status' => 'error',
                'rows_saved' => 0,
                'last_error' => $message,
            ]);

            throw new Exception($message);
        }
    }

    private function providerFromEnv(): SERPProviderInterface
    {
        if (($_ENV['SERP_PROVIDER'] ?? 'dataforseo') !== 'dataforseo') {
            throw new Exception('Only DataForSEO SERP provider is currently implemented.');
        }

        return new DataForSEOSerpProvider();
    }

    private function enforceDailyLimit(int $ownerId, string $siteKey): void
    {
        $limit = (int)($_ENV['SERP_DAILY_LIMIT'] ?? 5);
        if ($limit <= 0) {
            return;
        }

        $today = date('Y-m-d');
        $count = 0;
        foreach ($this->repo->latestSerpSnapshots($ownerId, $siteKey, 1000) as $snapshot) {
            if (str_starts_with((string)$snapshot->checked_at, $today)) {
                $count++;
            }
        }

        if ($count >= $limit) {
            throw new Exception('SERP daily limit reached for this site.');
        }
    }

    private function taskIdFromResponse(array $response): string
    {
        return (string)($response['tasks'][0]['id'] ?? '');
    }

    private function fetchWithPolling(string $taskId): array
    {
        $attempts = (int)($_ENV['SERP_FETCH_ATTEMPTS'] ?? 8);
        $sleepSeconds = (int)($_ENV['SERP_FETCH_SLEEP_SECONDS'] ?? 3);
        $last = [];

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                sleep($sleepSeconds);
            }

            $last = $this->provider->fetchTask($taskId);
            $items = $last['tasks'][0]['result'][0]['items'] ?? [];
            if (is_array($items) && count($items) > 0) {
                return $last;
            }
        }

        return $last;
    }

    private function saveResult(object $site, string $keyword, string $location, string $language, string $device, int $depth, string $taskId, string $providerName, array $response): array
    {
        $result = $response['tasks'][0]['result'][0] ?? [];
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        $organic = $this->organicResults($items, $depth);
        $ownDomain = (string)($site->domain ?? '');
        $own = $this->ownResult($organic, $ownDomain);
        $topCompetitor = $this->topCompetitor($organic, $ownDomain);
        $extras = $this->extractExtras($items);

        $snapshotId = $this->repo->createSerpSnapshot([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'property_url' => 'sc-domain:' . (string)$site->domain,
            'domain' => (string)$site->domain,
            'keyword_text' => $keyword,
            'location_name' => $location,
            'language_code' => $language,
            'device' => $device,
            'search_engine' => 'google',
            'provider' => $providerName,
            'provider_task_id' => $taskId,
            'depth' => $depth,
            'source' => 'dataforseo_serp',
            'own_domain' => $ownDomain,
            'own_best_position' => $own['position'] ?? null,
            'own_best_url' => $own['url'] ?? null,
            'top_competitor_domain' => $topCompetitor['domain'] ?? null,
            'top_competitor_position' => $topCompetitor['position'] ?? null,
            'result_count' => count($organic),
            'intent_detected' => $this->inferIntent($items),
            'recommended_content_type' => $this->recommendedContentType($items),
            'difficulty_estimate' => $this->difficultyEstimate($organic, $ownDomain),
            'validation_status' => 'serp_validated',
            'raw_response_json' => [
                'extras' => $extras,
                'provider_result_metadata' => [
                    'se_domain' => $result['se_domain'] ?? null,
                    'check_url' => $result['check_url'] ?? null,
                    'total_count' => $result['total_count'] ?? null,
                ],
            ],
            'searched_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($organic as $item) {
            $this->repo->createSerpResult([
                'id_snapshot' => $snapshotId,
                'result_position' => (int)$item['position'],
                'result_title' => $item['title'] ?? null,
                'result_url' => $item['url'] ?? null,
                'result_domain' => $item['domain'] ?? null,
                'snippet' => $item['snippet'] ?? null,
                'is_own_domain' => $this->isOwnDomain((string)($item['domain'] ?? ''), $ownDomain),
                'result_type' => $item['type'] ?? 'organic',
            ]);
        }

        return [
            'snapshot_id' => $snapshotId,
            'rows_saved' => count($organic),
        ];
    }

    private function organicResults(array $items, int $depth): array
    {
        $organic = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'organic') {
                continue;
            }

            $organic[] = [
                'position' => (int)($item['rank_group'] ?? $item['rank_absolute'] ?? count($organic) + 1),
                'domain' => $item['domain'] ?? $this->domainFromUrl((string)($item['url'] ?? '')),
                'url' => $item['url'] ?? null,
                'title' => $item['title'] ?? null,
                'snippet' => $item['description'] ?? $item['snippet'] ?? null,
                'type' => 'organic',
            ];

            if (count($organic) >= $depth) {
                break;
            }
        }

        return $organic;
    }

    private function extractExtras(array $items): array
    {
        $extras = [
            'related_searches' => [],
            'people_also_ask' => [],
            'local_pack' => [],
            'ads' => [],
        ];

        foreach ($items as $item) {
            $type = (string)($item['type'] ?? '');
            if (in_array($type, ['related_searches', 'people_also_ask', 'local_pack', 'paid'], true)) {
                $key = $type === 'paid' ? 'ads' : $type;
                $extras[$key][] = $item;
            }
        }

        return $extras;
    }

    private function ownResult(array $organic, string $ownDomain): array
    {
        foreach ($organic as $item) {
            if ($this->isOwnDomain((string)($item['domain'] ?? ''), $ownDomain)) {
                return [
                    'position' => (int)$item['position'],
                    'url' => $item['url'] ?? null,
                ];
            }
        }

        return [];
    }

    private function topCompetitor(array $organic, string $ownDomain): array
    {
        foreach ($organic as $item) {
            if (!$this->isOwnDomain((string)($item['domain'] ?? ''), $ownDomain)) {
                return [
                    'position' => (int)$item['position'],
                    'domain' => $item['domain'] ?? null,
                ];
            }
        }

        return [];
    }

    private function inferIntent(array $items): string
    {
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'local_pack') {
                return 'Commercial / local';
            }
        }

        return 'Commercial';
    }

    private function recommendedContentType(array $items): string
    {
        foreach ($items as $item) {
            $url = strtolower((string)($item['url'] ?? ''));
            if (str_contains($url, 'blog') || str_contains($url, 'how-')) {
                return 'Article or guide';
            }
        }

        return 'Dedicated landing page';
    }

    private function difficultyEstimate(array $organic, string $ownDomain): string
    {
        $directoryHits = 0;
        foreach ($organic as $item) {
            $domain = strtolower((string)($item['domain'] ?? ''));
            if (preg_match('/(theknot|weddingwire|yelp|zola|eventective|thumbtack)/', $domain)) {
                $directoryHits++;
            }
        }

        if ($directoryHits >= 3) {
            return 'High directory competition';
        }

        return $this->ownResult($organic, $ownDomain) ? 'Own domain present' : 'Moderate';
    }

    private function isOwnDomain(string $domain, string $ownDomain): bool
    {
        $domain = strtolower(preg_replace('/^www\./', '', $domain));
        $ownDomain = strtolower(preg_replace('/^www\./', '', $ownDomain));

        return $domain !== '' && $ownDomain !== '' && ($domain === $ownDomain || str_ends_with($domain, '.' . $ownDomain));
    }

    private function domainFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    private function sanitizeError(string $message): string
    {
        $password = (string)($_ENV['DATAFORSEO_PASSWORD'] ?? '');
        if ($password !== '') {
            $message = str_replace($password, '[redacted]', $message);
        }

        return preg_replace('/Authorization:\\s*Basic\\s+[^\\s]+/i', 'Authorization: Basic [redacted]', $message);
    }
}
