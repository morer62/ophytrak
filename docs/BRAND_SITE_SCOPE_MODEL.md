# Brand Site Scope Model

## Purpose

This document separates business ownership from public brand/site identity.

The older ecosystem often used `id_owner` or `id_user_business` as the main boundary. That is still required, but it is not enough for brands that share an owner or database while exposing different public sites.

## Core Fields

| Field | Meaning |
| --- | --- |
| `id_owner` | Owner/company that owns operational records such as Store products, orders, tasks, payments and reports. |
| `id_user_business` | Mobile/public request alias for the business owner. Apps commonly send this value. |
| `business_id` | Mobile/public request alias for the business owner. Avomeal app sends this with `id_user_business`. |
| `site_key` | Public site/brand filter, for example `avomeal`. |
| `brand_key` | Optional brand identity key when a screen needs more than one site identity. |
| `site_visibility` | Public visibility boundary for content/products on a specific site. |
| `brand_site_settings` | Intended per-site configuration for brand, SMTP, payment, minimum order, sender identity and public defaults. |

## Ownership Versus Visibility

Ownership answers:

```text
Who owns this record operationally?
```

Site scope answers:

```text
Which public brand/site may show this record?
```

These are related but not identical.

Example:

```text
id_user_business = 2
site_key = avomeal
brand = Avomeal
```

Not every record owned by owner `2` should appear on Avomeal. Avomeal Store, CMS and mobile pages should also check `site_key` and public visibility when those fields/settings exist.

## Current Brand Scopes

| Brand/site | Owner scope | Site scope | Notes |
| --- | --- | --- | --- |
| VNV Events | owner `2` in reviewed mobile/backend docs | VNV Events public site at `vnvevents.com` | Event/service operation. |
| Avomeal | owner `2` in Avomeal app contract | `site_key=avomeal` | Food/store brand under shared VNV Events database ecosystem. |
| Ophyra | Ophyra Level 1/Level 2 owners | Ophyra platform identity | Commercial SaaS/platform. |
| Jonnys Media | future explicit integration | future site key | Do not model as a generic customer unless required. |

## Avomeal Rule

Avomeal mobile requires:

```text
id_user_business=2
business_id=2
site_key=avomeal
```

Backend Store/WebView routes opened by the app should filter Avomeal products, categories, orders and subscriptions by owner plus site visibility:

```text
id_owner or id_user_business = 2
site_key = avomeal
site_visibility = VISIBLE
```

During review, the Avomeal mobile app already sends this scope. The `VNV_Gourmet` web repo still appears mostly owner-scoped and contains legacy `VNV Gourmet` branding in some templates. Treat full `site_key` enforcement as a backend compatibility gap to verify before production release.

## VNV Events Rule

VNV Events uses owner `2` in the reviewed mobile app docs:

```text
BUSINESS_ID=2
MOBILE_OWNER_ID=2
```

VNV Events public pages, CMS content, forums, event requests and SEO files belong to the VNV Events brand and should not be labeled as Ophyra or Avomeal.

## Implementation Guidance

- Keep `id_owner` on operational tables.
- Add or honor `site_key` where one owner can expose multiple public brands.
- Do not show all owner-owned Store products on every brand site.
- Payment and SMTP settings should resolve first by site/brand when supported, then by owner fallback.
- Mobile apps must centralize business/site config and avoid scattering raw owner IDs or brand names.

