# Jonnys Media Project Context

## What Jonnys Media Is

Jonnys Media is the personal/professional brand of the developer, creator and technical leader behind this ecosystem of projects.

Public domain:

```text
jonnys.media
```

Jonnys Media should not be treated as just another client account inside Ophyra. It represents the professional identity, technical leadership, creative direction and development work behind Ophyra, VNV Events, Avomeal and related systems.

## Business Purpose

Jonnys Media can represent:

* software development,
* product leadership,
* technical consulting,
* creative/media services,
* project ownership,
* ecosystem strategy,
* portfolio and public professional identity.

The tone, services, content and design should be reviewed from the current `jonnys.media` public experience before making brand-facing changes.

## Relationship With Ophyra

Ophyra can mention or connect to Jonnys Media because Jonnys Media is part of the broader ecosystem and represents the person/brand leading the work.

The correct relationship is:

```text
Jonnys Media = professional/personal brand and leadership identity
Ophyra = modular operations platform and central/reference system
```

Do not model Jonnys Media as a normal SaaS customer unless a specific implementation explicitly requires a business account or operational record.

## Repository Note

The requested repository was:

```text
morer62/jonnys_media
```

During the current review, that repository was not visible through the connected GitHub tools. Future agents should inspect the repository and public website if access becomes available.

Until then, this document uses the product-owner context provided in the repository discussion.

## What May Be Monitored From Ophyra

If Jonnys Media later shares operational data with Ophyra, Level 1 could monitor or administer:

* business/profile information,
* leads or contacts,
* services/projects,
* payments if connected,
* reports if connected,
* content or marketing workflows if implemented.

Those integrations should be explicit. Do not assume Jonnys Media data is the same as VNV Events, Avomeal or Ophyra platform data.

## Growth Hub Integration

Ophyra Growth Hub can manage SEO planning and central CMS drafts for Jonnys Media with:

```text
site_key=jonnysmedia
domain=jonnys.media
Search Console property=sc-domain:jonnys.media
```

Current Search Console status is connected and the latest verified import returned data. Jonnys Media should replace the earlier mistaken `ophyra.com` target in the Growth Hub required Search Console properties.

Jonnys Media Growth Hub content should focus on the public professional brand: technical leadership, development work, creative/media services, portfolio positioning and ecosystem strategy.

Published Growth Hub content for Jonnys Media should be consumed by `site_key=jonnysmedia` only.

## Required Growth Hub Sitemap Receiver

Jonnys Media must implement the production receiver endpoint described in `GROWTH_HUB_BRAND_SITE_ADAPTATION_GUIDE.md`:

```text
POST /api/ophyra/growth-hub/sitemap-refresh
```

When Ophyra marks `jonnysmedia` as `production`, every published, updated or archived Growth Hub page should trigger this endpoint. Jonnys Media must rebuild `https://jonnys.media/sitemap.xml` from:

- existing Jonnys Media public routes;
- Ophyra Growth Hub published routes for `site_key=jonnysmedia`;
- only canonical, indexable URLs that render `200 OK`.

If Ophyra is set to `development`, Jonnys Media must not rebuild the production sitemap from local preview URLs.

## What Remains Outside Ophyra

Jonnys Media keeps its own:

* domain,
* public brand,
* portfolio,
* messaging,
* visual identity,
* navigation,
* public content,
* service positioning,
* external website assets.

## Implementation Rule

When working on Jonnys Media, first confirm whether the work belongs to:

* the public Jonnys Media brand,
* Ophyra platform documentation,
* a shared operational/admin layer,
* a future integration.

Do not collapse Jonnys Media into Ophyra as a generic module.
