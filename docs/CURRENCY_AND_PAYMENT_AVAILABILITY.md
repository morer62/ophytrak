# Currency And Payment Availability

## Objetivo

Ophyra usa precios base en USD. La moneda que ve el cliente, la moneda que acepta el procesador y la moneda final registrada pueden ser distintas, por lo que el checkout debe guardar un snapshot completo:

- `base_amount/base_currency`: precio comercial base, siempre USD.
- `display_amount/display_currency`: precio mostrado al cliente.
- `payment_amount/payment_currency`: monto enviado al proveedor o banco.
- `exchange_rate`: tasa USD -> payment currency usada en el momento.
- `provider_type/payment_method/payment_provider_id`: ruta de cobro.
- `manual_review_status`: estado manual para transferencia bancaria.

## Implementacion

- `src/Services/CurrencyPricingService.php` resuelve moneda visible, lee tasas cacheadas desde `exchange_rates` y hace fallback seguro a USD cuando no hay tasa.
- `src/Services/PaymentAvailabilityService.php` filtra metodos por pais, moneda, sitio y proveedor activo.
- `src/views/public/commerce/store/checkout/index.php` integra el snapshot en `summary`, `paypal_create_order` y `pay`.
- `src/views/public/commerce/store/checkout/index.twig` agrega selector de moneda, pais de facturacion y metodo de pago.
- `src/Repositories/StorePaymentsRepository.php` agrega `addCompatible()` para guardar columnas nuevas solo cuando la migracion ya fue aplicada.

## Flujo

1. El carrito calcula subtotal/descuento/total en USD.
2. El cliente elige moneda visible.
3. Se busca tasa cacheada en `exchange_rates`.
4. Se filtran proveedores por moneda/pais/sitio.
5. Si no hay proveedor digital compatible, se mantiene transferencia bancaria.
6. El pago digital guarda estado `PAID`; transferencia bancaria guarda `PENDING` y requiere revision manual.

## QA

- Cambiar moneda a USD debe mantener monto 1:1.
- Cambiar moneda sin tasa cacheada debe volver a USD.
- Square solo debe quedar disponible para sus pares pais/moneda permitidos.
- Stripe/PayPal deben ocultarse si su moneda configurada no esta soportada.
- Transferencia bancaria debe estar disponible aunque no haya proveedor activo.
- Un pago por banco no debe marcar la orden como pagada ni descontar stock.
- Un pago digital exitoso debe seguir marcando orden pagada y descontando stock.

## Riesgos

- Las tasas requieren carga periodica o manual en `exchange_rates`.
- El soporte real de moneda tambien depende de la cuenta del proveedor, no solo de la lista local.
- Las pantallas administrativas deben exponer revision manual antes de operar banco en produccion.
