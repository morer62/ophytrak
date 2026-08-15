# VNV Events Payment Methods Integration

## Objetivo

Este documento resume la adicion realizada en Ophyra para que el sistema web VNV Events pueda consumir el flujo de metodos de pago guardados, consentimiento de cobros futuros y cobros manuales autorizados.

La integracion es:

```text
gateway-agnostic
business-scoped
client-scoped
sin migraciones
sin Level 3
sin almacenar CVV
sin listar metodos guardados en vistas publicas sin sesion
```

## Tablas requeridas

El SQL manual aplicado crea estas tablas:

```text
client_saved_payment_methods
client_auto_charge_consents
authorized_manual_charge_logs
```

Archivo SQL fuente:

```text
docs/sql/client_payment_methods_auto_charge_consents_and_manual_charges.sql
```

## Servicios principales

El consumo backend debe pasar por:

```text
src/Services/ClientPaymentMethodService.php
src/Services/AuthorizedManualChargeService.php
```

Repositorios nuevos:

```text
src/Repositories/ClientSavedPaymentMethodsRepository.php
src/Repositories/ClientAutoChargeConsentsRepository.php
src/Repositories/AuthorizedManualChargeLogsRepository.php
```

## Proveedores soportados

La abstraccion de pagos fue extendida en:

```text
src/Services/Payment/AbstractPaymentProvider.php
src/Services/Payment/StripeProvider.php
src/Services/Payment/SquareProvider.php
src/Services/Payment/PayPalProvider.php
```

Capacidades esperadas:

```text
supportsSavedPaymentMethods()
supportsChargingSavedPaymentMethods()
chargeSavedPaymentMethod()
```

Estado actual:

```text
Stripe: soporta cargo de metodo guardado.
Square: soporta cargo de card-on-file.
PayPal: no expone metodo guardado reutilizable en este sprint.
```

## Contrato para checkout

Cuando VNV Events procese un pago con tarjeta nueva y quiera permitir guardado/autorizacion, debe enviar estos campos junto al pago:

```text
save_payment_method: 0|1
auto_charge_consent: 0|1
payment_token_type: new_card|stored_card
card_brand
card_last4
card_exp
billing_zip
```

Reglas:

```text
save_payment_method y auto_charge_consent son independientes.
auto_charge_consent debe iniciar desmarcado.
No guardar si no hay id_user_business.
No guardar si no hay id_client/user_id seguro.
No guardar token de un solo uso como metodo reutilizable.
No listar metodos guardados sin sesion del cliente.
```

## Store checkout ya integrado

Archivos conectados:

```text
src/views/public/commerce/store/checkout/index.php
src/views/public/commerce/store/checkout/index.twig
src/views/templates/partials/payment-method-consent-modal.twig
```

Flujo:

```text
1. El cliente llena datos de checkout.
2. El sistema valida nombre, email, direccion y billing ZIP.
3. Si el proveedor activo es Stripe o Square y se usa tarjeta nueva, se abre el modal.
4. El cliente puede guardar metodo y/o aceptar consentimiento futuro.
5. Se tokeniza/cobra.
6. Solo despues de un pago exitoso se crea customer/card-on-file reutilizable.
7. Se registra client_saved_payment_methods si aplica.
8. Se registra client_auto_charge_consents si aplica.
```

## Ajuste especifico Stripe ZIP

Se aplico una correccion preventiva por el caso reportado donde el cliente podia percibir que el boton en modo Stripe no validaba el ZIP.

Cambios:

```text
El formulario valida billing_zip antes de abrir el modal y antes de tokenizar.
El Stripe card element usa hidePostalCode: true para evitar doble ZIP.
stripe.createToken recibe address_zip con billing_zip.
stripe.createToken recibe address_country.
```

Esto mantiene un solo campo ZIP visible y asegura que el ZIP viaje a Stripe durante la tokenizacion.

## Cobro manual autorizado

Pantallas administrativas integradas:

```text
src/views/panel/level1/planner-hub/management/orders/orders/payments/index.php
src/views/panel/level1/planner-hub/management/orders/orders/payments/index.twig
src/views/panel/level2/planner-hub/management/orders/orders/payments/index.php
src/views/panel/level2/planner-hub/management/orders/orders/payments/index.twig
```

El boton de cobro manual solo aparece cuando:

```text
El usuario admin es Level 1 o Level 2.
La orden pertenece al business correcto.
Existe balance pendiente.
Existe metodo guardado activo.
Existe consentimiento activo no revocado.
El proveedor activo soporta cargo de metodo guardado.
```

En exito:

```text
Se registra orders_payments.
Se actualiza estado de pago cuando corresponde.
Se registra authorized_manual_charge_logs con SUCCESS.
Se envia email al cliente.
```

En fallo:

```text
Se registra authorized_manual_charge_logs con FAILED.
No se envia email de exito.
Se muestra error administrativo.
```

## Como debe consumirlo VNV Events

Para guardar metodo desde un checkout propio:

```php
$service = new \App\Services\ClientPaymentMethodService();

$service->recordFromSuccessfulPayment([
    'id_user_business' => $businessId,
    'id_client' => $clientId,
    'user_id' => $clientId,
    'payment_provider' => $providerType,
    'provider_customer_id' => $providerCustomerId,
    'provider_payment_method_id' => $providerPaymentMethodId,
    'provider_reference' => $providerReference,
    'method_type' => 'card',
    'brand' => $cardBrand,
    'last4' => $cardLast4,
    'exp_month' => $expMonth,
    'exp_year' => $expYear,
    'billing_name' => $billingName,
    'billing_email' => $billingEmail,
    'source' => 'vnv_events_checkout',
    'related_order_id' => $orderId,
    'related_payment_id' => $paymentId,
    'save_payment_method' => $savePaymentMethod ? 1 : 0,
    'auto_charge_consent' => $autoChargeConsent ? 1 : 0,
]);
```

Importante:

```text
provider_customer_id/provider_payment_method_id deben ser reutilizables.
No usar payment nonce, source id temporal o token one-time como metodo guardado.
```

## Validaciones ejecutadas

Se ejecuto validacion PHP:

```text
php -l src/Services/ClientPaymentMethodService.php
php -l src/views/public/commerce/store/checkout/index.php
php -l src/Services/AuthorizedManualChargeService.php
php -l src/Services/Payment/AbstractPaymentProvider.php
php -l src/Services/Payment/StripeProvider.php
php -l src/Services/Payment/SquareProvider.php
php -l src/Services/Payment/PayPalProvider.php
```

Resultado:

```text
No syntax errors detected.
```

Se valido JSON:

```text
src/Languages/en.json
src/Languages/es.json
src/Languages/fr.json
src/Languages/pt.json
src/Languages/pr.json
```

Resultado:

```text
OK JSON en todos los archivos.
```

Se revisaron anclajes de ZIP, Stripe y consentimiento:

```text
hidePostalCode
address_zip
billingZipInput
save_payment_method
auto_charge_consent
recordStoreCheckoutPaymentMethodPreferences
```

Resultado:

```text
Los anclajes existen en frontend y backend del Store checkout.
```

## Pendiente intencional

Las rutas publicas de order-access no deben activar guardado de metodo hasta revisar cada gateway por separado.

Motivo:

```text
Un token de un solo uso no equivale a un metodo reutilizable.
Guardar ese token generaria fallos posteriores o cargos no ejecutables.
```

Cuando esas rutas generen customer/payment method reutilizable, pueden consumir `ClientPaymentMethodService::recordFromSuccessfulPayment()` con el contrato anterior.
