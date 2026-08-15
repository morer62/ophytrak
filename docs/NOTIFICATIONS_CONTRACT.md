# Notifications Contract

## Purpose

This document defines how mobile push notification registration and sending should work across branded apps.

## Expo Token Registration

Canonical endpoint:

```text
POST /api/set-expo-push-token
```

Accepted auth:

- `Authorization: Bearer <api_token>`;
- JSON/form `token`;
- JSON/form `api_token`.

Accepted Expo token fields:

```text
expo_push_token
expo_token
expoPushToken
pushToken
```

Valid token formats:

```text
ExponentPushToken[...]
ExpoPushToken[...]
```

Expected response:

```text
success
message
user_id
expo_token_saved
```

## Current Consumers

VNV Events app:

- requests/stores Expo token after login/panel load;
- sends bearer token;
- older docs mention `expo_token` during login.

Avomeal app:

- requests Expo permissions from the panel shell;
- posts `expo_push_token` and `api_token`;
- sends bearer token;
- currently does not always send site scope to the Expo endpoint.

## Backend Sending

Backend service:

```text
src/Services/NotificationService.php
```

Expo endpoint:

```text
https://exp.host/--/api/v2/push/send
```

The service should:

- skip empty/invalid Expo tokens;
- send title, body and optional data;
- log cURL or Expo rejected responses;
- never send to users outside the intended owner/site context.

## Scope Rules

Notification target selection should check:

- user ID;
- user level;
- owner/company scope;
- `clients_users` for Level 5 relationships;
- selected workspace for Level 4;
- site key for brand-specific Store/content events when supported.

Avomeal notifications should not be sent for VNV Events orders or content. VNV Events notifications should not be sent for Avomeal Store/subscription events.

## Event Categories

Likely notification senders:

- chat messages;
- team invitations;
- assigned service/event tasks;
- Store preparation/delivery assignments;
- Store delivery updates;
- Store order/subscription updates;
- event request intake;
- payment failure or support alerts, if later enabled.

## Compatibility Warnings

- Keep `/api/set-expo-push-token` route stable.
- Keep multiple Expo token field names supported.
- Bearer token should beat stale WebView session state.
- Add site/business scope fields rather than replacing existing payload keys.
- Real push QA requires a physical device or valid Expo token; PHP lint cannot prove delivery.

