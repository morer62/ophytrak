# Ophyra Affiliate Program

Last updated: 2026-06-05

## Structural rule

The affiliate program is a global user capability.

An affiliate profile belongs to the global user account through `affiliate_profiles.id_user`. It must not depend on `level`, `id_owner`, `business_id`, `company_id` or a specific business workspace.

A user can be a client, team member, business owner, Level 1 admin and affiliate at the same time. The affiliate area must remain available when the user changes level, creates a company, buys as a client or works for multiple companies.

## Status flow

Supported affiliate application states:

- `NOT_APPLIED`: no `affiliate_profiles` row exists yet.
- `PENDING`: the user submitted an application and is waiting for Level 1 review.
- `APPROVED`: Level 1 approved the user and assigned a commission rate.
- `REJECTED`: Level 1 rejected the application.
- `SUSPENDED`: Level 1 paused the affiliate.

Only `APPROVED` affiliates receive an active referral link and can generate commissions.

## Commission rules

Allowed commission rates are configured in env:

- `AFFILIATE_ALLOWED_COMMISSION_RATES=30,40,50`
- `AFFILIATE_DEFAULT_COMMISSION_RATE=30`
- `AFFILIATE_COMMISSION_ON_RENEWALS=false`

Initial rule: commissions are generated only for the first confirmed payment of eligible paid Ophyra modules. Renewals are intentionally disabled until a future sprint changes the program rules.

Commissionable modules:

- `service_operations`
- `store_logistics`
- `advanced_storage_qr_inventory`
- `ai_advisor`
- `ticket_sales_rsvp`

Not commissionable:

- Ophyra Base Profile, because it is free.
- Custom Domain + SEO Page Builder, because it is coming soon.
- Free/manual activations.
- Failed payments.
- Abandoned checkouts.
- Payments from unapproved affiliates.

## Tracking

Approved affiliate links use:

```text
/r/AFF000001?ref=AFF000001
```

The `/r/{code}` route validates that the affiliate code is active and that the affiliate profile is `APPROVED`, then stores referral data in a cookie. `AFFILIATE_COOKIE_DAYS` controls expiration.

Signup reads that cookie and creates `affiliate_referrals` after user creation. Self-referrals and duplicates are ignored.

## Manual payouts

Affiliate payouts are manual. Level 1 can review pending/approved/payable commissions, select commissions, upload proof and mark them paid.

The payout record is stored in `affiliate_commission_payments`. Included commissions are marked `paid` and stamped with the payout batch ID.

Do not add automatic ACH, PayPal Payouts or Stripe Connect payout automation without a separate sprint.

## Sensitive payout information

Bank data must never be displayed in full. Store only masked values in UI:

- routing number as `****1234`
- account number as `****1234`

The current implementation encrypts bank routing/account values when the aligned columns exist and uses `AFFILIATE_PAYOUT_ENCRYPTION_KEY`, falling back to `PAYMENT_ENCRYPTION_KEY` or `VNV_SECRET_KEY`.

## Required SQL

Review and run:

```text
db/ophyra_affiliate_program_required.sql
```

This SQL aligns affiliate tables, referral tracking, commission snapshots, payout proof fields and encrypted bank columns.
