# OPHYRA_BUSINESS_MODEL.md

## 1. Purpose of this document

This document defines the current business model for Ophyra.

It should be used as the source of truth for:

* Product positioning.
* Pricing.
* Free starter workspace.
* Add-on modules.
* Monthly billing.
* Prorated add-ons.
* Signup strategy.
* Sidebar and dashboard behavior.
* Referral commissions.
* Level 2 / Level 3 transition.
* Public website copy.
* Future product decisions.

This document complements `docs/AGENTS.md`.

`docs/AGENTS.md` is the ecosystem entry point and explains how Ophyra relates to VNV Events, Avomeal / VNV Gourmet, Jonnys Media and the related mobile apps.

This document explains what Ophyra sells and how the product should behave commercially.

For the broader ecosystem map, also read:

```text
docs/ECOSYSTEM_OVERVIEW.md
```

Commercial launch readiness for signup, payments, modules and affiliates is tracked in:

```text
docs/OPHYRA_COMMERCIAL_LAUNCH_READINESS.md
docs/OPHYRA_IMMEDIATE_LAUNCH_TASKS.md
```

---

## 2. What Ophyra is

Ophyra is a modular business operations platform with Service Operations as the current active commercial workspace.

Ophyra helps businesses manage:

* Leads.
* Clients.
* Team members.
* Orders.
* Contracts.
* E-signatures.
* Internal chat.
* Basic payroll.
* Business profile.
* Operational dashboards.
* Inventory.
* Storage.
* Store operations as a future/coming-soon workspace.
* Delivery as part of the Store + Logistics roadmap.
* Tracking as part of the Store + Logistics roadmap.
* Fulfillment as part of the Store + Logistics roadmap.
* Add-on modules as the business grows.

Ophyra should not be positioned as:

* Planner Hub.
* A venue/vendor marketplace.
* Software only for events.
* Software only for venues.
* Software only for vendors.
* A generic CRM.
* A free trial product.
* A multidomain platform in this phase.
* A tool only for service providers.
* A tool only for online stores.

Main positioning:

> Ophyra is a modular operations platform for businesses that manage clients, orders, teams, inventory, delivery, and daily operations.

Strategic message:

> Start with the free Base Profile. Activate the operational modules your business needs next.

---

## 2.1 2026 strategy update

The current business strategy is documented in `docs/OPHYRA_2026_BUSINESS_STRATEGY_UPDATE.md` and overrides older commercial language in this file when there is conflict.

Current rule:

* Service Operations is the primary active paid workspace.
* Store + Logistics is a visible `Coming soon` workspace for Ophyra Level 2, Level 4 and Level 5.
* Marketplace Connectors remains an active separate paid add-on.
* Store tables, routes and brand-specific flows may remain for compatibility, Avomeal/VNV Gourmet, Level 1/admin testing or future roadmap.
---

## 3. Strategic background

Ophyra evolved from an internal architecture used to build projects such as:

* VNV Events.
* Avomeal.
* jonnys.media.
* E-Planner Hub / Planner Hub.

Those projects already exist and can continue operating independently.

Avomeal can also be operated from the centralized Ophyra database as a separate Business Operation when it is scoped to its own owner/company and uses Store + Logistics with nutrition, weekly menu and meal-subscription extensions. That model is documented in `docs/AVOMEAL_OPHYRA_INTEGRATION_MODEL.md`.

Ophyra should now evolve as its own software product.

The current strategy is not to force all existing projects into one complex multidomain system.

The current strategy is:

* Keep existing launched projects independent when that is safer.
* Allow Avomeal to run as a separated Business Operation inside Ophyra when using owner/company scope, not a new Level 1.
* Build Ophyra as a clean modular SaaS product.
* Avoid multidomain / tenant complexity for now.
* Focus on what already works.
* Improve signup, navigation, billing, modules, dashboard and product clarity.
* Remove old Venue/Vendor pricing logic from the new Ophyra model.
* Use Level 2 as the standard business owner level for new accounts.
* Treat Level 3 as legacy only.
* Position Ophyra beyond events, without becoming generic.
* Focus current commercial execution on service businesses and operations-heavy companies, while keeping commerce/logistics as roadmap and brand-specific compatibility.

---

## 4. Ideal customer

Ophyra is designed for businesses that manage clients, orders, team members, inventory, delivery, fulfillment or daily operations.

Ophyra is especially useful for businesses that currently depend on disconnected tools like WhatsApp, spreadsheets, emails, notes, calendars, payment apps, inventory sheets and manual follow-up.

Examples:

* Event planners.
* Catering companies.
* Venues.
* Production companies.
* Creative studios.
* Agencies.
* Consultants.
* Local service providers.
* Meal prep businesses.
* Delivery-based businesses.
* Wholesale stores.
* Small distributors.
* Product-based businesses with local delivery.
* Businesses that manage orders and fulfillment.
* Businesses with storage, inventory or stock movement.
* Businesses with internal teams and recurring operations.

Main category:

> Businesses that manage clients, teams, orders, inventory, delivery or operational workflows.

This keeps Ophyra focused while allowing it to serve more than only service providers.

---

## 5. Current commercial model

Ophyra uses a simple monthly subscription model.

There is one free Base Profile, Service Operations as the active paid operating workspace, optional paid add-on modules, and Store + Logistics as a coming-soon workspace.

There is no free trial for paid add-on modules without payment.

The basic public Business Profile can be created and completed without payment. Paid operational tools require the corresponding active core module or add-on.

Commercial flow:

1. The user creates a business account from the web signup.
2. The user can complete the basic public Business Profile.
3. The system authenticates the user automatically and sends them to `/panel/onboarding`.
4. The user starts with Ophyra Base Profile for free.
5. The user can activate Service Operations or optional active add-ons. Store + Logistics is visible as Coming soon until it is commercially reactivated.
6. Active modules appear in the sidebar and dashboard.
7. Inactive modules should not appear as available operational tools.
8. If a user tries to access an inactive module directly by URL, backend access should block or redirect.
9. Add-ons should align to the main subscription renewal date.
10. Add-ons should be prorated when activated mid-cycle.

The business account is the operational scope. Team Members may work for multiple companies through company relationships, and clients may buy from or hold orders with multiple companies. The technical access model is documented in `docs/USER_COMPANY_ACCESS_MODEL.md`.

---

## 6. Official `.env` business pricing

The `.env` must use the clean Ophyra pricing model.

No legacy Planner Hub, Venue or Vendor pricing variables should be used for new Ophyra billing.

Approved `.env` variables:

```env
# Ophyra Business Plan Configuration

# Free starter workspace
OPHYRA_FREE_STARTER_BASE=true
OPHYRA_BASE_PROFILE_PRICE=0.00
OPHYRA_BASE_MONTHLY=0.00

# Paid modules require payment or approved manual activation
FREE_MEMBERSHIP_DAYS=0

# Core paid modules
SERVICE_OPERATIONS_PRICE=24.00
STORE_LOGISTICS_PRICE=34.00
OPHYRA_MODULE_SERVICE_OPERATIONS_MONTHLY=24.00

# Optional paid modules
ADVANCED_STORAGE_QR_INVENTORY_PRICE=15.00
AI_ADVISOR_PRICE=9.00
TICKET_SALES_RSVP_PRICE=15.00
OPHYRA_MODULE_INVENTORY_STORAGE_MONTHLY=15.00
OPHYRA_MODULE_ADVANCED_STORAGE_QR_INVENTORY_MONTHLY=15.00
OPHYRA_MODULE_STORE_DELIVERY_TRACKING_MONTHLY=34.00
OPHYRA_MODULE_AI_ADVISOR_MONTHLY=9.00
OPHYRA_MODULE_TICKETS_RSVP_MONTHLY=15.00
MARKETPLACE_CONNECTORS_PRICE=12.00
OPHYRA_MODULE_MARKETPLACE_CONNECTORS_MONTHLY=12.00

# Future module
CUSTOM_DOMAIN_SEO_PAGE_BUILDER_STATUS=coming_soon

# Billing rules
OPHYRA_BILLING_CYCLE=monthly
OPHYRA_ADDONS_PRORATE=true
OPHYRA_USE_SINGLE_RENEWAL_DATE=true

# Stripe Billing Automation safety switches
STRIPE_WEBHOOK_SECRET=
STRIPE_WEBHOOK_HARD_DISABLE=false
STRIPE_WEBHOOK_LOG_ONLY=false
```

Do not use new legacy variables.

Do not reintroduce:

* `VENUE_PAYMENT_AMOUNT`
* `SERVICE_PAYMENT_AMOUNT`
* `FREE_DAYS_BEFORE_RENEWAL_LISTING`
* `FREE_DAYS_BEFORE_RENEWAL_PLANNER_HUB`
* `MEMBERSHIP_PLANNER_HUB_MONTH`
* `MEMBERSHIP_PLANNER_HUB_ANUAL`
* `VENUE_PAYMENT_AMOUNT_WITH_MEMBERSHIP_DISCOUNT`
* `SERVICE_PAYMENT_AMOUNT_WITH_MEMBERSHIP_DISCOUNT`
* `MEMBERSHIP_PLAN_MONTHLY`
* `MEMBERSHIP_PLAN_QUARTERLY`
* `MEMBERSHIP_PLAN_ANNUAL`

If old code still references those variables, that code should be audited and updated to the new Ophyra variables.

---

## 7. Ophyra Base Profile

Ophyra Base Profile is the free starting layer.

Official price:

```text
Ophyra Base Profile: $0/month
```

Ophyra Base Profile includes:

* Basic public Business Profile.
* Limited starter workspace.
* Default Level 2 business-owner signup path.
* Visibility into available paid modules.

Service Operations flow:

```text
Lead â†’ Client â†’ Order â†’ Contract â†’ Signature â†’ Team Execution â†’ Basic Payroll
```

The base layer should not require payment or renewal.

It should remain accessible even when a paid module expires.

Ophyra Base Profile is not the operational core. The active operational core today is Service Operations. Store + Logistics remains documented as a future/coming-soon workspace.

CRM, Team, payroll basics, communications and reports are unlocked by Service Operations in the current commercial model. They are not sold as separate modules.

---

## 8. Paid modules

Paid modules are optional monthly modules.

Current approved paid modules:

| Module                           | Price source                                   | Description                                                                                  |
| -------------------------------- | ---------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Service Operations               | `SERVICE_OPERATIONS_PRICE`                     | CRM, clients, service orders, contracts, team, payroll basics, communication and reports.     |
| Store + Logistics                | `STORE_LOGISTICS_PRICE` / coming-soon status   | Roadmap workspace for online store, products, orders, fulfillment, delivery, tracking, basic inventory and reports. Not active for new Level 2/4/5 Ophyra UI in this phase. |
| Advanced Storage / QR Inventory  | `ADVANCED_STORAGE_QR_INVENTORY_PRICE`          | Containers, physical items, QR labels, storage locations and advanced inventory operations.   |
| AI Advisor                       | `AI_ADVISOR_PRICE`                             | Ideas, summaries, recommendations, content support and operational guidance.                  |
| Ticket Sales + RSVP              | `TICKET_SALES_RSVP_PRICE`                      | Event registrations, RSVP and ticket sales. Ophyra does not charge a ticket fee.             |
| Marketplace Connectors           | `MARKETPLACE_CONNECTORS_PRICE`                 | Mercado Libre, TikTok Business / Shop and Shopify tokens, manual sync and status mapping.    |
| Custom Domain + SEO Page Builder | `CUSTOM_DOMAIN_SEO_PAGE_BUILDER_STATUS`        | Future custom-domain, SEO and page builder layer. No price should be shown yet.              |

Paid modules should only become active after successful payment or approved manual activation from admin.

New signup safeguard:

```text
A new Level 2 business owner does not receive paid modules automatically.
```

The new account can access onboarding, business profile, billing, payment methods, support and the module catalog. Paid modules must remain locked unless one of these conditions is true:

* the user paid for the module;
* Level 1 manually activated it;
* Level 1 gifted it;
* there is an explicitly documented trial for that module.

If there is no active, valid `user_modules` row for a paid module, the system must treat that module as inactive and block direct Level 2 URL access.

Marketplace Connectors is a separate paid module from Store + Logistics. It may prepare and map external orders, external statuses and public tracking links, but it must not be bundled automatically with Store + Logistics unless Level 1 explicitly activates it or the user pays for it.

---

## 9. Store, delivery and logistics positioning

Ophyra should not be described only as a service-business platform.

The Store + Logistics workspace remains important as a roadmap and compatibility area because it allows Ophyra to serve businesses that sell, move, prepare, package, deliver or fulfill products. It is not the current active commercial workspace for new Ophyra Level 2/4/5 users.

This includes:
 
*   delivery operations.
* Wholesale stores.
* Small distributors.
* Local commerce businesses.
* Product-based businesses with delivery.
* Businesses managing orders and fulfillment.
* Businesses that need tracking visibility.
* Businesses that need internal control over store orders and delivery status.

The commercial message should make this clear:

> Ophyra starts with Service Operations, then lets businesses add specialized modules. Store + Logistics remains on the roadmap as a coming-soon workspace.

Store fulfillment should remain operationally connected to Team. Service/event tasks and Store tasks keep their correct tables, but Team Members should see a unified `My Work` experience. Store payment status must stay separate from preparation, fulfillment and delivery status. Tracking is allowed only during active assigned work.

This allows Ophyra to serve both:

* Service operations.
* Commerce and logistics operations.

Do not position Ophyra as only a planner, vendor, event or service provider tool.

---

## 10. Inventory positioning

Advanced Storage / QR Inventory is an add-on because not every business needs advanced inventory and physical storage operations.

However, for certain businesses it is critical.

Examples:

* Event rental companies.
* Production companies.
* Warehouses.
* Storage-based operations. 
* Wholesale stores.
* Product-based local businesses.
* Companies managing physical items, containers, equipment or stock.

The inventory module should be positioned as a way to keep physical operations organized.

Core message:

> Track what you own, where it is, and how it connects to your operation.

Inventory should connect naturally with:

* Orders.
* Store.
* Delivery as part of the Store + Logistics roadmap.
* Storage locations.
* QR labels.
* Internal team tasks.

---

## 11. Modules that are not separate products right now

The following should not be treated as separate paid modules in the current model:

* WhatsApp.
* Advanced Chat.
* Advanced File Management.
* Advanced Reports.
* Automations.

### WhatsApp

WhatsApp is not part of the current Ophyra module strategy.

It may be reconsidered in the future, but it should not be promoted or sold as an active Ophyra add-on right now.

### Advanced Chat

Basic chat is part of Team.

Do not sell â€œAdvanced Chatâ€ as a separate module.

### Advanced File Management

This is not clearly defined.

Do not introduce it as a module until its scope is defined.

### Advanced Reports

There should not be a separate â€œAdvanced Reportsâ€ module at this stage.

Each active module should include useful and visually polished reports by default.

Examples:

* CRM should show useful lead and follow-up information.
* Orders should show order status, activity and revenue-related summaries.
* Team should show team activity and basic payroll information.
* Inventory should show inventory-related summaries if the add-on is active.
* Store + Logistics reports should remain documented for future/brand-specific compatibility, but should not appear as active Level 2/4/5 functionality while the workspace is coming soon.

### Automations

Automations are not currently defined as a product module.

Do not introduce or sell this module until the scope is clearly defined.

---

## 12. Monthly billing

Ophyra is monthly-first.

All current pricing is monthly.

The platform should not prioritize annual, quarterly or launch-phase pricing in this model.

Starter workspace:

```text
Ophyra Base Profile: $0/month
```

Paid modules:

```text
Service Operations: $24/month
Store + Logistics: Coming soon
Advanced Storage / QR Inventory: +$15/month
AI Advisor: +$9/month
Ticket Sales + RSVP: +$15/month
Custom Domain + SEO Page Builder: Coming Soon
```

The payment model should remain simple and easy to understand.

---

## 13. Main renewal date

The main renewal date comes from the first paid add-on, or from a paid base subscription if Ophyra reintroduces one later.

Current existing logic already uses:

* `users.membership_due_date`
* `users.membership_type`
* `payments_all`
* `UserRepository::updateMembershipAndRegisterPayment()`

For now, do not remove this existing membership logic.

The new module system should build on top of it.

Example:

If the user pays Ophyra Base on June 2:

```text
Main renewal date: July 2
Billing day: 2
```

All add-ons should align to that same renewal date.

---

## 14. Prorated add-ons

When a user activates an add-on in the middle of an existing paid module cycle, the add-on can be prorated until the next paid renewal date. If there is no paid cycle yet, the add-on starts its own monthly renewal date.

Example:

* User pays Ophyra Base on June 2.
* Next renewal date is July 2.
* User activates Advanced Storage / QR Inventory on June 15.
* Inventory should be charged only from June 15 to July 2.
* On July 2, Ophyra Base and Inventory should renew together.

Goal:

```text
One billing cycle per user.
```

Rules:

* The first paid add-on defines the renewal date when there is no paid base subscription.
* Add-ons align to the base renewal date.
* Add-ons activated mid-cycle should be prorated.
* Add-ons activate after successful payment.
* Add-ons should not create separate billing cycles.
* Add-on payment history must be stored.
* If payment fails, the add-on should not become active or should remain pending.
* If an add-on is canceled, it may remain active until the end of the paid cycle, depending on the cancellation rule implemented.

---

## 15. Referral program

Ophyra has a referral / affiliate area that should remain part of the business model.

Referral commissions are managed from the admin area.

Commission percentage may vary depending on the admin configuration.

Supported commission percentages:

* 30%
* 40%
* 50%

Rules:

* Referral commissions should be calculated based on the percentage configured in the admin area.
* The commission percentage should not be hardcoded.
* Existing referral / affiliate logic should not be removed.
* If membership payment logic is updated, referral commission creation must still be considered.
* If add-on payments become commissionable, that rule must be explicitly defined before implementation.
* Base subscription commissions and add-on commissions may have different rules if needed in the future.

Current principle:

```text
Referral commissions must follow the admin configuration.
```

Do not assume a fixed commission percentage.

---

## 16. Technical module structure

The SQL for the initial module system has already been applied.

Current new tables:

* `modules`
* `user_modules`

Current new fields in `institution_profile`:

* `business_nature`
* `business_operation_type`

The intended technical flow:

1. `modules` defines available modules.
2. `user_modules` defines active add-ons by user.
3. Only Base Profile is included in the free starter workspace.
4. Paid modules are active only if present and active in `user_modules`.
5. Sidebar should check module access.
6. Dashboard should show active modules and available add-ons.
7. Backend should block access to inactive modules.

Recommended future code pieces:

* `ModulesRepository`
* `UserModulesRepository`
* `ModuleAccessService`

Do not put all module logic inside `UserRepository`.

`UserRepository` already handles users, memberships, clients, tokens, associated clients, institution logic and more.

Module access should be separated.

---

## 17. Signup strategy

Historically:

```text
Level 2 = Venue
Level 3 = Vendor / Service Provider
```

New model:

```text
Level 2 = Business Owner / Account Owner
Level 3 = Annulled for active business modeling / legacy only
```

Rules:

* New Ophyra business accounts should be created as Level 2.
* New signup should not ask Venue vs Vendor.
* New signup should not create Level 3 users.
* Level 3 should not be used for new dashboards, sidebars, onboarding, billing, modules or operational routing.
* Level 3 should not be deleted yet if legacy routes/data still exist, but it is not an active business category.
* Business type should be stored as business profile data, not as user level.

Signup should ask:

```text
What best describes your business?
```

Suggested values:

* Event Planning / Production.
* Catering / Food Service.
* Venue / Physical Location.
* Creative Studio / Media.
* Agency / Consulting. 
* Local Service Provider.
* Wholesale / Distribution.
* Retail / Local Store.
* Other.

Signup may also ask:

```text
How does your business operate?
```

Suggested values:

* I provide services at client locations.
* I have a physical location.
* I do both.
* I operate mostly online.
* I manage products, inventory or deliveries.
* I sell products and fulfill orders.

Store these in:

* `institution_profile.business_nature`
* `institution_profile.business_operation_type`

---

## 18. Dashboard and sidebar behavior

The dashboard and sidebar must be based on:

* User level.
* Base membership status.
* Active modules.
* Permissions.
* Role.

They should not rely only on user level.

Dashboard should show:

* Free Base Profile status.
* Active paid modules.
* Available add-ons.
* Clear explanation of what the user can unlock.
* Useful summaries from active modules.
* Reports that look good by default.
* Suggested modules based on business nature when appropriate.

Examples:

* If a user selects Delivery, the dashboard may show Store + Logistics as Coming soon and may suggest Service Operations or Advanced Storage when relevant.
* If a user selects Wholesale / Distribution, the dashboard may suggest Advanced Storage / QR Inventory and show Store + Logistics as Coming soon.
* If a user selects Event Planning / Production, the dashboard may suggest Ticket Sales + RSVP.
* If a user selects Venue / Physical Location, the dashboard may suggest Ticket Sales + RSVP and Advanced Storage / QR Inventory.
* If a user selects Creative Studio / Media, the dashboard may focus first on CRM, Clients, Orders and Contracts.

Sidebar should show:

* Free routes: Home, Onboarding, Business Profile, Billing & Modules, Payment Methods and Account.
* Operational modules as active links only when the matching paid module is active.
* Locked operational modules as disabled/contextual links, or hide them, but never as free included functionality.

Backend should still validate access even if sidebar hides links.

---

## 19. Home and public website messaging

The public home page should not talk only about service providers.

It should communicate that Ophyra can support businesses that manage:

* Services.
* Clients.
* Orders.
* Teams.
* Contracts.
* Inventory.
* Store orders.
* Delivery as part of the Store + Logistics roadmap.
* Fulfillment as part of the Store + Logistics roadmap.
* Tracking as part of the Store + Logistics roadmap.
* Daily operations.

Preferred public positioning:

> Run your business operations from one modular platform.

Supporting message:

> Start with a free business profile. Activate Service Operations for client work, orders, contracts, team execution and reports. Add inventory, AI, ticketing or marketplace connectors only when your business needs them. Store + Logistics is coming soon.

Avoid making the home sound like:

* A marketplace.
* Planner Hub.
* A vendor/venue directory.
* A CRM only.
* A service provider tool only.
* A store builder only.
* A logistics platform only.

Ophyra should feel like an operational base that can expand.

---

## 20. Existing projects separation

VNV Events, Avomeal and jonnys.media already exist.

They should not be broken to force a perfect shared architecture.

They may continue running as independent systems based on the original architecture.

Avomeal has a supported brand-specific centralization path that may continue using Store/Delivery structures with nutrition, meal-prep, weekly menu, subscriptions and delivery-zone extensions while staying separate from VNV Events by `id_owner`. This compatibility path does not make Store + Logistics an active general Ophyra commercial workspace.

Ophyra should evolve as its own SaaS product.

Future cleanup may include:

* Separating data.
* Separating databases.
* Moving VNV Events data fully into its own VNV Events system.
* Keeping Ophyra as the product platform.

Do not try to solve that migration now.

Do not migrate Avomeal historical data automatically until products, customers, subscriptions, payment tokens and order history have a reviewed import plan.

---

## 21. Pricing summary

| Product                          | Price source                                   |
| -------------------------------- | ---------------------------------------------- |
| Ophyra Base Profile              | `OPHYRA_BASE_PROFILE_PRICE`                    |
| Service Operations               | `SERVICE_OPERATIONS_PRICE`                     |
| Store + Logistics                | `STORE_LOGISTICS_PRICE` / Coming soon          |
| Advanced Storage / QR Inventory  | `ADVANCED_STORAGE_QR_INVENTORY_PRICE`          |
| AI Advisor                       | `AI_ADVISOR_PRICE`                             |
| Ticket Sales + RSVP              | `TICKET_SALES_RSVP_PRICE`                      |
| Custom Domain + SEO Page Builder | `CUSTOM_DOMAIN_SEO_PAGE_BUILDER_STATUS`        |

---

## 22. Product principle

Ophyra should be simple to understand:

```text
Create the free Base Profile.
Choose the operational module you need.
Activate only the modules your business will actually use.
```

Do not complicate the model with:

* Founder pricing.
* Launch phases.
* Venue pricing.
* Vendor pricing.
* Free trial days.
* Annual plans as the main offer.
* Separate Advanced Reports.
* Undefined Automations.
* Legacy Planner Hub pricing.

Final strategic phrase:

> Ophyra starts with Service Operations: CRM, Clients, Team, Orders, Contracts, Signatures, Basic Payroll and Business Profile. Then the business adds only the active modules it actually needs - inventory, AI, ticketing, marketplace connectors or future Store + Logistics when released.

## Administrative control and reporting

Level 1 must keep Ophyra Global Admin metrics separate from Business Operations metrics. Subscription revenue, active memberships and module activations belong to the platform view. Customer-order collections, Store sales, payroll, leads and delivery tasks belong to the operating-business view.

Business revenue is counted from recorded payments, never from orders merely created. Daily recommendations use internal business data and Business Profile location only.

## Level 2 access-control rule

Level 2 Base Profile alone must not unlock operational modules.

The allowed free Level 2 areas are:

* Home.
* Onboarding.
* Business Profile.
* Billing & Modules.
* Payment Methods.
* Account/settings.

The following route groups require active paid modules:

* CRM, Clients, Orders, Team, Team Chat, Payroll and operational payments require Service Operations in the current commercial model.
* Contracts require Service Operations.
* Store, Products, Store Orders, Fulfillment and Delivery/Tracking are coming soon for general Ophyra Level 2/4/5 access. Legacy/admin/brand-specific Store routes must still enforce owner/module scope where they remain reachable.
* Advanced Storage, QR Inventory and Containers require Advanced Storage / QR Inventory.
* AI Advisor requires AI Advisor.
* Tickets, RSVP and Event Registration require Ticket Sales + RSVP.
* Marketplace token setup, manual sync records and external order/status mapping require Marketplace Connectors.

If a Level 2 user opens a locked route directly, the app must show a contextual locked-module page with Review modules, Add payment method and Back to onboarding actions. It must not silently redirect to Payment Methods or Payment Setup.

