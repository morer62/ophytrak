# Bank Transfer Workflow

## Comportamiento

La transferencia bancaria esta disponible para todos los paises y monedas como fallback manual.

En tienda publica:

- No se tokeniza tarjeta.
- No se llama a Stripe, Square ni PayPal.
- Se crea la orden con estado pendiente.
- Se crea `store_payments` con `status = PENDING`.
- Si existen las columnas nuevas, se guarda `manual_review_status = PENDING`.
- No se descuenta inventario ni se marca la orden como pagada.

## Revision Manual

El administrador debe confirmar el pago en panel antes de liberar fulfillment:

1. Verificar comprobante o conciliacion bancaria.
2. Actualizar pago a `PAID`.
3. Marcar `manual_review_status = APPROVED`.
4. Marcar orden como pagada/procesando.
5. Descontar stock si corresponde.

## Pendiente

La capa de datos y checkout ya soporta el estado manual. Falta una pantalla administrativa dedicada para aprobar/rechazar transferencias y capturar notas de revision.
