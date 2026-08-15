# CMS Content Identity Model

## Purpose

CMS and generated content can live in shared databases and inherited codebases, but public content must respect brand/site identity.

## Content Types

The ecosystem can publish:

- Ophyra public pages and landing pages;
- VNV Events CMS pages;
- VNV Events blog posts;
- VNV Events location pages;
- VNV Events public forum threads and replies;
- Avomeal Store pages, products and categories;
- future Jonnys Media pages;
- generated SEO files such as sitemap, robots and llms files.

## Scope Requirements

CMS/content queries should consider:

```text
id_owner / id_user_business
site_key
site_visibility
brand_site_settings
published/status fields
noindex flags
```

Owner scope alone is not enough when one owner can expose more than one public brand.

## VNV Events CMS

`vnv-events` includes:

- CMS pages;
- blog posts and categories;
- location pages;
- public forums;
- SEO / AI Control Center at `/panel/seo-center`;
- public files `/sitemap.xml`, `/robots.txt`, `/llms.txt`, `/llms-full.txt`.

SEO generation includes public, published, indexable URLs from static pages, CMS, locations, Store records and public forums. It must exclude drafts, unpublished content, noindex content, panel, API, login and private routes.

## Avomeal Content

Avomeal public content should use:

```text
id_user_business=2
site_key=avomeal
site_visibility=VISIBLE
```

Avomeal app expects Store and product pages to show Avomeal-visible products only. The reviewed `VNV_Gourmet` web repo still contains legacy `VNV Gourmet` branding in CMS templates, so this is a known cleanup/compatibility area.

## Forum Rule

VNV Events forums are standalone public community content. They are not:

- private chat;
- order messaging;
- blog comments;
- Ophyra SaaS support tickets.

Forum quick signup creates a Level 5 account and returns the user to the thread. It must not create business accounts or Ophyra memberships.

## Generation Rule

Generated files must belong to the public brand domain that generated them.

Examples:

```text
VNV Events sitemap -> https://vnvevents.com/sitemap.xml
Avomeal sitemap -> https://avomeal.com/sitemap.xml, when implemented
```

Do not generate Avomeal URLs into VNV Events files unless a deliberate cross-brand landing page exists.

