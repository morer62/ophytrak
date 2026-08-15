# VNV Events / Ophyra Integration Model

## Purpose

This document explains how VNV Events relates to Ophyra after reviewing the VNV Events web repo and mobile app documentation.

## Identity

VNV Events is a real event planning, production and services business.

Public domain:

```text
vnvevents.com
```

Ophyra may act as central admin/reference architecture, but VNV Events is not a generic Ophyra module.

## Repositories

```text
morer62/vnv-events
morer62/vnv-mobile-app
```

## Active VNV User Model

VNV Events currently focuses on:

- Level 1: owner/admin;
- Level 4: team member/collaborator;
- Level 5: client/public account;
- Level 6: CMS/marketing operator.

Do not assume Level 2/3 are active primary VNV flows unless local code proves it.

## VNV Events Web Surface

The `vnv-events` repo includes:

- Level 1 operations dashboard;
- orders, estimates, suborders, contracts and payments;
- clients, team, payroll/time clock and chat;
- order calendar for Level 1 and Level 4;
- music sessions and planning tools;
- CMS pages, blog posts, templates and location pages;
- public forums;
- SEO Center at `/panel/seo-center`;
- public SEO files: `/sitemap.xml`, `/robots.txt`, `/llms.txt`, `/llms-full.txt`;
- public event request popup and Level 1 intake/archive flow.

## VNV Mobile Scope

The VNV Events mobile app uses:

```text
BUSINESS_ID=2
MOBILE_OWNER_ID=2
source=vnv_mobile_app
```

The app docs identify owner:

```text
info@vnvevents.com
users.id = 2
level = 1
id_owner = 2
```

Mobile signup creates or associates Level 5 clients only. Client access must respect `clients_users`, not only `users.id_owner`.

## SEO And CMS

VNV Events owns its public CMS, forums and SEO files.

The SEO Center includes only public, published and indexable content from:

- static VNV pages;
- CMS pages and blog posts;
- location pages;
- Store categories/products where public;
- published public forum threads.

It excludes:

- panel routes;
- API routes;
- login/signup/private routes;
- drafts/unpublished/noindex content.

## Growth Hub Integration

Ophyra Growth Hub can manage SEO planning and central CMS drafts for VNV Events with:

```text
site_key=vnvevents
domain=vnvevents.com
Search Console property=sc-domain:vnvevents.com
```

VNV Events should consume only approved/published Growth Hub content for `site_key=vnvevents`. Operational data such as orders, estimates, contracts, clients, payroll and chat remains in the VNV operational scope and must not be mixed with Growth Hub SEO records.

The current VNV Events Growth Hub flow is:

1. define services/offers such as weddings, quinceaneras, corporate events, rentals and decor;
2. import Search Console query/page data;
3. validate service + location opportunities with SERP when DataForSEO credentials are active;
4. create page/article/location opportunities;
5. generate or edit CMS drafts using category + template + brief;
6. approve/publish so VNV Events can consume the content through the public Growth Hub API.

## Required Growth Hub Sitemap Receiver

VNV Events must implement the production receiver endpoint described in `GROWTH_HUB_BRAND_SITE_ADAPTATION_GUIDE.md`:

```text
POST /api/ophyra/growth-hub/sitemap-refresh
```

When Ophyra marks `vnvevents` as `production`, every published, updated or archived Growth Hub page should trigger this endpoint. VNV Events must then rebuild `https://vnvevents.com/sitemap.xml` from:

- existing VNV public routes;
- Ophyra Growth Hub published routes for `site_key=vnvevents`;
- only canonical, indexable URLs that render `200 OK`.

If Ophyra is set to `development`, VNV Events must not rebuild the production sitemap from local preview URLs.

VNV Events default SERP market is:

```text
Miami, Florida, United States
```

Doral is a selectable market, not the global default.

## Ophyra Integration Boundary

Ophyra Level 1 may monitor VNV Events operations when records are scoped to the VNV owner/company.

Do not mix VNV operational metrics with:

- Ophyra subscriptions;
- Ophyra add-on revenue;
- affiliate payouts;
- Avomeal Store sales.

## Production Readiness Notes From VNV Docs

VNV launch QA requires:

- event request SQL;
- `seo_files_logs`;
- forum migrations/columns;
- SMTP test sending to VNV addresses;
- SEO file regeneration after final content;
- browser QA for Level 1/4/5;
- public content spot checks for VNV language and metadata.
