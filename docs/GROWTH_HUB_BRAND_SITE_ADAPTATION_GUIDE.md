# Growth Hub brand site adaptation guide

This guide explains how each public brand repository should adapt to Ophyra Growth Hub content.

Applies to:

- VNV Events: `site_key=vnvevents`
- Avomeal / VNV Gourmet: `site_key=avomeal`
- Jonnys Media: `site_key=jonnysmedia`

Ophyra remains the source of truth for generated CMS content, templates, categories, routes, SEO metadata, media, schema, and publishing state. Public brand sites should render only approved published content.

## Required Brand Admin Scope

Brand-side CMS admin areas should stay focused on the public content layer. They should allow authorized users to:

- View, edit, and add templates.
- View, edit, and add categories.
- View, edit, and add articles/pages.
- Preview articles/pages before publication.
- Approve and publish only through Ophyra-backed write endpoints or a shared authenticated admin flow.

Brand repos should not expose Growth Hub internals such as competitor analysis, Search Console imports, SERP diagnostics, agent runs, or opportunity scoring unless the repo is specifically an Ophyra admin surface.

## Public API Consumption

Use these Ophyra endpoints from the public brand repositories:

```text
GET /api/growth-hub/content?site_key=vnvevents
GET /api/growth-hub/content?site_key=vnvevents&type=blog
GET /api/growth-hub/content?site_key=vnvevents&slug=my-page-slug
GET /api/growth-hub/content?site_key=vnvevents&route=/my-page-slug
GET /api/growth-hub/routes?site_key=vnvevents
GET /api/growth-hub/sitemap?site_key=vnvevents
```

Only published content is returned publicly. A page should be visible after it is approved and published in Ophyra. Schedule dates should not block visibility once approval publishes the page.

## Publishing Environment And Sitemap Refresh

Each Growth Hub site has a publishing environment:

- `development`: Ophyra may create, edit, preview and publish content for local testing, but it must not ask the public brand site to rebuild or optimize its production sitemap.
- `production`: after a page is published, updated while published, or archived, Ophyra must notify the brand repository so the public `sitemap.xml` is rebuilt from current published routes.

The environment is stored in `growth_sites.sitemap_settings.environment`.

Production brand sites must expose a protected receiver endpoint. Recommended route:

```text
POST /api/ophyra/growth-hub/sitemap-refresh
```

Ophyra sends JSON:

```json
{
  "event": "content_published",
  "site_key": "vnvevents",
  "site_name": "VNV Events",
  "environment": "production",
  "content_id": 123,
  "route": "/corporate-event-agency",
  "canonical_url": "https://vnvevents.com/corporate-event-agency",
  "public_base_url": "https://vnvevents.com",
  "sitemap_url": "https://vnvevents.com/sitemap.xml",
  "ophyra_sitemap_url": "https://ophyra.example.com/api/growth-hub/sitemap?site_key=vnvevents",
  "ophyra_routes_url": "https://ophyra.example.com/api/growth-hub/routes?site_key=vnvevents",
  "ophyra_content_url": "https://ophyra.example.com/api/growth-hub/content?site_key=vnvevents&route=%2Fcorporate-event-agency",
  "changed_at": "2026-06-07T12:00:00-04:00"
}
```

Possible `event` values:

- `content_published`
- `content_updated`
- `content_archived`
- `manual_sitemap_refresh`

Authentication:

- The endpoint must require `Authorization: Bearer {shared_token}`.
- The token is configured in Ophyra as `receiver_sitemap_token`.
- Reject missing or invalid tokens with `401`.

Receiver behavior:

- Fetch `ophyra_sitemap_url` or `ophyra_routes_url`.
- Rebuild the public brand `sitemap.xml` from published, indexable Growth Hub routes plus existing brand routes.
- Remove archived/unpublished Growth Hub routes.
- Keep canonical URLs on the production domain.
- Do not include local, panel, API, draft, archived, noindex, login or admin URLs.
- Return `2xx` JSON when the sitemap is refreshed successfully.

Recommended response:

```json
{
  "ok": true,
  "site_key": "vnvevents",
  "urls_written": 42,
  "sitemap_url": "https://vnvevents.com/sitemap.xml",
  "refreshed_at": "2026-06-07T12:00:03-04:00"
}
```

This receiver endpoint is a hard requirement for production use. If it is not implemented, pages can still publish through Ophyra, but the public sitemap may stay stale and Google discovery will be weaker.

## Payload Fields To Render

Each public page should consume these fields when present:

- `title`, `excerpt`, `body`
- `seo.title`, `seo.description`, `seo.canonical`, `seo.robots`
- `seo.og_title`, `seo.og_description`, `seo.og_image`
- `schema_json`
- `template.key`, `template.name`, `template.css_text`, `template.structure`
- `category.slug`, `category.name`
- `blocks`
- `media`
- `route`, `canonical_url`, `published_at`, `updated_at`

Template CSS must be scoped to the rendered template wrapper so CSS from one template does not leak into the rest of the public site.

## Rendering Requirements

Render blocks gracefully by `block_type`:

- `hero`: main page heading, intro, eyebrow, hero image when provided.
- `body_section`: editorial sections, service explanations, evidence, page plan, or research notes.
- `photo_gallery`: images from `media` or block data.
- `cta`: call-to-action title, copy, label, and URL.
- `faq`: question and answer items.
- `local_map`: map block only when verified coordinates, embed data, or a valid service-area location is available.
- Unknown block types: render as a safe default text/content section.

If `blocks` are missing, render `body` as the fallback.

In practice, brand repos should prefer the final `body` HTML first because Ophyra now generates complete page sections with template-compatible classes, generated media placement, citations and internal links. Use `blocks` as a fallback or for custom structured renderers.

Required wrapper:

```html
<article class="cms-preview-document cms-preview-template-{template.key}">
  {body}
</article>
```

Required style source:

```html
<style>{template.css_text}</style>
```

If a brand repo renders the body as escaped text instead of HTML, the page will look broken and the template will not be applied.

## SEO Metadata And Schema

Each public page should inject Ophyra-provided metadata:

- `<title>` from `seo.title`.
- `<meta name="description">` from `seo.description`.
- Canonical URL from `seo.canonical` or `canonical_url`.
- Open Graph and Twitter card metadata from `seo`.
- JSON-LD from `schema_json`.

Do not invent schema on the public repo when Ophyra already provides `schema_json`. If FAQ schema, product schema, service schema, local business schema, or article schema needs enrichment, that should be generated and stored in Ophyra first, then consumed by the public repo.

## Images And Thumbnails

Public repos should render images from the `media` payload.

Expected behavior:

- Use a media item marked as thumbnail or hero when available.
- Otherwise use the first content media item as the page thumbnail and `og:image`.
- Use `alt_text`, `caption`, dimensions, and format when present.
- If no media exists, use a brand-level fallback image.

Ophyra should generate and register AI thumbnails and in-article AI images before publish when the page recipe requires images. Public repos should not silently fabricate images; they should only render images present in the payload or documented brand fallbacks.

Generated in-article images are placed as alternating media/text rows:

- 1 image: image on the right, text on the left.
- 2 images: right, then left.
- 3 images: right, left, right.
- Continue alternating for additional images.
- Desktop target is roughly 50% image and 50% text.
- Mobile target is a single stacked column.

Brand repos must support these classes:

- `cms-block-media-text`
- `cms-media-text-grid`
- `cms-media-text-row`
- `cms-media-right`
- `cms-media-left`
- `cms-image-generated`
- `cms-image-copy`

## FAQ And Real Questions

FAQ blocks should be rendered only from provided block data.

The content generation process should base FAQ questions on real demand signals when available, such as Search Console queries, SERP People Also Ask data, competitor page themes, or reviewed forum/question sources. Public repos should not generate extra FAQ questions at render time.

## Maps And Coordinates

Map rendering must be conditional.

Render a map only if the payload includes one of:

- Verified latitude and longitude.
- A reviewed Google Maps embed URL.
- A valid location object that the brand repo can resolve safely.

If coordinates are missing, show service-area text or omit the map block. Do not guess coordinates from free text.

## Internal Linking

Articles and landing pages should link to existing brand service pages and related CMS pages.

Rules:

- Use links provided in block data, body HTML, metadata, or Ophyra route data.
- For service URLs, verify against the official production brand site before adding the link.
- Do not invent service URLs.
- Avoid creating a new page when an existing published CMS route or production service page already satisfies the same keyword/service intent.

Ophyra should track created routes and opportunity keys so new pages do not cannibalize existing pages.

Ophyra now sends internal link candidates to the generator and stores the selected plan in `metadata.research_plan.internal_links`. Public sites should render links already present in `body`; they should not add duplicate related-link sections if the body already includes them.

## External Citations

External citations should be rendered only when Ophyra provides reviewed citation metadata.

For sources such as Quora, Reddit, forums, or third-party articles:

- Link to the source page.
- Use short excerpts only when allowed.
- Prefer paraphrased context over long copied text.
- Do not add citations that were not reviewed or stored in the payload.

If citations are required for an article, Ophyra should gather and store them during the research step before the public site renders the article.

For web citations, Ophyra attempts to discover a public page, fetch the title and short excerpts, then stores reviewed sources under `metadata.research_plan.citation_plan.reviewed_citations`. Quora, Reddit and forum-like pages are tagged as community sources. If discovery is blocked or unavailable, Ophyra does not render placeholder review links and does not invent a citation.

Public repos should:

- Render citation links already included in `body`.
- Preserve `rel="noopener nofollow"` for external citation anchors.
- Avoid showing long copied text from third-party pages.

## Publishing Rules

Public brand sites must not show drafts.

Visible content should require:

- `status=PUBLISHED`
- `approval_status=APPROVED` or `approval_status=PUBLISHED`
- A published route when route-based rendering is used.

When an editor approves a draft, the expected behavior is immediate publication. The approval action should clear any blocking schedule and set `published_at` if it was empty.

Brand repos must implement route fallback/catch-all rendering. A published Growth Hub page should not 404 just because the file does not exist physically in the brand repo. The repo should catch the request, call Ophyra by exact route, and render the payload when found.

When the brand repo receives a sitemap refresh webhook, it should rebuild the sitemap after confirming the published route renders `200 OK`. If a published Growth Hub route still renders `404`, fix the catch-all renderer before relying on sitemap refresh.

## Current Ophyra Support

Ophyra currently supports:

- Public content and route endpoints by `site_key`.
- Public sitemap endpoint by `site_key`.
- Published-only public payloads.
- Template payload with `css_text`.
- Category payload.
- Content blocks.
- Media payload.
- SEO metadata payload.
- JSON-LD schema fallback.
- Approval and immediate publish from the Growth Hub admin.
- Production-only receiver sitemap refresh webhooks after publish/update/archive.
- Opportunity tracking to reduce duplicate/cannibalized page creation.

## Required Enrichment Before Full Production Quality

These items should be implemented or verified before relying on Growth Hub content as final production editorial output:

- AI thumbnail and in-article image generation must create actual registered media rows before publish.
- FAQ questions should be enriched from real query/SERP/question-source data, not only generic patterns.
- Map blocks should include verified coordinates or embeds.
- Internal links should be generated from verified production service URLs and Ophyra CMS routes.
- External citations, including community or forum references, should be reviewed and stored as citation metadata.
- Generated drafts should include enough research context to explain why the page exists, which demand signal it serves, and which existing page it should not cannibalize.

## Brand Repo Checklist

For each brand repository:

- Configure the correct `site_key`.
- Fetch public routes from Ophyra.
- Fetch public sitemap or route data from Ophyra when refreshing production sitemap.
- Fetch page content by `route` or `slug`.
- Render template CSS safely.
- Render blocks and media.
- Inject metadata and JSON-LD.
- Hide drafts and unpublished content.
- Implement `POST /api/ophyra/growth-hub/sitemap-refresh` with bearer-token auth.
- Rebuild public `sitemap.xml` when Ophyra sends a production refresh event.
- Do not run sitemap refresh for local/development Ophyra URLs.
- Add a CMS admin area limited to templates, categories, and articles/pages.
- Send edits, additions, approvals, and publishes back to Ophyra instead of creating a disconnected local CMS.
