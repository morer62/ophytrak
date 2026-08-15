# Ophyra Growth Hub

Ophyra Growth Hub is a level 1 administration module for centralized CMS, SEO, media and public content delivery for owned sites.

It is not just a page builder. It is the control room for deciding what content should exist, why it should exist, how it should be generated, and which public site should consume it.

## Scope

Growth Hub uses `id_owner + site_key` as the logical scope. It must not use `id_user_business` as the primary CMS/SEO scope.

Initial content sites:

- `id_owner=2`, `site_key=vnvevents`, domain `vnvevents.com`
- `id_owner=2`, `site_key=avomeal`, domain `avomeal.com`
- `id_owner=2`, `site_key=jonnysmedia`, domain `jonnys.media`

Ophyra itself is the platform/admin system. `ophyra.com` is not a required Search Console target for this Growth Hub content workflow.

Routes:

- `/panel/growth-hub`
- `/panel/growth-hub?site_key=vnvevents`
- `/panel/growth-hub?site_key=avomeal`
- `/panel/growth-hub?site_key=jonnysmedia`

For the product-level explanation of how the CMS should feel to an admin user, read:

- `docs/CMS_SIMPLE_MODEL.md`

## Panel Map

Current panel sections:

- Site settings: domain, public base URL and sitemap settings per `site_key`.
- Opportunity Discovery: services/offers, Search Console imports, Search Console page/query opportunities and SERP validation.
- SERP research status: DataForSEO task state, saved rows and last safe error.
- Research Lab: selected keyword + selected market, internal competitor gap signals and future Trends/SERP enrichment.
- Target locations: active markets used by autocomplete, maps and local SEO.
- Tracked competitors: competitor domains by service and market, used only to extract gaps, keywords and buyer questions for our own positioning.
- SERP snapshots/results: stored top organic results and own-domain comparison.
- Opportunities: Search Console, manual and system-generated ideas tied to keywords, services and locations.
- CMS builder: category, template, content type, route, brief, FAQ/map/image instructions and preview-ready draft creation.
- Article preview: private draft rendering using saved `cms_content_blocks` before approval/publish.
- Media registry: Cloudinary metadata for uploaded or AI-generated media.
- Inventory link: existing pages, articles and locations live in the content inventory.

The intended UX is a three-step flow:

1. `Opportunity Discovery`: use services, Search Console, markets and SERP to choose the right keyword/page target.
2. `Opportunities`: convert the research into page/article/location ideas and explain what each idea is trying to improve.
3. `CMS Builder`: select category and template, write one strong brief, generate/preview/edit, then approve and publish.

## Three-Step Closure Status

Reviewed and QA-verified on 2026-06-06.

Step 1, `Opportunity Discovery`, is now the place to define the real services, choose a market, import Search Console data, add final keywords and request SERP validation. Location inputs use Google Places autocomplete when available and also show configured markets as a fallback list. The global VNV Events default is `Miami, Florida, United States`; Doral is only one selectable market.

Step 2, `Opportunities`, now groups Search Console opportunities, system-suggested opportunities and manually saved opportunities. Each item explains the keyword, location/service context and reason it exists. Clicking an opportunity fills the Step 3 builder so the user can create a draft from the prior research instead of retyping fields.

Step 3, `CMS Builder`, now uses database-backed categories and templates, creates preview-ready drafts, stores route/canonical data, creates CMS blocks, queues the generation/research run and renders a private in-panel article preview. Editing a draft preserves its real template and category IDs.

QA checks performed:

- Dashboard loaded for `vnvevents`, `avomeal` and `jonnysmedia` with site-specific categories/templates.
- Step 1 saved a selected keyword with market and priority.
- Step 2 saved an opportunity tied to that keyword and market.
- Step 3 generated a VNV Events draft with `id_template`, `id_cms_category`, route, blocks and preview CSS.
- Editing the draft preserved `id_template` and `id_cms_category`.
- Same slug/title was allowed across different `site_key` values after removing the old cross-site slug uniqueness constraint.
- Temporary QA rows were removed after the test.
- PHP lint passed for Growth Hub services, repository, SERP/Search Console services and the panel controller.
- Twig parse passed for `src/views/panel/level1/growth-hub/index.twig`.

## Database

Required SQL:

- `db/ophyra_growth_hub_level1_required.sql`

Core tables:

- `growth_sites`: brand/site settings.
- `cms_templates`: brand-scoped reusable visual/content recipes, including CSS and block structure.
- `cms_categories`: brand-scoped editorial categories.
- `cms_contents`: structured content records.
- `cms_content_blocks`: block-based content for Twig rendering.
- `cms_routes`: public route map per site.
- `cms_media`: Cloudinary asset registry and metadata.
- `seo_keywords`: keyword storage for manual, Search Console and future import sources.
- `growth_target_locations`: county/city/location targets for map and local SEO tracking.
- `growth_competitors`: competitor domains per brand, county, city and service/product.
- `seo_search_console_snapshots`: Google Search Console keyword/page performance imports.
- `seo_search_console_import_status`: Search Console connection/import status.
- `seo_serp_snapshots`: DataForSEO SERP validation snapshots.
- `seo_serp_results`: organic result rows captured for each SERP snapshot.
- `seo_serp_import_status`: DataForSEO task/fetch status and safe errors.
- `seo_google_trends_snapshots`: future Google Trends interest and related query imports.
- `seo_opportunities`: scored content and ranking opportunities.
- `seo_agent_runs`: generation/audit run tracking.

All core records include `id_owner` and `site_key`.

## Publishing Environment And Receiver Sitemap Refresh

`growth_sites.sitemap_settings` controls whether a brand is being rendered for development or production.

Required keys:

```json
{
  "environment": "development",
  "public_base_url": "https://vnvevents.com",
  "sitemap_url": "https://vnvevents.com/sitemap.xml",
  "receiver_sitemap_endpoint": "https://vnvevents.com/api/ophyra/growth-hub/sitemap-refresh"
}
```

Behavior:

- `development`: content can be created, edited, previewed and even marked published in Ophyra, but Ophyra skips remote sitemap optimization/refresh.
- `production`: after publish, update of an already-published page, or archive of a published page, Ophyra posts a sitemap refresh event to the configured receiver endpoint.

The receiver endpoint belongs in the public brand repo, not in Ophyra. Its job is to rebuild that brand's public `sitemap.xml` from Ophyra's published route/content APIs and the brand site's own static/service routes.

Ophyra records each receiver refresh attempt in `seo_agent_runs` with `run_type=receiver_sitemap_refresh` and `status=COMPLETED`, `FAILED` or `SKIPPED`.

See `docs/GROWTH_HUB_BRAND_SITE_ADAPTATION_GUIDE.md` for the exact endpoint contract and payload.

## Search Console Integration

Search Console is the source of truth for existing demand. It imports query/page rows for the last 28 days with:

```text
dimensions = ["query", "page"]
rowLimit = 1000
```

Saved fields:

- property/domain
- query
- page
- clicks
- impressions
- CTR
- average position
- start/end date
- imported timestamp

Required properties:

```text
vnvevents:sc-domain:vnvevents.com
avomeal:sc-domain:avomeal.com
jonnysmedia:sc-domain:jonnys.media
```

Latest verified import on 2026-06-06:

- `vnvevents`: connected, 609 rows imported.
- `avomeal`: connected, 0 rows returned.
- `jonnysmedia`: connected, 1 row imported.

If a property is connected but has no rows, the panel should show it as connected with `no rows returned`, not as a failure.

## SERP / DataForSEO Integration

DataForSEO is the primary SERP provider for validating opportunities. It is not meant for generic keyword research. Its job is to answer:

```text
For this service + location, what is Google rewarding right now?
```

Provider configuration:

```text
SERP_ENABLED=true
SERP_PROVIDER=dataforseo
SERP_MODE=standard_queue
SERP_RESEARCH_DEPTH=5
SERP_DAILY_LIMIT=5
SERP_DEFAULT_LOCATION="Miami, Florida, United States"
SERP_DEFAULT_LANGUAGE=en
SERP_DEFAULT_DEVICE=desktop
```

Expected task endpoint:

```text
POST https://api.dataforseo.com/v3/serp/google/organic/task_post
```

The provider uses Basic Auth with `DATAFORSEO_LOGIN` and `DATAFORSEO_PASSWORD`. `DATAFORSEO_PASSWORD` must be the API password from DataForSEO API Access, not the normal account login password.

Implemented:

- create Google Organic Standard Queue tasks;
- fetch task results;
- save the snapshot in `seo_serp_snapshots`;
- save top organic rows in `seo_serp_results`;
- extract position, domain, URL, title, snippet and result type;
- store related searches, people also ask, local pack and ads when DataForSEO returns them;
- record safe task status/errors in `seo_serp_import_status`;
- avoid saving snapshots if task creation fails.

Current blocker:

```text
DataForSEO credentials are present but not authorized.
```

The code reaches the correct endpoint with Basic Auth and receives HTTP 401. The next step is to replace `DATAFORSEO_PASSWORD` with the API Access password and confirm the DataForSEO account/API access is verified.

## VNV Events Markets

Do not use Doral as the global default. Miami is the default market. Doral is one selectable market.

Active VNV Events markets:

- Miami-Dade County: Miami, Doral, Kendall, Hialeah.
- Broward County: Fort Lauderdale, Hollywood, Pembroke Pines, Weston, Sunrise.
- Palm Beach County: West Palm Beach, Boca Raton, Delray Beach.

Location fields in the panel should use Google Places autocomplete where possible. Manual free-text locations should be treated as a fallback, not the main workflow.

## Market Intelligence

Growth Hub is designed to move beyond generic SEO data. Each owned brand can track:

- target counties, cities and service areas;
- competitor domains per location and service/product;
- services/offers the brand wants to grow;
- keywords per location, intent, priority and source;
- Search Console clicks, impressions, CTR and average position;
- SERP top 5 competitors and own-domain presence;
- future Google Trends interest, trend direction and related queries.

The useful output of Step 1 is not a pile of fields. It is a final keyword/service/market list plus competitor context. Step 2 uses that list to explain why each content opportunity exists.

## CMS Direction

Growth Hub should move toward fewer visible fields and more guided creation.

Preferred creation flow:

1. research keywords, competitors, locations and SERP signals;
2. turn that research into opportunities;
3. choose one opportunity or existing draft as the base;
4. choose category and template;
5. write one clear instruction for the generator;
6. generate article, images, FAQ, map and source notes together;
7. preview inside Ophyra;
8. ask the generator for changes;
9. approve and publish.

Templates and categories should be brand-scoped with `site_key`. A VNV Events template or category must not appear while creating Avomeal content unless it is explicitly shared.

Templates and categories are stored in the database:

- `cms_templates.site_key`
- `cms_templates.template_key`
- `cms_templates.template_structure_json`
- `cms_templates.css_text`
- `cms_categories.site_key`
- `cms_categories.slug`
- `cms_contents.id_template`
- `cms_contents.id_cms_category`

`metadata_json.template_key` and `metadata_json.content_category` are retained as readable snapshots, but they are not the source of truth. The draft must point to the real template/category records when they exist.

Current seeded counts:

- `vnvevents`: 5 categories, 4 templates.
- `avomeal`: 3 categories, 2 templates.
- `jonnysmedia`: 2 categories, 1 template.

Template CSS source and editing:

- `cms_templates.css_text` is the editable visual skin for generated content.
- The generator should not paste large CSS into the article body or prompt.
- Growth Hub Step 3 includes a `Template CSS` editor that updates `cms_templates.css_text` for the selected `site_key`.
- The current CSS seeds were rebuilt after reviewing the local sibling repositories:
  - `../vnv-events`: public event/service pages, Growth Hub renderer and blog post styling.
  - `../vnv-gourmet`: CMS article test template and warm food/store visual direction.
  - `../jonnys-media`: public CMS renderer and dark audiovisual/professional brand direction.
- VNV Events templates use the real luxury event language found in the repo: dark stage-like backgrounds, turquoise accents, gold CTA treatment, Playfair-style headings, high-contrast service sections and premium rounded media cards.
- Avomeal templates use the food/editorial language from the CMS test template: cream backgrounds, brown ink, warm accent, readable article cards and gallery-first food imagery.
- Jonnys Media templates use a darker production-oriented skin: black/blue panels, cyan highlights, crisp cards and media-forward grids.

The backend can still store SEO, route, schema, media, blocks, source metadata and quality checks. The admin user should not have to understand every field before creating a useful draft.

## Integration With Public Sites

Growth Hub owns the central CMS and SEO planning records. Public sites consume only approved content via API.

VNV Events:

- `site_key=vnvevents`
- domain `vnvevents.com`
- primary scope for event services, weddings, quinceaneras, corporate events, locations and planning content
- should consume published Growth Hub pages/routes/media while preserving its own operational CRM/orders/contracts flows

Avomeal:

- `site_key=avomeal`
- domain `avomeal.com`
- primary scope for catering, meal prep, office lunch, menus and food/store content
- should use Growth Hub content and SEO planning without mixing Store orders or VNV Events event operations

Jonnys Media:

- `site_key=jonnysmedia`
- domain `jonnys.media`
- primary scope for professional identity, technical leadership, media/creative services and portfolio content
- should be treated as a distinct public brand, not as `ophyra.com`

Ophyra:

- platform/admin system
- documentation, modules, marketplace, billing and internal operations
- not a required Search Console content target for this Growth Hub SEO panel

## Media Rules

Images are uploaded through the existing application file layer: `FileUtils::uploadFile()` / `CloudinaryClient::client()`. Growth Hub does not require a separate Cloudinary account or new credentials.

Cloudinary stores the asset bytes. The database stores the Growth Hub metadata:

- `cloudinary_public_id`
- `cloudinary_url`
- `secure_url`
- `source_type`
- `usage_type`
- dimensions, format and bytes
- prompt and metadata when AI generated
- content/block relationship when available

For VNV Events, `source_type=ai_generated` must not be presented as a real event photo. The upload layer stores an `ai_disclosure_required` metadata flag for that case.

## Public API

Public consumers must read only approved published content.

Endpoints:

- `GET /api/growth-hub/content?site_key=vnvevents&type=blog`
- `GET /api/growth-hub/content?site_key=vnvevents&slug=my-page`
- `GET /api/growth-hub/content?site_key=vnvevents&route=/blog/my-page`
- `GET /api/growth-hub/routes?site_key=vnvevents`
- `GET /api/growth-hub/media?site_key=vnvevents&id_content=123`
- `GET /api/growth-hub/blocks?site_key=vnvevents&id_content=123`
- `GET /api/growth-hub/sitemap?site_key=vnvevents`
- `GET /api/growth-hub/monitoring?site_key=vnvevents`

Official CMS route rules:

- `page`: `/{slug}` (legacy aliases `landing` and `custom` map to `page`)
- `location`: `/locations/{slug}`
- `blog`: `/blog/{slug}`

Ophyra normalizes stored public routes with a trailing slash at publish time, for example `/wedding-packages/`, `/locations/doral-event-planning/` and `/blog/planning-guide/`. Public consumers should continue accepting both slash variants for historical rows.

`growth_sites.public_base_url` controls the production or local render base, for example `https://vnvevents.com` or `http://localhost/vnvevents.com`.

The service filters content by:

- matching `id_owner`
- matching `site_key`
- `status = PUBLISHED`
- `approval_status IN (APPROVED, PUBLISHED)`

Drafts and review content are not exposed.

## Twig Blocks

Reusable block templates live in:

- `src/views/templates/cms/blocks/`

Renderer:

- `src/views/templates/cms/render-blocks.twig`

Fallback:

- `src/views/templates/cms/blocks/default.twig`

Initial block templates:

- `hero`
- `body_section`
- `cta`
- `faq`
- `photo_gallery`

## Environment

Relevant variables are documented in `.env.example`.

Important variables:

- `SEO_AGENT_ENABLED`
- `SEO_AGENT_DEFAULT_OWNER_ID`
- `SEO_AGENT_DEFAULT_SITE_KEY`
- `SEO_AGENT_AUTO_PUBLISH`
- `SEO_AGENT_REQUIRE_APPROVAL`
- `OPENAI_API_KEY`
- `OPENAI_TEXT_MODEL`
- `OPENAI_IMAGE_MODEL`
- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `CLOUDINARY_DEFAULT_FOLDER`
- `GOOGLE_SEARCH_CONSOLE_ENABLED`
- `GOOGLE_SEARCH_CONSOLE_AUTH_MODE`
- `GOOGLE_SEARCH_CONSOLE_REQUIRED_PROPERTIES`
- `GOOGLE_TRENDS_ENABLED`
- `GOOGLE_MAPS_API_KEY`
- `GOOGLE_PLACES_API_KEY`
- `SERP_PROVIDER`
- `DATAFORSEO_LOGIN`
- `DATAFORSEO_PASSWORD`
- `SERP_MODE`
- `SERP_RESEARCH_DEPTH`
- `SERP_DAILY_LIMIT`
- `SERP_DEFAULT_LOCATION`
- `SERP_DEFAULT_LANGUAGE`
- `SERP_DEFAULT_DEVICE`
- `GROWTH_HUB_SERP_REFRESH_ENABLED`
- `GROWTH_HUB_MONITORING_TOKEN`
- `SEO_AGENT_DAILY_SCAN_CRON`

## Current MVP Status

Implemented:

- Level 1 Growth Hub shell.
- Site selector.
- Site settings for public base URL and domain.
- Service/offer list used by Opportunity Discovery.
- Target location registry for map/local SEO tracking.
- Competitor registry by brand, county/city and service/product.
- Keyword registry for Search Console, Trends and SERP monitoring.
- Google Places autocomplete hooks for location inputs.
- Google Search Console OAuth integration.
- Search Console import for `vnvevents`, `avomeal` and `jonnysmedia`.
- Search Console status table with connected/not connected, last import, rows and last error.
- Search Console page/query opportunities tied to services and content ideas.
- Step 2 opportunities screen with Search Console, suggested and saved opportunities.
- Opportunity-to-builder prefill for Step 3.
- Step 3 preview-ready page creation.
- Template/category selection loaded from database tables.
- Drafts store `id_template` and `id_cms_category` when created from the builder.
- Private article preview rendered from final saved body HTML with saved CMS blocks as fallback.
- Preview applies `cms_templates.css_text` from the selected template.
- Redirect to Step 3 preview after page creation.
- DataForSEO SERP provider interface and implementation.
- DataForSEO task status/error logging.
- System-proposed opportunities from Search Console, uncovered keywords and locations.
- Planned draft creation with research/media/map/FAQ instructions.
- `seo_agent_runs` queue entries for planned research/generation.
- Content modification workflow.
- Monitoring JSON endpoint for external/internal dashboards.
- Per-brand XML sitemap endpoint for public site indexing.
- SERP snapshot tables and dashboard readout.
- Draft creation into `cms_contents`.
- Default block creation into `cms_content_blocks`.
- Route registration into `cms_routes`.
- Cloudinary upload plus AI image metadata registration into `cms_media`.
- Public API read layer for published content, routes, blocks and media.

## Current Page Generation Contract

Growth Hub Step 3 now creates publishable page HTML rather than a technical brief. The saved public payload includes:

- Optional title-idea proposals before generation: Step 3 can generate 5 editable title/angle/brief options from the keyword, opportunity source, competitor context and editor notes. Editors can apply one idea or create several related preview drafts in sequence.
- `cms_contents.body`: final HTML body with public-facing sections.
- `cms_templates.css_text`: the selected visual skin.
- `cms_media`: generated or uploaded images registered to the content.
- `schema_json`: JSON-LD for the public page.
- `metadata_json.quality_status`, `quality_score` and `quality_notes`: automated draft review state.
- `metadata_json.research_plan`: research mode, generation depth, research summary, citation plan, internal links, map plan, generation log and anti-cannibalization notes.
- `cms_routes`: active public route and canonical URL.

Generation depth:

- `page_review`: lightweight page review, default max 3200 tokens.
- `short_article`: short researched article, default max 4500 tokens.
- `seo_standard`: standard SEO article, default max 8000 tokens and at least 3 reviewed sources before publish.
- `deep_article`: deeper editorial article, default max 12000 tokens and at least 5 reviewed sources before publish.
- Token budgets can be overridden with `GROWTH_HUB_AI_MAX_TOKENS_PAGE_REVIEW`, `GROWTH_HUB_AI_MAX_TOKENS_SHORT_ARTICLE`, `GROWTH_HUB_AI_MAX_TOKENS_SEO_STANDARD` and `GROWTH_HUB_AI_MAX_TOKENS_DEEP_ARTICLE`.

Generated image layout rule:

- Each generated in-article image is paired with adjacent text.
- Desktop layout is approximately 50% text and 50% image.
- Image side alternates: right, left, right, left.
- Public renderers must support `cms-block-media-text`, `cms-media-text-row`, `cms-media-right`, `cms-media-left`, `cms-image-generated` and `cms-image-copy`.

External citations:

- Growth Hub searches public web results, fetches readable pages, and stores only reviewed sources with a real URL, title and excerpt.
- Quora, Reddit and forum-like sources are tagged as `source_type=community`; third-party guides/articles are tagged as references.
- Blocked or unreadable sources are not rendered as placeholder links. If there are no safe sources, the public page does not show a citation section.
- Reviewed sources are fed into the AI as `research_summary` before drafting, so the generated article can use the research context instead of appending unrelated links afterward.

Internal links:

- Growth Hub sends published Growth Hub routes and production-site links to the generator.
- If the generated body does not include internal links, Growth Hub appends a related-pages section.

Publishing quality gate:

- Approval recalculates the quality review before publishing.
- Drafts with internal brief language, placeholder text, missing required sources, missing requested map embed, weak structure or too little concrete detail are marked `failed_review` and cannot be published until edited or regenerated.
- The review result is stored in `metadata_json` so editors can see why a page was blocked without adding new database columns.

Collision prevention:

- New pages are blocked when they appear to cannibalize an existing keyword/service/location intent.
- Active routes cannot be silently reassigned from one content item to another.

Public brand repositories must implement catch-all renderers. A published Growth Hub page should not require a physical file in VNV Events, Avomeal or Jonnys Media. See `docs/GROWTH_HUB_PUBLIC_SITE_CONSUMPTION.md`.

Not implemented yet:

- Keyword CSV import UI.
- Full opportunity scoring engine using Search Console + SERP + services.
- Automated competitor scanner execution.
- Automated daily SERP worker.
- Google Trends importer.
- DataForSEO successful snapshot fetch, blocked until valid API Access credentials are active.
- AI template optimization.
- Public brand repo catch-all renderers for VNV Events, Avomeal and Jonnys Media need to be implemented/verified because those sites can 404 even when Ophyra has published content.

## Execution Plan

Next execution order:

1. Implement/verify brand repo catch-all renderers using `docs/GROWTH_HUB_PUBLIC_SITE_CONSUMPTION.md`.
2. Fix DataForSEO API Access credentials, rerun validation searches, and confirm rows are saved in `seo_serp_snapshots` and `seo_serp_results`.
3. Add scheduled workers for Search Console refresh, SERP refresh and future Google Trends enrichment.
4. Add richer automated competitor scanner execution for manually added competitor URLs.
5. Add keyword CSV import UI if bulk seed import is still needed.
6. Add AI template optimization after public receivers can render the current template contract.
