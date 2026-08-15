# API Endpoints Contract

## Purpose

This document is the compatibility contract for endpoints consumed by Ophyra, VNV Events, Avomeal and the mobile apps.

Do not rename routes or remove JSON fields without checking the consuming repositories:

```text
morer62/vnv-mobile-app
morer62/vnv-gourmet-app
morer62/vnv-events
morer62/VNV_Gourmet
```

## Contract Template

Every endpoint should be documented with:

```text
Route
Method
Purpose
Auth required
Required params
Business scope
Site scope
Response shape
Consumers
Compatibility warning
Related files
```

## Auth Endpoints

### `/api/auth/login`

- Method: `POST`
- Purpose: email/password mobile and web login.
- Auth required: no.
- Required params: `email`, `password`.
- Business scope: mobile apps send `id_user_business`; Avomeal also sends `business_id`.
- Site scope: Avomeal app sends `site_key=avomeal`; VNV Events app does not currently require a site key.
- Response shape: preserves `success`, `message`, `token`, `api_token`, `user`; some apps tolerate `data.token` and `data.user`.
- Consumers: VNV Events app `SignInScreen`, Avomeal app `SignInScreen`, web login.
- Compatibility warning: do not remove `token`; mobile stores it in `AsyncStorage` key `Token`.
- Related files: `src/views/api/auth/login.php`, `src/Services/ApiAuthService.php`.

### `/api/auth/signup`

- Method: `POST`
- Purpose: mobile final-client signup for branded apps.
- Auth required: no.
- Required params: `name`, `lastname`, `email`, `phone`, `password`, `passwordConfirmation`, `level=5`.
- Business scope: accepts `id_user_business`, `id_owner`, `business_id` or `owner_id`.
- Site scope: Avomeal app sends `site_key=avomeal`.
- Response shape: `success`, `message`, `token`/`api_token`, `user`; apps tolerate root or `data` wrapper.
- Consumers: VNV Events app, Avomeal app.
- Compatibility warning: must not create Level 1/2/3 users, memberships, modules or affiliate records from mobile.
- Related files: `src/views/api/auth/signup.php`, `clients_users`.

### `/api/auth/validate-token`

- Method: `POST`
- Purpose: validate stored mobile/API token and return normalized user.
- Auth required: token in body, JSON, form, query fallback or `Authorization: Bearer`.
- Required params: `token` or `api_token`.
- Business scope: Avomeal app sends owner and site scope with the token.
- Site scope: optional today, required for Avomeal WebView-aware flows.
- Response shape: `success`, `message`, `user`.
- Consumers: both mobile apps, WebView/session restoration flows.
- Compatibility warning: bearer token should take priority over stale PHP sessions.
- Related files: `src/views/api/auth/validate-token.php`, `src/Services/ApiAuthService.php`.

### `/api/auth/forgot-password`

- Method: `POST`
- Purpose: password reset request.
- Auth required: no.
- Required params: `email`.
- Business scope: mobile apps send `id_user_business`; Avomeal sends `business_id`.
- Site scope: Avomeal sends `site_key=avomeal`.
- Response shape: `success`, `message`; may include OAuth-account hints.
- Consumers: both mobile apps.
- Compatibility warning: reset emails should use the correct brand/site sender when SMTP scope supports it.
- Related files: `src/views/api/auth/forgot-password.php`.

### Legacy Google/Apple Mobile Signup

- Routes: `/api/auth/google/signup-app`, `/api/auth/apple-signing/client-app`.
- Method: `POST`.
- Purpose: legacy/optional client app signup.
- Required params: provider token plus `accountType=client` and `level=5`.
- Consumers: present in Avomeal route constants but Google/Apple UI is currently disabled in active app flows.
- Compatibility warning: keep routes stable, but do not treat them as active commercial Ophyra signup.

## Mobile Runtime Endpoints

### `/api/version`

- Method: `GET`.
- Purpose: app version check.
- Consumers: both mobile apps.
- Compatibility warning: apps compare backend version to local `APP_VERSION`; force-update logic may be enabled later.

### `/api/set-expo-push-token`

- Method: `POST`.
- Purpose: save Expo push token on `users.expo_token`.
- Auth required: bearer token or token body.
- Required params: one of `expo_push_token`, `expo_token`, `expoPushToken`, `pushToken`.
- Business scope: not always sent by apps today.
- Site scope: should be added for brand-specific notification routing where supported.
- Response shape: `success`, `message`, `user_id`, `expo_token_saved`.
- Consumers: both mobile apps.
- Compatibility warning: accept multiple Expo field names and validate token format.
- Related files: `src/views/api/set-expo-push-token.php`, `src/Services/NotificationService.php`.

## WebView Token Entry

### `/Panel/Tokenapi/{token}/{route}`

- Method: `GET`.
- Purpose: mobile WebView token handoff into protected backend pages.
- Auth required: token path segment.
- Business scope: Avomeal app appends `id_user_business=2&business_id=2`.
- Site scope: Avomeal app appends `site_key=avomeal`.
- Response shape: creates PHP session then redirects to internal route.
- Consumers: both mobile apps.
- Compatibility warning: do not remove or rename this route; it avoids double login.
- Related files: `src/Kernel.php`, `LoginService::validateToken`.

## Store / Commerce WebView Routes

These are not native JSON APIs today. The apps open backend web pages through Tokenapi.

| Route | Purpose | Consumers |
| --- | --- | --- |
| `/meal-plans` | Avomeal storefront entry | Avomeal app |
| `/store/cart` | Store cart | Avomeal app |
| `/store/checkout` | Store checkout | Avomeal app |
| `/panel/store/orders/home` | Level 5 Store orders | Avomeal app |
| `/panel/store/orders/home?intent=reorder` | Reorder entry point | Avomeal app |
| `/panel/store/subscriptions/home` | Level 5 subscriptions | Avomeal app |
| `/panel/store/nutrition-advisor` | Avomeal nutrition tool | Avomeal app |
| `/panel/store/wellness-advisor` | Avomeal wellness tool | Avomeal app |
| `/panel/planner-hub/team/store/orders/home` | Level 4 Store work | Avomeal app |

Store pages must revalidate product visibility, owner scope, stock, price, cart and payment provider server-side.

## VNV Events WebView Routes

| Route | Purpose | Consumers |
| --- | --- | --- |
| `/panel/planner-hub/orders/orders` | Level 5 client orders | VNV Events app |
| `/panel/music-sessions` | Music sessions | VNV Events app |
| `/panel/planning-tools` | Planning tools | VNV Events app |
| `/panel/chat` | Client chat | VNV Events app |
| `/panel/planner-hub/team/my-work` | Level 4 work hub | VNV Events app |
| `/panel/planner-hub/team/payroll/clock` | Level 4 clock/geolocation | VNV Events app |
| `/panel/planner-hub/team/orders/orders` | Level 4 job schedule | VNV Events app |
| `/panel/planner-hub/team/chat` | Level 4 chat | VNV Events app |

## CMS / Content / SEO

CMS and SEO are mostly web/panel routes, not mobile JSON APIs.

Important VNV Events routes/files:

- `/panel/seo-center`
- `/forums/`
- `/forums/{slug}/`
- `/sitemap.xml`
- `/robots.txt`
- `/llms.txt`
- `/llms-full.txt`

Public content must exclude drafts, private routes, panel routes, login and API routes from SEO files.

### Brand Receiver: `/api/ophyra/growth-hub/sitemap-refresh`

- Method: `POST`.
- Purpose: let Ophyra notify a public brand site that Growth Hub published, updated or archived content and the public `sitemap.xml` should be rebuilt.
- Auth required: `Authorization: Bearer {receiver_sitemap_token}`.
- Required params: JSON body with `event`, `site_key`, `environment`, `ophyra_sitemap_url`, `ophyra_routes_url`, `changed_at`; `content_id`, `route` and `canonical_url` are included when the event is page-specific.
- Business scope: brand repo validates its own configured `site_key`.
- Site scope: `vnvevents`, `avomeal` or `jonnysmedia`.
- Response shape: JSON such as `{"ok":true,"site_key":"vnvevents","urls_written":42,"sitemap_url":"https://vnvevents.com/sitemap.xml"}`.
- Consumers: VNV Events, Avomeal and Jonnys Media public repositories.
- Compatibility warning: production receiver endpoint is required before relying on Growth Hub production publishing. Development Ophyra URLs must not update production sitemaps.
- Related files: `docs/GROWTH_HUB_BRAND_SITE_ADAPTATION_GUIDE.md`, `src/Services/GrowthHubService.php`.

### Route Check: `/api/growth-hub/route-check`

- Method: `GET`
- Purpose: validate whether a `route` is available before a brand publish/rollback cycle.
- Query:
  - `site_key=vnvevents|avomeal|jonnysmedia`
  - `route=/blog/slug|/locations/slug|/slug`
- Response example: `{"available":false,"reason":"existing_cms_route","matched_route":"/blog/example/","site_key":"vnvevents","priority":"protected_existing_page"}`
- Consumers: Ophyra publish workflow, brand repository route validators.

## Critical Warnings

- Mobile signup is Level 5 only.
- Do not remove `api_token` or `token`.
- Add JSON fields instead of renaming or deleting existing fields.
- WebView routes are real consumers even when there is no JSON endpoint.
- Avomeal requires owner scope plus `site_key=avomeal` for public Store visibility.
- VNV Events public CMS/forum/SEO content belongs to VNV Events, not Ophyra.
