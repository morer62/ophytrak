# Payment Gateway Ownership Policy: No Stripe Connect

## Estado actual

Ophyra / VNV Events no debe usar Stripe Connect como flujo principal de cobro.

El modelo vigente es:

```text
Cada usuario/business configura su propia pasarela de pago.
Cada usuario/business define si usa Stripe, Square o PayPal.
Cada usuario/business define si usa sandbox/test o production.
Cada usuario/business provee sus propias credenciales.
Cada cobro debe procesarse con la pasarela activa del business correspondiente.
```

## Motivo

Stripe Connect centraliza riesgo operativo y financiero en la plataforma cuando los fondos o disputas quedan asociados a la cuenta/plataforma principal.

Hubo un caso real donde un usuario se registro, proceso cobros y posteriormente ocurrio un chargeback. La responsabilidad del chargeback termino recayendo sobre la plataforma.

Para evitar repetir ese riesgo:

```text
No reactivar Stripe Connect como arquitectura de pagos.
No crear cuentas conectadas nuevas para cobros.
No enrutar cobros de negocios por una cuenta central de Ophyra.
No guardar metodos de pago asociados a una cuenta conectada como si fueran metodos reutilizables globales.
```

## Regla obligatoria

Todo flujo de pago debe resolver primero:

```text
id_user_business
payment_providers_credentials
provider activo
environment activo
credenciales propias del business
```

Despues de resolver eso, el sistema puede cobrar mediante:

```text
Stripe directo del business
Square directo del business
PayPal directo del business
```

## Guardado de tarjetas

Los metodos guardados deben ser scoped por:

```text
id_user_business
id_client
payment_provider
environment/credenciales del business
```

No se debe guardar un customer/payment method creado en Stripe Connect dentro de `client_saved_payment_methods` para luego cobrarlo con el provider directo.

Eso puede fallar o, peor, mezclar responsabilidades de cuentas distintas.

## Chargebacks

Con el modelo actual:

```text
El business propietario de la pasarela asume sus disputas.
El business propietario de la pasarela asume sus chargebacks.
Ophyra/VNV Events no debe ser el merchant central del cobro.
```

## Implicacion para order-access

Los flujos de:

```text
first
full
second
suborder/first
suborder/full
suborder/second
tip
```

deben usar la pasarela activa del business via `PaymentProvidersRepository` y `PaymentProviderFactory`.

Los flujos legacy que aun referencien:

```text
StripeAccountsRepository
StripeServiceV2
createCustomerWithCardOnConnectedAccount
chargeCustomerOnConnectedAccount
```

deben considerarse deuda tecnica y no deben ampliarse con nuevas capacidades de tarjetas guardadas hasta ser migrados al provider activo del business.

## Decision

La implementacion nueva de tarjetas guardadas, consentimiento futuro y cobros manuales autorizados debe vivir sobre la arquitectura gateway-agnostic actual:

```text
payment_providers_credentials
PaymentProvidersRepository::getActiveProviderForOwner()
PaymentProviderFactory::create()
ClientPaymentMethodService
AuthorizedManualChargeService
```

No sobre Stripe Connect.
