# Level 2 Business Account Model

## Purpose

This document removes the old Venue/Vendor split from the active Ophyra business model.

## Current Rule

All active Ophyra businesses are Level 2.

Level 2 is the only active business-owner/account-owner level for new and current Ophyra business flows.

Level 3 is annulled for active business modeling. Do not use Level 3 for new businesses, vendor accounts, service-provider accounts, onboarding, billing, module access, dashboards, sidebars or operational routing.

## Historical Model

The old model was:

```text
Level 2 = Venue
Level 3 = Vendor / Service Provider
```

That model is no longer active.

## New Model

The active model is:

```text
Level 2 = Business Owner / Account Owner
Level 3 = legacy only / inactive for new Ophyra business flows
```

The business type is no longer represented by `users.level`.

Business identity should be represented by Business Profile fields such as:

```text
institution_profile.business_nature
institution_profile.business_operation_type
institution_profile.layout_type
```

or equivalent profile/module data.

Examples of business types that all remain Level 2:

* Venue / hospitality.
* Event planning / production.
* Service business.
* Logistics / delivery.
* Store / product business.
* Food / catering / meal-prep.
* Agency / consulting.
* Creative studio / media.

## Implementation Rules

* Web signup must create new business accounts as Level 2.
* Web signup must not create Level 3 users.
* The UI must not ask users to choose Venue vs Vendor as user level.
* The UI may ask what the business does and store that as profile data.
* Module access must be decided by owner-scoped modules and permissions, not by Level 2 vs Level 3.
* Routes and dashboards should be standardized around Level 2 business ownership.
* Do not mutate `users.level` to switch business type.
* Existing Level 3 folders may remain only for backward compatibility until intentionally removed.

## How To Handle Legacy Level 3 Code

Level 3 code may still exist in the repository because it was inherited from earlier Venue/Vendor architecture.

Do not extend Level 3 for new work.

If a task touches a Level 3 route, first verify whether the route is still reachable. Prefer redirecting, deprecating, or documenting it instead of adding new Level 3 behavior.

## Relationship With Other Levels

* Level 1 remains Ophyra Global Admin / central operations.
* Level 2 is every active business account.
* Level 4 is team member and can work for many Level 2 businesses through `user_institutions`.
* Level 5 is client/customer and can interact with many Level 2 businesses through `clients_users`.
* Level 6 remains marketing/CMS/specialized where used.

