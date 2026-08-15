# Client Payment Methods And Authorized Charges

## Purpose

This document defines the implementation design for saved client payment methods, future-charge consent, and Level 1 / Level 2 manual authorized balance charges.

This sprint must remain gateway-agnostic and business-scoped. Do not assume Stripe. Do not reintroduce Level 3.

## Current Findings

Ophyra already has a payment-provider abstraction:

```text
src/Services/Payment/AbstractPaymentProvider.php
src/Services/Payment/PaymentProviderFactory.php
src/Services/Payment/StripeProvider.php
src/Services/Payment/SquareProvider.php
src/Services/Payment/PayPalProvider.php
```

Business gateway configuration is stored in:

```text
payment_providers_credentials
```

The active provider is resolved through:

```text
PaymentProvidersRepository::getActiveProviderForOwner()
```

Order payment links use:

```text
src/views/public/order-access/*
src/views/public/commerce/order-access/*
```

Store checkout and order-access flows use:

```text
src/views/public/store/*
src/views/public/commerce/store/*
```

Legacy saved cards exist in:

```text
user_cards
```

That table is not sufficient for this sprint because it is user-only, does not include `id_user_business`, does not store provider type generically, and does not record future-charge consent. It also has a legacy `cvv` column that must not be used.

## Manual SQL

Run only after review:

```text
docs/sql/client_payment_methods_auto_charge_consents_and_manual_charges.sql
```

The SQL creates:

```text
client_saved_payment_methods
client_auto_charge_consents
authorized_manual_charge_logs
```

No migrations are created for this sprint.

## Gateway Rules

All payment-method behavior must resolve:

```text
id_user_business
active provider credentials
provider capability
client session state
payment context
```

Provider capability should be exposed through methods such as:

```text
supportsSavedPaymentMethods()
supportsChargingSavedPaymentMethods()
createProviderCustomerIfNeeded()
attachPaymentMethod()
chargeSavedPaymentMethod()
```

Stripe and Square can be implemented first. PayPal should remain payment-only until a verified reusable token/billing-agreement flow exists in this codebase.

## Session Rules

Logged-in Level 5 clients may see saved payment methods scoped to:

```text
id_user_business
id_client / user_id
payment_provider
status = ACTIVE
```

Public unauthenticated payment links must not list saved methods. They may offer save/consent only when the backend can safely resolve the client and business context from the order or store order.

If the client cannot be resolved safely, do not save a method and do not record future-charge consent.

## Consent Rules

Future-charge consent is separate from saving a method.

The checkbox must be:

```text
unchecked by default
optional
auditable
business-scoped
provider-scoped
revocable later
```

Recommended consent version:

```text
2026-06-10
```

Recommended English text:

```text
I authorize this business to charge my saved payment method for balances, approved orders, recurring charges, tips or pending payments related to my services or purchases.
```

## Backend Design

Add repositories/services following existing project style:

```text
ClientSavedPaymentMethodsRepository
ClientAutoChargeConsentsRepository
AuthorizedManualChargeLogsRepository
ClientPaymentMethodService
AuthorizedManualChargeService
```

The service layer should provide:

```text
getActivePaymentGatewayForBusiness()
gatewaySupportsSavedPaymentMethods()
listClientSavedPaymentMethods()
saveClientPaymentMethod()
recordAutoChargeConsent()
getActiveAutoChargeConsent()
chargeSavedPaymentMethod()
recordAuthorizedManualChargeAttempt()
```

Provider-specific APIs stay inside provider adapters.

## Frontend Design

Create a reusable modal partial/component for:

```text
save payment method
authorize future charges
continue without saving
```

The modal should receive payment context:

```text
payment_type
id_user_business
order_id
store_order_id
payment_id
active_provider
supports_saved_methods
uses_saved_method
has_active_consent
client_is_authenticated
```

The modal appears only when a new payment method is being used, or when a saved method is being used but consent is missing and the flow intentionally asks for consent.

## Manual Charge Flow

Level 1 and Level 2 may charge a saved method only when all are true:

```text
admin level is 1 or 2
order belongs to id_user_business
Level 2 owns that id_user_business
client belongs to the order/business context
saved method belongs to same client and business
consent is ACTIVE and not revoked
amount is positive
balance is pending
provider supports saved-method charge
idempotency key has not already succeeded
```

On success, the system should:

```text
record orders_payments or orders_advances consistently with the existing payment type
update order payment status when fully paid
write authorized_manual_charge_logs
send success email to the client
```

On failure:

```text
write authorized_manual_charge_logs
show a clear admin error
do not send success email
```

## Email

Use the existing owner-scoped email service if available. The successful manual-charge email should use the client's preferred language when available.

English subject:

```text
Your remaining payment was processed successfully
```

Spanish subject:

```text
Tu pago restante fue procesado con exito
```

## Translation Keys Needed

Add keys to all active language JSON files before exposing UI text:

```text
payment.save_method
payment.authorize_future_charges
payment.continue_without_saving
payment.saved_method
payment.method_not_saved
payment.authorization_accepted
payment.authorization_pending
payment.authorization_revoked
payment.gateway_saved_methods_not_supported
payment.charge_saved_method
payment.confirm_charge
payment.charge_success
payment.charge_failed
payment.remaining_payment_processed
payment.service_ready_to_proceed
```

## Out Of Scope

Do not implement in this sprint:

```text
automatic recurring engine
automatic retries
cron jobs
bulk charges
charging without explicit admin action
PayPal reusable billing agreements unless already verified
```

## Risks

The main risks are:

```text
legacy user_cards being mistaken for scoped client methods
provider enum limitations in payment_providers_credentials
public payment links saving methods without a safe client identity
Level 2 accidentally seeing or charging another business's clients
duplicate charges from refresh/double click
PayPal being treated like Stripe/Square without reusable-token support
```

## Implementation Order

1. Add repositories for the three new tables. Done.
2. Extend provider abstraction with saved-method capability methods. Done.
3. Implement Stripe/Square saved-method charge support where provider APIs are already available. Done.
4. Add reusable modal and hidden context fields. Done as a Twig partial.
5. Integrate order payment links. Foundation is ready; route-level save/consent must only be enabled where the provider returns reusable customer/payment-method identifiers.
6. Integrate Store checkout/order-access. Done for commerce Store checkout with Stripe/Square reusable customer/card-on-file flows and session-safe saved-card listing.
7. Add Level 1 / Level 2 manual-charge action on order payment detail. Done.
8. Add translations. Done for English, Spanish, French and Portuguese files.
9. Add QA checklist documentation. Done below.

## Implemented Files

```text
src/Repositories/ClientSavedPaymentMethodsRepository.php
src/Repositories/ClientAutoChargeConsentsRepository.php
src/Repositories/AuthorizedManualChargeLogsRepository.php
src/Services/ClientPaymentMethodService.php
src/Services/AuthorizedManualChargeService.php
src/Services/Payment/AbstractPaymentProvider.php
src/Services/Payment/StripeProvider.php
src/Services/Payment/SquareProvider.php
src/Services/Payment/PayPalProvider.php
src/Repositories/PaymentProvidersRepository.php
src/views/templates/partials/payment-method-consent-modal.twig
src/views/panel/level1/planner-hub/management/orders/orders/payments/index.php
src/views/panel/level1/planner-hub/management/orders/orders/payments/index.twig
src/views/panel/level2/planner-hub/management/orders/orders/payments/index.php
src/views/panel/level2/planner-hub/management/orders/orders/payments/index.twig
src/views/public/commerce/store/checkout/index.php
src/views/public/commerce/store/checkout/index.twig
src/Languages/en.json
src/Languages/es.json
src/Languages/fr.json
src/Languages/pt.json
src/Languages/pr.json
docs/sql/client_payment_methods_auto_charge_consents_and_manual_charges.sql
```

## Public Checkout Integration Contract

Public order and Store payment screens should send these optional fields with their existing payment request:

```text
save_payment_method=1
auto_charge_consent=1
saved_payment_method_id
payment_token_type=new_card|stored_card
provider_customer_id
provider_payment_method_id
card_brand
card_last4
card_exp_month
card_exp_year
```

Rules:

```text
Do not list saved methods when no client session exists.
Do not save a method when the client cannot be resolved safely.
Do not record consent without id_user_business and client identity.
Do not ask to save when payment_token_type=stored_card.
```

## Manual Charge UI

The order payments panel now evaluates whether the order can be manually charged from a saved payment method:

```text
panel/planner-hub/management/orders/orders/payments?id={order_id}
```

It only exposes the charge action when:

```text
the logged admin is Level 1 or Level 2
the order is in the admin's allowed business scope
there is pending balance
the client has an ACTIVE saved method for the order business
the client has ACTIVE, non-revoked auto-charge consent for that method
the provider supports saved-method charges
```

On success:

```text
orders_payments receives a payment record
authorized_manual_charge_logs receives SUCCESS
order payment/status workflow is updated when fully paid
the client receives the remaining-payment processed email
```

On failure:

```text
authorized_manual_charge_logs receives FAILED
the admin sees an error
no success email is sent
```

## QA Checklist

Run after applying the manual SQL:

```text
SQL file applies cleanly.
No CVV or full card number exists in new tables.
Level 2 cannot access another owner order charge action.
Manual charge is hidden when there is no saved method.
Manual charge is hidden when consent is missing or revoked.
Manual charge is hidden when balance is zero.
Manual charge rejects provider mismatch.
Manual charge rejects unsupported provider such as PayPal saved method.
Stripe saved customer/payment method charges succeed in sandbox.
Square saved card charges succeed in sandbox.
authorized_manual_charge_logs records SUCCESS and FAILED attempts.
orders_payments records successful manual authorized charges.
Successful manual charge sends the client email.
Failed manual charge does not send success email.
Public guest checkout does not list saved methods.
Commerce Store checkout asks for save/consent before new Stripe/Square card payment.
Commerce Store checkout stores the method only after the gateway returns reusable customer/card-on-file identifiers.
Commerce Store checkout does not list saved cards without a Level 5 session matching the checkout email.
```

## Remaining Work

The backend foundation, commerce Store checkout, and admin manual-charge path are implemented. Public order-access payment routes still need route-by-route gateway review before enabling save/consent because one-time card tokens must not be stored as reusable methods.

This is intentionally not treated as a cron/autopay engine. Automatic recurring charges, retries and bulk charging remain a separate sprint.
