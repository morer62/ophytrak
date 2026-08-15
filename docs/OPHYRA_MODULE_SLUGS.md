# Ophyra Module Slugs

## Purpose

Ophyra currently supports canonical commercial slugs and legacy operational slugs. Existing operational records in `user_modules` may use legacy slugs, so runtime code must resolve both forms through `OphyraPricingService`, `ModulesRepository` or `ModuleAccessService`.

Do not create new hardcoded module checks without going through those services.

## Slug Map

| Commercial product | Canonical slug | Legacy/runtime slug |
| --- | --- | --- |
| Ophyra Base Profile | `base_profile` | `business_profile` |
| Service Operations | `service_operations` | `services` |
| Store + Logistics | `store_logistics` | `store_delivery_tracking` |
| Advanced Storage / QR Inventory | `advanced_storage_qr_inventory` | `inventory_storage` |
| AI Advisor | `ai_advisor` | `ai_advisor` |
| Ticket Sales + RSVP | `ticket_sales_rsvp` | `tickets_rsvp` |
| Marketplace Connectors | `marketplace_connectors` | `marketplace_connectors` |
| Custom Domain + SEO Page Builder | `custom_domain_seo_page_builder` | `custom_domain_seo_page_builder` |

## Rules

- Public pricing and labels should use canonical commercial names.
- `user_modules` may continue to store legacy slugs until a dedicated data migration is planned.
- Route guards must accept compatible slugs through `ModuleGuardService` / `ModuleAccessService`.
- Billing quotes and payments must normalize through `OphyraPricingService`.
- Custom Domain + SEO Page Builder remains `coming_soon` / `INACTIVE` and must not expose checkout.
