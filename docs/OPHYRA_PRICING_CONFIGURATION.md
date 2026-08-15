# Ophyra pricing configuration

Ophyra prices are centralized in `src/Services/OphyraPricingService.php` and read from `.env`.

The approved production variables are:

| Product | Required variable |
| --- | --- |
| Ophyra Base Profile | `OPHYRA_BASE_PROFILE_PRICE` |
| Service Operations | `SERVICE_OPERATIONS_PRICE` |
| Store + Logistics | `STORE_LOGISTICS_PRICE` |
| Advanced Storage / QR Inventory | `ADVANCED_STORAGE_QR_INVENTORY_PRICE` |
| AI Advisor | `AI_ADVISOR_PRICE` |
| Ticket Sales + RSVP | `TICKET_SALES_RSVP_PRICE` |
| Marketplace Connectors | `MARKETPLACE_CONNECTORS_PRICE` |
| Custom Domain + SEO Page Builder | `CUSTOM_DOMAIN_SEO_PAGE_BUILDER_STATUS` |
| Ophyra billing currencies | `OPHYRA_SUPPORTED_BILLING_CURRENCIES` |

Rules:

- Base pricing currency is configured with `OPHYRA_BILLING_BASE_CURRENCY` and is currently USD.
- New Ophyra module checkout currencies are configured with `OPHYRA_SUPPORTED_BILLING_CURRENCIES`.
- The active new-checkout currency set is `USD,EUR,GBP,CAD,AUD,MXN,BRL,CLP,COP`.
- JPY and CHF may remain visible in old payment history, but they must not appear for new Ophyra module checkout.
- Ophyra module checkout uses fixed per-currency prices from `.env`, not dynamic exchange-rate conversion.
- If a module is missing a fixed price for the selected currency, checkout for that module/currency must be disabled with a clear message.
- Ophyra Base Profile is free by default and does not unlock operational modules.
- Service Operations, Store + Logistics, Advanced Storage / QR Inventory, AI Advisor, Ticket Sales + RSVP and Marketplace Connectors require active `user_modules` rows or approved manual/admin activation.
- CRM, Clients, Orders, Contracts, Team, Payroll and Chat must never be inferred as active from Base Profile alone.
- Public views, module access, checkout and renewals must consume `OphyraPricingService`; they should not carry their own price constants.
- Custom Domain + SEO Page Builder is `coming_soon`; it must not expose a public price or checkout path.
- Marketplace Connectors is priced separately at `USD 12/month` by default through `MARKETPLACE_CONNECTORS_PRICE` / `OPHYRA_MODULE_MARKETPLACE_CONNECTORS_MONTHLY`, with per-currency overrides such as `MARKETPLACE_CONNECTORS_PRICE_COP`. It is not included with Store + Logistics.
- A missing or invalid value is logged and resolved by the centralized service fallback, so checkout never receives null or negative amounts.
- Ophyra subscription and module payments store a payment snapshot in `payments_all`: base amount/currency, charged amount/currency, exchange rate, provider and payment method.
- Level 2 business customer payments remain independent and use the provider/currency configured in `payment_providers_credentials`.
