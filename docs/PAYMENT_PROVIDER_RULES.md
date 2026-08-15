# Payment Provider Rules

## Matriz Base

La disponibilidad se calcula en `PaymentAvailabilityService`.

| Metodo | Regla |
| --- | --- |
| Stripe | Moneda soportada por la lista local y credencial activa. Pais permisivo por defecto. |
| PayPal | Moneda soportada por la lista local y credencial activa. Pais permisivo por defecto. |
| Square | Moneda soportada y par pais/moneda permitido: US/USD, CA/CAD, AU/AUD, JP/JPY, GB/GBP, IE-FR-ES/EUR. |
| Bank transfer | Siempre disponible, con revision manual. |

## Reglas En BD

La tabla `payment_provider_availability_rules` permite bloquear o habilitar por:

- `provider_type`
- `country_code`
- `currency`
- `id_user_business`
- `site_key`
- `vendor_id`

Usar `*` como comodin en pais o moneda. Si no existe una regla, el servicio usa la regla local por defecto.

## Vendor Buttons

Los botones publicos deben renderizarse desde `available_methods`. Un boton de proveedor solo debe mostrarse si:

- La credencial esta activa.
- La moneda configurada del proveedor esta soportada.
- El pais del cliente no bloquea ese proveedor.
- El `site_key` coincide o la credencial no esta restringida a sitio.

Transferencia bancaria debe mostrarse como fallback manual.
