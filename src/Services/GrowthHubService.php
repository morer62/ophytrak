<?php

namespace App\Services;

use App\Entity\User;
use App\Repositories\GrowthHubRepository;
use App\Services\Serp\DataForSEOSerpProvider;
use App\Utils\FileUtils;
use Exception;

class GrowthHubService
{
    private GrowthHubRepository $repo;

    private const GENERATION_MODES = [
        'page_review' => ['label' => 'Page review', 'max_tokens' => 3200, 'target_sources' => 2, 'min_sources_to_publish' => 0],
        'short_article' => ['label' => 'Short article', 'max_tokens' => 4500, 'target_sources' => 4, 'min_sources_to_publish' => 2],
        'seo_standard' => ['label' => 'SEO standard article', 'max_tokens' => 8000, 'target_sources' => 6, 'min_sources_to_publish' => 3],
        'deep_article' => ['label' => 'Deep article', 'max_tokens' => 12000, 'target_sources' => 10, 'min_sources_to_publish' => 5],
    ];

    private const CONTENT_INTENTS = [
        'brand_informational' => [
            'label' => 'Brand informational',
            'title_style' => 'Clear, trustworthy and service-specific. The title can mention the brand when useful.',
            'body_style' => 'Inform the visitor, explain the service, show brand fit and include practical buyer questions.',
            'fiction_allowed' => false,
        ],
        'provocative_story' => [
            'label' => 'Provocative story',
            'title_style' => 'Curious, story-driven and slightly provocative without clickbait or unsupported claims.',
            'body_style' => 'Open with a clearly fictional but realistic scenario, then use the story to explain the service, problem and decision points.',
            'fiction_allowed' => true,
        ],
        'local_countdown' => [
            'label' => 'Local countdown',
            'title_style' => 'Countdown/list title with a local or practical hook.',
            'body_style' => 'Structure the article as a numbered countdown or ranked list with useful local/service context and a CTA.',
            'fiction_allowed' => false,
        ],
        'editorial_rewrite' => [
            'label' => 'Editorial rewrite',
            'title_style' => 'Magazine-style blog headline with narrative energy and strong positioning.',
            'body_style' => 'Read like a polished rewritten blog article: story arc, sharper transitions, stronger positioning and useful takeaways.',
            'fiction_allowed' => false,
        ],
        'tutorial_learning' => [
            'label' => 'Tutorial / learning',
            'title_style' => 'How-to title focused on learning, steps or mistakes to avoid.',
            'body_style' => 'Teach the reader how to do or evaluate something with steps, examples, checks and practical warnings.',
            'fiction_allowed' => false,
        ],
    ];

    private const GENERIC_PHRASES = [
        'in today\'s fast-paced world',
        'when it comes to',
        'it is important to note',
        'there are many factors to consider',
        'in this article',
        'look no further',
        'whether you are planning',
    ];

    public function __construct()
    {
        $this->repo = new GrowthHubRepository();
    }

    public function dashboard(?User $user, string $siteKey, array $researchInput = []): array
    {
        $sites = $this->repo->getSites();
        $site = null;

        foreach ($sites as $candidate) {
            if ($candidate->site_key === $siteKey) {
                $site = $candidate;
                break;
            }
        }

        if (!$site && $sites) {
            $site = $sites[0];
            $siteKey = (string)$site->site_key;
        }

        if (!$site) {
            return [
                'sites' => [],
                'site' => null,
                'selected_site_key' => $siteKey,
                'sitemap_settings' => [],
                'contents' => [],
                'media' => [],
                'content_count' => 0,
                'media_count' => 0,
                'competitor_count' => 0,
                'keyword_count' => 0,
                'target_locations' => [],
                'site_services' => [],
                'site_products' => [],
                'cms_categories' => [],
                'cms_templates' => [],
                'gsc_page_opportunities' => [],
                'serp_statuses' => [],
                'serp_health' => [
                    'status' => 'not_configured',
                    'label' => 'SERP not configured',
                    'message' => 'DataForSEO credentials are not configured.',
                    'last_error' => '',
                ],
                'competitors' => [],
                'competitor_ai_opportunities' => [],
                'market_opportunities' => [],
                'keywords' => [],
                'serp_snapshots' => [],
                'search_console_statuses' => [],
                'opportunities' => [],
                'suggested_opportunities' => [],
                'research_lab' => $this->emptyResearchLab($siteKey, $researchInput),
                'calendar' => [],
                'editing_content' => null,
                'preview_content' => null,
                'active_step' => (string)($researchInput['step'] ?? '1'),
            ];
        }

        $ownerId = (int)$site->id_owner;
        $previewContentId = !empty($researchInput['preview_content_id']) ? (int)$researchInput['preview_content_id'] : 0;

        $competitors = $this->repo->competitors($ownerId, $siteKey);
        $serpSnapshots = $this->repo->latestSerpSnapshots($ownerId, $siteKey);
        $serpStatuses = $this->repo->serpImportStatuses($ownerId, $siteKey);
        $servicesRaw = $this->decodeJsonField($site->main_services ?? []);
        $productsRaw = $this->decodeJsonField($site->main_products ?? []);
        $services = $this->offerLabels($servicesRaw);
        $products = $this->offerLabels($productsRaw);
        $contentsForMatching = $this->repo->recentContents($ownerId, $siteKey, 200);
        $dismissedKeys = $this->repo->archivedOpportunityKeys($ownerId, $siteKey);
        $analysisCompetitorId = (int)($researchInput['analysis_competitor'] ?? 0);
        $analysisCompetitor = $this->findCompetitorById($competitors, $analysisCompetitorId);
        if (!$analysisCompetitor && $competitors !== []) {
            $analysisCompetitor = $competitors[0];
            $analysisCompetitorId = (int)($analysisCompetitor->id ?? 0);
        }
        $analysisService = trim((string)($researchInput['analysis_service'] ?? ($services[0] ?? '')));
        if ($analysisService === '' && $analysisCompetitor) {
            $analysisService = trim((string)($analysisCompetitor->service_or_product ?? ''));
        }
        $analysisLocation = trim((string)($researchInput['analysis_location'] ?? ''));
        if ($analysisLocation === '' && $analysisCompetitor) {
            $analysisLocation = $this->competitorMarket($analysisCompetitor);
        }
        $analysisServices = $analysisService !== '' ? [$analysisService] : $services;
        $analysisCompetitors = $analysisCompetitor ? [$analysisCompetitor] : $competitors;

        return [
            'sites' => $sites,
            'site' => $site,
            'selected_site_key' => $siteKey,
            'sitemap_settings' => $this->decodeJsonField($site->sitemap_settings ?? []),
            'contents' => $this->repo->recentContents($ownerId, $siteKey),
            'media' => $this->repo->recentMedia($ownerId, $siteKey),
            'target_locations' => $this->repo->targetLocations($ownerId, $siteKey),
            'site_services' => $services,
            'site_products' => $products,
            'site_service_offers' => $this->offerEntries($servicesRaw),
            'site_product_offers' => $this->offerEntries($productsRaw),
            'site_service_lines' => $this->offerLines($servicesRaw),
            'site_product_lines' => $this->offerLines($productsRaw),
            'cms_categories' => $this->repo->cmsCategories($ownerId, $siteKey),
            'cms_templates' => $this->repo->cmsTemplates($ownerId, $siteKey),
            'gsc_page_opportunities' => $this->searchConsolePageOpportunities($ownerId, $siteKey, $services, $dismissedKeys, $contentsForMatching),
            'competitors' => $competitors,
            'keywords' => $this->repo->keywords($ownerId, $siteKey),
            'serp_snapshots' => $serpSnapshots,
            'serp_statuses' => $serpStatuses,
            'serp_health' => $this->serpHealth($serpStatuses, $serpSnapshots),
            'search_console_statuses' => $this->repo->latestSearchConsoleStatuses($ownerId, $siteKey),
            'opportunities' => $this->repo->opportunities($ownerId, $siteKey),
            'suggested_opportunities' => $this->suggestOpportunities($ownerId, $siteKey),
            'market_opportunities' => $this->marketOpportunities($ownerId, $siteKey, $dismissedKeys, $contentsForMatching),
            'competitor_ai_opportunities' => $this->competitorFallbackPreview($site, $analysisCompetitors, $analysisServices, [], $analysisLocation, $dismissedKeys, $contentsForMatching),
            'analysis_competitor' => $analysisCompetitorId,
            'analysis_competitor_row' => $analysisCompetitor,
            'analysis_service' => $analysisService,
            'analysis_location' => $analysisLocation,
            'research_lab' => $this->researchLab($site, $researchInput),
            'calendar' => $this->repo->calendarContents($ownerId, $siteKey),
            'editing_content' => !empty($_GET['edit_content_id'])
                ? $this->repo->contentForAdmin($ownerId, $siteKey, (int)$_GET['edit_content_id'])
                : null,
            'preview_content' => $previewContentId > 0
                ? $this->adminContentPreview($siteKey, $previewContentId)
                : null,
            'content_count' => $this->repo->countContents($ownerId, $siteKey),
            'media_count' => $this->repo->countMedia($ownerId, $siteKey),
            'competitor_count' => $this->repo->countCompetitors($ownerId, $siteKey),
            'keyword_count' => $this->repo->countKeywords($ownerId, $siteKey),
            'can_manage' => $user && (int)$user->getLevel() === 1,
            'active_step' => (string)($researchInput['step'] ?? ($previewContentId > 0 ? '3' : '1')),
        ];
    }

    public function contentInventory(?User $user, string $siteKey, array $filters = []): array
    {
        $dashboard = $this->dashboard($user, $siteKey);
        $site = $dashboard['site'];

        if (!$site) {
            $dashboard['content_inventory'] = [];
            $dashboard['content_status_counts'] = [];
            $dashboard['filters'] = $filters;
            return $dashboard;
        }

        $ownerId = (int)$site->id_owner;
        $resolvedSiteKey = (string)$site->site_key;

        $dashboard['content_inventory'] = $this->repo->contentInventory($ownerId, $resolvedSiteKey, $filters);
        $dashboard['content_status_counts'] = $this->repo->contentStatusCounts($ownerId, $resolvedSiteKey);
        $dashboard['filters'] = [
            'status' => strtoupper(trim((string)($filters['status'] ?? ''))),
            'content_type' => strtolower(trim((string)($filters['content_type'] ?? ''))),
            'q' => trim((string)($filters['q'] ?? '')),
        ];

        return $dashboard;
    }

    public function createDraft(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $title = $this->normalizeTitle((string)$input['title']);
        if ($title === '') {
            throw new Exception('Content title is required.');
        }
        $slug = $this->uniqueSlug($site, (string)(($input['slug'] ?? '') ?: $title));
        $type = $this->normalizeContentType((string)(($input['content_type'] ?? '') ?: 'blog'));
        $slug = $this->uniqueSlugForRoute($site, $type, $slug);
        $route = $this->routeForType($type, $slug);
        $body = trim((string)($input['body'] ?? ''));
        $primaryKeyword = $this->normalizeTitle((string)($input['primary_keyword'] ?? ''));
        $targetLocation = $this->normalizeTitle((string)($input['target_location'] ?? ''));
        $metadata = array_merge([
            'created_from' => 'level1_growth_hub',
            'auto_publish_allowed' => false,
        ], $this->decodeJsonField($input['metadata_json'] ?? []));
        $metadata['primary_keyword'] = $primaryKeyword;
        $metadata['target_location'] = $targetLocation;
        $templateKey = trim((string)($input['template_key'] ?? $metadata['template_key'] ?? ''));
        $categorySlug = trim((string)($input['content_category'] ?? $metadata['content_category'] ?? ''));
        $template = $templateKey !== '' ? $this->repo->cmsTemplateByKey((int)$site->id_owner, (string)$site->site_key, $templateKey) : null;
        $category = $categorySlug !== '' ? $this->repo->cmsCategoryBySlug((int)$site->id_owner, (string)$site->site_key, $categorySlug) : null;
        if ($template) {
            $metadata['template_key'] = $template->template_key;
            $metadata['template_name'] = $template->name;
        }
        if ($category) {
            $metadata['content_category'] = $category->slug;
            $metadata['content_category_name'] = $category->name;
        }

        $contentId = $this->repo->createContent([
            'id_owner' => (int)$site->id_owner,
            'id_template' => $template ? (int)$template->id : null,
            'id_cms_category' => $category ? (int)$category->id : null,
            'site_key' => (string)$site->site_key,
            'content_type' => $type,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => trim((string)($input['excerpt'] ?? '')),
            'body' => $body,
            'seo_title' => $this->normalizeTitle((string)($input['seo_title'] ?? $title)),
            'meta_description' => trim((string)($input['meta_description'] ?? '')),
            'primary_keyword' => $primaryKeyword,
            'target_location' => $targetLocation,
            'status' => 'DRAFT',
            'approval_status' => 'DRAFT',
            'created_by' => $user->getId(),
            'schema_json' => $this->decodeJsonField($input['schema_json'] ?? []),
            'metadata_json' => $metadata,
        ]);

        $this->repo->createDefaultBlocks((int)$site->id_owner, (string)$site->site_key, $contentId, $title, $body, $metadata, $site);
        $this->repo->createRoute([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'id_content' => $contentId,
            'route' => $route,
            'route_type' => $type,
            'status' => 'ACTIVE',
            'canonical_url' => rtrim((string)$site->public_base_url, '/') . $route,
        ]);

        return $contentId;
    }

    public function createPlannedDraft(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $keyword = $this->normalizeTitle((string)($input['primary_keyword'] ?? $input['keyword_text'] ?? ''));
        $location = $this->normalizeTitle((string)($input['target_location'] ?? $input['location_name'] ?? ''));
        $type = $this->normalizeContentType((string)($input['content_type'] ?? 'blog'));
        $title = $this->normalizeTitle((string)($input['title'] ?? ''));
        $templateKey = trim((string)($input['template_key'] ?? ''));
        $includeMap = !empty($input['include_map']) || $type === 'location' || $templateKey === 'local-location-page';

        if ($includeMap && $location === '') {
            throw new \InvalidArgumentException('Add a location or address before creating a local page with a map.');
        }

        if ($templateKey === 'local-location-page') {
            $type = 'location';
            $input['content_type'] = 'location';
            $input['include_map'] = '1';
        }
        if ($type === 'location') {
            $input['content_intent'] = 'brand_informational';
        }

        if ($title === '') {
            $title = $this->titleFromPlan($type, $keyword, $location, (string)$site->site_name);
        }

        $gscContext = $this->repo->searchConsoleContextForPlan((int)$site->id_owner, (string)$site->site_key, $keyword, $location, 6);
        $serpSnapshots = $this->repo->serpSnapshotsForPlan((int)$site->id_owner, (string)$site->site_key, $keyword, $location, 4);
        $serpResults = $this->repo->serpResultsForPlan((int)$site->id_owner, (string)$site->site_key, $keyword, $location, 8);

        $competitors = $this->competitorsForDraft($site, $input);
        $competitorSignals = $this->competitorPageSignals($competitors);
        $serviceOrProduct = $this->normalizeTitle((string)($input['service_or_product'] ?? ''));
        $duplicateCheck = $this->matchingContent(
            $this->repo->recentContents((int)$site->id_owner, (string)$site->site_key, 200),
            $keyword,
            $location,
            $serviceOrProduct
        );
        $allowRelatedPreviews = !empty($input['allow_related_previews']);
        if ($duplicateCheck && !$allowRelatedPreviews) {
            throw new \InvalidArgumentException('This page may cannibalize existing content: "' . $duplicateCheck['title'] . '"'
                . ($duplicateCheck['route'] !== '' ? ' at ' . $duplicateCheck['route'] : '')
                . '. Edit the existing page or choose a more specific keyword/location.');
        }

        $internalLinks = $this->internalLinksForDraft($site, $keyword, $location, $serviceOrProduct);
        $imageCount = max(0, (int)($input['images_to_generate'] ?? 0));
        $generationConfig = $this->generationConfigForDraft($input, $type);
        if (trim((string)($input['generation_basis'] ?? 'approved_sources')) === 'edited_brief') {
            $generationConfig['min_sources_to_publish'] = 0;
        }
        $citationCount = $this->citationCountForDraft($input, $generationConfig);
        $citationPlan = $this->citationPlanForDraft($keyword, $location, $citationCount, $input);
        $sourceReferenceImages = $this->sourceReferenceImagesForDraft($input, $type, 3);
        $aiImageCount = $type === 'location' && !empty($input['use_source_images']) ? 0 : $imageCount;
        $imagePlan = $this->imagePlanForDraft($site, $title, $keyword, $location, $aiImageCount);
        $researchSummary = $this->researchSummaryForDraft($citationPlan, $competitorSignals, $serpResults, $gscContext, $generationConfig, $includeMap, $location);
        $usedEmergencyDraftBody = false;
        $body = $this->aiPageBody($site, $input, $title, $keyword, $location, $gscContext, $serpSnapshots, $serpResults, $competitorSignals, $internalLinks, $researchSummary, $generationConfig);
        if ($body === null) {
            $usedEmergencyDraftBody = true;
            $body = $this->briefBody($site, $input, $title, $keyword, $location, $citationPlan, $generationConfig);
        }
        if (!empty($input['include_map'])) {
            $body = $this->appendMapEmbedHtml($body, $location);
        }
        $body = $this->appendRequestedAssetsHtml($body, $imagePlan, $citationPlan, $keyword, $location);
        $body = $this->appendSourceReferenceImagesHtml($body, $sourceReferenceImages, $keyword, $location);
        $body = $this->appendInternalLinksHtml($body, $internalLinks);
        $body = $this->ensureReadableCtaHtml($body);
        $plannedSlug = trim((string)($input['slug'] ?? '')) ?: $this->slugify($title);
        $competitorName = $this->normalizeTitle((string)($input['competitor_name'] ?? ''));
        $competitorDomain = strtolower(trim((string)($input['competitor_domain'] ?? '')));
        $blockedPublicTerms = array_values(array_filter([$competitorName, $competitorDomain]));
        $qualityReview = $this->qualityReviewForDraft($title, $body, $input['meta_description'] ?? $this->metaFromPlan($title, $keyword, $location), $plannedSlug, $citationPlan, $imagePlan, $generationConfig, $includeMap, $blockedPublicTerms);
        if ($usedEmergencyDraftBody) {
            $qualityReview['status'] = 'needs_review';
            $qualityReview['score'] = min((int)($qualityReview['score'] ?? 0), 40);
            array_unshift($qualityReview['notes'], 'AI draft was rejected by the public-content guard, so Ophyra saved an emergency editable draft instead of losing the page.');
        }
        if ($qualityReview['status'] === 'failed_review' && $this->hasBlockingQualityIssue($qualityReview)) {
            throw new \RuntimeException('The generated article failed the publishing quality guard: ' . implode(' ', $qualityReview['notes']) . ' Regenerate with approved sources or stronger editor observations.');
        }
        $input['title'] = $title;
        $input['body'] = $body;
        $input['slug'] = $plannedSlug;
        $input['content_type'] = $type;
        $input['meta_description'] = trim((string)($input['meta_description'] ?? $this->metaFromPlan($title, $keyword, $location)));
        $input['primary_keyword'] = $keyword;
        $input['target_location'] = $location;
        $faqCount = max(0, (int)($input['faq_count'] ?? 0));

        $input['schema_json'] = [];
        $input['metadata_json'] = [
            'content_category' => trim((string)($input['content_category'] ?? '')),
            'template_key' => trim((string)($input['template_key'] ?? '')),
            'service_or_product' => $serviceOrProduct,
            'content_intent' => $generationConfig['content_intent'],
            'content_intent_label' => $generationConfig['intent']['label'] ?? '',
            'article_type' => $generationConfig['mode'],
            'generation_mode' => $generationConfig['mode'],
            'quality_status' => $qualityReview['status'],
            'quality_score' => $qualityReview['score'],
            'quality_notes' => $qualityReview['notes'],
            'research_plan' => [
                'generation_config' => $generationConfig,
                'sources' => (string)($input['research_sources'] ?? 'own_data'),
                'web_citations_count' => $citationCount,
                'images_to_generate' => $aiImageCount,
                'source_images_requested' => $type === 'location' && !empty($input['use_source_images']),
                'faq_count' => $faqCount,
                'include_map' => $includeMap,
                'provided_data' => trim((string)($input['provided_data'] ?? '')),
                'editor_observations' => trim((string)($input['editor_observations'] ?? '')),
                'generation_basis' => trim((string)($input['generation_basis'] ?? 'approved_sources')),
                'gsc_context' => $gscContext,
                'serp_snapshots' => $serpSnapshots,
                'serp_results' => $serpResults,
                'competitor_context' => [
                    'name' => $competitorName,
                    'domain' => $competitorDomain,
                    'service_or_product' => $serviceOrProduct,
                    'source_reason' => trim((string)($input['source_reason'] ?? '')),
                ],
                'anti_cannibalization' => [
                    'status' => $duplicateCheck ? ($allowRelatedPreviews ? 'allowed_related_preview' : 'possible_match') : 'clear',
                    'matched_content' => $duplicateCheck,
                    'allow_related_previews' => $allowRelatedPreviews,
                ],
                'image_plan' => $imagePlan,
                'citation_plan' => $citationPlan,
                'source_reference_images' => $sourceReferenceImages,
                'research_summary' => $researchSummary,
                'quality_review' => $qualityReview,
                'internal_links' => $internalLinks,
                'map_plan' => [
                    'requested' => $includeMap,
                    'location' => $location,
                    'requires_verified_coordinates' => $includeMap,
                    'map_embed_url' => $includeMap && $location !== '' ? 'https://www.google.com/maps?q=' . rawurlencode($location) . '&output=embed' : null,
                    'google_maps_url' => $includeMap && $location !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($location) : null,
                    'map_lat' => null,
                    'map_lng' => null,
                ],
                'generation_log' => [
                    'emergency_draft_body_used' => $usedEmergencyDraftBody,
                    'sources_found' => (int)($citationPlan['sources_found'] ?? 0),
                    'valid_sources_used' => count($citationPlan['reviewed_citations'] ?? []),
                    'community_sources_used' => (int)($citationPlan['community_sources_used'] ?? 0),
                    'map_generated' => $includeMap && $location !== '',
                    'max_tokens' => $generationConfig['max_tokens'],
                    'quality_status' => $qualityReview['status'],
                    'quality_score' => $qualityReview['score'],
                    'quality_notes' => $qualityReview['notes'],
                ],
                'external_queries' => array_values(array_filter([
                    $keyword,
                    $keyword !== '' && $location !== '' ? $keyword . ' ' . $location : null,
                    $location !== '' ? (string)$site->site_name . ' ' . $location : null,
                ])),
            ],
        ];

        $contentId = $this->createDraft($user, $input);
        $generatedMedia = $this->generateImagesForDraft($site, $contentId, $imagePlan, $user);
        if ($generatedMedia !== []) {
            $input['metadata_json']['research_plan']['generated_media'] = $generatedMedia;
            $body = $this->replaceImagePlaceholdersWithMedia($body, $generatedMedia);
            $qualityReview = $this->qualityReviewForDraft($title, $body, $input['meta_description'], $plannedSlug, $citationPlan, $imagePlan, $generationConfig, $includeMap, $blockedPublicTerms);
            $input['metadata_json']['quality_status'] = $qualityReview['status'];
            $input['metadata_json']['quality_score'] = $qualityReview['score'];
            $input['metadata_json']['quality_notes'] = $qualityReview['notes'];
            $input['metadata_json']['research_plan']['quality_review'] = $qualityReview;
            $input['metadata_json']['research_plan']['generation_log']['quality_status'] = $qualityReview['status'];
            $input['metadata_json']['research_plan']['generation_log']['quality_score'] = $qualityReview['score'];
            $input['metadata_json']['research_plan']['generation_log']['quality_notes'] = $qualityReview['notes'];
            $this->repo->updateContentBody((int)$site->id_owner, (string)$site->site_key, $contentId, $body, $input['metadata_json']);
        }
        $opportunityId = (int)($input['opportunity_id'] ?? 0);
        if ($opportunityId > 0) {
            $this->repo->markOpportunityStatus((int)$site->id_owner, (string)$site->site_key, $opportunityId, 'DRAFTED');
        }

        $this->repo->createAgentRun([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'run_type' => 'planned_content_research',
            'status' => 'PENDING',
            'summary' => 'Research package queued for Growth Hub draft #' . $contentId,
            'input_json' => [
                'id_content' => $contentId,
                'title' => $title,
                'content_type' => $type,
                'primary_keyword' => $keyword,
                'target_location' => $location,
                'research_plan' => $input['metadata_json']['research_plan'] ?? [],
            ],
        ]);

        return $contentId;
    }

    public function generateTitleIdeas(User $user, array $input): array
    {
        $site = $this->requireSite((string)($input['site_key'] ?? ''));
        $keyword = $this->normalizeTitle((string)($input['primary_keyword'] ?? $input['keyword_text'] ?? ''));
        $location = $this->normalizeTitle((string)($input['target_location'] ?? $input['location_name'] ?? ''));
        $service = $this->normalizeTitle((string)($input['service_or_product'] ?? ''));
        $competitor = trim(implode(' / ', array_filter([
            $this->normalizeTitle((string)($input['competitor_name'] ?? '')),
            strtolower(trim((string)($input['competitor_domain'] ?? ''))),
        ])));
        $providedData = trim((string)($input['provided_data'] ?? ''));
        $editorObservations = trim((string)($input['editor_observations'] ?? ''));
        $generationBasis = trim((string)($input['generation_basis'] ?? 'approved_sources'));
        $sourceReason = trim((string)($input['source_reason'] ?? ''));
        $type = $this->normalizeContentType((string)($input['content_type'] ?? 'page'));
        $generationConfig = $this->generationConfigForDraft($input, $type);
        $sourceCandidates = $this->sourceCandidatesForIdea($site, $keyword, $location, 5, $input);

        $fallback = $this->fallbackTitleIdeas($site, $keyword, $location, $service, $competitor, $providedData, $sourceReason, $generationConfig);
        $fallback['research_sources'] = $sourceCandidates;
        $fallback['source'] = $sourceCandidates !== [] ? 'web_sources_fallback' : $fallback['source'];
        $apiKey = trim((string)($_ENV['OPENAI_TOKEN'] ?? $_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return $fallback;
        }

        $response = $this->callOpenAIJson($apiKey, [
            'model' => $_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a sharp editorial strategist and conversion copywriter. Return compact valid JSON only. Create exactly one strong editable page idea before drafting. The title must follow the requested editorial intent, sell a clear angle, and not just repeat the keyword. Also produce a customer-facing post extract, a refined focus keyphrase, an optional location, and a copy-ready brief. Fictional scenario content is allowed only when the provided intent explicitly allows fiction; if used, keep it clearly framed as a realistic invented scenario and never invent facts about the brand. Do not invent prices, awards, testimonials, guarantees, availability or real-world claims. Competitor or brand research inputs are internal only: use them to infer gaps, keyword angles, buyer questions and positioning, but never mention competitor names, domains, URLs or brands in visible titles, extracts, angles, CTA copy or brief additions.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'brand' => [
                            'name' => (string)$site->site_name,
                            'voice' => (string)($site->brand_voice ?? ''),
                            'cta_label' => (string)($site->default_cta_label ?? 'Contact us'),
                        ],
                        'seed' => [
                            'keyword' => $keyword,
                            'location' => $location,
                            'service_or_product' => $service,
                            'content_type' => $type,
                            'generation_mode' => $generationConfig['mode'] ?? 'seo_standard',
                            'content_intent' => $generationConfig['content_intent'] ?? 'brand_informational',
                            'intent_label' => (string)($generationConfig['intent']['label'] ?? 'Brand informational'),
                            'intent_title_style' => (string)($generationConfig['intent']['title_style'] ?? ''),
                            'intent_body_style' => (string)($generationConfig['intent']['body_style'] ?? ''),
                            'fiction_allowed' => !empty($generationConfig['intent']['fiction_allowed']),
                            'internal_competitor_research_source' => $competitor,
                            'source_reason' => $sourceReason,
                            'editor_notes' => $providedData,
                            'editor_observations' => $editorObservations,
                            'source_candidates_to_use_as_essence_after_editor_approval' => $sourceCandidates,
                            'visible_copy_rule' => 'Do not mention or promote competitor names, domains, URLs or brands. Convert competitor signals into our own gap-driven keyword and buyer-question strategy.',
                            'rewrite_rule' => 'Use the source candidates only as research essence and angle inspiration. Never copy paragraphs, outlines, claims, or phrasing. The final article must be fully original and brand-specific.',
                            'keyword_rule' => 'Never replace the editor keyword with another topic. If keyword is wedding planner, do not switch it to corporate events, catering, or another service.',
                            'location_rule' => 'Do not add South Florida, Miami, Broward, or any market unless the editor provided that location or a reviewed source clearly requires local context.',
                            'location_page_rule' => $type === 'location'
                                ? 'This is a location page. Suggest a practical local service page closely based on the source/reference, with area context and service coverage. Do not create a fictional story or switch services.'
                                : '',
                        ],
                        'required_json_shape' => [
                            'ideas' => [
                                [
                                    'title' => 'customer-facing title, max 78 chars',
                                    'excerpt' => 'one specific reader problem or hook, not a generic sales slogan',
                                    'focus_keyphrase' => 'same core keyword the editor entered; do not switch service/topic',
                                    'location' => 'only the editor-provided location, otherwise empty string',
                                    'angle' => 'short editorial angle',
                                    'preview' => '2-3 sentence sales description of what the page/article would cover',
                                    'research_focus' => 'what to investigate before drafting',
                                    'cta_angle' => 'empty string unless a real configured CTA is provided by the editor',
                                    'brief_addition' => 'writer direction focused on reader questions, source synthesis, and article structure; no generic marketing slogans',
                                ],
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ],
            'temperature' => 0.75,
            'max_tokens' => 1400,
            'response_format' => ['type' => 'json_object'],
        ]);

        $ideas = is_array($response['ideas'] ?? null) ? $response['ideas'] : [];
        $clean = [];
        foreach ($ideas as $idea) {
            $title = $this->normalizeTitle((string)($idea['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $ideaText = implode(' ', array_map(static fn($value): string => is_scalar($value) ? (string)$value : '', $idea));
            if ($this->ideaConflictsWithKeyword($ideaText, $keyword)) {
                continue;
            }
            $clean[] = [
                'title' => substr($title, 0, 110),
                'angle' => trim((string)($idea['angle'] ?? '')),
                'excerpt' => trim((string)($idea['excerpt'] ?? '')),
                'focus_keyphrase' => $keyword,
                'location' => $location,
                'preview' => trim((string)($idea['preview'] ?? '')),
                'research_focus' => trim((string)($idea['research_focus'] ?? '')),
                'cta_angle' => trim((string)($idea['cta_angle'] ?? '')),
                'brief_addition' => trim((string)($idea['brief_addition'] ?? '')),
                'research_sources' => $sourceCandidates,
            ];
            if (count($clean) >= 1) {
                break;
            }
        }

        return count($clean) >= 1 ? ['ideas' => $clean, 'research_sources' => $sourceCandidates, 'source' => $sourceCandidates !== [] ? 'openai + web sources' : 'openai'] : $fallback;
    }

    private function ideaConflictsWithKeyword(string $ideaText, string $keyword): bool
    {
        $ideaText = strtolower($ideaText);
        $keyword = strtolower($keyword);
        foreach (['corporate', 'catering', 'wedding', 'birthday', 'venue'] as $topic) {
            if (!str_contains($keyword, $topic) && str_contains($ideaText, $topic)) {
                return true;
            }
        }

        return false;
    }

    public function updateContent(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        $contentId = (int)($input['id_content'] ?? 0);
        if ($contentId <= 0) {
            throw new Exception('Content id is required.');
        }

        $title = $this->normalizeTitle((string)($input['title'] ?? ''));
        if ($title === '') {
            throw new Exception('Content title is required.');
        }

        $type = $this->normalizeContentType((string)($input['content_type'] ?? 'blog'));
        $slug = $this->uniqueSlug($site, (string)($input['slug'] ?? $title), $contentId);
        $slug = $this->uniqueSlugForRoute($site, $type, $slug, $contentId);
        $route = $this->routeForType($type, $slug);
        $existingContent = $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId);
        $metadata = $this->decodeJsonField($input['metadata_json'] ?? []);
        $metadata['last_edited_from'] = 'level1_growth_hub';
        $metadata['last_edited_by'] = $user->getId();
        $templateKey = trim((string)($input['template_key'] ?? $metadata['template_key'] ?? ''));
        $categorySlug = trim((string)($input['content_category'] ?? $metadata['content_category'] ?? ''));
        $template = $templateKey !== '' ? $this->repo->cmsTemplateByKey((int)$site->id_owner, (string)$site->site_key, $templateKey) : null;
        $category = $categorySlug !== '' ? $this->repo->cmsCategoryBySlug((int)$site->id_owner, (string)$site->site_key, $categorySlug) : null;
        if ($template) {
            $metadata['template_key'] = $template->template_key;
            $metadata['template_name'] = $template->name;
        }
        if ($category) {
            $metadata['content_category'] = $category->slug;
            $metadata['content_category_name'] = $category->name;
        }
        $templateId = $template ? (int)$template->id : (int)($existingContent->id_template ?? 0);
        $categoryId = $category ? (int)$category->id : (int)($existingContent->id_cms_category ?? 0);

        $this->repo->updateContent((int)$site->id_owner, (string)$site->site_key, $contentId, [
            'id_owner' => (int)$site->id_owner,
            'id_template' => $templateId > 0 ? $templateId : null,
            'id_cms_category' => $categoryId > 0 ? $categoryId : null,
            'site_key' => (string)$site->site_key,
            'content_type' => $type,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => trim((string)($input['excerpt'] ?? '')) ?: null,
            'body' => trim((string)($input['body'] ?? '')) ?: null,
            'seo_title' => trim((string)($input['seo_title'] ?? $title)) ?: null,
            'meta_description' => trim((string)($input['meta_description'] ?? '')) ?: null,
            'primary_keyword' => trim((string)($input['primary_keyword'] ?? '')) ?: null,
            'target_location' => trim((string)($input['target_location'] ?? '')) ?: null,
            'scheduled_at' => null,
            'schema_json' => $this->decodeJsonField($input['schema_json'] ?? []),
            'metadata_json' => $metadata,
        ]);

        $this->repo->createRoute([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'id_content' => $contentId,
            'route' => $route,
            'route_type' => $type,
            'status' => 'ACTIVE',
            'canonical_url' => rtrim((string)$site->public_base_url, '/') . $route,
        ]);

        if ((string)($existingContent->status ?? '') === 'PUBLISHED') {
            $this->notifyReceiverSitemapRefresh($site, $contentId, 'content_updated');
        }
    }

    public function approveAndPublishContent(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        $contentId = (int)($input['id_content'] ?? 0);
        if ($contentId <= 0) {
            throw new Exception('Content id is required.');
        }

        $content = $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId);
        if (!$content) {
            throw new Exception('Content draft was not found for this Growth Hub site.');
        }

        if (trim((string)($content->body ?? '')) === '') {
            throw new Exception('Add page body content before approving and publishing.');
        }

        $metadata = $this->decodeJsonField($content->metadata_json ?? []);
        $blockedPublicTerms = array_values(array_filter([
            (string)($metadata['research_plan']['competitor_context']['name'] ?? ''),
            (string)($metadata['research_plan']['competitor_context']['domain'] ?? ''),
        ]));
        $qualityReview = $this->qualityReviewForDraft(
            (string)$content->title,
            (string)$content->body,
            (string)($content->meta_description ?? ''),
            (string)($content->slug ?? ''),
            $metadata['research_plan']['citation_plan'] ?? [],
            $metadata['research_plan']['image_plan'] ?? [],
            $metadata['research_plan']['generation_config'] ?? $this->generationConfigForDraft([], (string)$content->content_type),
            !empty($metadata['research_plan']['map_plan']['requested']),
            $blockedPublicTerms
        );
        if (!isset($metadata['research_plan']) || !is_array($metadata['research_plan'])) {
            $metadata['research_plan'] = [];
        }
        $metadata['quality_status'] = $qualityReview['status'] === 'failed_review' ? 'published_with_warnings' : $qualityReview['status'];
        $metadata['quality_score'] = $qualityReview['score'];
        $metadata['quality_notes'] = $qualityReview['notes'];
        $metadata['research_plan']['quality_review'] = $qualityReview;
        $metadata['research_plan']['quality_publish_override'] = [
            'allowed' => true,
            'approved_by' => (int)$user->getId(),
            'approved_at' => date('c'),
            'reason' => 'Manual level 1 approval publishes Growth Hub content even when automated quality checks return warnings.',
        ];
        $this->repo->updateContentBody((int)$site->id_owner, (string)$site->site_key, $contentId, (string)$content->body, $metadata);

        if (trim((string)($content->route ?? '')) === '') {
            $route = $this->routeForType((string)$content->content_type, (string)$content->slug);
            $this->repo->createRoute([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'id_content' => $contentId,
                'route' => $route,
                'route_type' => (string)$content->content_type,
                'status' => 'ACTIVE',
                'canonical_url' => rtrim((string)$site->public_base_url, '/') . $route,
            ]);
        }

        $content = $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId);
        if ($content) {
            $this->persistPublicContract($content, $site);
        }

        $this->repo->publishContentNow((int)$site->id_owner, (string)$site->site_key, $contentId, (int)$user->getId());
        $this->notifyReceiverSitemapRefresh($site, $contentId, 'content_published');
    }

    public function archiveContent(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        $contentId = (int)($input['id_content'] ?? 0);
        if ($contentId <= 0) {
            throw new Exception('Content id is required.');
        }

        $content = $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId);
        if (!$content) {
            throw new Exception('Content was not found for this Growth Hub site.');
        }

        if ((string)($content->status ?? '') === 'PUBLISHED') {
            $this->notifyReceiverSitemapRefresh($site, $contentId, 'content_archived');
        }
        $this->repo->archiveContent((int)$site->id_owner, (string)$site->site_key, $contentId);
    }

    public function restoreArchivedContent(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        $contentId = (int)($input['id_content'] ?? 0);
        if ($contentId <= 0) {
            throw new Exception('Content id is required.');
        }

        $content = $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId);
        if (!$content || (string)$content->status !== 'ARCHIVED') {
            throw new Exception('Only archived content can be restored.');
        }

        $this->repo->restoreArchivedContent((int)$site->id_owner, (string)$site->site_key, $contentId);
    }

    public function permanentlyDeleteContent(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        $contentId = (int)($input['id_content'] ?? 0);
        if ($contentId <= 0) {
            throw new Exception('Content id is required.');
        }

        $content = $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId);
        if (!$content || (string)$content->status !== 'ARCHIVED') {
            throw new Exception('Only archived content can be permanently deleted.');
        }

        $media = $this->repo->mediaRecordsForContent((int)$site->id_owner, (string)$site->site_key, $contentId);
        foreach ($media as $item) {
            $metadata = $this->decodeJsonField($item->metadata_json ?? []);
            $localFile = trim((string)($metadata['local_file'] ?? ''));
            if ($localFile !== '') {
                $this->deleteGeneratedLocalFile($localFile);
            }

            $url = trim((string)($item->secure_url ?? $item->cloudinary_url ?? ''));
            if ($url !== '' && str_contains($url, 'cloudinary.com')) {
                FileUtils::removeFile($url);
            }
        }

        $this->repo->permanentlyDeleteContent((int)$site->id_owner, (string)$site->site_key, $contentId);
    }

    private function deleteGeneratedLocalFile(string $path): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $target = realpath($path);
        if (!$root || !$target || !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($target)) {
            @unlink($target);
        }
    }

    public function updateSiteSettings(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        if ((int)$user->getLevel() !== 1) {
            throw new Exception('Only level 1 admins can update Growth Hub site settings.');
        }

        $baseUrl = $this->normalizeBaseUrl((string)($input['public_base_url'] ?? $site->public_base_url ?? ''));
        if ($baseUrl === '') {
            throw new Exception('Public base URL is required.');
        }

        $domain = $this->domainFromUrl($baseUrl) ?: $this->domainFromUrl((string)($input['domain'] ?? $site->domain ?? ''));
        $sitemapSettings = $this->decodeJsonField($site->sitemap_settings ?? []);
        $routeRules = $this->decodeJsonField($site->route_rules ?? []);
        $environment = strtolower(trim((string)($input['publishing_environment'] ?? $sitemapSettings['environment'] ?? 'development')));
        if (!in_array($environment, ['development', 'production'], true)) {
            $environment = 'development';
        }
        $receiverEndpoint = $this->normalizeOptionalUrl((string)($input['receiver_sitemap_endpoint'] ?? $sitemapSettings['receiver_sitemap_endpoint'] ?? ''));
        $receiverToken = trim((string)($input['receiver_sitemap_token'] ?? ''));
        if ($receiverToken === '') {
            $receiverToken = trim((string)($sitemapSettings['receiver_sitemap_token'] ?? ''));
        }
        if ($environment === 'production' && $receiverEndpoint === '') {
            throw new Exception('Production publishing requires a receiver sitemap refresh endpoint.');
        }

        $sitemapSettings['public_base_url'] = $baseUrl;
        $sitemapSettings['sitemap_url'] = rtrim($baseUrl, '/') . '/sitemap.xml';
        $sitemapSettings['environment'] = $environment;
        $sitemapSettings['receiver_sitemap_endpoint'] = $receiverEndpoint;
        if ($receiverToken !== '') {
            $sitemapSettings['receiver_sitemap_token'] = $receiverToken;
        }
        $routeRules['page'] = '/{slug}';
        $routeRules['landing'] = '/{slug}';
        $routeRules['custom'] = '/{slug}';
        $routeRules['location'] = '/locations/{slug}';
        $routeRules['blog'] = '/blog/{slug}';

        $this->repo->updateSiteSettings((int)$site->id_owner, (string)$site->site_key, [
            'public_base_url' => $baseUrl,
            'domain' => $domain,
            'sitemap_settings' => $sitemapSettings,
            'route_rules' => $routeRules,
        ]);
        $this->repo->refreshSiteCanonicalUrls((int)$site->id_owner, (string)$site->site_key, $baseUrl);
    }

    public function refreshReceiverSitemap(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        if ((int)$user->getLevel() !== 1) {
            throw new Exception('Only level 1 admins can refresh receiver sitemaps.');
        }

        $this->notifyReceiverSitemapRefresh($site, !empty($input['id_content']) ? (int)$input['id_content'] : null, 'manual_sitemap_refresh');
    }

    public function updateSiteOffers(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        if ((int)$user->getLevel() !== 1) {
            throw new Exception('Only level 1 admins can update Growth Hub services.');
        }

        $services = $this->parseOfferRows($input['main_services_label'] ?? [], $input['main_services_url'] ?? []);
        if (!$services) {
            $services = $this->parseListField((string)($input['main_services_text'] ?? ''));
        }
        if (!$services) {
            throw new Exception('Add at least one service or offer.');
        }

        $products = $this->parseOfferRows($input['main_products_label'] ?? [], $input['main_products_url'] ?? []);
        if (!$products) {
            $products = $this->parseListField((string)($input['main_products_text'] ?? ''));
        }
        $this->repo->updateSiteOffers((int)$site->id_owner, (string)$site->site_key, $services, $products);
    }

    public function updateTemplateCss(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        if ((int)$user->getLevel() !== 1) {
            throw new Exception('Only level 1 admins can update Growth Hub templates.');
        }

        $templateId = (int)($input['id_template'] ?? 0);
        if ($templateId <= 0) {
            throw new Exception('Template id is required.');
        }

        $template = $this->repo->cmsTemplateById((int)$site->id_owner, (string)$site->site_key, $templateId);
        if (!$template) {
            throw new Exception('Template is not available for this Growth Hub site.');
        }

        $this->repo->updateCmsTemplateCss(
            (int)$site->id_owner,
            (string)$site->site_key,
            $templateId,
            (string)($input['css_text'] ?? '')
        );
    }

    public function createTemplate(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        if ((int)$user->getLevel() !== 1) {
            throw new Exception('Only level 1 admins can add Growth Hub templates.');
        }

        $name = $this->normalizeTitle((string)($input['template_name'] ?? ''));
        if ($name === '') {
            throw new Exception('Template name is required.');
        }

        $templateKey = $this->slugify((string)($input['template_key'] ?? $name));
        $type = trim((string)($input['template_type'] ?? 'general')) ?: 'general';
        $cssText = trim((string)($input['css_text'] ?? ''));
        if ($cssText === '') {
            $cssText = '.cms-preview-template-' . $templateKey . ' .cms-block-hero{background:#0f172a;color:#fff}.cms-preview-template-' . $templateKey . ' .cms-block-cta{background:#f8fafc}';
        }

        return $this->repo->createCmsTemplate([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'name' => $name,
            'template_key' => $templateKey,
            'description' => trim((string)($input['description'] ?? '')) ?: 'Custom Growth Hub template.',
            'type' => $type,
            'css_text' => $cssText,
            'metadata_json' => [
                'source' => 'growth_hub_panel',
                'created_by' => $user->getId(),
            ],
        ]);
    }

    public function createOpportunity(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            throw new Exception('Opportunity title is required.');
        }

        return $this->repo->createOpportunity([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'opportunity_type' => (string)($input['opportunity_type'] ?? 'content_gap'),
            'title' => $title,
            'keyword_text' => trim((string)($input['keyword_text'] ?? '')) ?: null,
            'location_name' => trim((string)($input['location_name'] ?? '')) ?: null,
            'service_or_product' => trim((string)($input['service_or_product'] ?? '')) ?: null,
            'score' => trim((string)($input['score'] ?? '50')) ?: 50,
            'reason_summary' => trim((string)($input['reason_summary'] ?? '')) ?: null,
            'recommended_content_type' => $this->normalizeContentType(trim((string)($input['recommended_content_type'] ?? 'blog')) ?: 'blog'),
            'recommended_route' => trim((string)($input['recommended_route'] ?? '')) ?: null,
            'data_json' => [
                'created_from' => 'level1_growth_hub',
                'created_by' => $user->getId(),
                'source' => trim((string)($input['source'] ?? 'manual_saved_opportunity')),
                'opportunity_key' => trim((string)($input['opportunity_key'] ?? '')) ?: $this->opportunityKey(
                    (string)($input['source'] ?? 'saved'),
                    trim((string)($input['keyword_text'] ?? '')),
                    trim((string)($input['location_name'] ?? '')),
                    trim((string)($input['service_or_product'] ?? '')),
                    trim((string)($input['competitor_domain'] ?? $input['competitor_name'] ?? ''))
                ),
                'competitor_id' => !empty($input['analysis_competitor']) ? (int)$input['analysis_competitor'] : null,
                'competitor_name' => trim((string)($input['competitor_name'] ?? '')) ?: null,
                'competitor_domain' => trim((string)($input['competitor_domain'] ?? '')) ?: null,
            ],
        ]);
    }

    public function dismissOpportunity(User $user, array $input): void
    {
        $site = $this->requireSite((string)$input['site_key']);
        $ownerId = (int)$site->id_owner;
        $siteKey = (string)$site->site_key;
        $opportunityId = (int)($input['opportunity_id'] ?? 0);

        if ($opportunityId > 0) {
            $this->repo->archiveOpportunity($ownerId, $siteKey, $opportunityId);
            return;
        }

        $key = trim((string)($input['opportunity_key'] ?? ''));
        if ($key === '') {
            throw new Exception('Opportunity key is required to dismiss this suggestion.');
        }

        $this->repo->createOpportunity([
            'id_owner' => $ownerId,
            'site_key' => $siteKey,
            'opportunity_type' => (string)($input['opportunity_type'] ?? 'dismissed_suggestion'),
            'title' => trim((string)($input['title'] ?? 'Dismissed suggestion')) ?: 'Dismissed suggestion',
            'keyword_text' => trim((string)($input['keyword_text'] ?? '')) ?: null,
            'location_name' => trim((string)($input['location_name'] ?? '')) ?: null,
            'service_or_product' => trim((string)($input['service_or_product'] ?? '')) ?: null,
            'score' => 0,
            'reason_summary' => 'Dismissed from Growth Hub by user.',
            'status' => 'ARCHIVED',
            'recommended_content_type' => trim((string)($input['recommended_content_type'] ?? '')) !== ''
                ? $this->normalizeContentType((string)$input['recommended_content_type'])
                : null,
            'data_json' => [
                'source' => 'dismissed_suggestion',
                'opportunity_key' => $key,
                'dismissed_by' => $user->getId(),
                'competitor_name' => trim((string)($input['competitor_name'] ?? '')) ?: null,
                'competitor_domain' => trim((string)($input['competitor_domain'] ?? '')) ?: null,
            ],
        ]);
    }

    public function uploadMedia(User $user, string $siteKey, array $file, array $input): int
    {
        $site = $this->requireSite($siteKey);
        $folder = trim((string)($site->cloudinary_folder ?: ($_ENV['CLOUDINARY_DEFAULT_FOLDER'] ?? 'ophyra-growth-hub')), '/');
        $sourceType = (string)($input['source_type'] ?? 'uploaded');

        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No valid image file was uploaded.');
        }

        $extension = pathinfo((string)$file['name'], PATHINFO_EXTENSION);
        $upload = FileUtils::uploadFile($file, $folder);

        return $this->repo->registerMedia([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'id_content' => !empty($input['id_content']) ? (int)$input['id_content'] : null,
            'related_block_id' => !empty($input['related_block_id']) ? (int)$input['related_block_id'] : null,
            'cloudinary_public_id' => (string)($upload['public_id'] ?? ''),
            'cloudinary_url' => $upload['url'] ?? null,
            'secure_url' => (string)($upload['secure_url'] ?? ''),
            'asset_type' => $upload['resource_type'] ?? 'image',
            'media_type' => $this->normalizeCmsMediaType($upload['resource_type'] ?? 'image', $file['type'] ?? null),
            'source_type' => $sourceType,
            'usage_type' => (string)($input['usage_type'] ?? 'gallery'),
            'prompt_used' => trim((string)($input['prompt_used'] ?? '')) ?: null,
            'alt_text' => trim((string)($input['alt_text'] ?? '')) ?: null,
            'title_text' => trim((string)($input['title_text'] ?? '')) ?: null,
            'caption' => trim((string)($input['caption'] ?? '')) ?: null,
            'width' => $upload['width'] ?? null,
            'height' => $upload['height'] ?? null,
            'format' => $upload['format'] ?? $extension,
            'bytes' => $upload['bytes'] ?? null,
            'folder' => $folder,
            'metadata_json' => [
                'original_filename' => $file['name'] ?? null,
                'cloudinary_version' => $upload['version'] ?? null,
                'ai_disclosure_required' => $siteKey === 'vnvevents' && $sourceType === 'ai_generated',
            ],
            'created_by' => $user->getId(),
        ]);
    }

    private function normalizeCmsMediaType(?string $assetType, ?string $mimeType = null): string
    {
        $assetType = strtolower(trim((string)$assetType));
        $mimeType = strtolower(trim((string)$mimeType));

        if ($assetType === 'image' || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($assetType === 'video' || str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if ($assetType === 'raw' || str_starts_with($mimeType, 'application/') || str_starts_with($mimeType, 'text/')) {
            return 'document';
        }

        return 'other';
    }

    public function createTargetLocation(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $location = trim((string)($input['location_name'] ?? ''));

        if ($location === '') {
            throw new Exception('Location name is required.');
        }

        return $this->repo->createTargetLocation([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'location_name' => $location,
            'county' => trim((string)($input['county'] ?? '')) ?: null,
            'state' => trim((string)($input['state'] ?? 'FL')) ?: 'FL',
            'country' => trim((string)($input['country'] ?? 'US')) ?: 'US',
            'lat' => trim((string)($input['lat'] ?? '')),
            'lng' => trim((string)($input['lng'] ?? '')),
            'priority_score' => trim((string)($input['priority_score'] ?? '50')) ?: 50,
            'notes' => trim((string)($input['notes'] ?? '')) ?: null,
            'created_by' => $user->getId(),
        ]);
    }

    public function createCompetitor(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $name = trim((string)($input['competitor_name'] ?? ''));
        $url = trim((string)($input['competitor_url'] ?? ''));

        if ($name === '' || $url === '') {
            throw new Exception('Competitor name and URL are required.');
        }

        return $this->repo->createCompetitor([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'competitor_name' => $name,
            'competitor_url' => $url,
            'county' => trim((string)($input['county'] ?? '')) ?: null,
            'city' => trim((string)($input['city'] ?? '')) ?: null,
            'state' => trim((string)($input['state'] ?? 'FL')) ?: 'FL',
            'service_or_product' => trim((string)($input['service_or_product'] ?? '')) ?: null,
            'source' => 'level1_manual',
            'notes' => trim((string)($input['notes'] ?? '')) ?: null,
            'created_by' => $user->getId(),
        ]);
    }

    public function createKeyword(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $keyword = trim((string)($input['keyword_text'] ?? ''));

        if ($keyword === '') {
            throw new Exception('Keyword is required.');
        }

        return $this->repo->createKeyword([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'keyword_text' => $keyword,
            'location' => trim((string)($input['location'] ?? '')) ?: null,
            'service_or_product' => trim((string)($input['service_or_product'] ?? '')) ?: null,
            'source' => 'level1_manual',
            'avg_monthly_searches' => trim((string)($input['avg_monthly_searches'] ?? '')),
            'competition' => trim((string)($input['competition'] ?? '')) ?: null,
            'cpc_low' => trim((string)($input['cpc_low'] ?? '')),
            'cpc_high' => trim((string)($input['cpc_high'] ?? '')),
            'intent' => trim((string)($input['intent'] ?? '')) ?: null,
            'priority_score' => trim((string)($input['priority_score'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')) ?: null,
            'created_by' => $user->getId(),
        ]);
    }

    public function createCompetitorAiOpportunities(User $user, array $input): int
    {
        $site = $this->requireSite((string)$input['site_key']);
        $ownerId = (int)$site->id_owner;
        $siteKey = (string)$site->site_key;
        $competitors = $this->repo->competitors($ownerId, $siteKey, 20);
        if ($competitors === []) {
            throw new Exception('Add at least one competitor before using the competitor AI fallback.');
        }

        $analysisCompetitorId = (int)($input['analysis_competitor'] ?? 0);
        $analysisCompetitor = $this->findCompetitorById($competitors, $analysisCompetitorId);
        if ($analysisCompetitor) {
            $competitors = [$analysisCompetitor];
        }

        $services = $this->offerLabels($this->decodeJsonField($site->main_services ?? []));
        $products = $this->offerLabels($this->decodeJsonField($site->main_products ?? []));
        $analysisService = trim((string)($input['analysis_service'] ?? ''));
        if ($analysisService === '' && $analysisCompetitor) {
            $analysisService = trim((string)($analysisCompetitor->service_or_product ?? ''));
        }
        $analysisLocation = trim((string)($input['analysis_location'] ?? ''));
        if ($analysisLocation === '' && $analysisCompetitor) {
            $analysisLocation = $this->competitorMarket($analysisCompetitor);
        }
        if ($analysisService !== '') {
            $services = [$analysisService];
            $products = [];
        }

        $opportunities = $this->aiCompetitorOpportunities($site, $competitors, $services, $products, $analysisLocation);
        $source = 'openai_competitor_scan';

        if ($opportunities === []) {
            $opportunities = $this->competitorFallbackPreview($site, $competitors, $services, $products, $analysisLocation);
            $source = 'local_competitor_fallback';
        }

        $count = 0;
        foreach ($opportunities as $row) {
            $this->repo->createOpportunity([
                'id_owner' => $ownerId,
                'site_key' => $siteKey,
                'opportunity_type' => 'competitor_ai_fallback',
                'title' => (string)($row['title'] ?? 'Competitor-backed page opportunity'),
                'keyword_text' => (string)($row['keyword'] ?? ''),
                'location_name' => (string)($row['location'] ?? ''),
                'service_or_product' => (string)($row['service'] ?? ''),
                'score' => (float)($row['score'] ?? 65),
                'reason_summary' => (string)($row['reason'] ?? 'Generated from tracked competitor context.'),
                'status' => 'IDEA',
                'recommended_content_type' => $this->normalizeContentType((string)($row['recommended_content_type'] ?? 'page')),
                'recommended_route' => (string)($row['recommended_route'] ?? ''),
                'data_json' => [
                    'source' => $source,
                    'opportunity_key' => (string)($row['opportunity_key'] ?? $this->opportunityKey(
                        $source,
                        (string)($row['keyword'] ?? ''),
                        (string)($row['location'] ?? ''),
                        (string)($row['service'] ?? ''),
                        (string)($row['competitor_domain'] ?? $row['competitor_name'] ?? '')
                    )),
                    'competitors' => $row['competitors'] ?? [],
                    'competitor_name' => $row['competitor_name'] ?? ($analysisCompetitor->competitor_name ?? null),
                    'competitor_domain' => $row['competitor_domain'] ?? ($analysisCompetitor->competitor_domain ?? null),
                    'competitor_id' => $row['competitor_id'] ?? ($analysisCompetitor->id ?? null),
                    'created_by' => $user->getId(),
                ],
            ]);
            $count++;
        }

        $this->repo->createAgentRun([
            'id_owner' => $ownerId,
            'site_key' => $siteKey,
            'run_type' => 'competitor_ai_fallback',
            'status' => $source === 'openai_competitor_scan' ? 'COMPLETED' : 'PENDING',
            'summary' => $count . ' competitor-backed opportunities generated via ' . $source . '.',
            'input_json' => [
                'competitor_count' => count($competitors),
                'services' => $services,
                'products' => $products,
            ],
        ]);

        return $count;
    }

    private function emptyResearchLab(string $siteKey, array $input = []): array
    {
        return [
            'site_key' => $siteKey,
            'seed_keyword' => trim((string)($input['research_keyword'] ?? '')),
            'seed_location' => trim((string)($input['research_location'] ?? '')),
            'seed_competitor' => trim((string)($input['research_competitor'] ?? '')),
            'source' => 'not_requested',
            'keyword_recommendations' => [],
            'competitor_recommendations' => [],
            'competitor_keyword_recommendations' => [],
        ];
    }

    private function serpHealth(array $statuses, array $snapshots): array
    {
        $hasLogin = trim((string)($_ENV['DATAFORSEO_LOGIN'] ?? '')) !== '';
        $hasPassword = trim((string)($_ENV['DATAFORSEO_PASSWORD'] ?? '')) !== '';
        $latestError = '';

        foreach ($statuses as $status) {
            $error = trim((string)($status->last_error ?? ''));
            if ($error !== '') {
                $latestError = $error;
                break;
            }
        }

        if (!$hasLogin || !$hasPassword) {
            return [
                'status' => 'not_configured',
                'label' => 'SERP API not configured',
                'message' => 'DataForSEO credentials are missing. Competitor AI fallback remains available.',
                'last_error' => $latestError,
            ];
        }

        if ($latestError !== '') {
            return [
                'status' => 'error',
                'label' => 'SERP API needs attention',
                'message' => 'DataForSEO returned an error. Fix the API credentials/account, or use competitor AI fallback meanwhile.',
                'last_error' => $latestError,
            ];
        }

        if ($snapshots !== []) {
            return [
                'status' => 'connected',
                'label' => 'SERP connected',
                'message' => 'SERP snapshots are available for validation.',
                'last_error' => '',
            ];
        }

        return [
            'status' => 'needs_test',
            'label' => 'SERP ready to test',
            'message' => 'Credentials are present, but no SERP snapshots have been saved yet.',
            'last_error' => '',
        ];
    }

    private function competitorFallbackPreview(object $site, array $competitors, array $services, array $products, string $preferredLocation = '', array $dismissedKeys = [], array $contents = []): array
    {
        if ($competitors === []) {
            return [];
        }

        $locations = $this->decodeJsonField($site->target_locations ?? []);
        $rows = [];

        foreach (array_slice($competitors, 0, 5) as $competitorIndex => $competitor) {
            $service = trim((string)($competitor->service_or_product ?? ''));
            if ($service === '') {
                $offers = array_values(array_filter(array_merge($services, $products)));
                $service = (string)($offers[0] ?? ($site->site_key === 'avomeal' ? 'catering' : 'event planning'));
            }

            $location = trim($preferredLocation) ?: $this->competitorMarket($competitor) ?: trim((string)($locations[0] ?? ''));
            $market = $location !== '' ? trim((string)explode(',', $location)[0]) : '';
            $base = strtolower(trim($service . ($market !== '' ? ' ' . $market : '')));
            $candidates = array_values(array_unique(array_filter([
                $base,
                strtolower(trim($service . ' services' . ($market !== '' ? ' ' . $market : ''))),
                strtolower(trim($service . ' packages' . ($market !== '' ? ' ' . $market : ''))),
                strtolower(trim($service . ' near me')),
            ])));

            foreach (array_slice($candidates, 0, 3) as $keywordIndex => $keyword) {
                $key = $this->opportunityKey('competitor_preview', $keyword, $location, $service, (string)($competitor->competitor_domain ?? $competitor->competitor_name ?? ''));
                if (in_array($key, $dismissedKeys, true)) {
                    continue;
                }
                $rows[] = [
                    'opportunity_key' => $key,
                    'title' => $this->normalizeTitle((string)($competitor->competitor_name ?? 'Competitor') . ' - ' . $keyword),
                    'keyword' => $keyword,
                    'service' => $service,
                    'location' => $location,
                    'score' => max(58, 88 - ($competitorIndex * 5) - ($keywordIndex * 3)),
                    'recommended_content_type' => 'page',
                    'recommended_route' => '/' . $this->slugify($keyword),
                    'reason' => 'Preview based on this competitor URL, its assigned service and market. Run IA to read public title, meta description and headings for sharper keyword angles.',
                    'competitors' => [(string)($competitor->competitor_domain ?? $competitor->competitor_name ?? '')],
                    'competitor_name' => (string)($competitor->competitor_name ?? ''),
                    'competitor_domain' => (string)($competitor->competitor_domain ?? ''),
                    'competitor_id' => (int)($competitor->id ?? 0),
                    'existing_content' => $this->matchingContent($contents, $keyword, $location, $service),
                    'source' => 'competitor_fallback_preview',
                ];
            }
        }

        return array_slice($rows, 0, 12);
    }

    private function aiCompetitorOpportunities(object $site, array $competitors, array $services, array $products, string $preferredLocation = ''): array
    {
        $apiKey = trim((string)($_ENV['OPENAI_TOKEN'] ?? $_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return [];
        }

        $signals = $this->competitorPageSignals($competitors);
        $system = 'You are a local SEO strategist. Return only compact valid JSON. You do not have live SERP ranking data. Infer our ranking opportunities from tracked competitor websites, their titles, meta descriptions, headings, the brand services, and the target market. Competitor data is internal gap research only: extract what competitors emphasize, what they miss, and which keywords or buyer questions our brand should target. Do not recommend promoting, naming, comparing against, or writing public copy about competitors. Competitor domains may appear only as internal metadata. Do not invent metrics.';
        $user = [
            'brand' => $site->site_name ?? $site->site_key,
            'site_key' => $site->site_key,
            'services' => array_values($services),
            'products' => array_values($products),
            'target_locations' => $preferredLocation !== '' ? [$preferredLocation] : $this->decodeJsonField($site->target_locations ?? []),
            'internal_competitor_gap_signals' => $signals,
            'required_json_shape' => [
                'opportunities' => [
                    [
                        'title' => 'string',
                        'keyword' => 'string',
                        'service' => 'string',
                        'location' => 'string',
                        'score' => 0,
                        'recommended_content_type' => 'page|location|blog',
                        'reason' => 'string',
                        'competitors' => ['domain'],
                    ],
                ],
            ],
        ];

        $payload = [
            'model' => $_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->callOpenAIJson($apiKey, $payload);
        if (!$response || empty($response['opportunities']) || !is_array($response['opportunities'])) {
            return [];
        }

        $rows = [];
        foreach ($response['opportunities'] as $row) {
            $keyword = strtolower(trim((string)($row['keyword'] ?? '')));
            if ($keyword === '') {
                continue;
            }
            $matchedCompetitor = $this->matchCompetitorFromRow($competitors, $row);
            $rows[] = [
                'opportunity_key' => $this->opportunityKey('openai_competitor_scan', $keyword, trim((string)($row['location'] ?? ($matchedCompetitor ? $this->competitorMarket($matchedCompetitor) : ''))), trim((string)($row['service'] ?? ($matchedCompetitor->service_or_product ?? ''))), (string)($matchedCompetitor->competitor_domain ?? $matchedCompetitor->competitor_name ?? '')),
                'title' => $this->normalizeTitle((string)($row['title'] ?? ucwords($keyword))),
                'keyword' => $keyword,
                'service' => trim((string)($row['service'] ?? ($matchedCompetitor->service_or_product ?? ''))),
                'location' => trim((string)($row['location'] ?? ($matchedCompetitor ? $this->competitorMarket($matchedCompetitor) : ''))),
                'score' => max(0, min(100, (float)($row['score'] ?? 65))),
                'recommended_content_type' => $this->normalizeContentType(trim((string)($row['recommended_content_type'] ?? 'page')) ?: 'page'),
                'recommended_route' => '/' . $this->slugify($keyword),
                'reason' => trim((string)($row['reason'] ?? 'Inferred from tracked competitor pages.')),
                'competitors' => is_array($row['competitors'] ?? null) ? array_values($row['competitors']) : [],
                'competitor_name' => (string)($matchedCompetitor->competitor_name ?? ''),
                'competitor_domain' => (string)($matchedCompetitor->competitor_domain ?? ''),
                'competitor_id' => (int)($matchedCompetitor->id ?? 0),
                'source' => 'openai_competitor_scan',
            ];
        }

        return array_slice($rows, 0, 10);
    }

    private function competitorPageSignals(array $competitors): array
    {
        $signals = [];
        foreach (array_slice($competitors, 0, 6) as $competitor) {
            $url = trim((string)($competitor->competitor_url ?? ''));
            if ($url === '') {
                continue;
            }

            $html = $this->fetchPublicHtml($url);
            $signals[] = [
                'name' => (string)($competitor->competitor_name ?? ''),
                'domain' => (string)($competitor->competitor_domain ?? ''),
                'url' => $url,
                'service_or_product' => (string)($competitor->service_or_product ?? ''),
                'city' => (string)($competitor->city ?? ''),
                'title' => $this->extractFirstMatch($html, '/<title[^>]*>(.*?)<\/title>/is'),
                'meta_description' => $this->extractFirstMatch($html, '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/is'),
                'headings' => array_slice($this->extractMatches($html, '/<h[1-2][^>]*>(.*?)<\/h[1-2]>/is'), 0, 8),
                'notes' => (string)($competitor->notes ?? ''),
            ];
        }

        return $signals;
    }

    private function aiPageBody(object $site, array $input, string $title, string $keyword, string $location, array $gscContext, array $serpSnapshots, array $serpResults, array $competitorSignals, array $internalLinks = [], array $researchSummary = [], array $generationConfig = []): ?string
    {
        $apiKey = trim((string)($_ENV['OPENAI_TOKEN'] ?? $_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return null;
        }

        $service = $this->normalizeTitle((string)($input['service_or_product'] ?? ''));
        $category = trim((string)($input['content_category'] ?? ''));
        $templateKey = trim((string)($input['template_key'] ?? 'service-landing'));
        $contentType = $this->normalizeContentType((string)($input['content_type'] ?? 'blog'));
        $isLocationPage = $contentType === 'location' || $templateKey === 'local-location-page';
        $providedData = trim((string)($input['provided_data'] ?? ''));
        $editorObservations = trim((string)($input['editor_observations'] ?? ''));
        $generationBasis = trim((string)($input['generation_basis'] ?? 'approved_sources'));
        $brandVoice = trim((string)($site->brand_voice ?? 'Helpful, specific and conversion-focused.'));
        $ctaLabel = trim((string)($input['selected_cta_label'] ?? $site->default_cta_label ?? 'Contact us'));
        $ctaUrl = trim((string)($input['selected_cta_url'] ?? $site->default_cta_url ?? '/contact'));
        if ($ctaLabel === '') {
            $ctaLabel = 'Contact us';
        }
        if ($ctaUrl === '') {
            $ctaUrl = '/contact';
        }
        $includeMap = !empty($input['include_map']);
        $faqCount = max(3, min(8, (int)($input['faq_count'] ?? 5)));
        $imageCount = max(0, min(8, (int)($input['images_to_generate'] ?? 0)));
        $intentKey = (string)($generationConfig['content_intent'] ?? 'brand_informational');
        if ($isLocationPage) {
            $intentKey = 'brand_informational';
        }
        $intent = is_array($generationConfig['intent'] ?? null) ? $generationConfig['intent'] : self::CONTENT_INTENTS['brand_informational'];
        $targetWordCount = max(600, min(3000, (int)($generationConfig['target_word_count'] ?? 1200)));

        $system = 'You are a senior landing page copywriter, local SEO strategist, and conversion editor. Return only compact valid JSON with one key named html. Write publishable, human, specific public page copy that would be worth reading after human review. The requested editorial intent is mandatory: change the structure, hook, title energy, section rhythm and examples to match that intent instead of defaulting to an informational SEO guide. Do not output markdown, notes, placeholder instructions, a technical brief, a content plan, or anything that says what the page should do. Never mention Search Console, GSC, SERP, opportunity score, impressions, CTR, average position, suggested URL, validation, publishing, brief, prompt, internal research, Ophyra, renderer, template, image slots, or page structure. Use those inputs silently to decide what to write. Do not invent testimonials, awards, exact prices, guarantees, availability, citations, or claims not supported by the provided context. Fictional scenarios are allowed only when the intent says fiction_allowed=true; they must be clearly realistic illustrative scenes, not fake testimonials or fake events claimed as real. Competitor page signals are internal research only: use them silently to identify keyword gaps, buyer questions, service differentiators and positioning opportunities. Never mention competitor names, domains, URLs, brands, competitor research, or the word competitor in visible copy, and never make the page sound like it is promoting another provider.';
        $user = [
            'brand' => [
                'site_key' => (string)$site->site_key,
                'name' => (string)$site->site_name,
                'voice' => $brandVoice,
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
            ],
            'page' => [
                'title' => $title,
                'primary_keyword' => $keyword,
                'location' => $location,
                'service_or_product' => $service,
                'content_type' => $contentType,
                'category' => $category,
                'template_key' => $templateKey,
                'is_location_page' => $isLocationPage,
                'include_map' => $includeMap,
                'image_slots_requested' => $imageCount,
                'faq_count' => $faqCount,
                'minimum_word_count' => $targetWordCount,
                'content_intent' => $intentKey,
                'intent_label' => (string)($intent['label'] ?? 'Brand informational'),
                'intent_title_style' => (string)($intent['title_style'] ?? ''),
                'intent_body_style' => (string)($intent['body_style'] ?? ''),
                'fiction_allowed' => !empty($intent['fiction_allowed']),
            ],
            'internal_generation_inputs_not_for_visible_copy' => [
                'brief_from_ui' => $providedData,
                'editor_observations' => $editorObservations,
                'generation_basis' => $generationBasis,
                'search_console_rows' => array_slice($gscContext, 0, 5),
                'serp_snapshots' => array_slice($serpSnapshots, 0, 3),
                'serp_results' => array_slice($serpResults, 0, 6),
                'internal_competitor_gap_signals' => array_slice($competitorSignals, 0, 4),
                'internal_links_to_use_when_relevant' => array_slice($internalLinks, 0, 6),
                'research_summary' => $researchSummary,
                'generation_config' => $generationConfig,
            ],
            'html_requirements' => [
                'Use only body HTML, not a full document.',
                'Use sections with classes: cms-block cms-block-hero, cms-block-body, cms-block-gallery when relevant, cms-block-faq, cms-block-cta.',
                'The hero must include p.cms-eyebrow, h1, and a warm opening paragraph.',
                'Use subtle, specific headings that sound like a real brand, not robotic SEO headings.',
                'Build the article from the provided research summary and source excerpts. If source coverage is thin, be transparent by writing practical guidance without unsupported claims.',
                'Treat approved external sources as research essence only: rewrite completely, add brand-specific judgement, and never copy the source wording or structure.',
                'If generation_basis is approved_sources, the approved source texts and URLs must drive the article. Use the brief only as editorial notes.',
                'If generation_basis is edited_brief, the edited brief can drive the article, but do not ignore approved sources when they are present.',
                'Respect editor observations as mandatory direction unless they conflict with safety, brand facts, or source limits.',
                'Write like a real public page, but match the selected intent: provocative story, countdown/list, editorial rewrite, tutorial or brand informational.',
                'If intent is provocative_story, open with a clearly invented realistic scene and then connect it to the buyer problem. Do not pretend the scenario is a real client story.',
                'If intent is local_countdown, use a countdown or numbered list as the main article spine, with each item carrying useful local/service detail.',
                'If intent is editorial_rewrite, use a narrative magazine-style structure with a stronger story arc and positioning than a generic blog post.',
                'If intent is tutorial_learning, teach with steps, checks, examples, mistakes to avoid and a clear learning outcome.',
                'If intent is brand_informational, explain the buyer problem, local context, decision factors, service flow, planning details, and what the visitor should consider before contacting the brand.',
                'If this is a location page, write a practical local service page, not a story, blog essay, fictional scene, or generic article. The page must explain the area, nearby neighborhoods or service coverage when useful, local access/logistics, and how the exact service_or_product can be provided in that location.',
                'For location pages, stay as close as possible to the supplied source URL and editor observations while rewriting fully in our words. Do not add creative openings that are not present in the reference.',
                'For location pages, never switch the service/topic. If service_or_product or primary_keyword says catering, do not mention DJ, wedding DJ, music, MC, photography, or any unrelated service unless the editor explicitly provided it.',
                'For location pages, do not invent local history, venues, landmarks, route facts, neighborhoods, or client scenarios. Mention nearby areas only as general service coverage if supported by the location/source context.',
                'If the page is service-related, include practical service details such as timing, staffing, setup, menu/service options, guest experience, coordination, and what information is needed for a quote.',
                'Include at least five concrete details, examples, checks, or decision points. Avoid generic filler.',
                'If internal competitor gap signals exist, convert them silently into stronger buyer questions, keyword angles and our own positioning. Do not include a competitor, market comparison, or competitor-insight section.',
                'Include FAQ using details/summary with real buyer questions tied to the service and market.',
                'Include a CTA section with an anchor using exactly the provided brand CTA URL and label. Do not invent CTA links.',
                'Write at least ' . $targetWordCount . ' words for article/guide content. Use more sections and better detail rather than filler.',
                'Include natural inline internal links inside paragraphs when internal links are provided. Example: "When you hire a <a href=\"URL\">Wedding Planner</a>..." Use the exact URLs from internal_links_to_use_when_relevant and do not invent URLs.',
                'Do not create a final "Related pages", "Related services", "Continue exploring", or forced link-list section. Backlinks belong naturally inside the article body.',
                'Do not create a section titled "Sources referenced", "Referenced sources", or similar. If outside sources are discussed, frame them editorially as expert/community perspective and keep the main article focused on the reader.',
                'If map is requested, include a cms-block cms-block-default section explaining the service area without fake coordinates.',
                'If image_slots_requested is greater than 0, do not create raw img tags, stacked galleries, placeholder image markup, or instructions about image layout.',
                'Write enough practical service detail in the page body so the generated visual story can sit beside meaningful customer-facing copy.',
                'Make the copy useful enough to publish after human review.',
                'Visible copy must talk to the customer directly. It must not describe the SEO process, the CMS, generation, rendering, research workflow, or what a future editor should do.',
            ],
        ];

        $payload = [
            'model' => $_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => 0.55,
            'max_tokens' => (int)($generationConfig['max_tokens'] ?? 4500),
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->callOpenAIJson($apiKey, $payload);
        $html = trim((string)($response['html'] ?? ''));
        if ($html === '' || !str_contains($html, '<')) {
            return null;
        }

        $html = $this->sanitizeGeneratedBodyHtml($html);
        if ($this->generatedBodyLooksLikeInternalBrief($html)) {
            return null;
        }

        $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (str_word_count($plain) < $targetWordCount) {
            $enriched = $this->enrichGeneratedBodyHtml($apiKey, $system, $user, $html, $targetWordCount, $generationConfig);
            if ($enriched !== null) {
                $html = $enriched;
            }
        }

        return $html;
    }

    private function enrichGeneratedBodyHtml(string $apiKey, string $system, array $context, string $draftHtml, int $targetWordCount, array $generationConfig): ?string
    {
        $payload = [
            'model' => $_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system . ' You are now doing the second editorial pass. Rewrite and enrich the supplied HTML into a stronger complete article. Return only compact valid JSON with one key named html.'],
                ['role' => 'user', 'content' => json_encode([
                    'task' => 'Second pass rewrite. Use the first draft only as raw material. Expand weak sections, add concrete service detail, improve reader intent, strengthen transitions, preserve valid links/CTA, and reach the target word count without filler.',
                    'target_word_count' => $targetWordCount,
                    'original_generation_context' => $context,
                    'first_pass_html' => $draftHtml,
                    'hard_rules' => [
                        'Return body HTML only inside the html JSON key.',
                        'Preserve cms-block classes and produce a publishable public page, not a plan or brief.',
                        'Do not mention Ophyra, prompts, renderers, SEO workflow, Search Console, SERP, validation, or publishing instructions.',
                        'Do not include placeholder text or instructions about what an editor should replace.',
                        'Use approved source material as essence only; rewrite completely.',
                    ],
                ], JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => 0.45,
            'max_tokens' => max((int)($generationConfig['max_tokens'] ?? 4500), min(12000, $targetWordCount * 5)),
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->callOpenAIJson($apiKey, $payload);
        $html = trim((string)($response['html'] ?? ''));
        if ($html === '' || !str_contains($html, '<')) {
            return null;
        }

        $html = $this->sanitizeGeneratedBodyHtml($html);
        if ($this->generatedBodyLooksLikeInternalBrief($html)) {
            return null;
        }

        return $html;
    }

    private function sanitizeGeneratedBodyHtml(string $html): string
    {
        $html = preg_replace('/```(?:html)?|```/i', '', $html) ?? $html;
        $html = preg_replace('/<!doctype[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/?(?:html|head|body)[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/<section\b[^>]*>\s*<h2>\s*(?:Questions people are asking|Questions people are already asking)\s*<\/h2>[\s\S]*?(?:Review Quora discussions|Before publishing)[\s\S]*?<\/section>/i', '', $html) ?? $html;
        $html = preg_replace('/<section\b[^>]*>\s*<h2>\s*(?:Related pages|Related services|Continue exploring)[\s\S]*?<\/section>/i', '', $html) ?? $html;

        return trim($html);
    }

    private function generatedBodyLooksLikeInternalBrief(string $html): bool
    {
        $text = strtolower(strip_tags($html));
        foreach ([
            'gsc opportunity',
            'search console',
            'serp',
            'opportunity score',
            'impressions',
            'average position',
            'ctr',
            'suggested url',
            'validate the opportunity',
            'before publishing',
            'this page should',
            'what ophyra will create',
            'ophyra will',
            'public renderers',
            'renderer',
            'image slots',
            'data-image-slot',
            'this visual should',
            'use this visual',
            'placeholder image',
            'generated image direction',
            'provided data',
            'required page structure',
            'quality rules',
            'replace this note',
            'review quora discussions',
            'questions people are asking',
            'visual story',
            'this image should',
            'this visual should',
            'what this looks like in practice',
            'a closer look at the experience',
            'customer-facing insight',
        ] as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function generationConfigForDraft(array $input, string $type): array
    {
        $mode = strtolower(trim((string)($input['generation_mode'] ?? $input['article_type'] ?? '')));
        if ($mode === '') {
            $researchSources = strtolower((string)($input['research_sources'] ?? ''));
            $mode = str_contains($researchSources, 'deep') ? 'deep_article' : 'seo_standard';
            if ($type === 'page' && trim((string)($input['primary_keyword'] ?? $input['keyword_text'] ?? '')) === '') {
                $mode = 'page_review';
            }
        }
        if (!isset(self::GENERATION_MODES[$mode])) {
            $mode = 'seo_standard';
        }

        $config = self::GENERATION_MODES[$mode];
        $envKey = 'GROWTH_HUB_AI_MAX_TOKENS_' . strtoupper($mode);
        if (!empty($_ENV[$envKey]) && (int)$_ENV[$envKey] > 0) {
            $config['max_tokens'] = (int)$_ENV[$envKey];
        }
        $config['mode'] = $mode;
        $targetWords = (int)($input['target_word_count'] ?? 1200);
        $config['target_word_count'] = max(600, min(3000, $targetWords > 0 ? $targetWords : 1200));
        $intentKey = $type === 'location'
            ? 'brand_informational'
            : $this->normalizeContentIntent((string)($input['content_intent'] ?? ''));
        $config['content_intent'] = $intentKey;
        $config['intent'] = self::CONTENT_INTENTS[$intentKey];

        return $config;
    }

    private function normalizeContentIntent(string $intent): string
    {
        $intent = strtolower(trim($intent));

        return isset(self::CONTENT_INTENTS[$intent]) ? $intent : 'brand_informational';
    }

    private function fallbackTitleIdeas(object $site, string $keyword, string $location, string $service, string $competitor, string $providedData, string $sourceReason, array $generationConfig = []): array
    {
        $topic = $service !== '' ? $service : ($keyword !== '' ? $keyword : 'this service');
        $market = $location !== '' ? ' in ' . $location : '';
        $brand = (string)($site->site_name ?? 'this brand');
        $intentKey = (string)($generationConfig['content_intent'] ?? 'brand_informational');
        $research = $competitor !== ''
            ? 'Use the tracked competitor only as internal gap research. Identify what they emphasize, what they omit, and which buyer questions or keyword angles ' . $brand . ' can answer better. Do not mention the competitor in public copy.'
            : 'Review local buyer questions, market service pages, and practical planning details before drafting.';
        $editorNote = $providedData !== '' ? ' Editor note: ' . $providedData : ($sourceReason !== '' ? ' Source context: ' . $sourceReason : '');

        $templates = [
            'provocative_story' => [
                'title' => 'The ' . $topic . $market . ' Moment Nobody Wants to Improvise',
                'excerpt' => 'A story-led article that opens with an invented but realistic planning scene, then turns that tension into useful service decisions and a clear next step.',
                'angle' => 'Provocative fictional scenario',
                'preview' => 'Use a fictional scene to create curiosity, then connect it to real planning choices, timing, service flow and buyer concerns.',
                'brief' => 'Write a provocative story-driven page for ' . $topic . $market . '. Start with a clearly fictional realistic scenario, then use the scene to explain decisions, risks, service flow and why a planned approach matters.' . $editorNote,
            ],
            'local_countdown' => [
                'title' => '7 ' . $topic . $market . ' Details That Change the Whole Experience',
                'excerpt' => 'A countdown-style article built for scanning, with each item giving the reader a useful local or service detail before they request help.',
                'angle' => 'Countdown list',
                'preview' => 'Build the article around a numbered countdown with specific, practical items instead of a generic explanation.',
                'brief' => 'Write a countdown article for ' . $topic . $market . '. Use numbered sections, concrete planning details, local/service context, internal links and a CTA.' . $editorNote,
            ],
            'editorial_rewrite' => [
                'title' => 'A Sharper Way to Think About ' . $topic . $market,
                'excerpt' => 'An editorial blog rewrite with a stronger narrative arc, sharper positioning and useful takeaways that still supports search intent.',
                'angle' => 'Editorial rewrite',
                'preview' => 'Make it read like a polished blog article, not a service checklist. Use story flow, better transitions and brand positioning.',
                'brief' => 'Write an editorial-style rewrite about ' . $topic . $market . '. Make it narrative, well-positioned, useful and structured like a strong blog article.' . $editorNote,
            ],
            'tutorial_learning' => [
                'title' => 'How to Plan ' . $topic . $market . ' Without Missing the Details',
                'excerpt' => 'A tutorial-style page that teaches the reader what to do, what to check and which mistakes to avoid before contacting a provider.',
                'angle' => 'Learning tutorial',
                'preview' => 'Teach with steps, examples, checks and mistakes to avoid. The reader should leave knowing how to move forward.',
                'brief' => 'Write a tutorial for ' . $topic . $market . '. Teach the reader step by step, include practical checks, common mistakes and a clear CTA.' . $editorNote,
            ],
            'brand_informational' => [
                'title' => $topic . $market . ': What to Know Before You Book',
                'excerpt' => 'A practical guide for visitors who want to understand the planning details, compare options and request a clearer next step before booking.',
                'angle' => 'Buyer confidence',
                'preview' => 'A direct, useful guide that helps visitors understand what matters before they request a quote.',
                'brief' => 'Write a strong buyer guide for ' . $topic . $market . '. Focus on decision points, planning details, common concerns, brand fit and why choosing an organized local provider matters.' . $editorNote,
            ],
        ];
        $selected = $templates[$intentKey] ?? $templates['brand_informational'];

        return [
            'ideas' => [[
                'title' => substr($this->normalizeTitle($selected['title']), 0, 110),
                'excerpt' => $selected['excerpt'],
                'focus_keyphrase' => $this->normalizeTitle($keyword !== '' ? $keyword : $topic),
                'location' => $location,
                'angle' => $selected['angle'],
                'preview' => $selected['preview'],
                'research_focus' => $research,
                'cta_angle' => 'Move readers from the selected angle into a clear quote, consultation or contact step.',
                'brief_addition' => $selected['brief'],
            ]],
            'source' => 'fallback',
        ];
    }

    private function citationCountForDraft(array $input, array $generationConfig): int
    {
        if (array_key_exists('reddit_citations_count', $input) && trim((string)$input['reddit_citations_count']) !== '') {
            return max(0, min(12, (int)$input['reddit_citations_count']));
        }

        $sources = strtolower((string)($input['research_sources'] ?? ''));
        if (!str_contains($sources, 'reddit') && !str_contains($sources, 'citation') && !str_contains($sources, 'web')) {
            return 0;
        }

        return max(0, min(12, (int)($generationConfig['target_sources'] ?? 4)));
    }

    private function researchSummaryForDraft(array $citationPlan, array $competitorSignals, array $serpResults, array $gscContext, array $generationConfig, bool $includeMap, string $location): array
    {
        $sources = is_array($citationPlan['reviewed_citations'] ?? null) ? $citationPlan['reviewed_citations'] : [];
        $community = array_values(array_filter($sources, static fn(array $source): bool => ($source['source_type'] ?? '') === 'community'));

        return [
            'mode' => $generationConfig['mode'] ?? 'seo_standard',
            'target_sources' => (int)($generationConfig['target_sources'] ?? 0),
            'valid_sources' => array_slice($sources, 0, 12),
            'valid_source_count' => count($sources),
            'community_sources' => array_slice($community, 0, 4),
            'community_source_count' => count($community),
            'competitor_signal_count' => count($competitorSignals),
            'serp_result_count' => count($serpResults),
            'search_console_signal_count' => count($gscContext),
            'local_context' => [
                'applies' => $includeMap || $location !== '',
                'location_name' => $location,
                'map_embed_url' => $includeMap && $location !== '' ? 'https://www.google.com/maps?q=' . rawurlencode($location) . '&output=embed' : null,
                'google_maps_url' => $includeMap && $location !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($location) : null,
                'map_lat' => null,
                'map_lng' => null,
                'coordinate_status' => 'not_verified',
            ],
            'concrete_inputs' => [
                'source_titles' => array_values(array_filter(array_map(static fn(array $source): string => (string)($source['title'] ?? ''), $sources))),
                'source_research_texts' => array_values(array_filter(array_map(static fn(array $source): string => (string)($source['research_text'] ?? $source['answer_excerpt'] ?? $source['excerpt'] ?? ''), array_slice($sources, 0, 5)))),
                'competitor_titles' => array_values(array_filter(array_map(static fn(array $signal): string => (string)($signal['title'] ?? ''), $competitorSignals))),
                'serp_titles' => array_values(array_filter(array_map(static fn($row): string => is_object($row) ? (string)($row->result_title ?? '') : (string)($row['result_title'] ?? ''), $serpResults))),
            ],
        ];
    }

    private function qualityReviewForDraft(string $title, string $body, string $metaDescription, string $slug, array $citationPlan, array $imagePlan, array $generationConfig, bool $includeMap, array $blockedPublicTerms = []): array
    {
        $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $plainLower = strtolower($plain);
        $wordCount = str_word_count($plain);
        $h2Count = preg_match_all('/<h2\b/i', $body);
        $validSources = is_array($citationPlan['reviewed_citations'] ?? null) ? count($citationPlan['reviewed_citations']) : 0;
        $minSources = (int)($generationConfig['min_sources_to_publish'] ?? 0);
        $concreteSignals = $this->concreteSignalCount($plain);
        $notes = [];
        $score = 100;

        if (trim($title) === '' || str_word_count($title) < 3) {
            $notes[] = 'Missing clear title.';
            $score -= 15;
        }
        $minimumWords = max(600, min(3000, (int)($generationConfig['target_word_count'] ?? 1200)));
        if (($generationConfig['mode'] ?? '') === 'page_review') {
            $minimumWords = min($minimumWords, 900);
        }
        if ($wordCount < $minimumWords) {
            $notes[] = 'Body is too short for a publishable article. Minimum words: ' . $minimumWords . '.';
            $score -= 30;
        }
        if ($h2Count < 3) {
            $notes[] = 'Needs at least 3 organized H2 sections.';
            $score -= 12;
        }
        if ($metaDescription === '' || strlen($metaDescription) < 80) {
            $notes[] = 'Missing useful meta description.';
            $score -= 10;
        }
        if (trim($slug) === '') {
            $notes[] = 'Missing slug.';
            $score -= 10;
        }
        if ($validSources < $minSources) {
            $notes[] = 'Not enough valid real sources for this generation mode.';
            $score -= 20;
        }
        if ($concreteSignals < 5) {
            $notes[] = 'Needs more concrete details, examples or buyer decision points.';
            $score -= 12;
        }
        if (!str_contains($body, 'cms-block-cta') && !str_contains($plainLower, 'contact') && !str_contains($plainLower, 'quote')) {
            $notes[] = 'Missing clear actionable CTA.';
            $score -= 10;
        }
        if ($includeMap && !str_contains($body, 'google.com/maps')) {
            $notes[] = 'Local/map mode requested but no map embed was prepared.';
            $score -= 8;
        }
        foreach (self::GENERIC_PHRASES as $phrase) {
            if (str_contains($plainLower, $phrase)) {
                $notes[] = 'Generic AI phrase detected: ' . $phrase;
                $score -= 5;
            }
        }
        if ($imagePlan !== [] && !str_contains($body, '<img') && !str_contains($body, 'data-image-slot')) {
            $notes[] = 'Images were requested but no image placement exists.';
            $score -= 6;
        }
        foreach ($blockedPublicTerms as $term) {
            $term = strtolower(trim((string)$term));
            $term = preg_replace('/^https?:\/\//', '', $term) ?? $term;
            $term = preg_replace('/^www\./', '', $term) ?? $term;
            if (strlen($term) < 4) {
                continue;
            }
            if (str_contains($plainLower, $term) || str_contains(strtolower($body), $term)) {
                $notes[] = 'Visible copy mentions an internal competitor research source.';
                $score -= 20;
                break;
            }
        }

        $score = max(0, min(100, $score));

        return [
            'status' => $score >= 70 && $notes === [] ? 'passed' : 'failed_review',
            'score' => $score,
            'notes' => $notes,
            'metrics' => [
                'word_count' => $wordCount,
                'h2_count' => $h2Count,
                'valid_sources' => $validSources,
                'required_sources' => $minSources,
                'concrete_signals' => $concreteSignals,
            ],
        ];
    }

    private function hasBlockingQualityIssue(array $qualityReview): bool
    {
        foreach (($qualityReview['notes'] ?? []) as $note) {
            $note = strtolower((string)$note);
            if (
                str_contains($note, 'generic ai phrase')
                || str_contains($note, 'internal competitor')
            ) {
                return true;
            }
        }

        return false;
    }

    private function concreteSignalCount(string $text): int
    {
        $count = 0;
        $count += preg_match_all('/\b\d+[\d,.%]*\b/', $text);
        $count += preg_match_all('/\b(?:before|after|during|timeline|staff|setup|menu|guest|venue|city|area|quote|budget|dietary|delivery|service|map|location)\b/i', $text);

        return min(20, $count);
    }

    private function competitorsForDraft(object $site, array $input): array
    {
        $competitors = $this->repo->competitors((int)$site->id_owner, (string)$site->site_key, 20);
        if ($competitors === []) {
            return [];
        }

        $wantedId = (int)($input['competitor_id'] ?? $input['analysis_competitor'] ?? 0);
        $wantedName = strtolower(trim((string)($input['competitor_name'] ?? '')));
        $wantedDomain = strtolower(trim((string)($input['competitor_domain'] ?? '')));
        $service = strtolower(trim((string)($input['service_or_product'] ?? '')));
        $matched = [];

        foreach ($competitors as $competitor) {
            $id = (int)($competitor->id ?? 0);
            $name = strtolower(trim((string)($competitor->competitor_name ?? '')));
            $domain = strtolower(trim((string)($competitor->competitor_domain ?? '')));
            $competitorService = strtolower(trim((string)($competitor->service_or_product ?? '')));
            if (($wantedId > 0 && $id === $wantedId)
                || ($wantedDomain !== '' && $domain !== '' && str_contains($domain, $wantedDomain))
                || ($wantedName !== '' && $name !== '' && str_contains($name, $wantedName))
                || ($service !== '' && $competitorService !== '' && str_contains($competitorService, $service))
            ) {
                $matched[] = $competitor;
            }
        }

        return array_slice($matched !== [] ? $matched : $competitors, 0, 4);
    }

    private function findCompetitorById(array $competitors, int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        foreach ($competitors as $competitor) {
            if ((int)($competitor->id ?? 0) === $id) {
                return $competitor;
            }
        }

        return null;
    }

    private function competitorMarket(object $competitor): string
    {
        $parts = array_filter([
            trim((string)($competitor->city ?? '')),
            trim((string)($competitor->county ?? '')),
            trim((string)($competitor->state ?? '')),
        ]);

        return implode(', ', array_values(array_unique($parts)));
    }

    private function matchCompetitorFromRow(array $competitors, array $row): ?object
    {
        $values = [];
        if (is_array($row['competitors'] ?? null)) {
            $values = array_map(static fn($value): string => strtolower(trim((string)$value)), $row['competitors']);
        }
        $values[] = strtolower(trim((string)($row['competitor'] ?? '')));
        $values[] = strtolower(trim((string)($row['competitor_domain'] ?? '')));
        $values = array_filter($values);

        foreach ($competitors as $competitor) {
            $name = strtolower(trim((string)($competitor->competitor_name ?? '')));
            $domain = strtolower(trim((string)($competitor->competitor_domain ?? '')));
            foreach ($values as $value) {
                if (($domain !== '' && str_contains($value, $domain)) || ($name !== '' && str_contains($value, $name))) {
                    return $competitor;
                }
            }
        }

        return $competitors[0] ?? null;
    }

    private function opportunityKey(string $source, string $keyword, string $location = '', string $service = '', string $competitor = ''): string
    {
        return sha1(strtolower(trim(implode('|', [
            $source,
            $this->slugify($keyword ?: 'no-keyword'),
            $this->slugify($location ?: 'no-location'),
            $this->slugify($service ?: 'no-service'),
            $this->slugify($competitor ?: 'no-competitor'),
        ]))));
    }

    private function matchingContent(array $contents, string $keyword, string $location = '', string $service = ''): ?array
    {
        $keyword = strtolower(trim($keyword));
        $location = strtolower(trim($location));
        $service = strtolower(trim($service));
        if ($keyword === '' && $service === '') {
            return null;
        }

        foreach ($contents as $content) {
            $haystack = strtolower(trim(implode(' ', [
                (string)($content->title ?? ''),
                (string)($content->slug ?? ''),
                (string)($content->primary_keyword ?? ''),
                (string)($content->target_location ?? ''),
            ])));
            $keywordMatch = $keyword !== '' && str_contains($haystack, $keyword);
            $serviceMatch = $service !== '' && str_contains($haystack, $service);
            $locationMatch = $location === '' || str_contains($haystack, $location);
            $tokenMatch = false;
            if ($keyword !== '') {
                $keywordTokens = array_filter(preg_split('/\s+/', $keyword) ?: [], static fn(string $term): bool => strlen($term) >= 5);
                $hits = 0;
                foreach ($keywordTokens as $term) {
                    if (str_contains($haystack, $term)) {
                        $hits++;
                    }
                }
                $tokenMatch = $keywordTokens !== [] && $hits >= max(1, (int)ceil(count($keywordTokens) * 0.7));
            }

            if (($keywordMatch || $serviceMatch || $tokenMatch) && $locationMatch) {
                return [
                    'id' => (int)($content->id ?? 0),
                    'title' => (string)($content->title ?? ''),
                    'route' => (string)($content->route ?? ''),
                    'status' => (string)($content->status ?? ''),
                ];
            }
        }

        return null;
    }

    private function fetchPublicHtml(string $url): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'OphyraGrowthHub/1.0',
            CURLOPT_MAXREDIRS => 3,
        ]);
        $html = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($html) || $status >= 400) {
            return '';
        }

        return substr($html, 0, 180000);
    }

    private function extractFirstMatch(string $html, string $pattern): string
    {
        if ($html === '' || !preg_match($pattern, $html, $matches)) {
            return '';
        }

        return $this->normalizeTitle(strip_tags(html_entity_decode((string)$matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function extractMatches(string $html, string $pattern): array
    {
        if ($html === '' || !preg_match_all($pattern, $html, $matches)) {
            return [];
        }

        $values = [];
        foreach ($matches[1] as $match) {
            $value = $this->normalizeTitle(strip_tags(html_entity_decode((string)$match, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function researchLab(object $site, array $input): array
    {
        $seedKeyword = trim((string)($input['research_keyword'] ?? ''));
        $seedLocation = trim((string)($input['research_location'] ?? ''));
        $seedCompetitor = trim((string)($input['research_competitor'] ?? ''));
        $trackedCompetitors = $this->repo->competitors((int)$site->id_owner, (string)$site->site_key, 12);

        if ($seedKeyword === '' && $seedCompetitor === '') {
            return $this->emptyResearchLab((string)$site->site_key, $input);
        }

        $ai = $this->aiResearchLab($site, $seedKeyword, $seedLocation, $seedCompetitor, $trackedCompetitors);
        if ($ai) {
            return $ai;
        }

        return $this->estimatedResearchLab($site, $seedKeyword, $seedLocation, $seedCompetitor, $trackedCompetitors);
    }

    private function aiResearchLab(object $site, string $keyword, string $location, string $competitor, array $trackedCompetitors = []): ?array
    {
        $apiKey = trim((string)($_ENV['OPENAI_TOKEN'] ?? $_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return null;
        }

        $system = 'You are a local SEO research assistant for an owned CMS. Return only compact valid JSON. Do not claim live Google Trends or SERP access. Use tracked competitor URLs/domains as market context and infer candidate keywords/opportunities from services, competitors, and location. Estimate when exact metrics are unavailable and mark source as ai_estimate.';
        $user = [
            'brand' => $site->site_name ?? $site->site_key,
            'site_key' => $site->site_key,
            'keyword' => $keyword,
            'location' => $location,
            'competitor' => $competitor,
            'tracked_competitors' => array_map(static fn(object $row): array => [
                'name' => (string)($row->competitor_name ?? ''),
                'domain' => (string)($row->competitor_domain ?? ''),
                'url' => (string)($row->competitor_url ?? ''),
                'service' => (string)($row->service_or_product ?? ''),
                'market' => trim((string)($row->city ?? $row->county ?? '')),
            ], array_slice($trackedCompetitors, 0, 8)),
            'required_json_shape' => [
                'keyword_recommendations' => [
                    ['keyword' => 'string', 'location' => 'string', 'volume' => 0, 'competition' => 'low|medium|high', 'priority' => 0, 'intent' => 'string', 'reason' => 'string'],
                ],
                'competitor_recommendations' => [
                    ['name' => 'string', 'domain' => 'string', 'location' => 'string', 'reason' => 'string'],
                ],
                'competitor_keyword_recommendations' => [
                    ['competitor' => 'string', 'keyword' => 'string', 'priority' => 0, 'reason' => 'string'],
                ],
            ],
        ];

        $payload = [
            'model' => $_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => 0.25,
            'max_tokens' => 900,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->callOpenAIJson($apiKey, $payload);
        if (!$response) {
            return null;
        }

        return $this->normalizeResearchLabPayload((string)$site->site_key, $keyword, $location, $competitor, $response, 'ai_estimate');
    }

    private function estimatedResearchLab(object $site, string $keyword, string $location, string $competitor, array $trackedCompetitors = []): array
    {
        $base = trim($keyword) !== '' ? trim($keyword) : 'event planning';
        $loc = trim($location);
        $brandType = (string)($site->site_key ?? '');
        $eventModifiers = $brandType === 'avomeal'
            ? ['meal delivery', 'healthy meal prep', 'weekly meal plan', 'catering meals', 'family meal delivery']
            : ['wedding planner', 'event planner', 'party planner', 'quinceanera planner', 'corporate event planning'];

        $recommendations = [];
        $terms = array_values(array_unique(array_filter(array_merge([$base], array_map(static fn(string $m): string => $m . ($loc ? ' ' . $loc : ''), $eventModifiers)))));

        foreach (array_slice($terms, 0, 8) as $index => $term) {
            $priority = max(45, 92 - ($index * 6));
            $recommendations[] = [
                'keyword' => strtolower($term),
                'location' => $loc,
                'volume' => 900 - ($index * 85),
                'competition' => $index < 3 ? 'high' : ($index < 6 ? 'medium' : 'low'),
                'priority' => $priority,
                'intent' => str_contains($term, 'planner') || str_contains($term, 'delivery') ? 'commercial' : 'informational',
                'reason' => 'Estimated local SEO variant based on the seed keyword and selected market.',
            ];
        }

        $competitorNames = $competitor !== ''
            ? [$competitor]
            : array_values(array_filter(array_map(static fn(object $row): string => (string)($row->competitor_name ?? $row->competitor_domain ?? ''), $trackedCompetitors)));
        if ($competitorNames === []) {
            $competitorNames = [
                $loc ? $loc . ' Business Operators' : 'Local Business Operators',
                'Premier Event Services',
                'Elite Party Planning',
                'South Florida Event Co',
            ];
        }

        $competitors = [];
        foreach ($competitorNames as $index => $name) {
            $tracked = $trackedCompetitors[$index] ?? null;
            $domain = $tracked ? (string)($tracked->competitor_domain ?? '') : strtolower(preg_replace('/[^a-z0-9]+/', '', $name)) . '.com';
            $competitors[] = [
                'name' => $name,
                'domain' => $domain,
                'location' => $loc,
                'reason' => $tracked ? 'Manual competitor tracked in Step 1. Use IA or SERP to infer keyword angles.' : 'Candidate competitor placeholder. Replace with SERP provider results when configured.',
            ];
        }

        $competitorKeywords = [];
        foreach (array_slice($competitors, 0, 3) as $competitorRow) {
            foreach (array_slice($recommendations, 0, 3) as $kw) {
                $competitorKeywords[] = [
                    'competitor' => $competitorRow['name'],
                    'keyword' => $kw['keyword'],
                    'priority' => $kw['priority'],
                    'reason' => 'Use this to compare competitor positioning against your final keyword list.',
                ];
            }
        }

        return [
            'site_key' => (string)$site->site_key,
            'seed_keyword' => $keyword,
            'seed_location' => $location,
            'seed_competitor' => $competitor,
            'source' => 'local_estimate',
            'keyword_recommendations' => $recommendations,
            'competitor_recommendations' => $competitors,
            'competitor_keyword_recommendations' => $competitorKeywords,
        ];
    }

    private function normalizeResearchLabPayload(string $siteKey, string $keyword, string $location, string $competitor, array $payload, string $source): array
    {
        $keywordRows = [];
        foreach (($payload['keyword_recommendations'] ?? []) as $row) {
            if (empty($row['keyword'])) {
                continue;
            }
            $keywordRows[] = [
                'keyword' => strtolower(trim((string)$row['keyword'])),
                'location' => trim((string)($row['location'] ?? $location)),
                'volume' => (int)($row['volume'] ?? 0),
                'competition' => strtolower((string)($row['competition'] ?? 'medium')),
                'priority' => (float)($row['priority'] ?? 50),
                'intent' => trim((string)($row['intent'] ?? 'mixed')),
                'reason' => trim((string)($row['reason'] ?? 'Recommended by research preview.')),
            ];
        }

        $competitors = [];
        foreach (($payload['competitor_recommendations'] ?? []) as $row) {
            if (empty($row['name']) && empty($row['domain'])) {
                continue;
            }
            $competitors[] = [
                'name' => trim((string)($row['name'] ?? $row['domain'])),
                'domain' => trim((string)($row['domain'] ?? '')),
                'location' => trim((string)($row['location'] ?? $location)),
                'reason' => trim((string)($row['reason'] ?? 'Potential competitor for the selected market.')),
            ];
        }

        $competitorKeywords = [];
        foreach (($payload['competitor_keyword_recommendations'] ?? []) as $row) {
            if (empty($row['keyword'])) {
                continue;
            }
            $competitorKeywords[] = [
                'competitor' => trim((string)($row['competitor'] ?? $competitor)),
                'keyword' => strtolower(trim((string)$row['keyword'])),
                'priority' => (float)($row['priority'] ?? 50),
                'reason' => trim((string)($row['reason'] ?? 'Keyword appears relevant to the competitor angle.')),
            ];
        }

        return [
            'site_key' => $siteKey,
            'seed_keyword' => $keyword,
            'seed_location' => $location,
            'seed_competitor' => $competitor,
            'source' => $source,
            'keyword_recommendations' => array_slice($keywordRows, 0, 10),
            'competitor_recommendations' => array_slice($competitors, 0, 8),
            'competitor_keyword_recommendations' => array_slice($competitorKeywords, 0, 12),
        ];
    }

    private function callOpenAIJson(string $apiKey, array $payload): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 18,
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($raw, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $json = json_decode((string)$content, true);

        return is_array($json) ? $json : null;
    }

    public function publicContentPayload(string $siteKey, ?string $type = null): array
    {
        $rows = $this->repo->publishedContent($siteKey, $type);
        $site = $this->repo->getSiteByKey($siteKey);

        return array_map(fn(object $row): array => $this->contentPayload($row, false, $site), $rows);
    }

    public function publicContentBySlug(string $siteKey, string $slug): ?array
    {
        $content = $this->repo->contentBySlug($siteKey, $slug);
        if (!$content) {
            return null;
        }

        $site = $this->repo->getSiteByKey($siteKey);

        return $this->contentPayload($content, true, $site);
    }

    public function publicContentByRoute(string $siteKey, string $route): ?array
    {
        $content = $this->repo->contentByRoute($siteKey, $route);
        if (!$content) {
            return null;
        }

        $site = $this->repo->getSiteByKey($siteKey);

        return $this->contentPayload($content, true, $site);
    }

    public function adminContentPreview(string $siteKey, int $contentId): ?array
    {
        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            return null;
        }

        $content = $this->repo->contentForAdmin((int)$site->id_owner, $siteKey, $contentId);
        if (!$content) {
            return null;
        }

        return $this->contentPayload($content, true, $site);
    }

    public function publicSitemapXml(string $siteKey): ?string
    {
        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            return null;
        }

        $rows = $this->repo->publishedContent($siteKey);
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($rows as $content) {
            $route = $this->normalizePublicRoute((string)($content->route ?: $this->routeForType((string)$content->content_type, (string)$content->slug)));
            $canonical = $content->canonical_url ?: rtrim((string)$site->public_base_url, '/') . $route;
            $lastmod = substr((string)($content->updated_at ?: $content->published_at ?: $content->created_at ?: date('Y-m-d')), 0, 10);
            $priority = match ($this->normalizeContentType((string)$content->content_type)) {
                'page' => '0.85',
                'location' => '0.78',
                'blog' => '0.68',
                default => '0.55',
            };

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars($canonical, ENT_XML1) . '</loc>';
            $xml[] = '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1) . '</lastmod>';
            $xml[] = '    <changefreq>weekly</changefreq>';
            $xml[] = '    <priority>' . $priority . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    public function mediaPayload(string $siteKey, ?int $contentId = null): array
    {
        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            return [];
        }

        return array_map(static fn(object $media): array => [
            'id' => (int)$media->id,
            'site_key' => $media->site_key,
            'id_content' => $media->id_content ? (int)$media->id_content : null,
            'secure_url' => $media->secure_url,
            'cloudinary_public_id' => $media->cloudinary_public_id,
            'source_type' => $media->source_type,
            'usage_type' => $media->usage_type,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'width' => $media->width ? (int)$media->width : null,
            'height' => $media->height ? (int)$media->height : null,
            'format' => $media->format,
        ], $this->repo->mediaForContent((int)$site->id_owner, $siteKey, $contentId));
    }

    public function blocksPayload(string $siteKey, int $contentId): ?array
    {
        $content = $this->repo->publishedContentById($siteKey, $contentId);
        if (!$content) {
            return null;
        }

        $blocks = $this->repo->blocksForContent((int)$content->id_owner, $siteKey, $contentId);

        return [
            'site_key' => $siteKey,
            'id_content' => $contentId,
            'blocks' => array_map(static fn(object $block): array => [
                'id' => (int)$block->id,
                'block_type' => $block->block_type,
                'block_key' => $block->block_key,
                'title' => $block->title,
                'data' => json_decode((string)$block->data_json, true) ?: [],
                'sort_order' => (int)$block->sort_order,
            ], $blocks),
        ];
    }

    public function monitoringPayload(string $siteKey): ?array
    {
        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            return null;
        }

        $ownerId = (int)$site->id_owner;

        return [
            'site' => [
                'site_key' => $site->site_key,
                'site_name' => $site->site_name,
                'domain' => $site->domain,
                'public_base_url' => $site->public_base_url,
            ],
            'keywords' => $this->repo->keywords($ownerId, $siteKey),
            'competitors' => $this->repo->competitors($ownerId, $siteKey),
            'target_locations' => $this->repo->targetLocations($ownerId, $siteKey),
            'serp_snapshots' => $this->repo->latestSerpSnapshots($ownerId, $siteKey, 50),
            'opportunities' => $this->repo->opportunities($ownerId, $siteKey, 50),
            'calendar' => $this->repo->calendarContents($ownerId, $siteKey, 90),
            'generated_at' => date('c'),
        ];
    }

    private function persistPublicContract(object $content, object $site): void
    {
        $route = $this->normalizePublicRoute((string)($content->route ?: $this->routeForType((string)$content->content_type, (string)$content->slug)));
        $canonical = rtrim((string)$site->public_base_url, '/') . $route;
        $seoTitle = trim((string)($content->seo_title ?: $content->title));
        $metaDescription = $this->normalizeMetaDescription((string)($content->meta_description ?: $content->excerpt ?: $content->body), $seoTitle);
        $blocks = $this->repo->blocksForContent((int)$content->id_owner, (string)$content->site_key, (int)$content->id);
        $schema = $this->decodeJsonField($content->schema_json ?? []);
        if ($schema === []) {
            $schema = $this->schemaForContent($content, $site, $canonical, $seoTitle, $metaDescription, $blocks);
        }
        $media = $this->mediaPayload((string)$content->site_key, (int)$content->id);
        $featuredImage = !empty($media[0]['secure_url']) ? (string)$media[0]['secure_url'] : null;

        $this->repo->createRoute([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'id_content' => (int)$content->id,
            'route' => $route,
            'route_type' => (string)$content->content_type,
            'status' => 'ACTIVE',
            'canonical_url' => $canonical,
        ]);

        $this->repo->updatePublicContract((int)$site->id_owner, (string)$site->site_key, (int)$content->id, [
            'type' => $this->legacyCmsTypeForContentType((string)$content->content_type),
            'body_html' => (string)($content->body ?? ''),
            'meta_title' => $seoTitle,
            'seo_title' => $seoTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonical,
            'robots' => 'index, follow',
            'featured_image_url' => $featuredImage,
            'schema_json' => $schema,
        ]);
    }

    private function legacyCmsTypeForContentType(string $contentType): string
    {
        return match ($this->normalizeContentType($contentType)) {
            'blog' => 'post',
            'location', 'page' => 'page',
            default => 'page',
        };
    }

    private function contentPayload(object $content, bool $withBlocks = false, ?object $site = null): array
    {
        $site = $site ?: $this->repo->getSiteByKey((string)$content->site_key);
        $metadata = $this->decodeJsonField($content->metadata_json ?? []);
        $schema = $this->decodeJsonField($content->schema_json ?? []);
        $route = $this->normalizePublicRoute((string)($content->route ?: $this->routeForType((string)$content->content_type, (string)$content->slug)));
        $canonical = $content->canonical_url ?: (($site ? rtrim((string)$site->public_base_url, '/') : '') . $route);
        $seoTitle = trim((string)($content->seo_title ?: $content->title));
        $metaDescription = $this->normalizeMetaDescription((string)($content->meta_description ?: $content->excerpt ?: $content->body), $seoTitle);
        $blocks = [];
        if ($withBlocks) {
            $blocks = $this->repo->blocksForContent((int)$content->id_owner, (string)$content->site_key, (int)$content->id);
        }
        $effectiveSchema = $schema ?: $this->schemaForContent($content, $site, $canonical, $seoTitle, $metaDescription, $blocks);
        $quality = $this->qualityAudit($content, $metadata, $effectiveSchema, $metaDescription);
        $featuredImage = trim((string)($content->featured_image_url ?? ''));

        $payload = [
            'id' => (int)$content->id,
            'site_key' => $content->site_key,
            'site' => [
                'site_key' => $site->site_key ?? $content->site_key,
                'site_name' => $site->site_name ?? null,
                'domain' => $site->domain ?? null,
                'public_base_url' => $site->public_base_url ?? null,
            ],
            'content_type' => $content->content_type,
            'type' => $content->type ?? $content->content_type,
            'title' => $content->title,
            'slug' => $content->slug,
            'excerpt' => $content->excerpt,
            'body' => $content->body,
            'body_html' => $content->body_html ?? $content->body,
            'content_json' => $this->decodeJsonField($content->content_json ?? []),
            'seo_title' => $seoTitle,
            'meta_title' => $content->meta_title ?? $seoTitle,
            'meta_description' => $metaDescription,
            'primary_keyword' => $content->primary_keyword,
            'target_location' => $content->target_location,
            'route' => $route,
            'canonical_url' => $canonical,
            'robots' => $content->robots ?? 'index, follow',
            'featured_image_url' => $featuredImage !== '' ? $featuredImage : null,
            'published_at' => $content->published_at,
            'updated_at' => $content->updated_at ?? null,
            'seo' => [
                'title' => $seoTitle,
                'description' => $metaDescription,
                'canonical' => $canonical,
                'robots' => $content->robots ?? 'index, follow',
                'og_title' => $seoTitle,
                'og_description' => $metaDescription,
                'og_url' => $canonical,
                'og_image' => $featuredImage !== '' ? $featuredImage : null,
            ],
            'schema_json' => $effectiveSchema,
            'metadata' => $metadata,
            'template' => [
                'id' => !empty($content->id_template) ? (int)$content->id_template : null,
                'key' => $content->template_key ?? ($metadata['template_key'] ?? null),
                'name' => $content->template_name ?? ($metadata['template_name'] ?? null),
                'css_text' => $content->template_css_text ?? null,
                'structure' => $this->decodeJsonField($content->template_structure_json ?? []),
            ],
            'category' => [
                'id' => !empty($content->id_cms_category) ? (int)$content->id_cms_category : null,
                'slug' => $content->cms_category_slug ?? ($metadata['content_category'] ?? null),
                'name' => $content->cms_category_name ?? ($metadata['content_category_name'] ?? null),
            ],
            'quality' => $quality,
        ];

        if ($withBlocks) {
            $payload['blocks'] = array_map(static fn(object $block): array => [
                'id' => (int)$block->id,
                'block_type' => $block->block_type,
                'block_key' => $block->block_key,
                'title' => $block->title,
                'data' => json_decode((string)$block->data_json, true) ?: [],
                'sort_order' => (int)$block->sort_order,
            ], $blocks);
            $payload['media'] = $this->mediaPayload((string)$content->site_key, (int)$content->id);
            if (!empty($payload['media'][0]['secure_url'])) {
                $payload['seo']['og_image'] = $payload['media'][0]['secure_url'];
                $payload['featured_image_url'] = $payload['featured_image_url'] ?: $payload['media'][0]['secure_url'];
                $payload['schema_json']['image'] = $payload['media'][0]['secure_url'];
            }
        }

        return $payload;
    }

    private function suggestOpportunities(int $ownerId, string $siteKey): array
    {
        $contents = $this->repo->recentContents($ownerId, $siteKey, 200);
        $keywords = $this->repo->keywords($ownerId, $siteKey, 100);
        $existingText = strtolower(implode(' ', array_map(static fn(object $content): string => $content->title . ' ' . $content->slug . ' ' . $content->primary_keyword . ' ' . $content->target_location, $contents)));
        $suggestions = [];

        foreach ($keywords as $keyword) {
            $keywordText = (string)$keyword->keyword_text;
            $location = (string)($keyword->location ?: '');
            if ($keywordText !== '' && !str_contains($existingText, strtolower($keywordText))) {
                $suggestions[] = [
                    'title' => $this->titleFromPlan('blog', $keywordText, $location, $siteKey),
                    'keyword_text' => $keywordText,
                    'location_name' => $location,
                    'service_or_product' => $keyword->service_or_product,
                    'score' => (float)($keyword->priority_score ?? 60),
                    'recommended_content_type' => $location ? 'location' : 'blog',
                    'reason_summary' => 'Create content to improve visibility for "' . $keywordText . '"' . ($location !== '' ? ' in ' . $location : '') . '. This keyword is in the final research list and does not appear to have a matching CMS page yet.',
                    'already_has_content' => false,
                ];
            }
        }

        usort($suggestions, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, 20);
    }

    private function marketOpportunities(int $ownerId, string $siteKey, array $dismissedKeys, array $contents): array
    {
        $rows = [];
        foreach ($this->repo->opportunities($ownerId, $siteKey, 80) as $row) {
            $data = $this->decodeJsonField($row->data_json ?? []);
            $competitorName = trim((string)($data['competitor_name'] ?? ''));
            $competitorDomain = trim((string)($data['competitor_domain'] ?? ''));
            if ($competitorName === '' && $competitorDomain === '') {
                continue;
            }

            $key = trim((string)($data['opportunity_key'] ?? '')) ?: $this->opportunityKey(
                'saved_competitor',
                (string)($row->keyword_text ?? ''),
                (string)($row->location_name ?? ''),
                (string)($row->service_or_product ?? ''),
                $competitorDomain ?: $competitorName
            );
            if (in_array($key, $dismissedKeys, true)) {
                continue;
            }

            $row->opportunity_key = $key;
            $row->competitor_name = $competitorName;
            $row->competitor_domain = $competitorDomain;
            $row->existing_content = $this->matchingContent($contents, (string)($row->keyword_text ?? ''), (string)($row->location_name ?? ''), (string)($row->service_or_product ?? ''));
            $rows[] = $row;
        }

        return $rows;
    }

    private function searchConsolePageOpportunities(int $ownerId, string $siteKey, array $services, array $dismissedKeys = [], array $contents = []): array
    {
        $rows = $this->repo->searchConsolePageOpportunities($ownerId, $siteKey, 20);
        $opportunities = [];

        foreach ($rows as $row) {
            $query = (string)$row->keyword_text;
            $service = $this->matchServiceForQuery($query, $services);
            $location = $this->locationFromQuery($query);
            $position = (float)($row->average_position ?? 0);
            $impressions = (int)($row->impressions ?? 0);
            $ctr = (float)($row->ctr ?? 0);
            $key = $this->opportunityKey('gsc', $query, $location, $service ?: '', (string)$row->page_url);
            if (in_array($key, $dismissedKeys, true)) {
                continue;
            }

            $opportunities[] = [
                'opportunity_key' => $key,
                'service' => $service ?: 'Service to classify',
                'location' => $location,
                'main_keyword' => $query,
                'source' => 'GSC detected',
                'intent' => $location ? 'Commercial / local' : 'Commercial',
                'current_page' => $row->page_url,
                'impressions' => $impressions,
                'clicks' => (int)($row->clicks ?? 0),
                'ctr' => $ctr,
                'average_position' => $position,
                'recommended_content_type' => 'page',
                'suggested_url' => '/' . $this->slugify($query),
                'priority' => $this->priorityFromGsc($impressions, $ctr, $position),
                'next_action' => 'Analyze SERP',
                'existing_content' => $this->matchingContent($contents, $query, $location, $service ?: ''),
            ];
        }

        return $opportunities;
    }

    private function matchServiceForQuery(string $query, array $services): ?string
    {
        $query = strtolower($query);
        foreach ($services as $service) {
            $serviceText = strtolower((string)$service);
            if ($serviceText !== '' && str_contains($query, $serviceText)) {
                return (string)$service;
            }

            foreach (preg_split('/\s+/', $serviceText) ?: [] as $term) {
                if (strlen($term) > 4 && str_contains($query, $term)) {
                    return (string)$service;
                }
            }
        }

        return null;
    }

    private function locationFromQuery(string $query): string
    {
        $known = ['miami', 'doral', 'kendall', 'hialeah', 'broward', 'fort lauderdale', 'west palm beach', 'miami-dade', 'weston', 'coral gables', 'aventura', 'hollywood'];
        $lower = strtolower($query);
        foreach ($known as $location) {
            if (str_contains($lower, $location)) {
                return ucwords($location);
            }
        }

        return '';
    }

    private function priorityFromGsc(int $impressions, float $ctr, float $position): string
    {
        if ($impressions >= 100 && ($position >= 8 || $ctr < 0.015)) {
            return 'High';
        }

        if ($impressions >= 25 || $position <= 12) {
            return 'Medium';
        }

        return 'Low';
    }

    private function briefBody(object $site, array $input, string $title, string $keyword, string $location, array $citationPlan = [], array $generationConfig = []): string
    {
        $sources = (string)($input['research_sources'] ?? 'own_data');
        $imageCount = max(0, (int)($input['images_to_generate'] ?? 0));
        $faqCount = max(0, (int)($input['faq_count'] ?? 0));
        $redditCount = max(0, (int)($input['reddit_citations_count'] ?? 0));
        $includeMap = !empty($input['include_map']) ? 'yes' : 'no';
        $userData = trim((string)($input['provided_data'] ?? ''));
        $competitorName = $this->normalizeTitle((string)($input['competitor_name'] ?? ''));
        $competitorDomain = strtolower(trim((string)($input['competitor_domain'] ?? '')));
        $service = $this->normalizeTitle((string)($input['service_or_product'] ?? ''));
        $category = trim((string)($input['content_category'] ?? ''));
        $template = trim((string)($input['template_key'] ?? ''));
        $type = $this->normalizeContentType((string)($input['content_type'] ?? 'blog'));
        $isLocationPage = $type === 'location' || $template === 'local-location-page';
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $paragraphs = static function (string $value) use ($e): string {
            $chunks = preg_split('/\n{2,}/', trim($value)) ?: [];
            return implode("\n", array_map(
                static fn(string $chunk): string => '<p>' . nl2br($e(trim($chunk)), false) . '</p>',
                array_filter($chunks, static fn(string $chunk): bool => trim($chunk) !== '')
            ));
        };

        $contextItems = array_filter([
            'Brand: ' . (string)$site->site_name,
            $service !== '' ? 'Service / offer: ' . $service : null,
            $category !== '' ? 'Category: ' . $category : null,
            $template !== '' ? 'Template: ' . $template : null,
            $keyword !== '' ? 'Primary keyword: ' . $keyword : null,
            $location !== '' ? 'Target location: ' . $location : null,
            $competitorName !== '' || $competitorDomain !== '' ? 'Competitor signal: ' . trim($competitorName . ' ' . ($competitorDomain !== '' ? '(' . $competitorDomain . ')' : '')) : null,
            'Research mode: ' . $sources,
            'Web citations requested: ' . $redditCount,
            'Images to generate/register: ' . $imageCount,
            'Map block requested: ' . $includeMap,
            'FAQ count requested: ' . $faqCount,
        ]);
        $audience = trim(implode(' in ', array_filter([$keyword ?: $service, $location])));
        $audience = $audience !== '' ? $audience : $title;
        $ctaLabel = trim((string)($site->default_cta_label ?? 'Contact us'));
        $ctaUrl = trim((string)($site->default_cta_url ?? '/contact'));
        $serviceLabel = $service !== '' ? $service : ($keyword !== '' ? $keyword : 'your event');
        $marketLabel = $location !== '' ? $location : 'South Florida';
        $marketPhrase = $location !== '' ? ' in ' . $location : '';
        $brandName = (string)$site->site_name;
        $intentKey = $this->normalizeContentIntent((string)($input['content_intent'] ?? ''));
        $intentCopy = [
            'provocative_story' => [
                'lead' => 'Imagine the room is ready, guests are arriving, and one small planning choice suddenly controls the whole mood. This is a fictional scene, but it reflects the kind of pressure that makes ' . $serviceLabel . ' worth planning carefully.',
                'heading' => 'The moment that should not be improvised',
                'body' => 'A strong plan is not only about having a service available. It is about knowing what happens when timing shifts, guests ask questions, the space behaves differently than expected and the host still needs the experience to feel calm.',
                'list_heading' => 'What the story should make clear',
                'items' => [
                    'Which planning choices create the most visible pressure.',
                    'How service flow changes the way guests experience the event.',
                    'What needs to be decided before event day instead of during it.',
                    'How a clear provider conversation turns uncertainty into a workable plan.',
                ],
            ],
            'local_countdown' => [
                'lead' => 'Some details look small until they decide how the whole experience feels. This countdown keeps the most important ones visible before the planning gets too far ahead.',
                'heading' => '7 details that change the experience',
                'body' => 'Use each item as a planning checkpoint. The goal is not to make the decision complicated; it is to notice the choices that usually separate a smooth event from one that feels rushed.',
                'list_heading' => 'The countdown',
                'items' => [
                    'Guest count and arrival rhythm.',
                    'Venue access, setup time and service movement.',
                    'The difference between required service and optional enhancements.',
                    'How the CTA or quote request should capture enough detail to be useful.',
                ],
            ],
            'editorial_rewrite' => [
                'lead' => $serviceLabel . ' is rarely just a line item. In ' . $marketLabel . ', it becomes part of how people remember the pacing, comfort and personality of the event.',
                'heading' => 'A sharper way to think about the experience',
                'body' => 'The useful version of this article is not a generic overview. It should connect the service to a real decision: what kind of event is being shaped, what details carry the most weight and how the brand helps the plan feel more intentional.',
                'list_heading' => 'The editorial angle to carry through',
                'items' => [
                    'Start with the experience, not only the keyword.',
                    'Use concrete planning details to support the narrative.',
                    'Show what the reader should notice before choosing a provider.',
                    'Close with a next step that feels natural after the story.',
                ],
            ],
            'tutorial_learning' => [
                'lead' => 'The fastest way to make a better decision is to know what to check first. This guide teaches the practical sequence behind ' . $serviceLabel . $marketPhrase . '.',
                'heading' => 'How to plan the next step',
                'body' => 'Start with the basics, then move into the details that affect timing, scope, setup, guest experience and quote accuracy. A tutorial should leave the reader more capable than when they arrived.',
                'list_heading' => 'Steps to work through',
                'items' => [
                    'Define the event goal, date, guest count and service expectations.',
                    'Clarify what must be included and what can remain optional.',
                    'Check timing, space, setup needs and coordination responsibilities.',
                    'Prepare a quote request with enough detail to get a useful answer.',
                ],
            ],
            'brand_informational' => [
                'lead' => $brandName . ' helps teams, hosts and planners shape ' . $audience . ' into a clear, organized experience from the first conversation.',
                'heading' => 'A smoother way to plan ' . $serviceLabel,
                'body' => 'The best results usually start before the event day. They start with a conversation about the audience, timing, space, priorities and the small details that make the experience feel intentional.',
                'list_heading' => 'What clients usually need clarified first',
                'items' => [
                    'What is included, what is optional and what needs to be decided early.',
                    'How the service adapts to the venue, guest count, schedule and style of the event.',
                    'What information is needed to prepare a useful quote or recommendation.',
                    'How the next step works once the client is ready to talk.',
                ],
            ],
        ];
        $selectedIntent = $intentCopy[$intentKey] ?? $intentCopy['brand_informational'];
        if ($isLocationPage) {
            $selectedIntent = [
                'lead' => $brandName . ' helps clients understand how ' . $serviceLabel . ' can work in ' . $marketLabel . ', with practical attention to the area, timing, access, guest needs and service coverage.',
                'heading' => 'A practical way to plan ' . $serviceLabel . ' in ' . $marketLabel,
                'body' => 'A location page should make the area easier to understand. The useful copy explains where the service fits, what local details can affect the plan and what a client should clarify before requesting a quote.',
                'list_heading' => 'What this location page should clarify',
                'items' => [
                    'How the service can be planned for the specific area.',
                    'What local access, timing, venue or guest-count details may matter.',
                    'Which nearby areas or service-coverage details are relevant without inventing facts.',
                    'What information is needed to prepare a useful local quote.',
                ],
            ];
        }
        $targetWordCount = max(600, min(3000, (int)($generationConfig['target_word_count'] ?? $input['target_word_count'] ?? 1200)));
        $approvedCitations = is_array($citationPlan['reviewed_citations'] ?? null) ? array_slice($citationPlan['reviewed_citations'], 0, 3) : [];
        $sourceItems = [];
        foreach ($approvedCitations as $citation) {
            $sourceTitle = $this->cleanCitationText((string)($citation['title'] ?? 'Reviewed source'), 16);
            $sourceUrl = trim((string)($citation['url'] ?? ''));
            $sourceText = $this->cleanCitationText((string)($citation['answer_excerpt'] ?? $citation['excerpt'] ?? ''), 58);
            $publisher = $this->cleanCitationText((string)($citation['publisher'] ?? $citation['domain'] ?? parse_url($sourceUrl, PHP_URL_HOST) ?? ''), 6);
            if ($sourceTitle === '' || $sourceUrl === '' || $sourceText === '') {
                continue;
            }
            $sourceItems[] = '<li><strong><a href="' . $e($sourceUrl) . '" target="_blank" rel="noopener nofollow">' . $e($sourceTitle) . '</a></strong>'
                . '<p><em>' . $e($publisher !== '' ? 'Referenced from ' . $publisher : 'Referenced source') . ':</em> ' . $e($sourceText) . '</p>'
                . '<p>' . $e('In our words, the useful lesson is that clients are not only buying a service name. They are buying clarity around scope, timing, expectations, communication and the local details that can change the final experience.') . '</p></li>';
        }
        $genericDevelopmentSections = [
            [
                'heading' => 'Start with what the service needs to accomplish',
                'body' => [
                    'Choosing ' . $serviceLabel . ' should start with the purpose of the event, not with a generic package label. The first useful question is what the service needs to make easier for the host, guests, planner or business team.',
                    'That conversation should connect the service to timing, setup, guest count, venue rules, presentation, staffing, communication and the kind of experience the client wants people to remember.',
                ],
            ],
            [
                'heading' => 'The conversation before the quote matters',
                'body' => [
                    'A useful quote should be built around the real shape of the event. Date, location, guest count, venue access, service windows, setup needs and optional add-ons can all change the scope.',
                    'This is why the first conversation should ask about more than a headline service. It should clarify what must be included, what can remain optional, what the venue requires and what the client wants to feel simple by the time guests arrive.',
                ],
            ],
            [
                'heading' => 'Budget should connect to scope',
                'body' => [
                    'Clients often compare providers as if every offer includes the same work. In practice, cost can reflect planning time, delivery or travel, staffing, setup complexity, materials, equipment, timeline pressure and the level of coordination needed.',
                    'The safer approach is to ask what would change the quote before approving the plan. If the event has multiple spaces, special access needs, tight timing, dietary requirements, presentation expectations or a larger guest count, those details should be discussed early.',
                ],
            ],
            [
                'heading' => 'Local logistics should shape the plan',
                'body' => [
                    'A strong plan should account for how the area and venue affect the work. Access windows, parking, loading, room layout, weather, guest flow and nearby service coverage can all affect what is realistic.',
                    'For ' . $brandName . ', the goal is to make those details visible before the event feels rushed. The service should fit the location and the schedule instead of being treated as an isolated line item.',
                ],
            ],
            [
                'heading' => 'What to ask before choosing',
                'body' => [
                    'Before committing, clients should ask what is included, what is optional, what information is needed, how changes are handled, how the team coordinates with the venue and what happens if timing shifts.',
                    'The right provider should be able to explain preparation, communication and service flow in specific terms. A confident answer is usually practical. A vague answer is a sign to slow down and clarify the plan.',
                ],
            ],
        ];
        $locationDevelopmentSections = [
            [
                'heading' => 'What this area changes about the plan',
                'body' => [
                    $marketLabel . ' should not be treated as a decorative keyword. A useful location page explains how the service can be organized for that area and what a client should consider before booking.',
                    'The most important details are usually practical: where the event is happening, how people arrive, how setup access works, how much time is available and whether the service needs to adapt to a specific venue or neighborhood.',
                ],
            ],
            [
                'heading' => 'Nearby coverage should be clear',
                'body' => [
                    'Many visitors arrive at a location page because they want to know whether the provider can serve their area, not because they want a long story. The copy should make coverage feel clear while avoiding invented neighborhood claims.',
                    'If the client is comparing nearby areas, the page should explain that the next step is to share the address, guest count, schedule and service needs so the team can confirm the best plan.',
                ],
            ],
            [
                'heading' => 'The original reference should guide the page',
                'body' => [
                    'When a source URL is provided, the page should stay close to that reference: the area, service context, useful details and images should guide the rewrite. The finished copy must still be original, but it should not drift into a new service or a creative storyline.',
                    'That is especially important for service pages where the client expects direct answers. If the page is about catering, the copy should stay with catering. If it is about planning, it should stay with planning. The location page should never wander into a different offer.',
                ],
            ],
            [
                'heading' => 'What clients should confirm before booking',
                'body' => [
                    'A good local inquiry should include the event date, exact area or venue direction, expected guest count, service style, timing, special requirements and any limits the location creates.',
                    $brandName . ' can then respond with a clearer recommendation because the page has prepared the visitor to share the details that actually affect scope.',
                ],
            ],
        ];
        $developmentSections = $isLocationPage ? $locationDevelopmentSections : $genericDevelopmentSections;
        $neededSections = $targetWordCount >= 1300 ? $developmentSections : array_slice($developmentSections, 0, 4);
        $developmentHtml = [];
        foreach ($neededSections as $section) {
            $developmentHtml[] = '<section class="cms-block cms-block-body"><h2>' . $e($section['heading']) . '</h2>'
                . implode('', array_map(static fn(string $paragraph): string => '<p>' . $e($paragraph) . '</p>', $section['body']))
                . '</section>';
        }
        $sourceHtml = $sourceItems !== []
            ? '<section class="cms-block cms-block-body cms-block-citations"><h2>' . $e('What other experts and communities are saying') . '</h2><p>' . $e('The approved references point to practical buyer questions. The goal is to translate those signals into clearer guidance for this exact service and location, not to repeat the source material.') . '</p><ul>' . implode('', $sourceItems) . '</ul></section>'
            : null;
        $faqItems = array_slice([
            ['How early should we start planning?', 'It is best to start once the date, venue direction and main goals are clear. That gives enough room to organize priorities, compare options and avoid rushed decisions.'],
            ['Can the service be adapted to our event size?', 'Yes. The right plan should reflect the guest count, schedule, space, budget direction and the experience you want people to remember.'],
            ['What happens after we request information?', 'Share the basic event details and the team can help clarify scope, availability, next steps and the best way to move forward.'],
            ['What should we compare before choosing?', 'Compare scope, communication, planning support, timing, setup needs, included services and the provider process for handling changes.'],
            ['Why does the first conversation matter?', 'The first conversation reveals whether the provider understands the real shape of the event, not only the keyword or service name.'],
        ], 0, max(1, min(8, $faqCount)));
        $faqHtml = '';
        foreach ($faqItems as $index => $item) {
            $faqHtml .= '  <details' . ($index === 0 ? ' open' : '') . '><summary>' . $e($item[0]) . '</summary><p>' . $e($item[1]) . '</p></details>';
        }

        return implode("\n", array_filter([
            '<section class="cms-block cms-block-hero">',
            '  <p class="cms-eyebrow">' . $e($brandName) . '</p>',
            '  <h1>' . $e($title) . '</h1>',
            '  <p>' . $e($selectedIntent['lead']) . '</p>',
            '</section>',
            '<section class="cms-block cms-block-body">',
            '  <h2>' . $e($selectedIntent['heading']) . '</h2>',
            '  <p>' . $e($selectedIntent['body']) . '</p>',
            '  <p>' . $e($brandName . ' brings those moving pieces into one plan, so the service feels less improvised and more connected to what the occasion actually needs.') . '</p>',
            '</section>',
            implode("\n", $developmentHtml),
            '<section class="cms-block cms-block-body">',
            '  <h2>' . $e($selectedIntent['list_heading']) . '</h2>',
            '  <ul><li>' . implode('</li><li>', array_map($e, $selectedIntent['items'])) . '</li></ul>',
            '</section>',
            $competitorName !== '' || $competitorDomain !== '' ? '<section class="cms-block cms-block-body"><h2>' . $e('Built for a competitive ' . $marketLabel . ' market') . '</h2><p>' . $e('People comparing providers want more than a list of services. They want to understand who is organized, who communicates clearly and who can translate the event goal into a practical plan.') . '</p></section>' : null,
            $sourceHtml,
            '<section class="cms-block cms-block-faq">',
            '  <h2>' . $e('Common questions before getting started') . '</h2>',
            $faqHtml,
            '</section>',
            '<section class="cms-block cms-block-cta">',
            '  <h2>' . $e('Ready to talk through the details?') . '</h2>',
            '  <p>' . $e('Tell us what you are planning, where it is happening and what you want the experience to feel like. We will help you turn that into a clearer next step.') . '</p>',
            '  <a href="' . $e($ctaUrl) . '">' . $e($ctaLabel) . '</a>',
            '</section>',
        ]));
    }

    private function appendRequestedAssetsHtml(string $body, array $imagePlan, array $citationPlan, string $keyword, string $location): string
    {
        $append = [];
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        if ($imagePlan !== [] && !str_contains($body, 'data-image-slot=')) {
            $rows = [];
            foreach ($imagePlan as $index => $item) {
                $alt = (string)($item['alt_text'] ?? trim($keyword . ' ' . $location));
                $caption = (string)($item['prompt_brief'] ?? 'Generated image direction');
                $side = $index % 2 === 0 ? 'right' : 'left';
                $text = (string)($item['text_block'] ?? 'The setup, timing, and presentation should make the experience feel organized before guests notice the details. That is where planning, communication, and service flow start to matter.');
                $points = is_array($item['supporting_points'] ?? null) ? $item['supporting_points'] : [];
                $figure = '<figure class="cms-image-placeholder">'
                    . '<div role="img" aria-label="' . $e($alt) . '" data-image-slot="' . $index . '"><span>Image ' . ($index + 1) . '</span></div>'
                    . '<figcaption>' . $e($caption) . '</figcaption>'
                    . '</figure>';
                $pointItems = [];
                foreach ($points as $point) {
                    $point = trim((string)$point);
                    if ($point !== '') {
                        $pointItems[] = '<li>' . $e($point) . '</li>';
                    }
                }
                $copy = '<div class="cms-image-copy"><h3>' . $e((string)($item['heading'] ?? 'A visual moment that supports the page')) . '</h3><p>' . $e($text) . '</p>'
                    . ($pointItems !== [] ? '<ul>' . implode('', $pointItems) . '</ul>' : '')
                    . '</div>';
                $rows[] = '<div class="cms-media-text-row cms-media-' . $side . '">'
                    . ($side === 'left' ? $figure . $copy : $copy . $figure)
                    . '</div>';
            }
            $append[] = '<section class="cms-block cms-block-gallery cms-block-media-text">'
                . '<h2>How the experience comes together</h2>'
                . '<p>These details show how presentation, timing, and service flow can turn a simple plan into a smoother guest experience.</p>'
                . '<div class="cms-media-text-grid">' . implode('', $rows) . '</div>'
                . '</section>';
        }

        if ($citationPlan !== [] && !str_contains(strtolower($body), 'quora') && !str_contains($body, 'cms-block-citations')) {
            $citations = is_array($citationPlan['reviewed_citations'] ?? null) ? $citationPlan['reviewed_citations'] : [];
            $hasReviewedCitations = false;
            if ($citations !== []) {
                $items = [];
                foreach (array_slice($citations, 0, 3) as $citation) {
                    $title = $this->cleanCitationText((string)($citation['title'] ?? 'Reviewed source'), 16);
                    $url = trim((string)($citation['url'] ?? ''));
                    $excerpt = $this->cleanCitationText((string)($citation['excerpt'] ?? ''), 34);
                    $answer = $this->cleanCitationText((string)($citation['answer_excerpt'] ?? ''), 44);
                    $takeaway = $answer !== '' ? $answer : $excerpt;
                    if ($url === '' || $title === '' || $takeaway === '') {
                        continue;
                    }
                    $publisher = $this->cleanCitationText((string)($citation['publisher'] ?? $citation['domain'] ?? parse_url($url, PHP_URL_HOST) ?? ''), 6);
                    $items[] = '<li><strong><a href="' . $e($url) . '" target="_blank" rel="noopener nofollow">' . $e($title) . '</a></strong>'
                        . ($publisher !== '' ? '<p><em>Referenced from ' . $e($publisher) . ':</em> ' . $e($takeaway) . '</p>' : '<p>' . $e($takeaway) . '</p>')
                        . '<p class="cms-source-commentary">' . $e('The useful point for this page is not to copy that source, but to connect the same buyer concern back to timing, expectations and the decision a client is trying to make.') . '</p>'
                        . '</li>';
                }
                if ($items !== []) {
                    $hasReviewedCitations = true;
                    $append[] = '<section class="cms-block cms-block-body cms-block-citations">'
                        . '<h2>What other experts and communities are saying</h2>'
                        . '<p>These outside references helped shape the buyer questions behind this article. The point is not to repeat them, but to bring their most useful signals into a clearer planning recommendation.</p>'
                        . '<ul>' . implode('', $items) . '</ul>'
                        . '</section>';
                }
            }

            if ($hasReviewedCitations) {
                return $this->insertHtmlBeforeFaqOrCta($body, implode("\n", $append));
            }
        }

        return trim($body . "\n" . implode("\n", $append));
    }

    private function appendSourceReferenceImagesHtml(string $body, array $images, string $keyword, string $location): string
    {
        if ($images === [] || str_contains($body, 'cms-block-source-images')) {
            return $body;
        }

        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $topic = trim(implode(' in ', array_filter([$keyword, $location])));
        $rows = [];
        foreach (array_slice($images, 0, 3) as $index => $image) {
            $url = trim((string)($image['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $alt = trim((string)($image['alt'] ?? $topic));
            $caption = trim((string)($image['caption'] ?? 'Local visual reference for the area.'));
            $side = $index % 2 === 0 ? 'right' : 'left';
            $figure = '<figure class="cms-source-image">'
                . '<img src="' . $e($url) . '" alt="' . $e($alt !== '' ? $alt : $topic) . '" loading="lazy">'
                . '<figcaption>' . $e($caption) . '</figcaption>'
                . '</figure>';
            $copy = '<div class="cms-image-copy"><h3>' . $e($index === 0 ? 'A closer look at the area' : 'Local context for the service') . '</h3>'
                . '<p>' . $e('This visual keeps the page grounded in the real place, area or service context instead of introducing an unrelated scene.') . '</p></div>';
            $rows[] = '<div class="cms-media-text-row cms-media-' . $side . '">'
                . ($side === 'left' ? $figure . $copy : $copy . $figure)
                . '</div>';
        }

        if ($rows === []) {
            return $body;
        }

        $html = '<section class="cms-block cms-block-gallery cms-block-source-images cms-block-media-text">'
            . '<h2>' . $e('A look at the local context') . '</h2>'
            . '<p>' . $e('These images support the location page while the copy explains the area and the service coverage clearly.') . '</p>'
            . '<div class="cms-media-text-grid">' . implode('', $rows) . '</div>'
            . '</section>';

        return $this->insertHtmlBeforeFaqOrCta($body, $html);
    }

    private function insertHtmlBeforeFaqOrCta(string $body, string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return trim($body);
        }

        foreach (['cms-block-faq', 'cms-block-cta'] as $className) {
            if (preg_match('/<section\b[^>]*class=["\'][^"\']*' . preg_quote($className, '/') . '[^"\']*["\'][^>]*>/i', $body, $match, PREG_OFFSET_CAPTURE)) {
                $offset = (int)$match[0][1];
                return trim(substr($body, 0, $offset) . "\n" . $html . "\n" . substr($body, $offset));
            }
        }

        return trim($body . "\n" . $html);
    }

    private function insertHtmlBeforeCta(string $body, string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return trim($body);
        }

        if (preg_match('/<section\b[^>]*class=["\'][^"\']*cms-block-cta[^"\']*["\'][^>]*>/i', $body, $match, PREG_OFFSET_CAPTURE)) {
            $offset = (int)$match[0][1];
            return trim(substr($body, 0, $offset) . "\n" . $html . "\n" . substr($body, $offset));
        }

        return trim($body . "\n" . $html);
    }

    private function ensureReadableCtaHtml(string $body): string
    {
        return preg_replace_callback('/<section\b([^>]*class=["\'][^"\']*cms-block-cta[^"\']*["\'][^>]*)>([\s\S]*?)<\/section>/i', function (array $matches): string {
            $attrs = $matches[1];
            $inner = $matches[2];
            $attrs = str_contains(strtolower($attrs), 'style=')
                ? preg_replace('/style=(["\'])(.*?)\1/i', 'style=$1$2; color: #fff;$1', $attrs)
                : $attrs . ' style="color: #fff;"';
            $inner = preg_replace('/<h2\b(?![^>]*style=)([^>]*)>/i', '<h2$1 style="color: #fff;">', $inner) ?? $inner;
            $inner = preg_replace('/<p\b(?![^>]*style=)([^>]*)>/i', '<p$1 style="color: rgba(255,255,255,.86);">', $inner) ?? $inner;

            return '<section ' . trim($attrs) . '>' . $inner . '</section>';
        }, $body) ?? $body;
    }

    private function appendInternalLinksHtml(string $body, array $links): string
    {
        if ($links === [] || str_contains(strtolower($body), 'cms-block-internal-links')) {
            return $body;
        }

        $used = [];
        foreach ($links as $link) {
            $url = trim((string)($link['url'] ?? ''));
            $title = trim((string)($link['title'] ?? ''));
            if ($url === '' || $title === '' || str_contains($body, 'href="' . $url . '"')) {
                continue;
            }
            $used[] = [
                'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            ];
        }

        if ($used === []) {
            return $body;
        }

        $phrases = array_map(
            static fn(array $link): string => '<a href="' . $link['url'] . '">' . $link['title'] . '</a>',
            array_slice($used, 0, 3)
        );
        $sentence = '<p class="cms-inline-link-note">A strong plan often connects the main topic with '
            . implode(', ', $phrases)
            . ' so the reader can move through the decision naturally.</p>';

        if (preg_match('/(<section\b[^>]*class=["\'][^"\']*cms-block-body[^"\']*["\'][^>]*>[\s\S]*?)(<\/section>)/i', $body)) {
            return trim(preg_replace('/(<section\b[^>]*class=["\'][^"\']*cms-block-body[^"\']*["\'][^>]*>[\s\S]*?)(<\/section>)/i', '$1' . $sentence . '$2', $body, 1) ?? $body);
        }

        return trim($body . "\n" . '<section class="cms-block cms-block-body">' . $sentence . '</section>');
    }

    private function appendMapEmbedHtml(string $body, string $location): string
    {
        $location = trim($location);
        if ($location === '' || str_contains(strtolower($body), 'google.com/maps') || str_contains(strtolower($body), 'cms-block-map')) {
            return $body;
        }

        $escapedLocation = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $src = 'https://www.google.com/maps?q=' . rawurlencode($location) . '&output=embed';

        return trim($body . "\n" . '<section class="cms-block cms-block-default cms-block-map">'
            . '<h2>Service area map</h2>'
            . '<p>This map gives visitors a quick sense of the area connected to ' . $escapedLocation . '.</p>'
            . '<iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="' . $escapedLocation . ' map" style="width:100%;min-height:320px;border:0;border-radius:18px;"></iframe>'
            . '</section>');
    }

    private function generateImagesForDraft(object $site, int $contentId, array $imagePlan, User $user): array
    {
        $apiKey = trim((string)($_ENV['OPENAI_TOKEN'] ?? $_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '' || $imagePlan === []) {
            return [];
        }

        $generated = [];
        foreach (array_slice($imagePlan, 0, 3) as $index => $item) {
            $prompt = trim((string)($item['prompt_brief'] ?? 'Editorial brand image'));
            $alt = trim((string)($item['alt_text'] ?? $prompt));
            $image = $this->generateOpenAIImage($apiKey, $prompt);
            if (!$image) {
                continue;
            }

            $saved = $this->saveGeneratedImageFile((string)$site->site_key, $contentId, $index, $image['bytes'], $image['format']);
            if (!$saved) {
                continue;
            }

            $mediaId = $this->repo->registerMedia([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'id_content' => $contentId,
                'related_block_id' => null,
                'cloudinary_public_id' => $saved['public_id'],
                'cloudinary_url' => $saved['url'],
                'secure_url' => $saved['url'],
                'asset_type' => 'image',
                'media_type' => 'image',
                'source_type' => 'ai_generated',
                'usage_type' => (string)($item['usage_type'] ?? ($index === 0 ? 'thumbnail' : 'article_support')),
                'prompt_used' => $prompt,
                'revised_prompt' => $image['revised_prompt'] ?? null,
                'model' => $image['model'],
                'alt_text' => $alt,
                'title_text' => $alt,
                'caption' => $prompt,
                'width' => null,
                'height' => null,
                'format' => $saved['format'],
                'bytes' => strlen($image['bytes']),
                'folder' => $saved['folder'],
                'metadata_json' => [
                    'local_file' => $saved['path'],
                    'ai_disclosure_required' => (string)$site->site_key === 'vnvevents',
                ],
                'created_by' => $user->getId(),
            ]);

            $generated[] = [
                'id' => $mediaId,
                'slot' => $index,
                'secure_url' => $saved['url'],
                'alt_text' => $alt,
                'caption' => $prompt,
            ];
        }

        return $generated;
    }

    private function generateOpenAIImage(string $apiKey, string $prompt): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $model = $_ENV['OPENAI_IMAGE_MODEL'] ?? 'gpt-image-1';
        $payload = [
            'model' => $model,
            'prompt' => $prompt . '. Editorial website image, polished commercial photography feel, no text overlay, no logos, no fake awards.',
            'n' => 1,
            'size' => $_ENV['OPENAI_IMAGE_SIZE'] ?? '1024x1024',
        ];

        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 75,
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($raw, true);
        $item = $decoded['data'][0] ?? null;
        if (!is_array($item)) {
            return null;
        }

        if (!empty($item['b64_json'])) {
            $bytes = base64_decode((string)$item['b64_json'], true);
            if ($bytes !== false) {
                return [
                    'bytes' => $bytes,
                    'format' => 'png',
                    'model' => $model,
                    'revised_prompt' => $item['revised_prompt'] ?? null,
                ];
            }
        }

        if (!empty($item['url'])) {
            $bytes = @file_get_contents((string)$item['url']);
            if ($bytes !== false) {
                return [
                    'bytes' => $bytes,
                    'format' => 'png',
                    'model' => $model,
                    'revised_prompt' => $item['revised_prompt'] ?? null,
                ];
            }
        }

        return null;
    }

    private function saveGeneratedImageFile(string $siteKey, int $contentId, int $index, string $bytes, string $format): ?array
    {
        $format = preg_replace('/[^a-z0-9]/i', '', strtolower($format)) ?: 'png';
        $folder = 'uploads/growth-hub/' . preg_replace('/[^a-z0-9_-]/i', '-', $siteKey) . '/' . date('Y/m');
        $baseDir = dirname(__DIR__, 2) . '/public/' . $folder;
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            return null;
        }

        $filename = 'content-' . $contentId . '-image-' . ($index + 1) . '-' . substr(sha1($bytes), 0, 10) . '.' . $format;
        $path = $baseDir . '/' . $filename;
        if (file_put_contents($path, $bytes) === false) {
            return null;
        }

        $relative = '/' . $folder . '/' . $filename;
        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        $url = $appUrl !== '' ? $appUrl . '/public' . $relative : '/public' . $relative;

        return [
            'path' => $path,
            'url' => $url,
            'folder' => $folder,
            'format' => $format,
            'public_id' => 'local-growth-hub/' . $siteKey . '/' . pathinfo($filename, PATHINFO_FILENAME),
        ];
    }

    private function replaceImagePlaceholdersWithMedia(string $body, array $media): string
    {
        foreach ($media as $item) {
            $slot = (int)($item['slot'] ?? 0);
            $url = (string)($item['secure_url'] ?? '');
            if ($url === '') {
                continue;
            }
            $alt = htmlspecialchars((string)($item['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars((string)($item['caption'] ?? ''), ENT_QUOTES, 'UTF-8');
            $replacement = '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" loading="lazy">';
            $pattern = '/<figure class="cms-image-placeholder">\s*<div role="img" aria-label="[^"]*" data-image-slot="' . $slot . '">[\s\S]*?<\/div>\s*<figcaption>[\s\S]*?<\/figcaption>\s*<\/figure>/i';
            $figure = '<figure class="cms-image-generated">' . $replacement . ($caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '') . '</figure>';
            $body = preg_replace($pattern, $figure, $body, 1) ?? $body;
        }

        return $body;
    }

    private function imagePlanForDraft(object $site, string $title, string $keyword, string $location, int $count): array
    {
        $count = max(0, min(12, $count));
        if ($count <= 0) {
            return [];
        }

        $brandName = (string)($site->site_name ?? $site->site_key ?? 'Brand');
        $topic = trim(implode(' in ', array_filter([$keyword ?: $title, $location])));
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $usage = $i === 1 ? 'thumbnail' : 'article_support';
            $side = ($i - 1) % 2 === 0 ? 'right' : 'left';
            $heading = $i === 1 ? 'A setup that feels organized from the start' : 'Service details that protect the timeline';
            $text = $i === 1
                ? 'The first impression is shaped by how the room, food, signage, and service rhythm come together. A strong plan helps guests move easily, understand their options, and feel that the event was prepared with care.'
                : 'The best events usually depend on quiet operational details: arrival windows, setup flow, replenishment, dietary notes, cleanup, and staff communication. Those details keep the experience polished while the host stays focused on the people in the room.';
            $points = $i === 1
                ? ['Clear presentation for guests and decision-makers.', 'A service flow that matches the space and schedule.', 'A look that supports the brand tone of the event.']
                : ['Preparation details handled before guests arrive.', 'Staffing and timing aligned with the event agenda.', 'Service choices that make the host look prepared.'];
            $items[] = [
                'usage_type' => $usage,
                'layout_side' => $side,
                'width_percent' => 50,
                'prompt_brief' => $brandName . ' ' . $topic . ' editorial image ' . $i,
                'alt_text' => trim($brandName . ' - ' . ($topic !== '' ? $topic : $title)),
                'heading' => $heading,
                'text_block' => $text,
                'supporting_points' => $points,
                'must_register_in_cms_media' => true,
            ];
        }

        return $items;
    }

    private function citationPlanForDraft(string $keyword, string $location, int $count, array $input = []): array
    {
        $count = max(0, min(12, $count));
        $approvedUrls = $this->approvedSourceUrlsFromInput($input);
        if ($approvedUrls !== []) {
            $count = max($count, count($approvedUrls));
        }

        if ($count <= 0 && $approvedUrls === []) {
            return [];
        }

        $queries = array_values(array_filter([
            trim($keyword . ' questions'),
            trim($keyword . ' ' . $location . ' questions'),
            trim($keyword . ' planning guide'),
            trim($keyword . ' checklist'),
            trim($keyword . ' common questions'),
        ]));

        $citations = $approvedUrls !== []
            ? $this->citationsFromApprovedUrls($approvedUrls, $count)
            : [];
        $communityCount = count(array_filter($citations, static fn(array $citation): bool => ($citation['source_type'] ?? '') === 'community'));

        return [
            'requested_count' => $count,
            'queries_to_review' => array_slice(array_values(array_unique($queries)), 0, $count),
            'sources_found' => count($citations),
            'community_sources_used' => $communityCount,
            'reviewed_citations' => $citations,
            'approval_mode' => $approvedUrls !== [] ? 'editor_approved_urls' : 'auto_discovered',
            'approved_urls' => $approvedUrls,
            'rule' => 'Use only fetched citations with a real URL and excerpt. Do not publish search-task placeholders.',
        ];
    }

    private function sourceCandidatesForIdea(object $site, string $keyword, string $location, int $count, array $input = []): array
    {
        $count = max(1, min(8, $count));
        $approvedUrls = $this->approvedSourceUrlsFromInput($input);
        if ($approvedUrls !== []) {
            return $this->citationsFromApprovedUrls($approvedUrls, $count);
        }

        $queries = array_values(array_filter([
            trim($keyword . ' ' . $location . ' guide'),
            trim($keyword . ' ' . $location . ' tips'),
            trim($keyword . ' planning guide'),
            trim($keyword . ' checklist'),
        ]));

        $openAiSources = $this->discoverOpenAIWebSearchCitations($site, $queries, $count);
        if ($openAiSources !== []) {
            return $openAiSources;
        }

        return $this->discoverLiveGoogleCitations($site, $queries, $count);
    }

    private function approvedSourceUrlsFromInput(array $input): array
    {
        $raw = $input['approved_source_urls'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $manualText = implode("\n", [
            (string)($input['approved_source_urls_manual'] ?? ''),
            (string)($input['editor_observations'] ?? ''),
        ]);
        if (preg_match_all('~https?://[^\s<>"\']+~i', $manualText, $matches)) {
            $raw = array_merge($raw, $matches[0]);
        }

        $urls = [];
        foreach ($raw as $url) {
            $url = trim((string)$url);
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            $urls[] = strtok($url, '#') ?: $url;
        }

        return array_slice(array_values(array_unique($urls)), 0, 12);
    }

    private function sourceReferenceImagesForDraft(array $input, string $type, int $limit): array
    {
        $limit = max(0, min(6, $limit));
        if ($limit <= 0 || $type !== 'location' || empty($input['use_source_images'])) {
            return [];
        }

        $urls = $this->approvedSourceUrlsFromInput($input);
        if ($urls === []) {
            return [];
        }

        $images = [];
        $seen = [];
        foreach (array_slice($urls, 0, 3) as $sourceUrl) {
            if ($this->isYoutubeUrl((string)$sourceUrl)) {
                continue;
            }
            $html = $this->fetchPublicHtml((string)$sourceUrl);
            if ($html === '') {
                continue;
            }
            foreach ($this->extractReferenceImagesFromHtml((string)$sourceUrl, $html) as $image) {
                $url = trim((string)($image['url'] ?? ''));
                $key = strtolower(strtok($url, '?') ?: $url);
                if ($url === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $image['source_page'] = (string)$sourceUrl;
                $images[] = $image;
                if (count($images) >= $limit) {
                    return $images;
                }
            }
        }

        return $images;
    }

    private function extractReferenceImagesFromHtml(string $baseUrl, string $html): array
    {
        $images = [];
        $ogImage = $this->extractFirstMatch($html, '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/is')
            ?: $this->extractFirstMatch($html, '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/is');
        if ($ogImage !== '') {
            $url = $this->absoluteUrl($baseUrl, html_entity_decode($ogImage, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($this->isUsefulReferenceImageUrl($url)) {
                $images[] = [
                    'url' => $url,
                    'alt' => 'Local area reference image',
                    'caption' => 'Local visual reference for the area.',
                ];
            }
        }

        if (preg_match_all('/<img\b[^>]*>/is', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                $src = $this->extractFirstMatch($tag, '/\bsrc=["\']([^"\']+)["\']/is')
                    ?: $this->extractFirstMatch($tag, '/\bdata-src=["\']([^"\']+)["\']/is')
                    ?: $this->extractFirstMatch($tag, '/\bdata-lazy-src=["\']([^"\']+)["\']/is');
                if ($src === '') {
                    $srcset = $this->extractFirstMatch($tag, '/\bsrcset=["\']([^"\']+)["\']/is');
                    $src = $srcset !== '' ? trim((string)preg_split('/\s+/', trim(explode(',', $srcset)[0] ?? ''))[0]) : '';
                }
                $url = $src !== '' ? $this->absoluteUrl($baseUrl, html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
                if (!$this->isUsefulReferenceImageUrl($url)) {
                    continue;
                }
                $alt = $this->cleanCitationText($this->extractFirstMatch($tag, '/\balt=["\']([^"\']*)["\']/is'), 14);
                $images[] = [
                    'url' => $url,
                    'alt' => $alt !== '' ? $alt : 'Local area reference image',
                    'caption' => 'Local visual reference for the area.',
                ];
            }
        }

        return $images;
    }

    private function isUsefulReferenceImageUrl(string $url): bool
    {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $lower = strtolower($url);
        if (str_starts_with($lower, 'data:') || str_contains($lower, 'placeholder')) {
            return false;
        }
        foreach (['.svg', '.gif', 'logo', 'icon', 'sprite', 'avatar', 'tracking', 'pixel', 'spacer', 'badge'] as $blocked) {
            if (str_contains($lower, $blocked)) {
                return false;
            }
        }

        return (bool)preg_match('/\.(?:jpe?g|png|webp)(?:[?#].*)?$/i', $lower);
    }

    private function citationsFromApprovedUrls(array $urls, int $count): array
    {
        $citations = [];
        foreach (array_slice($urls, 0, max(1, $count)) as $url) {
            $citation = $this->extractPublicCitation((string)$url);
            if ($citation) {
                $citation['approval_status'] = 'editor_approved';
                $citations[] = $citation;
            }
        }

        return $citations;
    }

    private function discoverWebCitations(array $queries, int $count): array
    {
        if ($count <= 0 || !function_exists('curl_init')) {
            return [];
        }

        $citations = [];
        $seen = [];
        foreach (array_slice($queries, 0, max(1, $count)) as $query) {
            foreach ($this->publicUrlsForQuery((string)$query, max(2, $count - count($citations) + 1)) as $url) {
                $key = strtolower(parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $citation = $this->extractPublicCitation($url);
                if ($citation) {
                    $citations[] = $citation;
                }
                if (count($citations) >= $count) {
                    return $citations;
                }
            }
        }

        return $citations;
    }

    private function discoverOpenAIWebSearchCitations(object $site, array $queries, int $count): array
    {
        $apiKey = trim((string)($_ENV['OPENAI_TOKEN'] ?? $_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '' || $queries === [] || !function_exists('curl_init')) {
            return [];
        }

        $query = trim((string)$queries[0]);
        if ($query === '') {
            return [];
        }

        $payload = [
            'model' => $_ENV['OPENAI_WEB_SEARCH_MODEL'] ?? $_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini',
            'tools' => [[
                'type' => 'web_search',
                'external_web_access' => true,
                'user_location' => [
                    'type' => 'approximate',
                    'country' => 'US',
                    'region' => 'Florida',
                    'timezone' => 'America/New_York',
                ],
            ]],
            'tool_choice' => 'auto',
            'include' => ['web_search_call.action.sources'],
            'input' => 'Search the web for useful articles, guides, or YouTube videos with transcripts about: "' . $query . '". Return only sources that could help rewrite an original article for ' . (string)$site->site_name . '.',
        ];

        $response = $this->callOpenAIResponses($apiKey, $payload);
        if (!$response) {
            return [];
        }

        $urls = $this->openAIResponseSourceUrls($response);
        if ($urls === []) {
            return [];
        }

        $citations = [];
        foreach (array_slice($urls, 0, $count * 2) as $url) {
            $citation = $this->extractPublicCitation($url);
            if ($citation) {
                $citation['search_source'] = 'openai_web_search';
                $citations[] = $citation;
            }
            if (count($citations) >= $count) {
                break;
            }
        }

        return $citations;
    }

    private function callOpenAIResponses(string $apiKey, array $payload): ?array
    {
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 45,
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string)$raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function openAIResponseSourceUrls(array $response): array
    {
        $urls = [];
        $walk = static function ($value) use (&$walk, &$urls): void {
            if (is_array($value)) {
                if (!empty($value['url']) && is_string($value['url'])) {
                    $urls[] = $value['url'];
                }
                foreach ($value as $child) {
                    $walk($child);
                }
            }
        };
        $walk($response['output'] ?? $response);

        $clean = [];
        foreach ($urls as $url) {
            $url = trim((string)$url);
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $clean[] = strtok($url, '#') ?: $url;
            }
        }

        return array_values(array_unique($clean));
    }

    private function discoverLiveGoogleCitations(object $site, array $queries, int $count): array
    {
        if ($count <= 0 || !function_exists('curl_init')) {
            return [];
        }

        $provider = null;
        if (($_ENV['SERP_ENABLED'] ?? 'false') === 'true') {
            try {
                $provider = new DataForSEOSerpProvider();
            } catch (\Throwable $e) {
                $provider = null;
            }
        }

        if (!$provider) {
            return [];
        }

        $citations = [];
        $seen = [];
        foreach (array_slice($queries, 0, max(1, $count)) as $query) {
            $urls = $this->liveGoogleUrlsForQuery($provider, (string)$query, $count);
            foreach ($urls as $row) {
                $url = trim((string)($row['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $key = strtolower((string)parse_url($url, PHP_URL_HOST) . (string)parse_url($url, PHP_URL_PATH));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $citation = $this->extractPublicCitation($url);
                if ($citation) {
                    $citation['search_source'] = 'live_google_serp';
                    $citation['google_position'] = (int)($row['position'] ?? 0);
                    $citations[] = $citation;
                }
                if (count($citations) >= $count) {
                    return $citations;
                }
            }
        }

        return $citations;
    }

    private function liveGoogleUrlsForQuery(DataForSEOSerpProvider $provider, string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $created = $provider->createTask([
                'keyword' => $query,
                'location_name' => trim((string)($_ENV['SERP_DEFAULT_LOCATION'] ?? 'Miami, Florida, United States')),
                'language_code' => trim((string)($_ENV['SERP_DEFAULT_LANGUAGE'] ?? 'en')),
                'device' => trim((string)($_ENV['SERP_DEFAULT_DEVICE'] ?? 'desktop')),
                'depth' => max(5, min(20, $limit + 5)),
            ]);
            $taskId = (string)($created['tasks'][0]['id'] ?? '');
            if ($taskId === '') {
                return [];
            }

            $attempts = max(2, (int)($_ENV['SERP_FETCH_ATTEMPTS'] ?? 6));
            $sleepSeconds = max(1, (int)($_ENV['SERP_FETCH_SLEEP_SECONDS'] ?? 2));
            $items = [];
            for ($i = 0; $i < $attempts; $i++) {
                if ($i > 0) {
                    sleep($sleepSeconds);
                }
                $fetched = $provider->fetchTask($taskId);
                $items = $fetched['tasks'][0]['result'][0]['items'] ?? [];
                if (is_array($items) && $items !== []) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        $rows = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'organic') {
                continue;
            }
            $url = trim((string)($item['url'] ?? ''));
            $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
            if ($url === '' || !$this->isUsefulCitationHost($host)) {
                continue;
            }
            $rows[] = [
                'url' => strtok($url, '#') ?: $url,
                'position' => (int)($item['rank_group'] ?? $item['rank_absolute'] ?? count($rows) + 1),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function publicUrlsForQuery(string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $html = $this->fetchPublicHtml('https://duckduckgo.com/html/?q=' . rawurlencode($query));
        if ($html === '') {
            return [];
        }

        $urls = [];
        if (preg_match_all('/<a[^>]+class=["\'][^"\']*result__a[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $url = html_entity_decode((string)$href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_contains($url, 'uddg=')) {
                    $parts = parse_url($url);
                    parse_str((string)($parts['query'] ?? ''), $params);
                    $url = (string)($params['uddg'] ?? $url);
                }
                $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
                if ($this->isUsefulCitationHost($host)) {
                    $urls[] = strtok($url, '#') ?: $url;
                }
                if (count($urls) >= $limit) {
                    break;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function isUsefulCitationHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        foreach (['google.', 'duckduckgo.', 'bing.', 'facebook.', 'instagram.', 'pinterest.', 'tiktok.', 'linkedin.', 'x.com', 'twitter.'] as $blocked) {
            if (str_contains($host, $blocked)) {
                return false;
            }
        }

        return true;
    }

    private function extractPublicCitation(string $url): ?array
    {
        if ($this->isYoutubeUrl($url)) {
            return $this->extractYoutubeCitation($url);
        }

        $html = $this->fetchPublicHtml($url);
        if ($html === '') {
            return null;
        }

        $title = $this->extractFirstMatch($html, '/<title[^>]*>(.*?)<\/title>/is');
        $description = $this->extractFirstMatch($html, '/<meta[^>]+(?:name|property)=["\'](?:description|og:description)["\'][^>]+content=["\']([^"\']+)["\']/is');
        $paragraphs = $this->extractMatches($html, '/<p[^>]*>(.*?)<\/p>/is');
        $readableText = $this->readableTextFromParagraphs($paragraphs, 2600);
        $answer = '';
        foreach ($paragraphs as $paragraph) {
            if (str_word_count($paragraph) >= 14) {
                $answer = $paragraph;
                break;
            }
        }

        $excerpt = $this->shortExcerpt($description !== '' ? $description : $answer, 34);
        $answerExcerpt = $this->shortExcerpt($answer, 36);
        if ($title === '' || ($excerpt === '' && $answerExcerpt === '')) {
            return null;
        }

        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        $sourceType = $this->isCommunityCitationHost($host) ? 'community' : 'reference';
        return [
            'source' => $host,
            'source_type' => $sourceType,
            'publisher' => $host,
            'domain' => $host,
            'title' => preg_replace('/\s*[-|]\s*[^-|]{2,60}$/', '', $title) ?: $title,
            'url' => $url,
            'excerpt' => $excerpt,
            'answer_excerpt' => $answerExcerpt !== $excerpt ? $answerExcerpt : '',
            'research_text' => $readableText,
            'research_text_source' => 'article_html',
            'published_date' => $this->extractFirstMatch($html, '/<meta[^>]+(?:name|property)=["\'](?:article:published_time|datePublished|date)["\'][^>]+content=["\']([^"\']+)["\']/is') ?: null,
            'fetched_at' => date('c'),
        ];
    }

    private function isYoutubeUrl(string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));

        return str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be');
    }

    private function extractYoutubeCitation(string $url): ?array
    {
        $videoId = $this->youtubeVideoId($url);
        if ($videoId === '') {
            return null;
        }

        $watchUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
        $html = $this->fetchPublicHtml($watchUrl);
        $title = $this->extractFirstMatch($html, '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/is')
            ?: $this->extractFirstMatch($html, '/<title[^>]*>(.*?)<\/title>/is');
        $description = $this->extractFirstMatch($html, '/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']/is');
        $transcript = $this->youtubeTranscript($videoId);
        if ($transcript === '' || str_word_count($transcript) < 80) {
            return null;
        }

        $host = strtolower((string)(parse_url($watchUrl, PHP_URL_HOST) ?: 'youtube.com'));

        return [
            'source' => $host,
            'source_type' => 'video_transcript',
            'publisher' => $host,
            'domain' => $host,
            'title' => $this->normalizeTitle($title !== '' ? $title : 'YouTube video transcript'),
            'url' => $watchUrl,
            'excerpt' => $this->shortExcerpt($description !== '' ? $description : $transcript, 34),
            'answer_excerpt' => $this->shortExcerpt($transcript, 42),
            'research_text' => $this->shortExcerpt($transcript, 2600),
            'research_text_source' => 'youtube_transcript',
            'published_date' => null,
            'fetched_at' => date('c'),
        ];
    }

    private function youtubeVideoId(string $url): string
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        $path = trim((string)(parse_url($url, PHP_URL_PATH) ?: ''), '/');
        if (str_contains($host, 'youtu.be')) {
            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $path) ? $path : '';
        }

        parse_str((string)(parse_url($url, PHP_URL_QUERY) ?: ''), $query);
        $id = (string)($query['v'] ?? '');
        if ($id === '' && preg_match('~(?:shorts|embed)/([A-Za-z0-9_-]{6,20})~', $path, $match)) {
            $id = (string)$match[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : '';
    }

    private function youtubeTranscript(string $videoId): string
    {
        $listXml = $this->fetchPublicHtml('https://www.youtube.com/api/timedtext?type=list&v=' . rawurlencode($videoId));
        if ($listXml === '') {
            return '';
        }

        $tracks = [];
        if (preg_match_all('/<track\b[^>]*>/i', $listXml, $matches)) {
            foreach ($matches[0] as $track) {
                $lang = $this->extractFirstMatch($track, '/lang_code=["\']([^"\']+)["\']/i');
                $name = $this->extractFirstMatch($track, '/name=["\']([^"\']*)["\']/i');
                if ($lang !== '') {
                    $tracks[] = ['lang' => $lang, 'name' => html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
                }
            }
        }

        if ($tracks === []) {
            return '';
        }

        usort($tracks, static function (array $a, array $b): int {
            $priority = static fn(string $lang): int => str_starts_with($lang, 'en') ? 0 : (str_starts_with($lang, 'es') ? 1 : 2);
            return $priority($a['lang']) <=> $priority($b['lang']);
        });

        foreach ($tracks as $track) {
            $query = [
                'v' => $videoId,
                'lang' => $track['lang'],
                'fmt' => 'json3',
            ];
            if ($track['name'] !== '') {
                $query['name'] = $track['name'];
            }
            $json = $this->fetchPublicHtml('https://www.youtube.com/api/timedtext?' . http_build_query($query));
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $chunks = [];
                foreach (($decoded['events'] ?? []) as $event) {
                    foreach (($event['segs'] ?? []) as $seg) {
                        $chunks[] = (string)($seg['utf8'] ?? '');
                    }
                }
                $text = $this->normalizeTranscriptText(implode(' ', $chunks));
                if ($text !== '') {
                    return $text;
                }
            }

            $xml = $this->fetchPublicHtml('https://www.youtube.com/api/timedtext?' . http_build_query(array_diff_key($query, ['fmt' => true])));
            if (preg_match_all('/<text[^>]*>(.*?)<\/text>/is', $xml, $xmlMatches)) {
                $text = $this->normalizeTranscriptText(implode(' ', array_map(
                    static fn(string $chunk): string => html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    $xmlMatches[1]
                )));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function normalizeTranscriptText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function readableTextFromParagraphs(array $paragraphs, int $wordLimit): string
    {
        $clean = [];
        foreach (array_slice($paragraphs, 0, 80) as $paragraph) {
            $value = $this->cleanCitationText((string)$paragraph, 80);
            if ($value !== '') {
                $clean[] = $value;
            }
        }
        $text = $this->normalizeTitle(implode(' ', $clean));
        if ($text === '') {
            return '';
        }

        return $this->shortExcerpt($text, $wordLimit);
    }

    private function isCommunityCitationHost(string $host): bool
    {
        foreach (['quora.com', 'reddit.com', 'stackexchange.com', 'stackoverflow.com', 'forum', 'boards.'] as $communityHost) {
            if (str_contains($host, $communityHost)) {
                return true;
            }
        }

        return false;
    }

    private function shortExcerpt(string $text, int $wordLimit): string
    {
        $text = $this->cleanCitationText($text, $wordLimit * 2);
        if ($text === '') {
            return '';
        }

        $words = preg_split('/\s+/', $text) ?: [];
        if (count($words) <= $wordLimit) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
    }

    private function cleanCitationText(string $text, int $wordLimit = 40): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/```[\s\S]*?```/', ' ', $text) ?? $text;
        $text = preg_replace('/<style[\s\S]*?<\/style>|<script[\s\S]*?<\/script>/i', ' ', $text) ?? $text;
        $text = preg_replace('/\.[a-z0-9_-]+\s*\{[^}]+\}/i', ' ', $text) ?? $text;
        $text = preg_replace('/@[a-z-]+\s*[^{]*\{[^}]+\}/i', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:display|position|background|transform|font-size|font-weight|z-index|height|width|padding|margin|color|text-decoration|webkit-[a-z-]+)\s*:[^;\s]+;?/i', ' ', $text) ?? $text;
        $text = $this->normalizeTitle($text);
        $lower = strtolower($text);

        if (
            $text === ''
            || str_contains($lower, 'once you have logged in refresh the page')
            || str_contains($lower, 'styled')
            || str_contains($lower, 'webkit')
            || substr_count($text, ';') > 5
            || substr_count($text, '{') > 1
            || substr_count($text, '}') > 1
        ) {
            return '';
        }

        $words = preg_split('/\s+/', $text) ?: [];
        if ($wordLimit > 0 && count($words) > $wordLimit) {
            return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
        }

        return implode(' ', $words);
    }

    private function internalLinksForDraft(object $site, string $keyword, string $location = '', string $service = ''): array
    {
        $candidates = $this->repo->internalLinkCandidates((int)$site->id_owner, (string)$site->site_key, 18);
        $terms = array_filter(array_map('strtolower', [
            $keyword,
            $location,
            $service,
            strtok($keyword, ' ') ?: '',
        ]));
        $baseUrl = rtrim((string)($site->public_base_url ?? ''), '/');
        $links = [];
        $links = array_merge($links, $this->configuredOfferInternalLinks($site, $terms, $baseUrl));

        foreach ($candidates as $content) {
            $haystack = strtolower(trim(implode(' ', [
                (string)($content->title ?? ''),
                (string)($content->content_type ?? ''),
                (string)($content->primary_keyword ?? ''),
                (string)($content->target_location ?? ''),
            ])));
            $score = 0;
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $score += strlen($term) > 6 ? 2 : 1;
                }
            }
            if ($score <= 0 && count($links) >= 5) {
                continue;
            }

            $route = (string)($content->route ?? '');
            $url = (string)($content->canonical_url ?? '');
            if ($url === '' && $route !== '') {
                $url = $baseUrl !== '' ? $baseUrl . $route : $route;
            }
            if ($url === '') {
                continue;
            }

            $links[] = [
                'title' => (string)($content->title ?? ''),
                'url' => $url,
                'route' => $route,
                'content_type' => (string)($content->content_type ?? ''),
                'reason' => $score > 0 ? 'Related by service, keyword or location' : 'Recent published page',
                'score' => $score,
            ];
        }

        if (count($links) < 3) {
            $links = array_merge($links, $this->productionInternalLinksForDraft($site, $terms, 8));
        }

        $deduped = [];
        foreach ($links as $link) {
            $url = strtolower((string)($link['url'] ?? ''));
            if ($url === '' || isset($deduped[$url])) {
                continue;
            }
            $deduped[$url] = $link;
        }

        $links = array_values($deduped);
        usort($links, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['title'], $b['title']));

        return array_slice($links, 0, 8);
    }

    private function productionInternalLinksForDraft(object $site, array $terms, int $limit): array
    {
        $baseUrl = rtrim((string)($site->public_base_url ?? ''), '/');
        if ($baseUrl === '') {
            return [];
        }

        $host = strtolower((string)parse_url($baseUrl, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return [];
        }

        $html = $this->fetchPublicHtml($baseUrl);
        if ($html === '') {
            return [];
        }

        $links = [];
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href = html_entity_decode((string)$match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = $this->normalizeTitle(strip_tags(html_entity_decode((string)$match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($href === '' || $text === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }
                $url = $this->absoluteUrl($baseUrl, $href);
                if ($url === '' || strtolower((string)parse_url($url, PHP_URL_HOST)) !== $host) {
                    continue;
                }

                $haystack = strtolower($text . ' ' . (parse_url($url, PHP_URL_PATH) ?: ''));
                $score = 0;
                foreach ($terms as $term) {
                    if ($term !== '' && str_contains($haystack, $term)) {
                        $score += strlen($term) > 6 ? 2 : 1;
                    }
                }

                $links[] = [
                    'title' => $text,
                    'url' => strtok($url, '#') ?: $url,
                    'route' => (string)(parse_url($url, PHP_URL_PATH) ?: ''),
                    'content_type' => 'production',
                    'reason' => $score > 0 ? 'Matched public site service or location link' : 'Existing public site link',
                    'score' => $score,
                ];

                if (count($links) >= $limit) {
                    break;
                }
            }
        }

        return $links;
    }

    private function configuredOfferInternalLinks(object $site, array $terms, string $baseUrl): array
    {
        $configured = array_merge(
            $this->offerLinks($this->decodeJsonField($site->main_services ?? []), $baseUrl),
            $this->offerLinks($this->decodeJsonField($site->main_products ?? []), $baseUrl)
        );
        $links = [];

        foreach ($configured as $item) {
            $title = $this->normalizeTitle((string)($item['title'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }

            $haystack = strtolower($title . ' ' . (parse_url($url, PHP_URL_PATH) ?: ''));
            $score = 1;
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $score += strlen($term) > 6 ? 3 : 1;
                }
            }

            $links[] = [
                'title' => $title,
                'url' => $url,
                'route' => (string)(parse_url($url, PHP_URL_PATH) ?: ''),
                'content_type' => 'configured_offer',
                'reason' => 'Configured service/product link',
                'score' => $score,
            ];
        }

        return $links;
    }

    private function absoluteUrl(string $baseUrl, string $href): string
    {
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $href;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        if ($host === '') {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }

        $path = rtrim(dirname((string)($base['path'] ?? '/')), '/\\');
        return $scheme . '://' . $host . ($path !== '' ? $path : '') . '/' . ltrim($href, '/');
    }

    private function titleFromPlan(string $type, string $keyword, string $location, string $brand): string
    {
        if ($keyword !== '' && $location !== '') {
            return ucwords($keyword) . ' in ' . $location;
        }

        if ($keyword !== '') {
            return ucwords($keyword);
        }

        if ($location !== '') {
            return ($type === 'location' ? 'Local Guide: ' : 'Growth Topic: ') . $location;
        }

        return 'Growth Hub Draft for ' . $brand;
    }

    private function metaFromPlan(string $title, string $keyword, string $location): string
    {
        $parts = [$title];
        if ($keyword !== '') {
            $parts[] = 'focused on ' . $keyword;
        }
        if ($location !== '') {
            $parts[] = 'for ' . $location;
        }

        return $this->normalizeMetaDescription(implode(' ', $parts), $title);
    }

    private function normalizeMetaDescription(string $value, string $fallbackTitle): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($value)));
        if ($text === '') {
            $text = $fallbackTitle;
        }

        if (strlen($text) <= 155) {
            return $text;
        }

        return rtrim(substr($text, 0, 152), " \t\n\r\0\x0B.,;:") . '...';
    }

    private function schemaForContent(object $content, ?object $site, string $canonical, string $title, string $description, array $blocks = []): array
    {
        $baseUrl = rtrim((string)($site->public_base_url ?? $canonical), '/');
        $siteName = (string)($site->site_name ?? 'Ophyra Growth Hub');
        $entityType = match ($this->normalizeContentType((string)$content->content_type)) {
            'location', 'landing', 'page' => 'Service',
            'blog' => 'Article',
            default => 'WebPage',
        };

        $entity = [
            '@type' => $entityType,
            '@id' => rtrim($canonical, '/') . '#' . strtolower($entityType),
            'url' => $canonical,
            'name' => $title,
            'headline' => $title,
            'description' => $description,
            'inLanguage' => $site->default_language ?? 'en',
            'keywords' => array_values(array_filter([
                $content->primary_keyword ?? null,
                $content->target_location ?? null,
                $content->content_type ?? null,
            ])),
            'publisher' => ['@id' => $baseUrl . '#organization'],
            'datePublished' => $content->published_at ?? null,
            'dateModified' => $content->updated_at ?? $content->published_at ?? null,
        ];

        if (!empty($content->target_location)) {
            $entity['areaServed'] = [
                '@type' => 'Place',
                'name' => $content->target_location,
            ];
        }

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $baseUrl . '#organization',
                'name' => $siteName,
                'url' => $baseUrl,
            ],
            [
                '@type' => 'WebSite',
                '@id' => $baseUrl . '#website',
                'name' => $siteName,
                'url' => $baseUrl,
                'publisher' => ['@id' => $baseUrl . '#organization'],
            ],
            [
                '@type' => 'WebPage',
                '@id' => rtrim($canonical, '/') . '#webpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description,
                'isPartOf' => ['@id' => $baseUrl . '#website'],
                'about' => ['@id' => rtrim($canonical, '/') . '#' . strtolower($entityType)],
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => rtrim($canonical, '/') . '#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $baseUrl . '/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $title,
                        'item' => $canonical,
                    ],
                ],
            ],
            array_filter($entity, static fn(mixed $value): bool => $value !== null && $value !== ''),
        ];

        $faqItems = $this->faqSchemaItems($blocks);
        if ($faqItems !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => rtrim($canonical, '/') . '#faq',
                'mainEntity' => $faqItems,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    private function faqSchemaItems(array $blocks): array
    {
        $items = [];
        foreach ($blocks as $block) {
            if ((string)($block->block_type ?? '') !== 'faq') {
                continue;
            }
            $data = $this->decodeJsonField($block->data_json ?? []);
            foreach (($data['items'] ?? []) as $item) {
                $question = trim((string)($item['question'] ?? ''));
                $answer = trim(strip_tags((string)($item['answer'] ?? '')));
                if ($question === '' || $answer === '') {
                    continue;
                }
                $items[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $answer,
                    ],
                ];
            }
        }

        return $items;
    }

    private function qualityAudit(object $content, array $metadata, array $schema, string $metaDescription): array
    {
        $checks = [
            'has_title' => trim((string)$content->title) !== '',
            'has_meta_description' => $metaDescription !== '',
            'meta_description_length_ok' => strlen($metaDescription) >= 70 && strlen($metaDescription) <= 160,
            'has_primary_keyword' => trim((string)($content->primary_keyword ?? '')) !== '',
            'has_body' => strlen(trim(strip_tags((string)($content->body ?? '')))) >= 300,
            'has_schema' => $schema !== [],
            'has_research_plan' => !empty($metadata['research_plan']),
        ];
        $passed = count(array_filter($checks));
        $score = (int)round(($passed / max(1, count($checks))) * 100);

        return [
            'score' => $score,
            'checks' => $checks,
            'needs_review' => $score < 80,
        ];
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function notifyReceiverSitemapRefresh(object $site, ?int $contentId, string $event): void
    {
        $settings = $this->decodeJsonField($site->sitemap_settings ?? []);
        $environment = strtolower(trim((string)($settings['environment'] ?? 'development')));
        if (!in_array($environment, ['development', 'production'], true)) {
            $environment = 'development';
        }

        $content = $contentId ? $this->repo->contentForAdmin((int)$site->id_owner, (string)$site->site_key, $contentId) : null;
        $baseUrl = rtrim((string)($site->public_base_url ?? ''), '/');
        $route = $content ? $this->normalizePublicRoute((string)($content->route ?: $this->routeForType((string)$content->content_type, (string)$content->slug))) : null;
        $canonical = $content && $route
            ? (string)($content->canonical_url ?: ($baseUrl . $route))
            : null;
        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');

        $payload = [
            'event' => $event,
            'site_key' => (string)$site->site_key,
            'site_name' => (string)$site->site_name,
            'environment' => $environment,
            'content_id' => $contentId,
            'route' => $route,
            'canonical_url' => $canonical,
            'public_base_url' => $baseUrl,
            'sitemap_url' => (string)($settings['sitemap_url'] ?? ($baseUrl !== '' ? $baseUrl . '/sitemap.xml' : '')),
            'ophyra_sitemap_url' => $appUrl !== '' ? $appUrl . '/api/growth-hub/sitemap?site_key=' . rawurlencode((string)$site->site_key) : null,
            'ophyra_routes_url' => $appUrl !== '' ? $appUrl . '/api/growth-hub/routes?site_key=' . rawurlencode((string)$site->site_key) : null,
            'ophyra_content_url' => $appUrl !== '' && $route ? $appUrl . '/api/growth-hub/content?site_key=' . rawurlencode((string)$site->site_key) . '&route=' . rawurlencode($route) : null,
            'changed_at' => date('c'),
        ];

        if ($environment !== 'production') {
            $this->repo->createAgentRun([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'run_type' => 'receiver_sitemap_refresh',
                'status' => 'SKIPPED',
                'input_json' => $payload,
                'output_json' => ['reason' => 'Site publishing environment is development. Receiver sitemap refresh is production-only.'],
                'summary' => 'Skipped receiver sitemap refresh for development site.',
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $endpoint = $this->normalizeOptionalUrl((string)($settings['receiver_sitemap_endpoint'] ?? ''));
        if ($endpoint === '') {
            $this->repo->createAgentRun([
                'id_owner' => (int)$site->id_owner,
                'site_key' => (string)$site->site_key,
                'run_type' => 'receiver_sitemap_refresh',
                'status' => 'FAILED',
                'input_json' => $payload,
                'output_json' => ['reason' => 'Production site has no receiver_sitemap_endpoint configured.'],
                'summary' => 'Receiver sitemap endpoint is missing for production publishing.',
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $startedAt = date('Y-m-d H:i:s');
        $result = $this->postReceiverSitemapRefresh($endpoint, trim((string)($settings['receiver_sitemap_token'] ?? '')), $payload);
        $ok = (bool)($result['ok'] ?? false);

        $this->repo->createAgentRun([
            'id_owner' => (int)$site->id_owner,
            'site_key' => (string)$site->site_key,
            'run_type' => 'receiver_sitemap_refresh',
            'status' => $ok ? 'COMPLETED' : 'FAILED',
            'input_json' => $payload + ['endpoint' => $endpoint],
            'output_json' => $result,
            'summary' => $ok
                ? 'Receiver sitemap refresh accepted by brand site.'
                : 'Receiver sitemap refresh failed or was rejected by brand site.',
            'started_at' => $startedAt,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function postReceiverSitemapRefresh(string $endpoint, string $token, array $payload): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status_code' => 0, 'error' => 'cURL is not available.'];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Ophyra-Growth-Hub-Event: ' . (string)($payload['event'] ?? 'sitemap_refresh'),
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return [
            'ok' => $status >= 200 && $status < 300,
            'status_code' => $status,
            'response' => is_array($decoded) ? $decoded : (is_string($raw) ? substr($raw, 0, 500) : null),
            'error' => $error ?: null,
        ];
    }

    private function parseListField(string $value): array
    {
        $items = preg_split('/[\r\n,]+/', $value) ?: [];
        $clean = [];

        foreach ($items as $item) {
            $parsed = $this->parseOfferItem(trim($item));
            if ($parsed === null) {
                continue;
            }

            $key = strtolower((string)($parsed['label'] ?? $parsed));
            if (!isset($clean[$key])) {
                $clean[$key] = $parsed;
            }
        }

        return array_values($clean);
    }

    private function parseOfferRows(array|string $labels, array|string $urls): array
    {
        $labels = is_array($labels) ? $labels : [$labels];
        $urls = is_array($urls) ? $urls : [$urls];
        $clean = [];

        foreach ($labels as $index => $rawLabel) {
            $label = $this->normalizeTitle((string)$rawLabel);
            $url = trim((string)($urls[$index] ?? ''));
            if ($label === '') {
                continue;
            }

            $parsed = $url !== ''
                ? ['label' => $label, 'url' => $url, 'backlink_url' => $url]
                : ['label' => $label];
            $key = strtolower($label);
            if (!isset($clean[$key])) {
                $clean[$key] = $parsed;
            }
        }

        return array_values($clean);
    }

    private function parseOfferItem(string $item): array|string|null
    {
        if ($item === '') {
            return null;
        }

        $parts = preg_split('/\s*(?:\||=>)\s*/', $item, 2) ?: [];
        $label = $this->normalizeTitle((string)($parts[0] ?? ''));
        $url = trim((string)($parts[1] ?? ''));
        if ($label === '') {
            return null;
        }

        if ($url === '') {
            return $label;
        }

        return [
            'label' => $label,
            'url' => $url,
        ];
    }

    private function offerLabels(array $items): array
    {
        $labels = [];
        foreach ($items as $item) {
            $label = is_array($item) ? (string)($item['label'] ?? $item['name'] ?? $item['title'] ?? '') : (string)$item;
            $label = $this->normalizeTitle($label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    private function offerEntries(array $items): array
    {
        $entries = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $label = $this->normalizeTitle((string)($item['label'] ?? $item['name'] ?? $item['title'] ?? ''));
                $url = trim((string)($item['backlink_url'] ?? $item['url'] ?? $item['href'] ?? ''));
            } else {
                $label = $this->normalizeTitle((string)$item);
                $url = '';
            }

            if ($label !== '') {
                $entries[] = [
                    'label' => $label,
                    'url' => $url,
                ];
            }
        }

        return $entries;
    }

    private function offerLines(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $label = $this->normalizeTitle((string)($item['label'] ?? $item['name'] ?? $item['title'] ?? ''));
                $url = trim((string)($item['url'] ?? $item['href'] ?? ''));
                if ($label !== '') {
                    $lines[] = $url !== '' ? $label . ' | ' . $url : $label;
                }
                continue;
            }

            $label = $this->normalizeTitle((string)$item);
            if ($label !== '') {
                $lines[] = $label;
            }
        }

        return array_values(array_unique($lines));
    }

    private function offerLinks(array $items, string $baseUrl = ''): array
    {
        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = $this->normalizeTitle((string)($item['label'] ?? $item['name'] ?? $item['title'] ?? ''));
            $url = trim((string)($item['backlink_url'] ?? $item['url'] ?? $item['href'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            if (str_starts_with($url, '/') && $baseUrl !== '') {
                $url = rtrim($baseUrl, '/') . $url;
            }

            $links[] = [
                'title' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }

    private function requireSite(string $siteKey): object
    {
        $site = $this->repo->getSiteByKey($siteKey);
        if (!$site) {
            throw new Exception('Growth Hub site is not configured.');
        }

        return $site;
    }

    private function routeForType(string $type, string $slug): string
    {
        $type = $this->normalizeContentType($type);
        $route = match ($type) {
            'page' => '/' . $slug,
            'blog' => '/blog/' . $slug,
            'location' => '/locations/' . $slug,
            default => '/' . $slug,
        };

        return $this->normalizePublicRoute($route);
    }

    private function normalizeContentType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'page', 'location', 'blog' => $type,
            'landing', 'service' => 'page',
            'guide', 'case_study', 'comparison', 'faq_page' => 'blog',
            default => 'page',
        };
    }

    private function normalizePublicRoute(string $route): string
    {
        $route = '/' . trim($route, '/');
        return $route === '/' ? '/' : rtrim($route, '/') . '/';
    }

    private function uniqueSlug(object $site, string $value, ?int $currentContentId = null): string
    {
        $base = $this->slugify($value);
        $slug = $base;
        $suffix = 2;

        while (!$this->repo->isContentSlugAvailable((int)$site->id_owner, (string)$site->site_key, $slug, $currentContentId)) {
            $slug = substr($base, 0, 190) . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function uniqueSlugForRoute(object $site, string $type, string $slug, ?int $currentContentId = null): string
    {
        $base = $this->slugify($slug);
        $candidate = $base;
        $suffix = 2;

        while (!$this->repo->isRouteAvailable((int)$site->id_owner, (string)$site->site_key, $this->routeForType($type, $candidate), $currentContentId)) {
            $candidate = substr($base, 0, 185) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $value = $this->normalizeTitle($value);
        $value = str_replace('&', ' and ', $value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? substr($value, 0, 190) : 'content-' . date('YmdHis');
    }

    private function normalizeTitle(string $value): string
    {
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }
        $value = preg_replace('/&#x([0-9a-f]+);/i', ' ', $value) ?? $value;
        $value = preg_replace('/&#([0-9]+);/', ' ', $value) ?? $value;
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = str_replace(["\xEF\xBF\xBD", '�'], '', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^https:://#i', 'https://', $value);
        $value = preg_replace('#^http:://#i', 'http://', $value);

        if (!preg_match('#^https?://#i', $value)) {
            $value = preg_match('#^(localhost|127\.0\.0\.1)(/|:|$)#i', $value)
                ? 'http://' . $value
                : 'https://' . $value;
        }

        return rtrim($value, '/');
    }

    private function normalizeOptionalUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return $this->normalizeBaseUrl($value);
    }

    private function domainFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $host = parse_url(str_starts_with($url, 'http') ? $url : 'https://' . $url, PHP_URL_HOST);
        $domain = strtolower((string)($host ?: $url));

        return preg_replace('/^www\./', '', $domain) ?: null;
    }
}
