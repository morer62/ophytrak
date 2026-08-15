# Ophyra SEO metadata audit

Date: 2026-06-21

## Head and metadata architecture

- Public layout: `src/views/templates/base.twig`.
- Private/admin layout: `src/views/templates/base.admin.twig`.
- Metadata service: `src/Services/OphyraSeoService.php`.
- Landing page metadata source: `src/Services/OphyraLandingPageService.php`.
- Template renderer: `src/Utils/TemplateResponse.php` injects `seo` and `schemaJsonList` into Twig.
- Robots endpoint: `public/robots.txt.php`.
- Sitemap endpoint: `public/sitemap.xml.php`.

## Global metadata now used

- Title: `Ophyra | Modular Business Operations Platform`
- Description: `Run clients, teams, orders, contracts, products, fulfillment, delivery and reports from one modular business workspace. Start free and activate only the tools your operation needs.`
- Author: `Ophyra`
- Default robots for public pages: `index, follow`
- Default robots for private/internal pages: `noindex, nofollow`
- Default OG image: `https://ophyra.com/assets/images/planner-hub-logo-positive.png`

## Public pages audited

- `/`
- `/signup`
- `/affiliates`
- `/forum`
- `/support`
- `/privacy-policy`
- `/cookie-policy`
- `/data-processing-notice`
- `/terms-and-conditions`
- `/modules/service-operations`
- `/modules/store-logistics`
- `/modules/advanced-storage-qr-inventory`
- `/modules/ai-advisor`
- `/modules/ticket-sales-rsvp`
- `/modules/marketplace-connectors`
- `/business/project-based-businesses`
- `/business/service-businesses`
- `/business/retail-online-stores`
- `/business/logistics-delivery`
- `/business/consultants-agencies`
- `/business/venues-hospitality`
- `/business-profile/{slug}`

## Private routes marked noindex

- `/panel/`
- `/app/`
- `/api/`
- `/admin/`
- `/dashboard/`
- `/account/`
- `/profile/`
- `/client-portal/`
- `/team-view/`
- `/membership/`
- `/memberships/`
- `/subscriptions-manager/`
- `/module-manager/`
- `/payments/`
- `/checkout`
- `/cart`
- `/billing`
- `/payment`
- `/order-access/`
- `/commerce/order-access/`
- `/tickets/purchase/`
- `/commerce/tickets/purchase/`
- `/storage/private/`
- `/webhook/`
- `/cron/`
- `/reset-password`
- `/update-password`
- `/forgot_password`
- `/login`
- `/logout`

## OG assets confirmed

- `public/assets/images/planner-hub-logo-positive.png`
- `public/assets/public/ophyra/screens/dashboard-view.png`
- `public/assets/public/ophyra/modules/crm-hero.webp`
- `public/assets/public/ophyra/modules/inventory-storage-hero.webp`
- `public/assets/public/ophyra/modules/orders-operations-hero.webp`
- `public/assets/public/ophyra/modules/store-delivery-tracking-hero.webp`
- `public/assets/public/ophyra/modules/team-payroll-hero.webp`
- `public/assets/public/ophyra/industries/consultants-agencies-hero.webp`
- `public/assets/public/ophyra/industries/event-planners-hero.webp`
- `public/assets/public/ophyra/industries/logistics-delivery-hero.webp`
- `public/assets/public/ophyra/industries/service-providers-hero.webp`
- `public/assets/public/ophyra/industries/venues-hospitality-hero.webp`

## OG image fallback

`OphyraLandingPageService` now uses `assets/public/ophyra/screens/dashboard-view.png` if a landing page references a missing public image. This prevents OG/Twitter cards from pointing to 404 assets.

## Legacy positioning cleaned

- `Event Pros` removed from public metadata author fallbacks.
- Forum global CTA changed from event-only language to business operators and teams.
- Growth Hub no longer suggests `Local Event Pros` as a generic keyword pattern.
- Events remain valid only in event-specific modules, RSVP/ticketing, venues/hospitality and event operations contexts.

## Structured data

`OphyraSeoService` already provides:

- `Organization`
- `WebSite`
- `SoftwareApplication`
- `OfferCatalog`
- `WebPage`
- `BreadcrumbList`

No fake ratings, reviews or social profiles are generated.

## Notes

- Canonical URLs are generated from normalized routes and do not include query strings by default.
- The public layout preserves existing CSS, JS, favicons, cookie consent, language selector and schema rendering.
- The private admin layout now includes `noindex, nofollow` meta tags.
- Cache purge was not executed in this sprint. Purge deployment/CDN cache after pushing to production.
