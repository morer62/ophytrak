<?php

namespace App\Repositories;

class GrowthHubRepository
{
    private Connection $db;
    private array $tableColumns = [];

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function getSites(): array
    {
        $this->db->query("SELECT * FROM growth_sites WHERE status = 'active' ORDER BY site_name ASC");

        return $this->db->fetchAll();
    }

    public function getSite(int $ownerId, string $siteKey): ?object
    {
        $this->db->query("SELECT * FROM growth_sites WHERE id_owner = :owner_id AND site_key = :site_key LIMIT 1");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        $site = $this->db->fetchOne();
        return $site ?: null;
    }

    public function getSiteByKey(string $siteKey): ?object
    {
        $this->db->query("SELECT * FROM growth_sites WHERE site_key = :site_key AND status = 'active' LIMIT 1");
        $this->db->bind(':site_key', $siteKey);

        $site = $this->db->fetchOne();
        return $site ?: null;
    }

    public function updateSiteSettings(int $ownerId, string $siteKey, array $data): void
    {
        $this->db->query("UPDATE growth_sites SET
              public_base_url = :public_base_url,
              domain = :domain,
              sitemap_settings = :sitemap_settings,
              route_rules = :route_rules,
              updated_at = NOW()
            WHERE id_owner = :owner_id AND site_key = :site_key");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':public_base_url', $data['public_base_url'] ?? null);
        $this->db->bind(':domain', $data['domain'] ?? null);
        $this->db->bind(':sitemap_settings', json_encode($data['sitemap_settings'] ?? []));
        $this->db->bind(':route_rules', json_encode($data['route_rules'] ?? []));
        $this->db->execute();
    }

    public function updateSiteOffers(int $ownerId, string $siteKey, array $services, array $products = []): void
    {
        $this->db->query("UPDATE growth_sites SET
              main_services = :main_services,
              main_products = :main_products,
              updated_at = NOW()
            WHERE id_owner = :owner_id AND site_key = :site_key");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':main_services', json_encode(array_values($services)));
        $this->db->bind(':main_products', json_encode(array_values($products)));
        $this->db->execute();
    }

    public function refreshSiteCanonicalUrls(int $ownerId, string $siteKey, string $baseUrl): void
    {
        $this->db->query("UPDATE cms_routes
            SET canonical_url = CONCAT(:base_url, route),
                updated_at = NOW()
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND status != 'archived'");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':base_url', rtrim($baseUrl, '/'));
        $this->db->execute();
    }

    public function countContents(int $ownerId, string $siteKey): int
    {
        $this->db->query("SELECT COUNT(*) AS total FROM cms_contents WHERE id_owner = :owner_id AND site_key = :site_key");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $row = $this->db->fetchOne();

        return (int)($row->total ?? 0);
    }

    public function countMedia(int $ownerId, string $siteKey): int
    {
        $this->db->query("SELECT COUNT(*) AS total FROM cms_media WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $row = $this->db->fetchOne();

        return (int)($row->total ?? 0);
    }

    public function countCompetitors(int $ownerId, string $siteKey): int
    {
        $this->db->query("SELECT COUNT(*) AS total FROM growth_competitors WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $row = $this->db->fetchOne();

        return (int)($row->total ?? 0);
    }

    public function countKeywords(int $ownerId, string $siteKey): int
    {
        $this->db->query("SELECT COUNT(*) AS total FROM seo_keywords WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $row = $this->db->fetchOne();

        return (int)($row->total ?? 0);
    }

    public function targetLocations(int $ownerId, string $siteKey): array
    {
        $this->db->query("SELECT * FROM growth_target_locations
            WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'
            ORDER BY priority_score DESC, county ASC, location_name ASC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        return $this->db->fetchAll();
    }

    public function cmsCategories(int $ownerId, string $siteKey): array
    {
        $this->db->query("SELECT * FROM cms_categories
            WHERE (id_owner = :owner_id OR id_owner IS NULL)
              AND site_key = :site_key
              AND is_active = 1
            ORDER BY name ASC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        return $this->db->fetchAll();
    }

    public function cmsTemplates(int $ownerId, string $siteKey): array
    {
        $this->db->query("SELECT * FROM cms_templates
            WHERE (id_owner = :owner_id OR id_owner IS NULL)
              AND (site_key = :site_key OR site_key IS NULL OR site_key = '')
              AND status = 'ACTIVE'
            ORDER BY name ASC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        return $this->db->fetchAll();
    }

    public function cmsCategoryBySlug(int $ownerId, string $siteKey, string $slug): ?object
    {
        $this->db->query("SELECT * FROM cms_categories
            WHERE (id_owner = :owner_id OR id_owner IS NULL)
              AND site_key = :site_key
              AND slug = :slug
              AND is_active = 1
            LIMIT 1");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':slug', $slug);

        $row = $this->db->fetchOne();
        return $row ?: null;
    }

    public function cmsTemplateByKey(int $ownerId, string $siteKey, string $templateKey): ?object
    {
        $this->db->query("SELECT * FROM cms_templates
            WHERE (id_owner = :owner_id OR id_owner IS NULL)
              AND (site_key = :site_key OR site_key IS NULL OR site_key = '')
              AND template_key = :template_key
              AND status = 'ACTIVE'
            ORDER BY CASE WHEN site_key = :site_key_order THEN 0 ELSE 1 END
            LIMIT 1");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':site_key_order', $siteKey);
        $this->db->bind(':template_key', $templateKey);

        $row = $this->db->fetchOne();
        return $row ?: null;
    }

    public function cmsTemplateById(int $ownerId, string $siteKey, int $templateId): ?object
    {
        $this->db->query("SELECT * FROM cms_templates
            WHERE id = :template_id
              AND (id_owner = :owner_id OR id_owner IS NULL)
              AND (site_key = :site_key OR site_key IS NULL OR site_key = '')
            LIMIT 1");
        $this->db->bind(':template_id', $templateId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        $row = $this->db->fetchOne();
        return $row ?: null;
    }

    public function updateCmsTemplateCss(int $ownerId, string $siteKey, int $templateId, string $cssText): void
    {
        $this->db->query("UPDATE cms_templates
            SET css_text = :css_text,
                metadata_json = JSON_SET(
                    COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()),
                    '$.last_css_update_source',
                    'growth_hub_panel'
                ),
                updated_at = NOW()
            WHERE id = :template_id
              AND (id_owner = :owner_id OR id_owner IS NULL)
              AND (site_key = :site_key OR site_key IS NULL OR site_key = '')");
        $this->db->bind(':css_text', $cssText);
        $this->db->bind(':template_id', $templateId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->execute();
    }

    public function createCmsTemplate(array $data): int
    {
        $this->db->query("INSERT INTO cms_templates
            (id_owner, site_key, name, template_key, description, type, preview_html, template_structure_json, css_text, metadata_json, status)
            VALUES
            (:owner_id, :site_key, :name, :template_key, :description, :type, :preview_html, :structure_json, :css_text, :metadata_json, 'ACTIVE')
            ON DUPLICATE KEY UPDATE
              name = VALUES(name),
              description = VALUES(description),
              type = VALUES(type),
              preview_html = VALUES(preview_html),
              template_structure_json = VALUES(template_structure_json),
              css_text = VALUES(css_text),
              metadata_json = VALUES(metadata_json),
              status = 'ACTIVE',
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':name', (string)$data['name']);
        $this->db->bind(':template_key', (string)$data['template_key']);
        $this->db->bind(':description', $data['description'] ?? null);
        $this->db->bind(':type', (string)($data['type'] ?? 'general'));
        $this->db->bind(':preview_html', $data['preview_html'] ?? '<section class="cms-preview"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></section>');
        $this->db->bind(':structure_json', json_encode($data['template_structure_json'] ?? [
            'supports' => ['hero', 'body_section', 'cta', 'faq'],
            'default_blocks' => ['hero', 'body_section', 'cta', 'faq'],
        ]));
        $this->db->bind(':css_text', (string)($data['css_text'] ?? ''));
        $this->db->bind(':metadata_json', json_encode($data['metadata_json'] ?? []));
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function competitors(int $ownerId, string $siteKey, int $limit = 100): array
    {
        $this->db->query("SELECT * FROM growth_competitors
            WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'
            ORDER BY county ASC, city ASC, competitor_name ASC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function keywords(int $ownerId, string $siteKey, int $limit = 100): array
    {
        $this->db->query("SELECT * FROM seo_keywords
            WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'
            ORDER BY priority_score DESC, avg_monthly_searches DESC, keyword_text ASC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function latestSerpSnapshots(int $ownerId, string $siteKey, int $limit = 20): array
    {
        $this->db->query("SELECT * FROM seo_serp_snapshots
            WHERE id_owner = :owner_id AND site_key = :site_key
            ORDER BY checked_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function latestSearchConsoleStatuses(int $ownerId, string $siteKey): array
    {
        $this->db->query("SELECT * FROM seo_search_console_import_status
            WHERE id_owner = :owner_id AND site_key = :site_key
            ORDER BY domain ASC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        return $this->db->fetchAll();
    }

    public function searchConsoleImportStatuses(): array
    {
        $this->db->query("SELECT s.*
            FROM seo_search_console_import_status s
            INNER JOIN (
                SELECT property_url, MAX(id) AS latest_id
                FROM seo_search_console_import_status
                GROUP BY property_url
            ) latest ON latest.latest_id = s.id
            ORDER BY FIELD(s.domain, 'vnvevents.com', 'avomeal.com', 'jonnys.media'), s.domain ASC");

        return $this->db->fetchAll();
    }

    public function searchConsolePageOpportunities(int $ownerId, string $siteKey, int $limit = 20): array
    {
        $this->db->query("SELECT
              keyword_text,
              page_url,
              property_url,
              domain,
              SUM(clicks) AS clicks,
              SUM(impressions) AS impressions,
              CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
              AVG(average_position) AS average_position,
              MAX(date_from) AS date_from,
              MAX(date_to) AS date_to,
              MAX(imported_at) AS imported_at
            FROM seo_search_console_snapshots
            WHERE id_owner = :owner_id
              AND site_key = :site_key
            GROUP BY keyword_text, page_url, property_url, domain
            HAVING impressions > 0
              AND (average_position BETWEEN 4 AND 30 OR ctr < 0.02)
            ORDER BY impressions DESC, average_position ASC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function createSerpSnapshot(array $data): int
    {
        $this->db->query("INSERT INTO seo_serp_snapshots
            (id_owner, site_key, id_keyword, property_url, domain, keyword_text, location_name, county, state, language_code, device, search_engine, provider, provider_task_id, depth, source, own_domain, own_best_position, own_best_url, top_competitor_domain, top_competitor_position, result_count, intent_detected, recommended_content_type, difficulty_estimate, validation_status, raw_response_json, searched_at, checked_at)
            VALUES
            (:owner_id, :site_key, :keyword_id, :property_url, :domain, :keyword_text, :location_name, :county, :state, :language_code, :device, :search_engine, :provider, :provider_task_id, :depth, :source, :own_domain, :own_best_position, :own_best_url, :top_competitor_domain, :top_competitor_position, :result_count, :intent_detected, :recommended_content_type, :difficulty_estimate, :validation_status, :raw_response_json, :searched_at, NOW())");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':keyword_id', !empty($data['id_keyword']) ? (int)$data['id_keyword'] : null);
        $this->db->bind(':property_url', $data['property_url'] ?? null);
        $this->db->bind(':domain', $data['domain'] ?? null);
        $this->db->bind(':keyword_text', (string)$data['keyword_text']);
        $this->db->bind(':location_name', $data['location_name'] ?? null);
        $this->db->bind(':county', $data['county'] ?? null);
        $this->db->bind(':state', $data['state'] ?? null);
        $this->db->bind(':language_code', $data['language_code'] ?? null);
        $this->db->bind(':device', (string)($data['device'] ?? 'desktop'));
        $this->db->bind(':search_engine', (string)($data['search_engine'] ?? 'google'));
        $this->db->bind(':provider', $data['provider'] ?? null);
        $this->db->bind(':provider_task_id', $data['provider_task_id'] ?? null);
        $this->db->bind(':depth', isset($data['depth']) ? (int)$data['depth'] : null);
        $this->db->bind(':source', (string)($data['source'] ?? 'dataforseo_serp'));
        $this->db->bind(':own_domain', $data['own_domain'] ?? null);
        $this->db->bind(':own_best_position', isset($data['own_best_position']) ? (int)$data['own_best_position'] : null);
        $this->db->bind(':own_best_url', $data['own_best_url'] ?? null);
        $this->db->bind(':top_competitor_domain', $data['top_competitor_domain'] ?? null);
        $this->db->bind(':top_competitor_position', isset($data['top_competitor_position']) ? (int)$data['top_competitor_position'] : null);
        $this->db->bind(':result_count', isset($data['result_count']) ? (int)$data['result_count'] : null);
        $this->db->bind(':intent_detected', $data['intent_detected'] ?? null);
        $this->db->bind(':recommended_content_type', $data['recommended_content_type'] ?? null);
        $this->db->bind(':difficulty_estimate', $data['difficulty_estimate'] ?? null);
        $this->db->bind(':validation_status', $data['validation_status'] ?? null);
        $this->db->bind(':raw_response_json', json_encode($data['raw_response_json'] ?? []));
        $this->db->bind(':searched_at', $data['searched_at'] ?? date('Y-m-d H:i:s'));
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function createSerpResult(array $data): int
    {
        $this->db->query("INSERT INTO seo_serp_results
            (id_snapshot, id_competitor, result_position, result_title, result_url, result_domain, snippet, is_own_domain, result_type)
            VALUES
            (:snapshot_id, :competitor_id, :result_position, :result_title, :result_url, :result_domain, :snippet, :is_own_domain, :result_type)");
        $this->db->bind(':snapshot_id', (int)$data['id_snapshot']);
        $this->db->bind(':competitor_id', !empty($data['id_competitor']) ? (int)$data['id_competitor'] : null);
        $this->db->bind(':result_position', (int)$data['result_position']);
        $this->db->bind(':result_title', $data['result_title'] ?? null);
        $this->db->bind(':result_url', $data['result_url'] ?? null);
        $this->db->bind(':result_domain', $data['result_domain'] ?? null);
        $this->db->bind(':snippet', $data['snippet'] ?? null);
        $this->db->bind(':is_own_domain', !empty($data['is_own_domain']) ? 1 : 0);
        $this->db->bind(':result_type', (string)($data['result_type'] ?? 'organic'));
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function upsertSerpImportStatus(array $data): void
    {
        $this->db->query("INSERT INTO seo_serp_import_status
            (id_owner, site_key, provider, keyword_text, location_name, device, connection_status, provider_task_id, last_successful_fetch, rows_saved, last_error)
            VALUES
            (:owner_id, :site_key, :provider, :keyword_text, :location_name, :device, :connection_status, :provider_task_id, :last_successful_fetch, :rows_saved, :last_error)
            ON DUPLICATE KEY UPDATE
              connection_status = VALUES(connection_status),
              provider_task_id = VALUES(provider_task_id),
              last_successful_fetch = VALUES(last_successful_fetch),
              rows_saved = VALUES(rows_saved),
              last_error = VALUES(last_error),
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':provider', (string)$data['provider']);
        $this->db->bind(':keyword_text', (string)$data['keyword_text']);
        $this->db->bind(':location_name', (string)$data['location_name']);
        $this->db->bind(':device', (string)($data['device'] ?? 'desktop'));
        $this->db->bind(':connection_status', (string)$data['connection_status']);
        $this->db->bind(':provider_task_id', $data['provider_task_id'] ?? null);
        $this->db->bind(':last_successful_fetch', $data['last_successful_fetch'] ?? null);
        $this->db->bind(':rows_saved', (int)($data['rows_saved'] ?? 0));
        $this->db->bind(':last_error', $data['last_error'] ?? null);
        $this->db->execute();
    }

    public function serpImportStatuses(int $ownerId, string $siteKey, int $limit = 10): array
    {
        $this->db->query("SELECT * FROM seo_serp_import_status
            WHERE id_owner = :owner_id AND site_key = :site_key
            ORDER BY updated_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function opportunities(int $ownerId, string $siteKey, int $limit = 30): array
    {
        $this->db->query("SELECT * FROM seo_opportunities
            WHERE id_owner = :owner_id AND site_key = :site_key
              AND status != 'ARCHIVED'
            ORDER BY FIELD(status, 'IDEA', 'APPROVED', 'DRAFTED', 'PUBLISHED', 'ARCHIVED'), score DESC, created_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function calendarContents(int $ownerId, string $siteKey, int $limit = 60): array
    {
        $this->db->query("SELECT c.*, r.route
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            WHERE c.id_owner = :owner_id
              AND c.site_key = :site_key
              AND (c.scheduled_at IS NOT NULL OR c.status IN ('DRAFT', 'PUBLISHED'))
            ORDER BY COALESCE(c.scheduled_at, c.published_at, c.updated_at, c.created_at) ASC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function createOpportunity(array $data): int
    {
        $this->db->query("INSERT INTO seo_opportunities
            (id_owner, site_key, id_keyword, opportunity_type, title, keyword_text, location_name, service_or_product, score, reason_summary, status, recommended_content_type, recommended_route, data_json)
            VALUES
            (:owner_id, :site_key, :keyword_id, :opportunity_type, :title, :keyword_text, :location_name, :service_or_product, :score, :reason_summary, :status, :recommended_content_type, :recommended_route, :data_json)");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':keyword_id', !empty($data['id_keyword']) ? (int)$data['id_keyword'] : null);
        $this->db->bind(':opportunity_type', (string)$data['opportunity_type']);
        $this->db->bind(':title', (string)$data['title']);
        $this->db->bind(':keyword_text', $data['keyword_text'] ?? null);
        $this->db->bind(':location_name', $data['location_name'] ?? null);
        $this->db->bind(':service_or_product', $data['service_or_product'] ?? null);
        $this->db->bind(':score', (float)($data['score'] ?? 0));
        $this->db->bind(':reason_summary', $data['reason_summary'] ?? null);
        $this->db->bind(':status', (string)($data['status'] ?? 'IDEA'));
        $this->db->bind(':recommended_content_type', $data['recommended_content_type'] ?? null);
        $this->db->bind(':recommended_route', $data['recommended_route'] ?? null);
        $this->db->bind(':data_json', json_encode($data['data_json'] ?? []));
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function archiveOpportunity(int $ownerId, string $siteKey, int $opportunityId): void
    {
        $this->db->query("UPDATE seo_opportunities
            SET status = 'ARCHIVED', updated_at = NOW()
            WHERE id_owner = :owner_id AND site_key = :site_key AND id = :opportunity_id");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':opportunity_id', $opportunityId);
        $this->db->execute();
    }

    public function markOpportunityStatus(int $ownerId, string $siteKey, int $opportunityId, string $status): void
    {
        $this->db->query("UPDATE seo_opportunities
            SET status = :status, updated_at = NOW()
            WHERE id_owner = :owner_id AND site_key = :site_key AND id = :opportunity_id");
        $this->db->bind(':status', $status);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':opportunity_id', $opportunityId);
        $this->db->execute();
    }

    public function archivedOpportunityKeys(int $ownerId, string $siteKey, int $limit = 500): array
    {
        $this->db->query("SELECT data_json FROM seo_opportunities
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND status = 'ARCHIVED'
            ORDER BY updated_at DESC, created_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        $keys = [];
        foreach ($this->db->fetchAll() as $row) {
            $data = json_decode((string)($row->data_json ?? ''), true);
            if (!empty($data['opportunity_key'])) {
                $keys[] = (string)$data['opportunity_key'];
            }
        }

        return array_values(array_unique($keys));
    }

    public function createAgentRun(array $data): int
    {
        $this->db->query("INSERT INTO seo_agent_runs
            (id_owner, site_key, run_type, status, input_json, output_json, summary, started_at, finished_at, created_at)
            VALUES
            (:owner_id, :site_key, :run_type, :status, :input_json, :output_json, :summary, :started_at, :finished_at, NOW())");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':run_type', (string)$data['run_type']);
        $this->db->bind(':status', (string)($data['status'] ?? 'PENDING'));
        $this->db->bind(':input_json', json_encode($data['input_json'] ?? []));
        $this->db->bind(':output_json', json_encode($data['output_json'] ?? []));
        $this->db->bind(':summary', $data['summary'] ?? null);
        $this->db->bind(':started_at', $data['started_at'] ?? null);
        $this->db->bind(':finished_at', $data['finished_at'] ?? null);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function createTargetLocation(array $data): int
    {
        $this->db->query("INSERT INTO growth_target_locations
            (id_owner, site_key, location_name, county, state, country, lat, lng, priority_score, notes, status)
            VALUES
            (:owner_id, :site_key, :location_name, :county, :state, :country, :lat, :lng, :priority_score, :notes, 'active')
            ON DUPLICATE KEY UPDATE
              priority_score = VALUES(priority_score),
              notes = VALUES(notes),
              lat = VALUES(lat),
              lng = VALUES(lng),
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':location_name', (string)$data['location_name']);
        $this->db->bind(':county', $data['county'] ?? null);
        $this->db->bind(':state', $data['state'] ?? 'FL');
        $this->db->bind(':country', $data['country'] ?? 'US');
        $this->db->bind(':lat', isset($data['lat']) && $data['lat'] !== '' ? $data['lat'] : null);
        $this->db->bind(':lng', isset($data['lng']) && $data['lng'] !== '' ? $data['lng'] : null);
        $this->db->bind(':priority_score', (float)($data['priority_score'] ?? 50));
        $this->db->bind(':notes', $data['notes'] ?? null);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function createCompetitor(array $data): int
    {
        $domain = $this->domainFromUrl((string)$data['competitor_url']);
        $this->db->query("INSERT INTO growth_competitors
            (id_owner, site_key, competitor_name, competitor_url, competitor_domain, county, city, state, service_or_product, source, notes, scan_status, status)
            VALUES
            (:owner_id, :site_key, :competitor_name, :competitor_url, :competitor_domain, :county, :city, :state, :service_or_product, :source, :notes, 'pending', 'active')
            ON DUPLICATE KEY UPDATE
              competitor_name = VALUES(competitor_name),
              competitor_url = VALUES(competitor_url),
              county = VALUES(county),
              city = VALUES(city),
              state = VALUES(state),
              service_or_product = VALUES(service_or_product),
              notes = VALUES(notes),
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':competitor_name', (string)$data['competitor_name']);
        $this->db->bind(':competitor_url', (string)$data['competitor_url']);
        $this->db->bind(':competitor_domain', $domain);
        $this->db->bind(':county', $data['county'] ?? null);
        $this->db->bind(':city', $data['city'] ?? null);
        $this->db->bind(':state', $data['state'] ?? 'FL');
        $this->db->bind(':service_or_product', $data['service_or_product'] ?? null);
        $this->db->bind(':source', $data['source'] ?? 'manual');
        $this->db->bind(':notes', $data['notes'] ?? null);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function createKeyword(array $data): int
    {
        $this->db->query("INSERT INTO seo_keywords
            (id_owner, site_key, keyword_text, location, service_or_product, source, avg_monthly_searches, competition, cpc_low, cpc_high, intent, priority_score, status, notes, imported_at)
            VALUES
            (:owner_id, :site_key, :keyword_text, :location, :service_or_product, :source, :avg_monthly_searches, :competition, :cpc_low, :cpc_high, :intent, :priority_score, 'active', :notes, NOW())
            ON DUPLICATE KEY UPDATE
              service_or_product = VALUES(service_or_product),
              avg_monthly_searches = VALUES(avg_monthly_searches),
              competition = VALUES(competition),
              cpc_low = VALUES(cpc_low),
              cpc_high = VALUES(cpc_high),
              intent = VALUES(intent),
              priority_score = VALUES(priority_score),
              notes = VALUES(notes),
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':keyword_text', strtolower(trim((string)$data['keyword_text'])));
        $this->db->bind(':location', $data['location'] ?? null);
        $this->db->bind(':service_or_product', $data['service_or_product'] ?? null);
        $this->db->bind(':source', $data['source'] ?? 'manual');
        $this->db->bind(':avg_monthly_searches', isset($data['avg_monthly_searches']) && $data['avg_monthly_searches'] !== '' ? (int)$data['avg_monthly_searches'] : null);
        $this->db->bind(':competition', $data['competition'] ?? null);
        $this->db->bind(':cpc_low', isset($data['cpc_low']) && $data['cpc_low'] !== '' ? (float)$data['cpc_low'] : null);
        $this->db->bind(':cpc_high', isset($data['cpc_high']) && $data['cpc_high'] !== '' ? (float)$data['cpc_high'] : null);
        $this->db->bind(':intent', $data['intent'] ?? null);
        $this->db->bind(':priority_score', isset($data['priority_score']) && $data['priority_score'] !== '' ? (float)$data['priority_score'] : null);
        $this->db->bind(':notes', $data['notes'] ?? null);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function createSearchConsoleSnapshot(array $data): int
    {
        $this->db->query("INSERT INTO seo_search_console_snapshots
            (id_owner, site_key, property_url, domain, keyword_text, page_url, country, device, clicks, impressions, ctr, average_position, date_from, date_to)
            VALUES
            (:owner_id, :site_key, :property_url, :domain, :keyword_text, :page_url, :country, :device, :clicks, :impressions, :ctr, :average_position, :date_from, :date_to)");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':property_url', $data['property_url'] ?? null);
        $this->db->bind(':domain', $data['domain'] ?? null);
        $this->db->bind(':keyword_text', strtolower(trim((string)$data['keyword_text'])));
        $this->db->bind(':page_url', $data['page_url'] ?? null);
        $this->db->bind(':country', $data['country'] ?? null);
        $this->db->bind(':device', $data['device'] ?? null);
        $this->db->bind(':clicks', (int)($data['clicks'] ?? 0));
        $this->db->bind(':impressions', (int)($data['impressions'] ?? 0));
        $this->db->bind(':ctr', isset($data['ctr']) ? (float)$data['ctr'] : null);
        $this->db->bind(':average_position', isset($data['average_position']) ? (float)$data['average_position'] : null);
        $this->db->bind(':date_from', (string)$data['date_from']);
        $this->db->bind(':date_to', (string)$data['date_to']);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function upsertSearchConsoleImportStatus(array $data): void
    {
        $this->db->query("INSERT INTO seo_search_console_import_status
            (id_owner, site_key, property_url, domain, connection_status, last_successful_import, rows_imported, last_error)
            VALUES
            (:owner_id, :site_key, :property_url, :domain, :connection_status, :last_successful_import, :rows_imported, :last_error)
            ON DUPLICATE KEY UPDATE
              domain = VALUES(domain),
              connection_status = VALUES(connection_status),
              last_successful_import = VALUES(last_successful_import),
              rows_imported = VALUES(rows_imported),
              last_error = VALUES(last_error),
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':property_url', (string)$data['property_url']);
        $this->db->bind(':domain', (string)$data['domain']);
        $this->db->bind(':connection_status', (string)$data['connection_status']);
        $this->db->bind(':last_successful_import', $data['last_successful_import'] ?? null);
        $this->db->bind(':rows_imported', (int)($data['rows_imported'] ?? 0));
        $this->db->bind(':last_error', $data['last_error'] ?? null);
        $this->db->execute();
    }

    public function isContentSlugAvailable(int $ownerId, string $siteKey, string $slug, ?int $currentContentId = null): bool
    {
        $extra = $currentContentId ? 'AND id != :content_id' : '';
        $this->db->query("SELECT id FROM cms_contents
            WHERE id_owner = :owner_id AND site_key = :site_key AND slug = :slug {$extra}
            LIMIT 1");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':slug', $slug);
        if ($currentContentId) {
            $this->db->bind(':content_id', $currentContentId);
        }

        return !$this->db->fetchOne();
    }

    public function isRouteAvailable(int $ownerId, string $siteKey, string $route, ?int $currentContentId = null): bool
    {
        $route = '/' . trim($route, '/');
        $extra = $currentContentId ? 'AND id_content != :content_id' : '';
        $this->db->query("SELECT id FROM cms_routes
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND route = :route
              AND status != 'archived'
              {$extra}
            LIMIT 1");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':route', $route);
        if ($currentContentId) {
            $this->db->bind(':content_id', $currentContentId);
        }

        return !$this->db->fetchOne();
    }

    public function internalLinkCandidates(int $ownerId, string $siteKey, int $limit = 12): array
    {
        $this->db->query("SELECT c.id, c.title, c.content_type, c.primary_keyword, c.target_location, r.route, r.canonical_url
            FROM cms_contents c
            INNER JOIN cms_routes r ON r.id_content = c.id
              AND r.id_owner = c.id_owner
              AND r.site_key = c.site_key
              AND UPPER(r.status) IN ('ACTIVE', 'PUBLISHED')
            WHERE c.id_owner = :owner_id
              AND c.site_key = :site_key
              AND c.status = 'PUBLISHED'
              AND c.approval_status IN ('APPROVED', 'PUBLISHED')
            ORDER BY c.published_at DESC, c.updated_at DESC, c.created_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', max(1, min(30, $limit)));

        return $this->db->fetchAll();
    }

    public function recentContents(int $ownerId, string $siteKey, int $limit = 12): array
    {
        $this->db->query("SELECT c.*, r.route
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            WHERE c.id_owner = :owner_id AND c.site_key = :site_key AND c.status != 'ARCHIVED'
            ORDER BY c.updated_at DESC, c.created_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function contentInventory(int $ownerId, string $siteKey, array $filters = [], int $limit = 200): array
    {
        $where = [
            'c.id_owner = :owner_id',
            'c.site_key = :site_key',
        ];

        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $type = trim((string)($filters['content_type'] ?? ''));
        $search = trim((string)($filters['q'] ?? ''));

        if ($status !== '') {
            $where[] = 'c.status = :status';
        }

        if ($type !== '') {
            $where[] = 'c.content_type = :content_type';
        }

        if ($search !== '') {
            $where[] = '(c.title LIKE :search OR c.primary_keyword LIKE :search OR c.target_location LIKE :search OR c.slug LIKE :search)';
        }

        $this->db->query("SELECT c.*, r.route, r.canonical_url, r.status AS route_status
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FIELD(c.status, 'DRAFT', 'READY', 'PUBLISHED', 'ARCHIVED'), COALESCE(c.published_at, c.updated_at, c.created_at) DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        if ($status !== '') {
            $this->db->bind(':status', $status);
        }

        if ($type !== '') {
            $this->db->bind(':content_type', $type);
        }

        if ($search !== '') {
            $this->db->bind(':search', '%' . $search . '%');
        }

        return $this->db->fetchAll();
    }

    public function contentStatusCounts(int $ownerId, string $siteKey): array
    {
        $this->db->query("SELECT status, COUNT(*) AS total
            FROM cms_contents
            WHERE id_owner = :owner_id AND site_key = :site_key
            GROUP BY status
            ORDER BY status ASC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);

        return $this->db->fetchAll();
    }

    public function recentMedia(int $ownerId, string $siteKey, int $limit = 18): array
    {
        $this->db->query("SELECT * FROM cms_media
            WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'
            ORDER BY created_at DESC
            LIMIT :limit");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    public function createContent(array $data): int
    {
        $this->db->query("INSERT INTO cms_contents
            (id_owner, id_template, id_cms_category, site_key, content_type, title, slug, excerpt, body, seo_title, meta_description, primary_keyword, target_location, status, approval_status, created_by, schema_json, metadata_json)
            VALUES
            (:owner_id, :template_id, :cms_category_id, :site_key, :content_type, :title, :slug, :excerpt, :body, :seo_title, :meta_description, :primary_keyword, :target_location, :status, :approval_status, :created_by, :schema_json, :metadata_json)");
        $this->bindContent($data);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function updateContent(int $ownerId, string $siteKey, int $contentId, array $data): void
    {
        $this->db->query("UPDATE cms_contents SET
              id_template = :template_id,
              id_cms_category = :cms_category_id,
              content_type = :content_type,
              title = :title,
              slug = :slug,
              excerpt = :excerpt,
              body = :body,
              seo_title = :seo_title,
              meta_description = :meta_description,
              primary_keyword = :primary_keyword,
              target_location = :target_location,
              scheduled_at = :scheduled_at,
              schema_json = :schema_json,
              metadata_json = :metadata_json,
              updated_at = NOW()
            WHERE id = :content_id AND id_owner = :owner_id AND site_key = :site_key");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':template_id', !empty($data['id_template']) ? (int)$data['id_template'] : null);
        $this->db->bind(':cms_category_id', !empty($data['id_cms_category']) ? (int)$data['id_cms_category'] : null);
        $this->db->bind(':content_type', (string)$data['content_type']);
        $this->db->bind(':title', (string)$data['title']);
        $this->db->bind(':slug', (string)$data['slug']);
        $this->db->bind(':excerpt', $data['excerpt'] ?? null);
        $this->db->bind(':body', $data['body'] ?? null);
        $this->db->bind(':seo_title', $data['seo_title'] ?? null);
        $this->db->bind(':meta_description', $data['meta_description'] ?? null);
        $this->db->bind(':primary_keyword', $data['primary_keyword'] ?? null);
        $this->db->bind(':target_location', $data['target_location'] ?? null);
        $this->db->bind(':scheduled_at', $data['scheduled_at'] ?? null);
        $this->db->bind(':schema_json', json_encode($data['schema_json'] ?? []));
        $this->db->bind(':metadata_json', json_encode($data['metadata_json'] ?? []));
        $this->db->bind(':content_id', $contentId);
        $this->db->execute();
    }

    public function updateContentBody(int $ownerId, string $siteKey, int $contentId, string $body, array $metadata = []): void
    {
        $this->db->query("UPDATE cms_contents
            SET body = :body,
                metadata_json = :metadata_json,
                updated_at = NOW()
            WHERE id = :content_id
              AND id_owner = :owner_id
              AND site_key = :site_key");
        $this->db->bind(':body', $body);
        $this->db->bind(':metadata_json', json_encode($metadata));
        $this->db->bind(':content_id', $contentId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->execute();
    }

    public function archiveContent(int $ownerId, string $siteKey, int $contentId): void
    {
        $this->db->query("UPDATE cms_contents
            SET status = 'ARCHIVED',
                approval_status = 'ARCHIVED',
                scheduled_at = NULL,
                updated_at = NOW()
            WHERE id = :content_id
              AND id_owner = :owner_id
              AND site_key = :site_key");
        $this->db->bind(':content_id', $contentId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->execute();

        $this->db->query("UPDATE cms_routes
            SET status = 'archived',
                updated_at = NOW()
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND id_content = :content_id");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':content_id', $contentId);
        $this->db->execute();
    }

    public function restoreArchivedContent(int $ownerId, string $siteKey, int $contentId): void
    {
        $this->db->query("UPDATE cms_contents
            SET status = 'DRAFT',
                approval_status = 'DRAFT',
                updated_at = NOW()
            WHERE id = :content_id
              AND id_owner = :owner_id
              AND site_key = :site_key
              AND status = 'ARCHIVED'");
        $this->db->bind(':content_id', $contentId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->execute();
    }

    public function mediaRecordsForContent(int $ownerId, string $siteKey, int $contentId): array
    {
        $this->db->query("SELECT * FROM cms_media
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND id_content = :content_id
            ORDER BY created_at DESC, id DESC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':content_id', $contentId);

        return $this->db->fetchAll();
    }

    public function permanentlyDeleteContent(int $ownerId, string $siteKey, int $contentId): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->query("DELETE FROM cms_content_blocks
                WHERE id_owner = :owner_id
                  AND site_key = :site_key
                  AND id_content = :content_id");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':site_key', $siteKey);
            $this->db->bind(':content_id', $contentId);
            $this->db->execute();

            $this->db->query("DELETE FROM cms_routes
                WHERE id_owner = :owner_id
                  AND site_key = :site_key
                  AND id_content = :content_id");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':site_key', $siteKey);
            $this->db->bind(':content_id', $contentId);
            $this->db->execute();

            $this->db->query("DELETE FROM cms_media
                WHERE id_owner = :owner_id
                  AND site_key = :site_key
                  AND id_content = :content_id");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':site_key', $siteKey);
            $this->db->bind(':content_id', $contentId);
            $this->db->execute();

            $this->db->query("DELETE FROM cms_contents
                WHERE id = :content_id
                  AND id_owner = :owner_id
                  AND site_key = :site_key
                  AND status = 'ARCHIVED'");
            $this->db->bind(':content_id', $contentId);
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':site_key', $siteKey);
            $this->db->execute();

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function publishContentNow(int $ownerId, string $siteKey, int $contentId, int $approvedBy): void
    {
        $this->db->query("UPDATE cms_contents
            SET status = 'PUBLISHED',
                approval_status = 'APPROVED',
                approved_by = :approved_by,
                scheduled_at = NULL,
                published_at = COALESCE(published_at, NOW()),
                updated_at = NOW()
            WHERE id = :content_id
              AND id_owner = :owner_id
              AND site_key = :site_key");
        $this->db->bind(':approved_by', $approvedBy);
        $this->db->bind(':content_id', $contentId);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->execute();

        $this->db->query("UPDATE cms_routes
            SET status = 'ACTIVE',
                updated_at = NOW()
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND id_content = :content_id
              AND status != 'archived'");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':content_id', $contentId);
        $this->db->execute();
    }

    public function contentForAdmin(int $ownerId, string $siteKey, int $contentId): ?object
    {
        $this->db->query("SELECT c.*, r.route, r.canonical_url,
              t.name AS template_name, t.template_key, t.css_text AS template_css_text, t.template_structure_json,
              cat.name AS cms_category_name, cat.slug AS cms_category_slug
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            LEFT JOIN cms_templates t ON t.id = c.id_template
            LEFT JOIN cms_categories cat ON cat.id = c.id_cms_category
            WHERE c.id_owner = :owner_id AND c.site_key = :site_key AND c.id = :content_id
            LIMIT 1");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':content_id', $contentId);

        $content = $this->db->fetchOne();
        return $content ?: null;
    }

    public function createRoute(array $data): int
    {
        $route = $this->normalizePublicRoute((string)$data['route']);
        $routeAlt = $this->routeAlternate($route);
        $this->db->query("SELECT id, id_content, route FROM cms_routes
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND route IN (:route, :route_alt)
              AND status != 'archived'
            LIMIT 1");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':route', $route);
        $this->db->bind(':route_alt', $routeAlt);
        $existingRoute = $this->db->fetchOne();
        if ($existingRoute && (int)$existingRoute->id_content !== (int)$data['id_content']) {
            throw new \RuntimeException('Public route already belongs to another Growth Hub page: ' . $route);
        }
        if ($existingRoute && (string)$existingRoute->route !== $route) {
            $this->db->query("UPDATE cms_routes
                SET route = :route,
                    route_type = :route_type,
                    status = :status,
                    canonical_url = :canonical_url,
                    route_hash = SHA2(:hash_source, 256),
                    updated_at = NOW()
                WHERE id = :route_id
                  AND id_owner = :owner_id
                  AND site_key = :site_key");
            $this->db->bind(':route', $route);
            $this->db->bind(':route_type', (string)$data['route_type']);
            $this->db->bind(':status', (string)$data['status']);
            $this->db->bind(':canonical_url', $data['canonical_url'] ?? null);
            $this->db->bind(':hash_source', (int)$data['id_owner'] . '|' . (string)$data['site_key'] . '|' . $route);
            $this->db->bind(':route_id', (int)$existingRoute->id);
            $this->db->bind(':owner_id', (int)$data['id_owner']);
            $this->db->bind(':site_key', (string)$data['site_key']);
            $this->db->execute();

            return (int)$existingRoute->id;
        }

        $this->db->query("UPDATE cms_routes
            SET status = 'archived',
                redirect_to = :redirect_to,
                updated_at = NOW()
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND id_content = :content_id
              AND route NOT IN (:route, :route_alt)
              AND status != 'archived'");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':content_id', (int)$data['id_content']);
        $this->db->bind(':route', $route);
        $this->db->bind(':route_alt', $routeAlt);
        $this->db->bind(':redirect_to', $route);
        $this->db->execute();

        $this->db->query("INSERT INTO cms_routes
            (id_owner, site_key, id_content, route, route_type, status, canonical_url, route_hash)
            VALUES
            (:owner_id, :site_key, :content_id, :route, :route_type, :status, :canonical_url, SHA2(:hash_source, 256))
            ON DUPLICATE KEY UPDATE
              id_content = VALUES(id_content),
              route_type = VALUES(route_type),
              status = VALUES(status),
              canonical_url = VALUES(canonical_url),
              updated_at = NOW()");
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':content_id', (int)$data['id_content']);
        $this->db->bind(':route', $route);
        $this->db->bind(':route_type', (string)$data['route_type']);
        $this->db->bind(':status', (string)$data['status']);
        $this->db->bind(':canonical_url', $data['canonical_url'] ?? null);
        $this->db->bind(':hash_source', (int)$data['id_owner'] . '|' . (string)$data['site_key'] . '|' . $route);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function searchConsoleContextForPlan(int $ownerId, string $siteKey, string $keyword, string $location = '', int $limit = 6): array
    {
        $keyword = trim($keyword);
        $location = trim($location);
        $limit = max(1, min(12, $limit));

        $locationShort = trim(explode(',', $location)[0] ?? '');
        $terms = array_values(array_unique(array_filter([$keyword, $location, $locationShort])));
        if ($terms === []) {
            return [];
        }

        $sql = "SELECT keyword_text, page_url, clicks, impressions, ctr, average_position, date_from, date_to
            FROM seo_search_console_snapshots
            WHERE id_owner = :owner_id
              AND site_key = :site_key
              AND (";
        $clauses = [];
        foreach ($terms as $index => $_term) {
            $clauses[] = "(keyword_text LIKE :term{$index} OR page_url LIKE :term{$index})";
        }
        $sql .= implode(' OR ', $clauses) . ")
            ORDER BY impressions DESC, clicks DESC, average_position ASC
            LIMIT {$limit}";

        $this->db->query($sql);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        foreach ($terms as $index => $term) {
            $this->db->bind(":term{$index}", '%' . $term . '%');
        }

        return $this->db->fetchAll();
    }

    public function serpSnapshotsForPlan(int $ownerId, string $siteKey, string $keyword, string $location = '', int $limit = 4): array
    {
        $keyword = trim($keyword);
        $location = trim($location);
        $locationShort = trim(explode(',', $location)[0] ?? '');
        $limit = max(1, min(10, $limit));

        $sql = "SELECT keyword_text, location_name, device, search_engine, provider, own_best_position, own_best_url,
                   top_competitor_domain, top_competitor_position, result_count, intent_detected, recommended_content_type,
                   difficulty_estimate, validation_status, checked_at
            FROM seo_serp_snapshots
            WHERE id_owner = :owner_id
              AND site_key = :site_key";

        if ($keyword !== '') {
            $sql .= " AND keyword_text LIKE :keyword";
        }
        if ($location !== '') {
            $locationClauses = ["location_name LIKE :location", "county LIKE :location", "state LIKE :location"];
            if ($locationShort !== '' && $locationShort !== $location) {
                $locationClauses[] = "location_name LIKE :location_short";
            }
            $sql .= " AND (" . implode(' OR ', $locationClauses) . ")";
        }

        $sql .= " ORDER BY checked_at DESC, own_best_position ASC LIMIT {$limit}";

        $this->db->query($sql);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        if ($keyword !== '') {
            $this->db->bind(':keyword', '%' . $keyword . '%');
        }
        if ($location !== '') {
            $this->db->bind(':location', '%' . $location . '%');
            if ($locationShort !== '' && $locationShort !== $location) {
                $this->db->bind(':location_short', '%' . $locationShort . '%');
            }
        }

        return $this->db->fetchAll();
    }

    public function serpResultsForPlan(int $ownerId, string $siteKey, string $keyword, string $location = '', int $limit = 8): array
    {
        $keyword = trim($keyword);
        $location = trim($location);
        $locationShort = trim(explode(',', $location)[0] ?? '');
        $limit = max(1, min(15, $limit));

        $sql = "SELECT r.result_position, r.result_title, r.result_url, r.result_domain, r.snippet, r.result_type,
                   s.keyword_text, s.location_name, s.checked_at
            FROM seo_serp_results r
            INNER JOIN seo_serp_snapshots s ON s.id = r.id_snapshot
            WHERE s.id_owner = :owner_id
              AND s.site_key = :site_key";

        if ($keyword !== '') {
            $sql .= " AND s.keyword_text LIKE :keyword";
        }
        if ($location !== '') {
            $locationClauses = ["s.location_name LIKE :location", "s.county LIKE :location", "s.state LIKE :location"];
            if ($locationShort !== '' && $locationShort !== $location) {
                $locationClauses[] = "s.location_name LIKE :location_short";
            }
            $sql .= " AND (" . implode(' OR ', $locationClauses) . ")";
        }

        $sql .= " ORDER BY s.checked_at DESC, r.result_position ASC LIMIT {$limit}";

        $this->db->query($sql);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        if ($keyword !== '') {
            $this->db->bind(':keyword', '%' . $keyword . '%');
        }
        if ($location !== '') {
            $this->db->bind(':location', '%' . $location . '%');
            if ($locationShort !== '' && $locationShort !== $location) {
                $this->db->bind(':location_short', '%' . $locationShort . '%');
            }
        }

        return $this->db->fetchAll();
    }

    public function createDefaultBlocks(int $ownerId, string $siteKey, int $contentId, string $title, string $body, array $metadata = [], ?object $site = null): void
    {
        $researchPlan = is_array($metadata['research_plan'] ?? null) ? $metadata['research_plan'] : [];
        $faqCount = max(0, min(8, (int)($researchPlan['faq_count'] ?? 0)));
        $includeMap = !empty($researchPlan['include_map']);
        $providedData = trim((string)($researchPlan['provided_data'] ?? ''));
        $keyword = trim((string)($metadata['primary_keyword'] ?? ''));
        $location = trim((string)($metadata['target_location'] ?? ''));
        $gscContext = is_array($researchPlan['gsc_context'] ?? null) ? $researchPlan['gsc_context'] : [];
        $serpSnapshots = is_array($researchPlan['serp_snapshots'] ?? null) ? $researchPlan['serp_snapshots'] : [];
        $serpResults = is_array($researchPlan['serp_results'] ?? null) ? $researchPlan['serp_results'] : [];
        $externalQueries = is_array($researchPlan['external_queries'] ?? null) ? $researchPlan['external_queries'] : [];
        $competitorContext = is_array($researchPlan['competitor_context'] ?? null) ? $researchPlan['competitor_context'] : [];
        $antiCannibalization = is_array($researchPlan['anti_cannibalization'] ?? null) ? $researchPlan['anti_cannibalization'] : [];
        $imagePlan = is_array($researchPlan['image_plan'] ?? null) ? $researchPlan['image_plan'] : [];
        $citationPlan = is_array($researchPlan['citation_plan'] ?? null) ? $researchPlan['citation_plan'] : [];
        $ctaLabel = (string)($site->default_cta_label ?? 'Contact us');
        $ctaUrl = (string)($site->default_cta_url ?? '/contact');
        $brandName = (string)($site->site_name ?? $siteKey);
        $proofCount = count($gscContext) + count($serpResults);
        $subheadline = $providedData !== ''
            ? substr(preg_replace('/\s+/', ' ', $providedData), 0, 180)
            : trim(implode(' ', array_filter([
                $keyword !== '' ? 'Built around "' . $keyword . '"' : null,
                $location !== '' ? 'for ' . $location : null,
                $proofCount > 0 ? 'with ' . $proofCount . ' search signals attached for review.' : 'with a research checklist ready for validation.',
            ])));

        $blocks = [
            ['hero', 'hero', $title, [
                'eyebrow' => strtoupper(str_replace(['-', '_'], ' ', (string)($metadata['content_category'] ?? $siteKey))),
                'headline' => $title,
                'subheadline' => $subheadline,
            ]],
            ['body_section', 'why_this_page', 'Why this page exists', [
                'body' => $this->draftIntroHtml($brandName, $keyword, $location, $providedData, $gscContext, $serpSnapshots),
            ]],
            ['body_section', 'search_console_evidence', 'Search Console signals to use', [
                'body' => $this->searchConsoleEvidenceHtml($gscContext, $keyword),
            ]],
            ['body_section', 'serp_evidence', 'SERP and competitor notes', [
                'body' => $this->serpEvidenceHtml($serpSnapshots, $serpResults, $keyword, $location, $competitorContext),
            ]],
            ['body_section', 'content_plan', 'Recommended page structure', [
                'body' => $this->contentPlanHtml($title, $brandName, $keyword, $location, $externalQueries, $antiCannibalization),
            ]],
            ['body_section', 'media_plan', 'Image and citation plan', [
                'body' => $this->mediaAndCitationPlanHtml($imagePlan, $citationPlan, $keyword, $location),
            ]],
            ['body_section', 'internal_linking_plan', 'Internal links to verify', [
                'body' => $this->internalLinkPlanHtml($brandName, $keyword, $location, $metadata),
            ]],
            ['cta', 'primary_cta', 'Call to action', [
                'text' => 'Review the attached search signals, tighten claims, then approve this page for publishing and indexing.',
                'label' => $ctaLabel,
                'url' => $ctaUrl,
            ]],
        ];

        if ($faqCount > 0) {
            $items = $this->faqItemsForDraft($keyword, $location, $brandName, $faqCount);

            $blocks[] = ['faq', 'faq', 'FAQ', ['items' => $items]];
        }

        if ($includeMap) {
            $blocks[] = ['local_map', 'local_map', 'Local map and area context', [
                'body' => $location !== ''
                    ? 'Add a verified embedded map and service-area note for ' . $location . ' before publishing.'
                    : 'Add a verified embedded map only after selecting a concrete target location.',
            ]];
        }

        foreach ($blocks as $index => [$type, $key, $blockTitle, $payload]) {
            $this->db->query("INSERT INTO cms_content_blocks
                (id_owner, site_key, id_content, block_type, block_key, title, data_json, sort_order, status)
                VALUES
                (:owner_id, :site_key, :content_id, :block_type, :block_key, :title, :data_json, :sort_order, 'active')");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':site_key', $siteKey);
            $this->db->bind(':content_id', $contentId);
            $this->db->bind(':block_type', $type);
            $this->db->bind(':block_key', $key);
            $this->db->bind(':title', $blockTitle);
            $this->db->bind(':data_json', json_encode($payload));
            $this->db->bind(':sort_order', ($index + 1) * 10);
            $this->db->execute();
        }
    }

    private function draftIntroHtml(string $brandName, string $keyword, string $location, string $providedData, array $gscContext, array $serpSnapshots): string
    {
        $parts = [];
        $target = trim(implode(' in ', array_filter([$keyword, $location])));
        $parts[] = '<p>This draft is built for <strong>' . $this->escape($brandName) . '</strong>'
            . ($target !== '' ? ' around <strong>' . $this->escape($target) . '</strong>' : '')
            . '. It should become a useful page only after the evidence below is reviewed and turned into specific claims.</p>';

        if ($providedData !== '') {
            $parts[] = '<p><strong>Business notes:</strong> ' . nl2br($this->escape($providedData)) . '</p>';
        }

        $parts[] = '<ul>'
            . '<li>' . count($gscContext) . ' Search Console rows attached for query demand, page overlap and position gaps.</li>'
            . '<li>' . count($serpSnapshots) . ' SERP snapshots attached for competitive validation.</li>'
            . '<li>Use services and offers as the primary strategy; keywords should support the page, not define it alone.</li>'
            . '</ul>';

        return implode("\n", $parts);
    }

    private function searchConsoleEvidenceHtml(array $rows, string $keyword): string
    {
        if ($rows === []) {
            return '<p>No matching Search Console rows were found for this draft yet. Keep this page as a brief until GSC data is imported for '
                . ($keyword !== '' ? '<strong>' . $this->escape($keyword) . '</strong>' : 'the selected service')
                . '.</p>';
        }

        $html = ['<p>Use these real queries to decide what the page must answer and whether it should optimize an existing URL or become a dedicated page.</p>', '<ul>'];
        foreach ($rows as $row) {
            $query = $this->rowValue($row, 'keyword_text', 'Unknown query');
            $page = $this->rowValue($row, 'page_url', '');
            $impressions = (int)$this->rowValue($row, 'impressions', 0);
            $clicks = (int)$this->rowValue($row, 'clicks', 0);
            $position = $this->rowValue($row, 'average_position', '');
            $html[] = '<li><strong>' . $this->escape($query) . '</strong>'
                . ' - ' . number_format($impressions) . ' impressions, ' . number_format($clicks) . ' clicks'
                . ($position !== '' ? ', avg position ' . $this->escape((string)round((float)$position, 1)) : '')
                . ($page !== '' ? '<br><small>Current page: ' . $this->escape($page) . '</small>' : '')
                . '</li>';
        }
        $html[] = '</ul>';

        return implode("\n", $html);
    }

    private function serpEvidenceHtml(array $snapshots, array $results, string $keyword, string $location, array $competitorContext = []): string
    {
        if ($snapshots === [] && $results === [] && $competitorContext === []) {
            return '<p>SERP validation is not complete for this draft yet. Queue or import SERP data before publishing so headings, FAQs and proof points reflect what Google is rewarding.</p>';
        }

        $html = [];
        if ($competitorContext !== [] && (!empty($competitorContext['name']) || !empty($competitorContext['domain']))) {
            $html[] = '<p><strong>Tracked competitor signal:</strong> '
                . $this->escape(trim((string)($competitorContext['name'] ?? 'Competitor')))
                . (!empty($competitorContext['domain']) ? ' (' . $this->escape((string)$competitorContext['domain']) . ')' : '')
                . '.</p>';
            if (!empty($competitorContext['source_reason'])) {
                $html[] = '<p><small>' . $this->escape((string)$competitorContext['source_reason']) . '</small></p>';
            }
        }

        if ($snapshots !== []) {
            $html[] = '<p><strong>Snapshot summary:</strong></p><ul>';
            foreach ($snapshots as $snapshot) {
                $ownPosition = $this->rowValue($snapshot, 'own_best_position', '');
                $competitor = $this->rowValue($snapshot, 'top_competitor_domain', '');
                $intent = $this->rowValue($snapshot, 'intent_detected', '');
                $recommendedType = $this->rowValue($snapshot, 'recommended_content_type', '');
                $html[] = '<li>'
                    . ($ownPosition !== '' ? 'Own best position: ' . $this->escape((string)$ownPosition) . '. ' : '')
                    . ($competitor !== '' ? 'Top competitor: ' . $this->escape($competitor) . '. ' : '')
                    . ($intent !== '' ? 'Intent: ' . $this->escape($intent) . '. ' : '')
                    . ($recommendedType !== '' ? 'Recommended type: ' . $this->escape($recommendedType) . '.' : '')
                    . '</li>';
            }
            $html[] = '</ul>';
        }

        if ($results !== []) {
            $html[] = '<p><strong>Organic results to review:</strong></p><ul>';
            foreach ($results as $result) {
                $title = $this->rowValue($result, 'result_title', 'Untitled result');
                $domain = $this->rowValue($result, 'result_domain', '');
                $position = $this->rowValue($result, 'result_position', '');
                $snippet = $this->rowValue($result, 'snippet', '');
                $html[] = '<li>'
                    . ($position !== '' ? '#' . $this->escape((string)$position) . ' ' : '')
                    . '<strong>' . $this->escape($title) . '</strong>'
                    . ($domain !== '' ? ' - ' . $this->escape($domain) : '')
                    . ($snippet !== '' ? '<br><small>' . $this->escape($snippet) . '</small>' : '')
                    . '</li>';
            }
            $html[] = '</ul>';
        }

        if ($keyword !== '' || $location !== '') {
            $html[] = '<p>Review angle: compare the above results against the selected service, location, offer clarity, FAQs and proof points before final approval.</p>';
        }

        return implode("\n", $html);
    }

    private function contentPlanHtml(string $title, string $brandName, string $keyword, string $location, array $externalQueries, array $antiCannibalization = []): string
    {
        $sections = [
            'Open with the service and location promise, not a generic keyword introduction.',
            'Explain who the page is for and which offer or package it supports.',
            'Use Search Console rows as evidence for what people already search and where the current site is weak.',
            'Use SERP rows to identify missing sections, examples, FAQs and proof competitors are using.',
            'Add a clear CTA that matches the selected brand and avoid unsupported claims about pricing, awards or availability.',
        ];

        $html = ['<p><strong>Working title:</strong> ' . $this->escape($title) . '</p>', '<ol>'];
        foreach ($sections as $section) {
            $html[] = '<li>' . $this->escape($section) . '</li>';
        }
        $html[] = '</ol>';

        if ($externalQueries !== []) {
            $html[] = '<p><strong>Queries to validate externally:</strong> ' . $this->escape(implode(' - ', array_map('strval', $externalQueries))) . '</p>';
        }

        $match = is_array($antiCannibalization['matched_content'] ?? null) ? $antiCannibalization['matched_content'] : null;
        if ($match) {
            $html[] = '<p><strong>Anti-cannibalization warning:</strong> Review existing page "'
                . $this->escape((string)($match['title'] ?? 'Existing page'))
                . '"'
                . (!empty($match['route']) ? ' at ' . $this->escape((string)$match['route']) : '')
                . ' before publishing this as a new URL.</p>';
        } else {
            $html[] = '<p><strong>Anti-cannibalization:</strong> No close CMS match was found at draft creation, but production service URLs still need a final check.</p>';
        }

        $html[] = '<p><strong>Brand lens:</strong> Keep the copy specific to ' . $this->escape($brandName)
            . ($location !== '' ? ' and the ' . $this->escape($location) . ' market' : '')
            . ($keyword !== '' ? ', with "' . $this->escape($keyword) . '" used naturally in headings and answers.' : '.')
            . '</p>';

        return implode("\n", $html);
    }

    private function mediaAndCitationPlanHtml(array $imagePlan, array $citationPlan, string $keyword, string $location): string
    {
        $html = [];
        if ($imagePlan !== []) {
            $html[] = '<p><strong>Images to generate or register:</strong></p><ul>';
            foreach ($imagePlan as $item) {
                $html[] = '<li>'
                    . $this->escape((string)($item['usage_type'] ?? 'image'))
                    . ' - ' . $this->escape((string)($item['prompt_brief'] ?? 'Brand image'))
                    . '<br><small>Alt text: ' . $this->escape((string)($item['alt_text'] ?? '')) . '</small>'
                    . '</li>';
            }
            $html[] = '</ul>';
        } else {
            $html[] = '<p>No generated images were requested. Use an existing Cloudinary image or brand fallback before publishing if this page needs a thumbnail.</p>';
        }

        if ($citationPlan !== []) {
            $queries = is_array($citationPlan['queries_to_review'] ?? null) ? $citationPlan['queries_to_review'] : [];
            $html[] = '<p><strong>Citations to review:</strong> ' . (int)($citationPlan['requested_count'] ?? 0) . ' requested.</p>';
            if ($queries !== []) {
                $html[] = '<p><small>Review queries: ' . $this->escape(implode(' - ', array_map('strval', $queries))) . '</small></p>';
            }
            $html[] = '<p><small>' . $this->escape((string)($citationPlan['rule'] ?? 'Use reviewed citations only.')) . '</small></p>';
        } else {
            $html[] = '<p>No external citations were requested. If the page uses Quora, Reddit, forum or third-party claims, add reviewed citation metadata before publishing.</p>';
        }

        if ($keyword !== '' || $location !== '') {
            $html[] = '<p>Keep media and citations aligned with '
                . $this->escape(trim(implode(' in ', array_filter([$keyword, $location]))))
                . '.</p>';
        }

        return implode("\n", $html);
    }

    private function internalLinkPlanHtml(string $brandName, string $keyword, string $location, array $metadata): string
    {
        $service = trim((string)($metadata['service_or_product'] ?? ''));
        $category = trim((string)($metadata['content_category_name'] ?? $metadata['content_category'] ?? ''));
        $items = [
            'Verify the official production service URL before adding an internal link.',
            'Link to an existing Growth Hub route if one already covers the same service intent.',
            'Do not publish a new URL if an existing page should be optimized instead.',
        ];

        $html = ['<ul>'];
        foreach ($items as $item) {
            $html[] = '<li>' . $this->escape($item) . '</li>';
        }
        $html[] = '</ul>';
        $html[] = '<p><small>Context: ' . $this->escape($brandName)
            . ($service !== '' ? ' / service: ' . $service : '')
            . ($category !== '' ? ' / category: ' . $category : '')
            . ($keyword !== '' ? ' / keyword: ' . $keyword : '')
            . ($location !== '' ? ' / market: ' . $location : '')
            . '.</small></p>';

        return implode("\n", $html);
    }

    private function faqItemsForDraft(string $keyword, string $location, string $brandName, int $count): array
    {
        $topic = $keyword !== '' ? $keyword : 'this service';
        $place = $location !== '' ? ' in ' . $location : '';
        $questions = [
            'What is included with ' . $topic . $place . '?',
            'How do I know whether this should be a dedicated page or an update to an existing page?',
            'What Search Console signals support this topic?',
            'Which SERP competitors should be reviewed before publishing?',
            'What details should ' . $brandName . ' verify before this page goes live?',
            'What photos, examples or proof points would make this page more useful?',
            'How should visitors take the next step after reading this page?',
            'What local or service-specific questions should the page answer?',
        ];

        $items = [];
        foreach (array_slice($questions, 0, max(1, $count)) as $question) {
            $items[] = [
                'question' => $question,
                'answer' => 'Answer this with verified business details and the attached search data before publishing.',
            ];
        }

        return $items;
    }

    private function rowValue(mixed $row, string $key, mixed $default = ''): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }
        if (is_object($row) && isset($row->{$key})) {
            return $row->{$key};
        }

        return $default;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function registerMedia(array $data): int
    {
        $this->db->query("INSERT INTO cms_media
            (id_owner, site_key, id_content, related_block_id, cloudinary_public_id, cloudinary_url, secure_url, asset_type, media_type, source_type, usage_type, prompt_used, revised_prompt, model, alt_text, title_text, caption, width, height, format, bytes, folder, metadata_json, status, created_by)
            VALUES
            (:owner_id, :site_key, :content_id, :block_id, :public_id, :url, :secure_url, :asset_type, :media_type, :source_type, :usage_type, :prompt_used, :revised_prompt, :model, :alt_text, :title_text, :caption, :width, :height, :format, :bytes, :folder, :metadata_json, :status, :created_by)");

        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':content_id', $data['id_content'] ? (int)$data['id_content'] : null);
        $this->db->bind(':block_id', $data['related_block_id'] ? (int)$data['related_block_id'] : null);
        $this->db->bind(':public_id', (string)$data['cloudinary_public_id']);
        $this->db->bind(':url', $data['cloudinary_url'] ?? null);
        $this->db->bind(':secure_url', (string)$data['secure_url']);
        $this->db->bind(':asset_type', $data['asset_type'] ?? null);
        $this->db->bind(':media_type', $data['media_type'] ?? null);
        $this->db->bind(':source_type', (string)($data['source_type'] ?? 'uploaded'));
        $this->db->bind(':usage_type', (string)($data['usage_type'] ?? 'gallery'));
        $this->db->bind(':prompt_used', $data['prompt_used'] ?? null);
        $this->db->bind(':revised_prompt', $data['revised_prompt'] ?? null);
        $this->db->bind(':model', $data['model'] ?? null);
        $this->db->bind(':alt_text', $data['alt_text'] ?? null);
        $this->db->bind(':title_text', $data['title_text'] ?? null);
        $this->db->bind(':caption', $data['caption'] ?? null);
        $this->db->bind(':width', isset($data['width']) ? (int)$data['width'] : null);
        $this->db->bind(':height', isset($data['height']) ? (int)$data['height'] : null);
        $this->db->bind(':format', $data['format'] ?? null);
        $this->db->bind(':bytes', isset($data['bytes']) ? (int)$data['bytes'] : null);
        $this->db->bind(':folder', $data['folder'] ?? null);
        $this->db->bind(':metadata_json', json_encode($data['metadata_json'] ?? []));
        $this->db->bind(':status', (string)($data['status'] ?? 'active'));
        $this->db->bind(':created_by', !empty($data['created_by']) ? (int)$data['created_by'] : null);
        $this->db->execute();

        return (int)$this->db->lastId();
    }

    public function publishedContent(string $siteKey, ?string $type = null): array
    {
        $site = $this->getSiteByKey($siteKey);
        if (!$site) {
            return [];
        }

        $sql = "SELECT c.*, r.route, COALESCE(r.canonical_url, c.canonical_url) AS canonical_url, r.status AS route_status,
                   t.name AS template_name, t.template_key, t.css_text AS template_css_text, t.template_structure_json,
                   cat.name AS cms_category_name, cat.slug AS cms_category_slug
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            LEFT JOIN cms_templates t ON t.id = c.id_template AND t.site_key = c.site_key
            LEFT JOIN cms_categories cat ON cat.id = c.id_cms_category AND cat.site_key = c.site_key
            WHERE c.id_owner = :owner_id
              AND c.site_key = :site_key
              AND c.status = 'PUBLISHED'
              AND c.approval_status IN ('APPROVED', 'PUBLISHED')";

        if ($type) {
            $sql .= " AND c.content_type = :content_type";
        }

        $sql .= " ORDER BY c.published_at DESC, c.updated_at DESC";
        $this->db->query($sql);
        $this->db->bind(':owner_id', (int)$site->id_owner);
        $this->db->bind(':site_key', $siteKey);
        if ($type) {
            $this->db->bind(':content_type', $type);
        }

        return $this->db->fetchAll();
    }

    public function contentBySlug(string $siteKey, string $slug): ?object
    {
        $site = $this->getSiteByKey($siteKey);
        if (!$site) {
            return null;
        }

        $this->db->query("SELECT c.*, r.route, COALESCE(r.canonical_url, c.canonical_url) AS canonical_url, r.status AS route_status,
              t.name AS template_name, t.template_key, t.css_text AS template_css_text, t.template_structure_json,
              cat.name AS cms_category_name, cat.slug AS cms_category_slug
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            LEFT JOIN cms_templates t ON t.id = c.id_template AND t.site_key = c.site_key
            LEFT JOIN cms_categories cat ON cat.id = c.id_cms_category AND cat.site_key = c.site_key
            WHERE c.id_owner = :owner_id
              AND c.site_key = :site_key
              AND c.slug = :slug
              AND c.status = 'PUBLISHED'
              AND c.approval_status IN ('APPROVED', 'PUBLISHED')
            LIMIT 1");
        $this->db->bind(':owner_id', (int)$site->id_owner);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':slug', $slug);

        $content = $this->db->fetchOne();
        return $content ?: null;
    }

    public function contentByRoute(string $siteKey, string $route): ?object
    {
        $site = $this->getSiteByKey($siteKey);
        if (!$site) {
            return null;
        }

        $route = $this->normalizePublicRoute($route);
        $routeAlt = $this->routeAlternate($route);
        $this->db->query("SELECT c.*, r.route, COALESCE(r.canonical_url, c.canonical_url) AS canonical_url, r.status AS route_status,
              t.name AS template_name, t.template_key, t.css_text AS template_css_text, t.template_structure_json,
              cat.name AS cms_category_name, cat.slug AS cms_category_slug
            FROM cms_routes r
            INNER JOIN cms_contents c ON c.id = r.id_content AND c.id_owner = r.id_owner AND c.site_key = r.site_key
            LEFT JOIN cms_templates t ON t.id = c.id_template AND t.site_key = c.site_key
            LEFT JOIN cms_categories cat ON cat.id = c.id_cms_category AND cat.site_key = c.site_key
            WHERE r.id_owner = :owner_id
              AND r.site_key = :site_key
              AND r.route IN (:route, :route_alt)
              AND r.status != 'archived'
              AND c.status = 'PUBLISHED'
              AND c.approval_status IN ('APPROVED', 'PUBLISHED')
            LIMIT 1");
        $this->db->bind(':owner_id', (int)$site->id_owner);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':route', $route);
        $this->db->bind(':route_alt', $routeAlt);

        $content = $this->db->fetchOne();
        return $content ?: null;
    }

    public function publishedContentById(string $siteKey, int $contentId): ?object
    {
        $site = $this->getSiteByKey($siteKey);
        if (!$site) {
            return null;
        }

        $this->db->query("SELECT c.*, r.route, COALESCE(r.canonical_url, c.canonical_url) AS canonical_url, r.status AS route_status,
              t.name AS template_name, t.template_key, t.css_text AS template_css_text, t.template_structure_json,
              cat.name AS cms_category_name, cat.slug AS cms_category_slug
            FROM cms_contents c
            LEFT JOIN cms_routes r ON r.id_content = c.id AND r.id_owner = c.id_owner AND r.site_key = c.site_key AND r.status != 'archived'
            LEFT JOIN cms_templates t ON t.id = c.id_template AND t.site_key = c.site_key
            LEFT JOIN cms_categories cat ON cat.id = c.id_cms_category AND cat.site_key = c.site_key
            WHERE c.id_owner = :owner_id
              AND c.site_key = :site_key
              AND c.id = :content_id
              AND c.status = 'PUBLISHED'
              AND c.approval_status IN ('APPROVED', 'PUBLISHED')
            LIMIT 1");
        $this->db->bind(':owner_id', (int)$site->id_owner);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':content_id', $contentId);

        $content = $this->db->fetchOne();
        return $content ?: null;
    }

    public function blocksForContent(int $ownerId, string $siteKey, int $contentId): array
    {
        $this->db->query("SELECT * FROM cms_content_blocks
            WHERE id_owner = :owner_id AND site_key = :site_key AND id_content = :content_id AND status = 'active'
            ORDER BY sort_order ASC, id ASC");
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        $this->db->bind(':content_id', $contentId);

        return $this->db->fetchAll();
    }

    public function mediaForContent(int $ownerId, string $siteKey, ?int $contentId = null): array
    {
        $sql = "SELECT * FROM cms_media WHERE id_owner = :owner_id AND site_key = :site_key AND status = 'active'";
        if ($contentId) {
            $sql .= " AND id_content = :content_id";
        }
        $sql .= " ORDER BY created_at DESC";

        $this->db->query($sql);
        $this->db->bind(':owner_id', $ownerId);
        $this->db->bind(':site_key', $siteKey);
        if ($contentId) {
            $this->db->bind(':content_id', $contentId);
        }

        return $this->db->fetchAll();
    }

    public function updatePublicContract(int $ownerId, string $siteKey, int $contentId, array $data): void
    {
        $sets = [];
        $bindings = [
            ':content_id' => $contentId,
            ':owner_id' => $ownerId,
            ':site_key' => $siteKey,
            ':seo_title' => $data['seo_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
        ];

        foreach ([
            'type',
            'body_html',
            'meta_title',
            'canonical_url',
            'robots',
            'featured_image_url',
            'schema_json',
        ] as $column) {
            if (!$this->tableHasColumn('cms_contents', $column)) {
                continue;
            }

            $placeholder = ':' . $column;
            $sets[] = "{$column} = {$placeholder}";
            $bindings[$placeholder] = $column === 'schema_json'
                ? json_encode($data[$column] ?? [])
                : ($data[$column] ?? null);
        }

        $sets[] = "seo_title = COALESCE(NULLIF(seo_title, ''), :seo_title)";
        $sets[] = "meta_description = COALESCE(NULLIF(meta_description, ''), :meta_description)";
        $sets[] = 'updated_at = NOW()';

        $this->db->query("UPDATE cms_contents SET " . implode(', ', $sets) . "
            WHERE id = :content_id AND id_owner = :owner_id AND site_key = :site_key");
        foreach ($bindings as $placeholder => $value) {
            $this->db->bind($placeholder, $value);
        }
        $this->db->execute();
    }

    private function bindContent(array $data): void
    {
        $this->db->bind(':owner_id', (int)$data['id_owner']);
        $this->db->bind(':template_id', !empty($data['id_template']) ? (int)$data['id_template'] : null);
        $this->db->bind(':cms_category_id', !empty($data['id_cms_category']) ? (int)$data['id_cms_category'] : null);
        $this->db->bind(':site_key', (string)$data['site_key']);
        $this->db->bind(':content_type', (string)$data['content_type']);
        $this->db->bind(':title', (string)$data['title']);
        $this->db->bind(':slug', (string)$data['slug']);
        $this->db->bind(':excerpt', $data['excerpt'] ?? null);
        $this->db->bind(':body', $data['body'] ?? null);
        $this->db->bind(':seo_title', $data['seo_title'] ?? null);
        $this->db->bind(':meta_description', $data['meta_description'] ?? null);
        $this->db->bind(':primary_keyword', $data['primary_keyword'] ?? null);
        $this->db->bind(':target_location', $data['target_location'] ?? null);
        $this->db->bind(':status', (string)($data['status'] ?? 'DRAFT'));
        $this->db->bind(':approval_status', (string)($data['approval_status'] ?? 'DRAFT'));
        $this->db->bind(':created_by', $data['created_by'] ? (int)$data['created_by'] : null);
        $this->db->bind(':schema_json', json_encode($data['schema_json'] ?? []));
        $this->db->bind(':metadata_json', json_encode($data['metadata_json'] ?? []));
    }

    private function normalizePublicRoute(string $route): string
    {
        $route = '/' . trim($route, '/');
        return $route === '/' ? '/' : rtrim($route, '/') . '/';
    }

    private function routeAlternate(string $route): string
    {
        $route = $this->normalizePublicRoute($route);
        return $route === '/' ? '/' : rtrim($route, '/');
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!array_key_exists($table, $this->tableColumns)) {
            $this->db->query("SHOW COLUMNS FROM {$table}");
            $this->tableColumns[$table] = array_map(
                static fn(object $row): string => (string)($row->Field ?? ''),
                $this->db->fetchAll()
            );
        }

        return in_array($column, $this->tableColumns[$table], true);
    }

    private function domainFromUrl(string $url): string
    {
        $candidate = trim($url);
        if ($candidate === '') {
            return '';
        }

        $host = parse_url(str_starts_with($candidate, 'http') ? $candidate : 'https://' . $candidate, PHP_URL_HOST);
        $domain = strtolower((string)($host ?: $candidate));

        return preg_replace('/^www\./', '', $domain) ?: $domain;
    }
}
