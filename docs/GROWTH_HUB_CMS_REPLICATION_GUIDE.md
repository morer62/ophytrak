# Growth Hub CMS Replication Guide

## Purpose

This guide defines how to replicate the VNV Events `/panel/cms/` admin surface model in Avomeal, Jonnys Media and future brand repositories, while keeping Ophyra as the single source of CMS truth.

It complements:

- `docs/OPHYRA_CMS_ROUTE_CONTRACT_REVIEW_2026_06_09.md` (route contract hardening after 404 incidents)
- `docs/GROWTH_HUB_PUBLIC_SITE_CONSUMPTION.md` (consumer-side resolver contract)

## Source Of Truth

Ophyra Growth Hub is the source of truth for Growth Hub CMS content.

Active CMS inventory must come from:

- `cms_contents`
- `cms_routes`
- `cms_categories`
- `cms_templates`
- `cms_content_blocks`
- `cms_media`

Do not treat these as legacy-only tables:

- `cms_location_pages`
- `blog_categories`
- `ai_content_drafts`
- old AI location/blog generators

## Brand Scope

Each brand repository must use its own `site_key`:

- VNV Events -> `vnvevents`
- Avomeal -> `avomeal`
- Jonnys Media -> `jonnysmedia`

Public and admin CMS queries must filter by `site_key`. Do not use `id_owner` as the only scope.

## Environment

Each brand should keep these in env/config:

- `APP_URL`
- `PUBLIC_BASE_URL`
- `OPHYRA_BASE_URL`
- `OPHYRA_GROWTH_SITE_KEY`
- `SITE_KEY`
- `SITE_NAME`
- `SITE_PUBLIC_BASE_URL`

## Final Content Model

Ophyra simplified public CMS content type model to:

- `page`
- `location`
- `blog`

Routing rules:

- `page` -> `/{slug}/`
- `location` -> `/locations/{slug}/`
- `blog` -> `/blog/{slug}/`

Landing pages are now handled as content model `page` with template `service-landing`.

Products are not Growth Hub CMS content. Products belong to Store/catalog domain.

## Template Model

Templates describe presentation, not publication scope.

Template examples:

- `service-landing`
- `local-location-page`
- `editorial-guide`
- `faq-resource`

Template `type` should stay aligned with the normalized model (`page`, `location`, `blog`).

Compatibility aliases observed in legacy consumers:

- `landing`, `service`, `custom` -> `page`
- `guide`, `faq_page`, `comparison`, `case_study`, `post` -> `blog`
- `product` -> Store/catalog, not Growth Hub CMS

## Admin Surface

The local `/panel/cms/` dashboard should expose:

- Content
- Categories
- Templates
- SEO Center

Avoid exposing legacy standalone cards as active CMS workflow:

- Blog Posts
- Blog Categories
- Location Pages
- AI Content Review

Those routes may exist as compatibility redirects, but should not be the active authoring path.

## Content Inventory and Filters

Required filters:

- `status`
- `content_type`
- `search`

`content_type` options should be `Page`, `Location`, `Blog` only.

Recommended columns:

- Content
- Type
- Template
- SEO Target
- Status
- Public URL
- Activity
- Actions

Open preview links must use the local app route helper (to respect local base path):

```twig
{{ path(item.main_route.route|trim('/')) }}
```

## Create / Edit Form

Fields to include:

- Content Type (`page` | `location` | `blog`)
- Category (`cms_categories`)
- Template (`cms_templates`)
- Status
- Title
- Slug
- Route
- Body / content fields
- SEO fields
- Schema JSON
- Featured image

If route is empty, generate by model + slug:

- `page`: `/wedding-planner-services/`
- `location`: `/locations/wedding-planner-doral/`
- `blog`: `/blog/how-to-plan-corporate-event/`

## Public Rendering Contract

Public resolvers should prefer `cms_contents` + `cms_routes` data from Ophyra by route:

- `cms_routes.site_key = active site_key`
- `cms_routes.status = ACTIVE`
- `cms_contents.site_key = active site_key`
- `cms_contents.status = PUBLISHED`
- `cms_contents.approval_status IN (APPROVED, PUBLISHED)` when present

Do not overwrite existing static pages, existing CMS routes, store/product pages, legacy location pages or reserved routes.

Preferred content rendering order:

1. `cms_contents.body_html`
2. `cms_contents.body`
3. `cms_contents.content_json`
4. `cms_content_blocks.data_json` ordered by `sort_order`

## Route Availability Check

Before creating or publishing a route, run a compatibility check across:

- physical public views/pages
- `cms_routes`
- existing category paths
- reserved prefixes
- sitemap and live HTTP probe (secondary)

If a route collides, do not overwrite. Use alternate slug or human review.

## QA Checklist

- `/panel/cms/` shows Content, Categories, Templates, SEO Center only.
- Content type filters/listing are `Page/Location/Blog`.
- Admin inventory matches Ophyra for the same `site_key`.
- Route generation is deterministic and unique by `site_key + slug`.
- Draft does not render publicly; approval should publish.
- Canonical URLs use public brand domain.
- Template CSS is applied and scoped.
- `cms_media` images are used for thumbnails/hero/body.

