# WebView Token Contract

## Purpose

The mobile apps use the backend web panel through a tokenized WebView bridge. This avoids a second login screen while keeping backend routes as the source of truth.

## Route Shape

Both mobile apps build URLs like:

```text
{webBaseUrl}Panel/Tokenapi/{token}/{internal-route}
```

The backend normalizes this to:

```text
panel/tokenapi/{token}/{internal-route}
```

Avomeal additionally appends scope:

```text
?id_user_business=2&business_id=2&site_key=avomeal
```

## Backend Responsibilities

`Panel/Tokenapi` should:

1. Read the token from the path.
2. Validate it with the same API-token logic used by mobile auth.
3. Load the matching user.
4. Create or refresh the PHP session for that user.
5. Mark the session as mobile app context where supported.
6. Preserve business/site scope into the redirected route context.
7. Redirect to the internal route.
8. Redirect to login or show a clear auth error if the token is invalid.

## Mobile Responsibilities

Mobile apps should:

- store the token in `AsyncStorage` key `Token`;
- use centralized WebView URL builders;
- avoid manually concatenating raw owner/site values in screens;
- enable geolocation for clock/delivery routes;
- detect login redirects or 401/403 responses and return users to native login when needed.

## VNV Events App Routes

VNV Events app opens:

```text
panel/planner-hub/orders/orders
panel/music-sessions
panel/planning-tools
panel/chat
panel/settings
panel/planner-hub/team/my-work
panel/planner-hub/team/payroll/clock
panel/planner-hub/team/payroll/pending
panel/planner-hub/team/orders/orders
panel/planner-hub/team/chat
```

Normal client signup must not expose owner/admin routes.

## Avomeal App Routes

Avomeal app opens:

```text
meal-plans
store/cart
store/checkout
panel/store/orders/home
panel/store/orders/home?intent=reorder
panel/store/subscriptions/home
panel/store/nutrition-advisor
panel/store/wellness-advisor
panel/settings
panel/planner-hub/team/store/orders/home
panel/planner-hub/team/my-work
panel/planner-hub/team/payroll/clock
panel/planner-hub/team/chat
panel/planner-hub/store/orders/home
```

Every Avomeal WebView URL should preserve:

```text
id_user_business=2
business_id=2
site_key=avomeal
```

## Token Expiration

Expected behavior:

- Backend redirects or returns a clear auth/permission error when the token is invalid.
- Mobile should clear `Token` and `UserData` when it detects a login redirect, 401 or 403.
- Avomeal app already has explicit WebView error handling for missing token, HTTP errors and login redirects.
- VNV Events app documentation still marks native expired-token cleanup as future work.

## Do Not Break

- Do not rename `Panel/Tokenapi`.
- Do not require mobile users to log in again inside WebView while the token is valid.
- Do not drop route query parameters such as `intent=reorder`.
- Do not ignore Avomeal `site_key` when filtering Store content.
- Do not expose internal admin routes to Level 5 mobile users.

