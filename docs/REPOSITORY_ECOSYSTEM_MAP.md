# Repository Ecosystem Map

## Purpose

This document maps the repositories that currently orbit Ophyra and explains which system owns each surface.

Ophyra is the central documentation and technical reference for the ecosystem. It is not the public identity of every related brand.

## Repository Map

| Repository | Active identity | Main role | Notes |
| --- | --- | --- | --- |
| `morer62/ophyra` | Ophyra | Central operations platform, shared architecture, documentation hub | Current repository. |
| `morer62/vnv-events` | VNV Events | Public VNV Events web system and event operations panel | Real events business at `vnvevents.com`. |
| `morer62/VNV_Gourmet` | Avomeal | Avomeal food/store web system | Repo name is legacy; public brand should be Avomeal. |
| `morer62/vnv-mobile-app` | VNV Events app | Expo/React Native mobile shell for VNV Events | Uses `BUSINESS_ID=2`; WebView token handoff. |
| `morer62/vnv-gourmet-app` | Avomeal app | Expo/React Native mobile shell for Avomeal | Uses `id_user_business=2`, `site_key=avomeal`. |

## What Each Repo Handles

Ophyra handles:

- commercial web signup for Ophyra business customers;
- Level 1 global admin and assisted billing/module control;
- shared owner-scoped operations model;
- central documentation for API, mobile, WebView, Store, payments, SMTP and brand/site scope.

`vnv-events` handles:

- VNV Events public site and event-service identity;
- Level 1/4/5/6 VNV dashboards;
- orders, estimates, clients, team, payroll, chat, music sessions and event requests;
- CMS pages, blog posts, location pages, forums;
- SEO Center for `sitemap.xml`, `robots.txt`, `llms.txt` and `llms-full.txt`;
- Store routes where active for VNV.

`VNV_Gourmet` handles:

- Avomeal web storefront and Store/customer flows;
- Store products, categories, cart, checkout, payments, orders and subscriptions;
- Level 1 Avomeal admin, Level 4 kitchen/delivery/team work and Level 5 customers;
- SMTP and payment-provider settings scoped by owner;
- legacy references to VNV Gourmet that should be migrated carefully to Avomeal in user-facing surfaces.

`vnv-mobile-app` handles:

- VNV Events mobile login/signup/forgot password;
- Level 5 client signup only;
- Level 4 team access;
- Expo push token registration;
- native dashboard links into WebView routes through `Panel/Tokenapi/{token}/{route}`.

`vnv-gourmet-app` handles:

- Avomeal mobile login/signup/forgot password;
- Avomeal customer signup only, normally Level 5;
- WebView entry to Store, cart, checkout, customer orders, subscriptions and nutrition/wellness tools;
- Expo push token registration;
- scope params `id_user_business=2`, `business_id=2`, `site_key=avomeal`.

## Central Rule

Shared code history does not mean shared identity.

Before changing an endpoint, route, email, payment, Store query, CMS query or mobile JSON shape, identify:

- the repository that consumes it;
- the business owner scope;
- the public brand/site scope;
- the user level;
- whether the flow is native API, WebView, public web or panel.

