# OPHYTRACK product split

OPHYTRACK is the logistics-focused distribution of the Ophyra codebase. It uses the existing Ophyra database and keeps the same user, owner, team-member, customer, language, notification, chat and payment-provider foundations.

## Included product surface

- Store products, orders and manual sales.
- Fulfillment, labels, QR/package identifiers and package search.
- Seller-owned preparation and delivery teams.
- Carrier organizations, carrier warehouses and carrier-owned drivers.
- Package custody timeline, evidence photos, incidents, returns and delivery proof.
- Customer tracking and communication.
- Inventory/warehouse, CRM in store context and marketplace connectors.
- English, Spanish, Portuguese and French localization infrastructure.

## Excluded product surface

Service orders, contracts, appointments/calendars, venues, service vendors, event invitations, ticketing and the AI advisor are not part of OPHYTRACK. They remain in Ophyra Services. OPHYTRACK filters these modules from catalogs and onboarding, rejects their checkout on the server, removes their navigation and redirects legacy direct routes.

## Runtime profile

Set `APP_PRODUCT=ophytrack`. `ProductProfileService` is the single product boundary used by routing, module catalogs, billing and templates. The deployment may use the same database credentials as Ophyra; no schema fork or data copy is required.

## Brand assets

- `public/assets/ophytrack/ophytrack-logo.svg`: transparent horizontal production logo.
- `public/assets/ophytrack/ophytrack-mark.svg`: compact navigation mark.
- `public/assets/ophytrack/ophytrack-logo-hero.png`: generated visual derived from the approved right-side concept.
- `public/assets/video/intro.mp4`: retained home video.

## Deployment

1. Copy `.env.example` to `.env` and retain the existing database credentials.
2. Set the final `APP_URL` and keep `APP_PRODUCT=ophytrack`.
3. Run `composer install` and `npm install`.
4. Point the web root at this repository and preserve the existing rewrite configuration.
5. Configure the hourly marketplace sync cron already included in the project when production credentials are available.

This product extraction does not introduce database migrations or require new SQL.
