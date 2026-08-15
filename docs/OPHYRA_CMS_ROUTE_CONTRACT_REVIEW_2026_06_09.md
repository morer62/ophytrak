# Ophyra CMS Route Contract Review - 2026-06-09

## Purpose

Defines the route compatibility contract used to avoid missing public pages after Growth Hub publishing across Ophyra and brand repos (`vnvevents`, `avomeal`, `jonnysmedia`).

## Hard Rule

Existing public routes have priority.

No component should overwrite, replace, or redirect an existing public route when publishing Growth Hub content. If a route exists, either:

- send the content to review,
- assign a different slug, or
- intentionally map by explicit human action.

## Finding That Triggered This Review

Ophyra was returning records with:

- `cms_contents.content_type = page | location | blog`
- `cms_routes.route_type = page | location | blog`

Some public repos resolved by legacy `cms_contents.type` values only (`page`, `post`), so valid records were treated as 404.

## Compatibility Resolution

Public resolver priority must follow:

1. `cms_contents.content_type`
2. `cms_routes.route_type`
3. `cms_contents.type`
4. fallback page

Legacy values should still be interpreted as aliases, while route creation and routing should stay on the new fields.

## Required Ophyra Fields

Before publish, verify:

- `cms_contents.site_key`
- `cms_contents.content_type` in `page | location | blog`
- `cms_routes.route`
- `cms_routes.route_type` matches content type
- `cms_contents.status = PUBLISHED`
- `cms_contents.approval_status IN (APPROVED, PUBLISHED)`
- `cms_routes.status = ACTIVE`

Route endpoint matching should accept both with and without trailing slash.

## Canonical Route Map

```text
page      -> /{slug}/
location  -> /locations/{slug}/
blog      -> /blog/{slug}/
```

Legacy aliases:

- `landing`, `service`, `custom` -> `page`
- `post` -> `blog`

## Public Route Resolver Priority

When resolving a request, public repos should prioritize:

1. physical/static public pages
2. existing local CMS routes/pages
3. published Ophyra routes for active `site_key`
4. category/product dynamic routes
5. 404

`cms_routes` is not the only collision source.

## Route Check Inputs

Before creating a route in Ophyra, check at least:

- physical public views in repo
- reserved router prefixes (`panel`, `api`, `login`, `blog`, `locations`, `category`, `store`, `product`, `commerce`, `auth`, etc.)
- `cms_routes`
- legacy content tables (`cms_location_pages`, `cms_categories`)
- `cms_contents.slug` route candidates
- sitemap as a secondary signal

## SQL: Detect route contract by slug/route

```sql
SELECT
  r.id AS route_id,
  r.route,
  r.route_type,
  r.status AS route_status,
  c.id AS content_id,
  c.content_type,
  c.type,
  c.title,
  c.status AS content_status,
  c.approval_status
FROM cms_routes r
JOIN cms_contents c ON c.id = r.id_content
WHERE r.site_key = :site_key
  AND r.route = :route
LIMIT 1;
```

## SQL: Legacy collisions

```sql
SELECT
  id,
  slug,
  title,
  status,
  site_key
FROM cms_location_pages
WHERE site_key = :site_key
  AND slug = :slug
LIMIT 1;
```

## Compatibility Backfill (Optional)

If legacy consumers still need `cms_contents.type`, prefer this compatibility migration:

```sql
UPDATE cms_contents
SET type = CASE
  WHEN content_type = 'blog' THEN 'post'
  WHEN content_type IN ('page', 'location') THEN 'page'
  ELSE type
END
WHERE (type IS NULL OR type = '')
  AND content_type IN ('page', 'location', 'blog');
```

```sql
UPDATE cms_routes r
JOIN cms_contents c ON c.id = r.id_content
SET r.route_type = CASE
  WHEN c.content_type = 'blog' THEN 'blog'
  WHEN c.content_type = 'location' THEN 'location'
  ELSE 'page'
END
WHERE (r.route_type IS NULL OR r.route_type = '')
  AND c.content_type IN ('page', 'location', 'blog');
```

## Public Image Contract for Locations/Lists

To improve route/list quality:

- `cms_contents.featured_image_url` should be available or explicitly missing by design
- `cms_categories.featured_image_url` should be available for category index pages
- fallback placeholder is allowed only as temporary signal for follow-up editorial action

## Recommended API/Route Observability

Checklist before Ophyra marks a page public:

- `site_key` correct
- route/type consistency (`content_type` -> `route_type`)
- route collision check passed
- canonical/SEO fields use public domain, not local paths
- route appears in `/api/growth-hub/routes?site_key=...`
- page endpoint resolves by route with/without slash
- sitemap is rebuilt in production consumers after publish/update/archive

## Related Documentation

- `docs/GROWTH_HUB_PUBLIC_SITE_CONSUMPTION.md`
- `docs/GROWTH_HUB_CMS_REPLICATION_GUIDE.md`
- `docs/API_ENDPOINTS_CONTRACT.md`
- `docs/ophyra-growth-hub.md`

