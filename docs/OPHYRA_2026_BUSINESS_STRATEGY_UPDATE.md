# Ophyra 2026 Business Strategy Update

## Decision

Ophyra's current commercial focus is Service Operations.

The active Ophyra offer should be presented as:

```text
Free Base Profile
+ Service Operations as the primary paid operating workspace
+ optional add-ons that support service-business execution
```

Store + Logistics is no longer an active commercial workspace for new Level 2, Level 4 or Level 5 Ophyra users in the current phase. It must remain visible only as a grey, disabled `Coming soon` area unless a brand-specific or admin-only flow explicitly needs legacy compatibility.

Marketplace Connectors remains a separate optional paid add-on. It is not bundled with Store + Logistics and should not be paused merely because Store + Logistics is coming soon.

## Product positioning

Use this global positioning:

```text
Ophyra is a modular business operations platform.
```

Use this operational focus for current sales and onboarding:

```text
Ophyra helps service businesses and operations-heavy teams manage clients, orders, contracts, files, team work, payroll basics, payments, reports and add-ons from one workspace.
```

Avoid presenting Ophyra globally as:

* an event-only system;
* a venue/vendor marketplace;
* a Store-first product;
* a logistics-only product;
* a food-delivery platform;
* a generic CRM with no operating model.

Events, Store, logistics, fulfillment and delivery remain valid verticals or roadmap areas, but Service Operations is the current active commercial center.

## Module status matrix

| Area | Current public/commercial status | Notes |
| --- | --- | --- |
| Base Profile | Active, free | Public business profile and starter workspace. |
| Service Operations | Active paid workspace | Main paid workspace for CRM, service orders, contracts, files, team, chat, payroll basics and reports. |
| Advanced Storage / QR Inventory | Active add-on | Supports physical items, containers, storage and QR tracking. |
| AI Advisor | Active add-on | Supports summaries, ideas, recommendations and operational guidance. |
| Ticket Sales + RSVP | Active add-on | Event-specific add-on. It should not define the global Ophyra identity. |
| Marketplace Connectors | Active add-on | Separate connector layer for marketplace tools and external sync mapping. |
| Store + Logistics | Coming soon | Grey/disabled in Level 2, Level 4 and Level 5 private navigation. Public pages may exist for SEO/roadmap but should clearly communicate coming soon. |
| Custom Domain + SEO Page Builder | Coming soon | Future public-site/SEO add-on. |

## Level-specific UI rule

### Level 2

Level 2 is the Business Owner / Account Owner.

In the current strategy:

* Service Operations can be active and operational.
* Store + Logistics must show as disabled/grey `Coming soon`.
* Store-related search suggestions must not navigate to Store operations.
* Product creation, Store order creation, fulfillment and delivery should not appear as active quick-create actions.
* Marketplace Connectors can still be sold and managed separately.

### Level 4

Level 4 is a team member.

In the current strategy:

* Service tasks, time clock, team chat and company access remain active where permitted.
* Store delivery/driver-mode surfaces must be grey/disabled `Coming soon` unless the screen belongs to a legacy brand-specific Store operation.

### Level 5

Level 5 is the final client.

In the current strategy:

* Service orders, contracts/files, messages, account and saved cards remain active.
* Store purchases/delivery must show as disabled/grey `Coming soon` in Ophyra client portal navigation.

## Public site rule

The public website may keep module and industry landing pages for Store + Logistics, retail and logistics because they support roadmap SEO and future demand capture.

However:

* Store + Logistics pages must not imply the module is currently active for new users.
* Public copy should favor Service Operations as the main active workspace.
* Store + Logistics should be described as `Coming soon` or roadmap when it appears in pricing/module blocks.
* Marketplace Connectors should remain separate from Store + Logistics.

## Documentation rule

Older documents may still describe implemented Store tables, routes, tasks, delivery logs, mobile WebView routes or Avomeal-specific Store behavior.

Do not delete those references blindly. Treat them as one of these:

1. legacy/internal implementation notes;
2. brand-specific Avomeal/VNV Gourmet behavior;
3. Level 1/admin compatibility;
4. future roadmap for Store + Logistics.

When a document describes current Ophyra commercial behavior, it must use the new strategy above.

## Technical compatibility

Do not remove Store tables, repositories or routes just because Store + Logistics is commercially paused.

The following may still exist for compatibility:

* `store_orders`
* `store_order_items`
* `store_payments`
* `store_order_workflow`
* `store_order_tasks`
* `store_delivery_location_logs`
* public Avomeal Store/cart/checkout routes
* Level 1/admin test or brand-specific routes

The commercial pause is a UI/product-positioning decision, not a database deletion.

## Recent implementation notes

The following changes were made to align the product with this strategy:

* Level 2 home workspace card for Store + Logistics is grey/disabled and says `Coming soon`.
* Level 2 sidebar and mobile sidebar show Store + Logistics as grey/disabled.
* Level 2 command search no longer navigates to Store products/orders/fulfillment/delivery.
* Level 2 quick-create no longer exposes Create Product or Create Store Order.
* Level 2 onboarding no longer recommends Store + Logistics as the active core.
* Level 2 module center and billing mark Store + Logistics as `Coming soon`.
* Level 4 Driver Mode is grey/disabled as `Coming soon`.
* Level 5 Store Purchases and Delivery is grey/disabled as `Coming soon`.
* Public module/pricing copy was adjusted so Service Operations is the active focus and Store + Logistics is roadmap.
* SEO metadata was updated so Ophyra is not positioned globally as an event-only platform.

## Safe language

Preferred:

```text
Start with a free business profile. Activate Service Operations for CRM, orders, contracts, team execution and reports. Add specialized modules when your operation needs them.
```

Allowed:

```text
Store + Logistics is coming soon.
Marketplace Connectors is a separate add-on.
Ticket Sales + RSVP is an optional event-specific add-on.
```

Avoid:

```text
Activate Service Operations or Store + Logistics today.
Store + Logistics is one of the two active operating cores.
Ophyra is built mainly for event pros.
Ophyra is primarily an online store platform.
```
