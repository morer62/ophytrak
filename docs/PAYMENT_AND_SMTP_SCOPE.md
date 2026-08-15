# Payment And SMTP Scope

## Purpose

This document defines how payments and email sender settings should resolve across Ophyra, VNV Events and Avomeal.

Payments and emails are high-risk brand surfaces. A customer should never receive an Avomeal email from VNV Events branding when Avomeal SMTP is configured, and VNV Events emails should not be sent as Avomeal.

## Payment Scope

Ophyra platform billing:

- Ophyra subscriptions and paid add-ons use Ophyra billing flows.
- Records are stored in `payments_all`.
- Revenue belongs to Ophyra Global Admin reporting.

Business operations payments:

- VNV Events service/order payments belong to VNV Events operations.
- Avomeal Store payments belong to Avomeal Store operations.
- Store payments use `store_payments`.
- Store checkout resolves provider through `payment_providers_credentials` and `PaymentProvidersRepository::getActiveProviderForOwner()`.

Do not mix:

- Ophyra SaaS subscription revenue;
- service/event customer payments;
- Avomeal Store sales;
- VNV Events Store sales;
- affiliate payouts;
- payroll.

## Avomeal Payment Rule

Avomeal app expects checkout to:

- read minimum order from backend/settings;
- use Avomeal's active payment provider;
- revalidate cart, price, stock, variations and visibility;
- create `store_orders`, `store_order_items` and `store_payments` under Avomeal ownership;
- respect `site_key=avomeal` where site-level Store visibility exists.

The reviewed Avomeal app treats backend checkout as the source of truth. No native checkout is implemented.

## VNV Events Payment Rule

VNV Events order-access flows remain separate from Store checkout:

```text
/order-access
/order-access/first
/order-access/second
/order-access/full
/order-access/advance
/order-access/suborder
```

These are token-based customer order payment/signature links and must not be merged into Ophyra subscription billing or Store checkout.

## SMTP Scope

SMTP settings are owner-scoped in the reviewed web repos through:

```text
SmtpCredentialsRepository
/panel/planner-hub/settings/smtp
```

Each brand/site should resolve email sender identity in this order when supported:

1. site-specific SMTP setting, for example Avomeal;
2. owner default SMTP setting;
3. safe platform fallback.

Email fields to keep brand-specific:

- provider name;
- from email;
- from name;
- reply-to;
- template branding;
- public domain links.

## Brand Email Rules

- Avomeal order, cart, checkout and subscription emails should use Avomeal branding when Avomeal SMTP is configured.
- VNV Events event request, order and client emails should use VNV Events branding.
- Ophyra billing/module emails should use Ophyra branding.
- Do not expose SMTP credentials in docs, logs, issues or support messages.

## Pending Gaps

- Confirm whether `brand_site_settings` exists in the active database.
- Confirm whether SMTP can resolve by `site_key`, not only `id_owner`.
- Confirm Avomeal public templates no longer default to legacy `VNV Gourmet` text where the active brand must be Avomeal.
- Add explicit site-level payment-provider rules if one owner continues to operate multiple public brands.

