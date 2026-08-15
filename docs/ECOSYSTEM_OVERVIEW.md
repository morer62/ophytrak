# Ecosystem Overview

## Purpose

This document explains how Ophyra, VNV Events, Avomeal / VNV Gourmet, Jonnys Media and the related mobile apps fit together.

The ecosystem shares history and technical patterns, but each project keeps its own business identity.

## Core Relationship

Ophyra is the parent/original system and the structural reference for many parts of the ecosystem.

Several related projects were created by copying, adapting or simplifying Ophyra code. That does not mean every project should behave exactly like Ophyra.

The correct mental model is:

```text
Ophyra = parent/reference platform and possible central admin layer
VNV Events = independent events business
Avomeal / VNV Gourmet = independent food, delivery and product business
Jonnys Media = personal/professional brand behind the ecosystem
Mobile apps = brand-specific mobile shells connected to web/backend systems
```

Current scope distinction:

```text
id_owner / id_user_business = operational ownership
site_key / brand_key = public brand or site identity
site_visibility = what content/products are public for that site
brand_site_settings = per-site brand/payment/SMTP/settings layer when present
```

This distinction is especially important for Avomeal:

```text
id_user_business = 2
site_key = avomeal
brand = Avomeal
```

Not every record owned by owner `2` should be visible on Avomeal.

## Repository Map

| Project | Repository | Domain / identity | Notes |
| --- | --- | --- | --- |
| Ophyra | Current repository | Ophyra | Modular operations platform and reference architecture. |
| VNV Events | `morer62/vnv-events` | `vnvevents.com` | Event operations, services, clients, team, orders and execution. |
| Avomeal / VNV Gourmet | `morer62/VNV_Gourmet` | `avomeal.com` | Food delivery, prepared meals, party kits, themed dinners and product orders. |
| Jonnys Media | Requested as `morer62/jonnys_media` | `jonnys.media` | Professional/personal brand of the developer and ecosystem leader. The repo was not visible to the current GitHub connector session. |
| Avomeal mobile app | `morer62/vnv-gourmet-app` | Avomeal app | Expo/React Native app, package name observed as `avomeal-app`. |
| VNV Events mobile app | `morer62/vnv-mobile-app` | VNV Events app | Expo/React Native app, package name observed as `vnv-events-app`. |

## Shared Technical Patterns

The web systems generally come from the same custom PHP/Twig/MySQL architecture:

* custom router/kernel,
* `src/views/` by panel level,
* `src/views/api/` for mobile/API/AJAX endpoints,
* repositories under `src/Repositories/`,
* services under `src/Services/`,
* MySQL/MariaDB tables scoped by fields like `id_owner`,
* public assets and uploads under `public/`.

The mobile apps are Expo/React Native apps that connect to backend/web systems through:

* login,
* signup,
* dashboard/navigation,
* WebView,
* stored auth token,
* Expo push notifications where configured.

## User Levels Across Projects

Ophyra keeps the broader platform model:

* Level 1: Ophyra Global Admin / super admin / central operations.
* Level 2: Business Owner / Account Owner for every active Ophyra business.
* Level 3: annulled for active business modeling; legacy/backward compatibility only.
* Level 4: team member.
* Level 5: client.
* Level 6: marketing / CMS / specialized marketing user.

Derived projects usually simplify the model:

* Level 1: owner/admin of that brand.
* Level 4: team member / employee / collaborator.
* Level 5: client.
* Level 6: marketing or special marketing user.

For derived projects, do not assume Level 2 or Level 3 are active business flows unless the project-specific code clearly uses them.

## Brand Boundaries

Each project can share architecture while still keeping separate:

* domain,
* public home,
* navigation,
* visual identity,
* assets,
* content,
* business logic,
* customer experience,
* operational priorities.

This is especially important for:

* VNV Events event-service workflows,
* Avomeal food/delivery/product workflows,
* Jonnys Media personal/professional positioning,
* Ophyra SaaS/platform administration.

## What To Read Before Editing

Start with `docs/AGENTS.md`, then read:

* `docs/REPOSITORY_ECOSYSTEM_MAP.md` for repo responsibilities,
* `docs/BRAND_SITE_SCOPE_MODEL.md` for owner/site identity separation,
* `docs/API_ENDPOINTS_CONTRACT.md` before changing API endpoints or JSON,
* `docs/WEBVIEW_TOKEN_CONTRACT.md` before changing `Panel/Tokenapi`,
* `docs/MOBILE_APPS_CONTRACT.md` before changing mobile-facing flows,
* `docs/OPHYRA_BUSINESS_MODEL.md` for Ophyra pricing/modules/access,
* `docs/VNV_EVENTS_PROJECT_CONTEXT.md` before touching VNV Events logic,
* `docs/VNV_EVENTS_OPHYRA_INTEGRATION_MODEL.md` before changing VNV/Ophyra shared behavior,
* `docs/AVOMEAL_PROJECT_CONTEXT.md` before touching Avomeal/VNV Gourmet logic,
* `docs/JONNYS_MEDIA_PROJECT_CONTEXT.md` before touching Jonnys Media content,
* `docs/MOBILE_APPS_ECOSYSTEM.md` before touching mobile app assumptions,
* `docs/MOBILE_API_NOTIFICATIONS_FLOW.md` before changing API/mobile responses,
* `docs/USER_COMPANY_ACCESS_MODEL.md` before changing owner/company/user relations.

## Rule

Shared origin does not mean shared identity.

Use Ophyra as the structural reference, but respect each project's public brand, business model, routes, data ownership and user experience.
