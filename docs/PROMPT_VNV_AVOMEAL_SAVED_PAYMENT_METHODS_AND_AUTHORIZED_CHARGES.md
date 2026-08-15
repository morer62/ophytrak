# Prompt para VNV Events / Avomeal: Metodos de Pago Guardados, Autorizacion Futura y Cobros Administrativos

## Contexto general

Necesitamos implementar un sistema completo y seguro para guardar metodos de pago de clientes, solicitar autorizacion de uso futuro, permitir que el cliente gestione sus tarjetas/metodos guardados y permitir que administradores autorizados cobren saldos pendientes desde ordenes, servicios y tienda.

Este sistema aplica para:

```text
VNV Events
Avomeal
Ophyra Store
Order Access / enlaces publicos de pago
Ordenes principales
Subordenes
Primer pago
Pago completo
Segundo pago
Pagos pendientes
Tips / propinas
```

El objetivo es que un cliente pueda pagar una vez, aceptar opcionalmente que su metodo quede guardado y autorizar opcionalmente cobros futuros relacionados con sus ordenes/servicios/compras. Luego, si existe autorizacion activa, el administrador correcto podra cobrar pagos pendientes sin pedir nuevamente la tarjeta.

## Regla de arquitectura de pagos

No usar Stripe Connect.

Cada business debe cobrar usando su propia pasarela configurada:

```text
Stripe directo del business
Square directo del business
PayPal directo del business, solo si existe flujo reusable aprobado
Sandbox/test o production segun configuracion del business
Credenciales propias por business
```

La pasarela activa se debe resolver siempre desde:

```text
id_user_business
payment_providers_credentials
PaymentProvidersRepository::getActiveProviderForOwner()
PaymentProviderFactory::create()
```

No se debe enrutar el cobro por una cuenta central de la plataforma.

Motivo: anteriormente un usuario proceso pagos y luego ocurrio un chargeback que termino generando responsabilidad para la plataforma. Para evitar ese riesgo, cada business debe ser responsable de su propia pasarela, disputas y chargebacks.

## Entidades requeridas

El sistema debe tener tablas o modelos equivalentes a:

```text
client_saved_payment_methods
client_auto_charge_consents
authorized_manual_charge_logs
```

### client_saved_payment_methods

Debe guardar solo referencias reutilizables del gateway, nunca CVV ni numero completo de tarjeta.

Campos esperados:

```text
id
id_user_business
id_client
user_id
payment_provider
provider_customer_id
provider_payment_method_id
provider_reference
method_type
brand
last4
exp_month
exp_year
billing_name
billing_email
is_default
status
source
metadata_json
created_at
updated_at
```

### client_auto_charge_consents

Debe guardar autorizacion explicita, auditable y revocable.

Campos esperados:

```text
id
id_user_business
id_client
user_id
payment_provider
saved_payment_method_id
consent_scope
consent_text
consent_version
accepted_at
revoked_at
status
source
ip_address
user_agent
related_order_id
related_store_order_id
related_payment_id
metadata_json
created_at
updated_at
```

### authorized_manual_charge_logs

Debe registrar cada intento de cobro administrativo.

Campos esperados:

```text
id
id_user_business
id_client
id_order
id_suborder
saved_payment_method_id
auto_charge_consent_id
payment_provider
amount
currency
status
gateway_charge_id
error_message
charged_by_user_id
idempotency_key
metadata_json
created_at
updated_at
```

## Consentimiento del cliente

Guardar metodo y autorizar cobros futuros son dos decisiones separadas.

En cada checkout donde se use una tarjeta nueva se deben mostrar dos opciones:

```text
[ ] Guardar este metodo de pago para futuras compras/pagos.
[ ] Autorizo a este negocio a cobrar este metodo guardado para saldos pendientes, ordenes aprobadas, tips, pagos recurrentes o compras futuras relacionadas con mis servicios.
```

Ambas opciones deben iniciar desmarcadas.

No se debe preseleccionar autorizacion futura.

Texto recomendado:

```text
I authorize this business to charge my saved payment method for balances, approved orders, recurring charges, tips or pending payments related to my services or purchases.
```

Version recomendada:

```text
2026-06-10
```

## Store checkout

En la tienda, el cliente debe poder:

```text
Pagar con tarjeta nueva.
Aceptar guardar metodo.
Aceptar autorizacion futura.
Si tiene sesion iniciada Level 5, seleccionar una tarjeta/metodo guardado.
No ver tarjetas guardadas si no tiene sesion iniciada.
```

Reglas:

```text
No listar metodos por email publico.
Solo listar si la sesion Level 5 pertenece al cliente.
Solo listar metodos del mismo id_user_business.
Solo listar metodos del provider activo.
No mostrar metodos Stripe si el business activo esta cobrando con Square.
No mostrar metodos Square si el business activo esta cobrando con Stripe.
```

## Order Access / Servicios

En enlaces publicos de pago de ordenes y servicios, el cliente debe poder hacer lo mismo que en Store:

```text
Pagar primer pago.
Pagar pago completo.
Pagar segundo pago.
Pagar suborden primer pago.
Pagar suborden completo.
Pagar suborden segundo pago.
Pagar tips/propinas.
Pagar saldo pendiente.
Guardar metodo si usa tarjeta nueva.
Autorizar uso futuro.
Si tiene sesion Level 5, seleccionar metodo guardado.
```

Reglas:

```text
El enlace publico no basta para mostrar tarjetas guardadas.
Debe existir sesion iniciada del cliente Level 5.
La sesion debe corresponder al id_client de la orden.
El metodo guardado debe pertenecer al mismo id_user_business.
El metodo guardado debe pertenecer al mismo provider activo.
```

## Panel del cliente Level 5

El usuario Level 5 debe tener una pantalla para gestionar sus metodos guardados.

Debe poder:

```text
Ver tarjetas/metodos guardados.
Ver business al que pertenece cada metodo.
Ver provider: Stripe, Square o PayPal si aplica.
Ver marca y ultimos 4 digitos.
Ver expiracion si existe.
Ver si tiene autorizacion activa.
Autorizar uso futuro si aun no autorizo.
Revocar autorizacion futura.
Eliminar/desactivar metodo guardado.
Marcar metodo por defecto, opcional.
```

No debe poder:

```text
Ver CVV.
Ver numero completo.
Ver metodos de otro cliente.
Ver metodos de otro business sin relacion.
Usar una tarjeta guardada en un business diferente.
```

## Panel administrador Level 1 y Level 2

Los administradores Level 1 y Level 2 deben poder ver en la orden si el cliente tiene:

```text
Metodo guardado activo.
Autorizacion futura activa.
Provider compatible.
Saldo pendiente.
```

Level 1:

```text
Puede ver/cobrar ordenes dentro de su scope permitido.
```

Level 2:

```text
Puede ver/cobrar solo ordenes creadas por el o pertenecientes a su id_user_business permitido.
No puede cobrar clientes de otro business.
No puede usar metodos guardados de otro business.
```

La UI del admin debe mostrar algo como:

```text
Metodo guardado: Visa **** 4242
Autorizacion: Activa
Provider: Stripe
Saldo pendiente: $X.XX
[Cobrar saldo pendiente autorizado]
```

El boton solo debe aparecer si todo es valido.

## Cobro administrativo autorizado

El cobro manual desde admin solo se permite si:

```text
Admin es Level 1 o Level 2.
La orden pertenece al business correcto.
El metodo guardado pertenece al mismo cliente.
El metodo guardado pertenece al mismo id_user_business.
Existe consentimiento activo y no revocado.
El provider activo del business soporta cobro con metodo guardado.
El monto es mayor que cero.
Existe saldo pendiente.
No existe idempotency_key exitoso previo para el mismo cobro.
```

En exito:

```text
Registrar pago en orders_payments u orders_advances segun corresponda.
Actualizar estado de pago de la orden/suborden si queda completo.
Crear registro SUCCESS en authorized_manual_charge_logs.
Enviar email al cliente indicando que el pago pendiente fue procesado.
Mostrar confirmacion al admin.
```

En fallo:

```text
Crear registro FAILED en authorized_manual_charge_logs.
No enviar email de exito.
Mostrar error claro al admin.
No cambiar estado de orden como pagada.
```

## Providers

### Stripe

Debe soportar:

```text
Crear customer reutilizable.
Guardar payment method/customer reusable.
Cobrar metodo guardado off-session si hay autorizacion.
Guardar brand, last4, exp_month, exp_year.
```

Debe enviar ZIP cuando aplique:

```text
Stripe Elements debe usar hidePostalCode: true si el formulario tiene ZIP propio.
stripe.createToken o PaymentMethod debe recibir address_zip.
```

### Square

Debe soportar:

```text
Crear customer.
Crear card-on-file.
Guardar card id como provider_payment_method_id.
Cobrar card-on-file si hay autorizacion.
Guardar brand, last4, exp_month, exp_year.
```

### PayPal

No tratar PayPal igual que Stripe/Square salvo que exista flujo reusable real.

Para guardar/cobrar metodo futuro en PayPal se requiere:

```text
Vaulting aprobado por PayPal
Billing Agreement
Subscription
Reference Transaction
o flujo equivalente oficialmente soportado
```

Si el proyecto no tiene ese flujo verificado:

```text
PayPal debe permitir pago normal.
PayPal no debe mostrar "guardar metodo" como si fuera tarjeta reusable.
PayPal no debe permitir cobro administrativo futuro.
```

## Seguridad obligatoria

Nunca guardar:

```text
CVV
Numero completo de tarjeta
Token de un solo uso como metodo reusable
Datos de tarjeta en logs
```

Nunca permitir:

```text
Listar tarjetas en vista publica sin sesion.
Cobrar tarjeta sin consentimiento activo.
Cobrar tarjeta de otro business.
Cobrar tarjeta de otro cliente.
Cobrar con provider distinto al provider con que se guardo el metodo.
Level 2 cobrando ordenes fuera de su scope.
```

## Pantallas requeridas

### Cliente Level 5

Ruta sugerida:

```text
/client/payment-methods
```

Funciones:

```text
Listar metodos.
Autorizar metodo.
Revocar autorizacion.
Eliminar/desactivar metodo.
Ver business/provider.
```

### Checkout publico con sesion

Aplicar a:

```text
/store/checkout
/order-access/first
/order-access/full
/order-access/second
/order-access/suborder/first
/order-access/suborder/full
/order-access/suborder/second
/order-access/success tip modal
```

Funciones:

```text
Mostrar selector de metodo guardado si Level 5 esta logueado.
Ocultar selector si no hay sesion.
Pagar con metodo guardado.
Pagar con tarjeta nueva.
Pedir guardar/autorizacion si usa tarjeta nueva.
```

### Admin Level 1 / Level 2

Aplicar a:

```text
Panel de pagos de orden.
Detalle de orden.
Detalle de suborden si existe.
```

Funciones:

```text
Mostrar estado de metodo guardado.
Mostrar estado de autorizacion.
Permitir cobrar saldo pendiente autorizado.
Registrar logs de intentos.
Enviar email al cliente si el cobro fue exitoso.
```

## Criterios de aceptacion

El sprint se considera completo solo si:

```text
Store permite guardar metodo y autorizacion.
Order Access permite guardar metodo y autorizacion.
Cliente Level 5 ve metodos guardados en su panel.
Cliente Level 5 puede eliminar/desactivar metodos.
Cliente Level 5 puede autorizar/revocar autorizacion.
Cliente Level 5 ve metodos guardados al pagar enlace publico si tiene sesion.
Cliente sin sesion no ve metodos guardados.
Admin Level 1 ve metodo/autorizacion en orden correspondiente.
Admin Level 2 ve metodo/autorizacion solo en orden correspondiente a su scope.
Admin puede cobrar saldo pendiente si hay autorizacion activa.
Se bloquea cobro si no hay autorizacion.
Se bloquea cobro si el provider activo no coincide.
Se bloquea PayPal futuro si no existe vault/billing agreement verificado.
Se registran logs SUCCESS y FAILED.
No se usa Stripe Connect.
No se guarda CVV.
No se guarda numero completo de tarjeta.
```

## Pruebas recomendadas

```text
Stripe sandbox: tarjeta nueva, guardar metodo, autorizar, cobrar saldo pendiente desde admin.
Square sandbox: tarjeta nueva, card-on-file, autorizar, cobrar saldo pendiente desde admin.
PayPal sandbox: pago normal; confirmar que no aparece guardado futuro si no hay vaulting.
Cliente sin sesion: no debe ver metodos guardados.
Cliente con sesion incorrecta: no debe ver metodos guardados.
Cliente con sesion correcta: debe ver solo sus metodos del business/provider activo.
Level 2 intentando cobrar orden de otro business: debe bloquear.
Consentimiento revocado: admin no puede cobrar.
Metodo eliminado/desactivado: no aparece ni puede cobrarse.
Provider cambiado de Stripe a Square: no mostrar metodos Stripe para cobro Square.
```

## Resumen ejecutivo

Implementar metodos de pago guardados y cobros futuros autorizados de forma segura, scoped por business y cliente, usando la pasarela propia configurada por cada business. Stripe y Square pueden soportar metodos reutilizables. PayPal solo debe soportarlo si existe vaulting/billing agreement real. El cliente Level 5 debe gestionar sus metodos y autorizaciones. El admin Level 1/2 debe poder cobrar saldos pendientes solo cuando exista autorizacion activa y el metodo pertenezca al mismo cliente/business/provider.
