# Mobile Apps Contract

## Purpose

This document summarizes the mobile app contracts discovered from `vnv-mobile-app` and `vnv-gourmet-app`.

The apps are branded shells. They do not own the core business logic.

## Shared Mobile Rules

- Mobile signup creates or associates final clients only, normally Level 5.
- Mobile signup must not create Ophyra businesses, Level 1/2/3 users, memberships, modules, billing flows or affiliates.
- Mobile apps store the API token in `AsyncStorage` key `Token`.
- Mobile apps store user data in `AsyncStorage` key `UserData`.
- Mobile apps open protected backend pages through `Panel/Tokenapi/{token}/{route}`.
- Expo push tokens are sent to `/api/set-expo-push-token`.
- Google/Apple routes may exist for compatibility, but active UI in reviewed apps is email/password-first.

## VNV Events App

Repository:

```text
morer62/vnv-mobile-app
```

Confirmed scope:

```text
API_URL=https://ophyra.com/
BUSINESS_ID=2
MOBILE_OWNER_ID=2
brand=VNV Events
```

Confirmed owner from app docs:

```text
info@vnvevents.com
users.id = 2
level = 1
id_owner = 2
```

Primary consumers:

- `/api/auth/login`
- `/api/auth/signup`
- `/api/auth/validate-token`
- `/api/auth/forgot-password`
- `/api/set-expo-push-token`
- `/search/client_request/index`
- WebView routes for Level 4 and Level 5 panel pages.

Important behavior:

- Login sends `id_user_business=2` and `source=vnv_mobile_app`.
- Signup sends `level=5`, `id_user_business=2`, `source=vnv_mobile_app`.
- Level 5 clients may be associated through `clients_users`, not only `users.id_owner`.
- WebView uses geolocation for clock/task pages.

## Avomeal App

Repository:

```text
morer62/vnv-gourmet-app
```

Confirmed scope:

```text
EXPO_PUBLIC_BUSINESS_ID=2
EXPO_PUBLIC_SITE_KEY=avomeal
EXPO_PUBLIC_BRAND_NAME=Avomeal
EXPO_PUBLIC_API_BASE_URL=https://avomeal.com/
EXPO_PUBLIC_WEB_BASE_URL=https://avomeal.com/
```

The active app identity is:

```text
name = Avomeal
slug = avomeal-app
bundleIdentifier = com.vnvevents.avomeal
android.package = com.vnvevents.avomeal
```

Primary consumers:

- `/api/auth/login`
- `/api/auth/signup`
- `/api/auth/forgot-password`
- `/api/auth/validate-token`
- `/api/set-expo-push-token`
- optional legacy `/api/auth/google/signup-app`
- optional legacy `/api/auth/apple-signing/client-app`
- WebView routes for Store, cart, checkout, orders, subscriptions and advisor tools.

Important behavior:

- Every auth/customer request should include `id_user_business=2`, `business_id=2`, `site_key=avomeal`.
- Native Store/cart/checkout screens are not implemented; those flows use backend WebView.
- Checkout must use backend minimum-order settings and Avomeal payment provider.
- Store content must filter by owner plus Avomeal site visibility.

## Future Mobile Work

- Add native handling for expired WebView sessions in VNV Events app.
- Verify Avomeal backend Store/cart/checkout/subscription routes with real test accounts.
- Add native JSON endpoints only after documenting route, scope and response shape here and in `API_ENDPOINTS_CONTRACT.md`.

