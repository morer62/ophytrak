# Exchange Rate Model

## Tabla

La migracion `db/currency_payment_availability_required.sql` crea `exchange_rates`.

Campos clave:

- `base_currency`: moneda origen, actualmente USD.
- `target_currency`: moneda destino.
- `rate`: multiplicador desde USD hacia destino.
- `rate_date`: fecha de la tasa.
- `source`: origen, por ejemplo `manual`, `system`, `provider`.
- `is_active`: permite desactivar una tasa sin borrarla.

## Uso

`CurrencyPricingService` busca la tasa activa mas reciente para `USD -> target_currency`. Si no existe tasa, el checkout vuelve a USD para evitar mostrar precios inventados.

## Operacion

Para activar una moneda visible no USD:

1. Insertar o actualizar una tasa en `exchange_rates`.
2. Probar el checkout con `display_currency`.
3. Confirmar que el proveedor activo soporta su propia `payment_currency`.

Ejemplo:

```sql
INSERT INTO exchange_rates (base_currency, target_currency, rate, rate_date, source, is_active)
VALUES ('USD', 'EUR', 0.92000000, CURRENT_DATE(), 'manual', 1)
ON DUPLICATE KEY UPDATE rate = VALUES(rate), is_active = 1, updated_at = CURRENT_TIMESTAMP;
```
