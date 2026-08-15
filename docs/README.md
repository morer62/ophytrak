# Ophyra Docs

## Start Here

This directory is the documentation hub for Ophyra and its sibling ecosystem.

Read in this order:

1. `AGENTS.md`
2. `REPOSITORY_ECOSYSTEM_MAP.md`
3. `ECOSYSTEM_OVERVIEW.md`
4. `OPHYRA_BUSINESS_MODEL.md`
5. `OPHYRA_2026_BUSINESS_STRATEGY_UPDATE.md`
6. `LEVEL_2_BUSINESS_ACCOUNT_MODEL.md`
7. `BRAND_SITE_SCOPE_MODEL.md`

Then read the relevant contract before changing a sensitive area:

- API/mobile endpoints: `API_ENDPOINTS_CONTRACT.md`
- WebView token flow: `WEBVIEW_TOKEN_CONTRACT.md`
- mobile apps: `MOBILE_APPS_CONTRACT.md`
- CMS/content simple overview: `CMS_SIMPLE_MODEL.md`
- Growth Hub SEO/CMS control panel: `ophyra-growth-hub.md`
- CMS/content identity contract: `CMS_CONTENT_IDENTITY_MODEL.md`
- Growth Hub route contract review: `OPHYRA_CMS_ROUTE_CONTRACT_REVIEW_2026_06_09.md`
- CMS replication guide for sibling brands: `GROWTH_HUB_CMS_REPLICATION_GUIDE.md`
- module slugs/pricing: `OPHYRA_MODULE_SLUGS.md`, `OPHYRA_PRICING_CONFIGURATION.md`
- affiliate/referral program: `OPHYRA_AFFILIATE_PROGRAM.md`
- payment/SMTP: `PAYMENT_AND_SMTP_SCOPE.md`
- notifications: `NOTIFICATIONS_CONTRACT.md`
- i18n/UI translation: `I18N_TRANSLATION_SYSTEM.md`
- contracts/signatures/time clock: `CONTRACT_SIGNATURE_ENGINE.md`, `CONTRACT_SECURITY_MODEL.md`

## Brand Context

- Ophyra: central platform and documentation/reference system.
- VNV Events: event operations brand at `vnvevents.com`.
- Avomeal: active food/store brand at `avomeal.com`; `VNV Gourmet` is legacy repo naming.
- Jonnys Media: professional/personal brand at `jonnys.media`; Growth Hub can manage SEO/CMS content for it when `site_key=jonnysmedia`.

## Current Growth Hub Targets

Growth Hub Search Console and SEO content planning should target:

- `vnvevents` -> `sc-domain:vnvevents.com`
- `avomeal` -> `sc-domain:avomeal.com`
- `jonnysmedia` -> `sc-domain:jonnys.media`

Do not use `ophyra.com` as a required Growth Hub Search Console target. Ophyra is the platform/admin system.

## Growth Hub Closure

The Growth Hub three-step workflow was QA-verified on 2026-06-06:

1. Opportunity Discovery saves selected keyword/market data.
2. Opportunities can be saved and used to fill the builder.
3. CMS Builder creates preview-ready drafts using DB-backed templates/categories and in-panel preview.

Templates and categories must remain database-backed through `cms_templates`, `cms_categories`, `cms_contents.id_template` and `cms_contents.id_cms_category`.

## Rule

Current business strategy: Service Operations is the active commercial workspace. Store + Logistics must be treated as `Coming soon` in Ophyra Level 2, Level 4 and Level 5 UI unless a brand-specific compatibility flow explicitly says otherwise. Read `OPHYRA_2026_BUSINESS_STRATEGY_UPDATE.md` before changing module navigation, billing, public copy, onboarding or metadata.

Before changing code, routes, database queries, emails, payment logic, API responses or mobile WebView behavior, identify owner scope, site/brand scope, user level and consumers.
