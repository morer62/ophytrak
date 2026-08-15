# Store + Logistics: estabilizacion QR y entrega

Fecha: 2026-07-16

## Resultado

La correccion se implementa sobre el flujo existente. No se crean tablas, controladores ni estados paralelos para delivery.

El circuito operativo queda asi:

```text
Level 1/2 asigna Preparation y Delivery
Level 4 Preparation prepara, empaca e imprime el tag QR
Level 4 Delivery escanea el tag desde Driver Mode
Driver Mode abre solamente una tarea DELIVERY asignada al usuario autenticado
El repartidor registra ubicacion, foto, comentario y resultado
Level 2 y Level 5 consultan tracking, estado y evidencia existentes
```

## Fuentes de verdad reutilizadas

```text
store_orders
store_order_workflow
store_order_tasks
store_delivery_location_logs
store_user_roles
user_institutions
chat_threads
chat_messages
```

El QR contiene el `public_access_token` ya asociado a la orden. El escaner no acepta una orden arbitraria: busca el token dentro de las tareas DELIVERY actualmente asignadas al Level 4. Las mutaciones siguen pasando por My Work y sus validaciones de owner, usuario, tarea y estado.

## Ajustes incluidos

- Resolucion de empleados Level 4 multiempresa mediante `user_institutions`, conservando compatibilidad con `id_owner` y `store_user_roles`.
- Fallback de asignacion para que un negocio pueda seleccionar colaboradores vinculados aunque aun no haya clasificado un rol delivery especifico.
- Impresion del tag desde una tarea PREPARATION.
- Escaneo por camara desde Driver Mode y apertura de la entrega asignada.
- Foto y comentario para prueba de entrega; comentario obligatorio en intento fallido o devolucion.
- Reparacion de enlaces publicos de producto/categoria usando la ruta Store existente.
- Filtro de categoria en una sola consulta, aislado por owner.
- Reparacion de redirecciones y enlaces de chat/ordenes del portal Level 5.
- Separacion visual entre Warehouse fisico y Store Inventory.
- Retiro del cambio directo de nivel desde el sidebar Level 2.

## Estados operativos

```text
PREPARATION -> READY_FOR_DELIVERY
DELIVERY -> OUT_FOR_DELIVERY
OUT_FOR_DELIVERY -> DELIVERED
OUT_FOR_DELIVERY -> DELIVERY_ATTEMPTED
OUT_FOR_DELIVERY -> RETURNED_TO_BUSINESS
```

No se renombra la columna legacy `kitchen_user_id`; en interfaz se mantiene el concepto general `Preparation`.

## Validacion

Ejecutar:

```bash
php tests/store_logistics_contract_test.php
```

La prueba valida contratos de rutas, QR, relaciones multiempresa, busqueda de Warehouse, JSON de idiomas y sintaxis de las plantillas Twig modificadas.

QA manual recomendado con HTTPS o localhost para permitir la camara:

1. Crear o elegir una orden Store con token publico.
2. Asignar Preparation y Delivery a usuarios Level 4 vinculados al mismo owner.
3. Completar Preparation e imprimir el tag.
4. Iniciar sesion como el delivery asignado y escanear el tag.
5. Marcar salida, registrar ubicacion y completar entrega con foto/comentario.
6. Confirmar tracking y evidencia en Level 2 y Level 5.
7. Repetir con `DELIVERY_ATTEMPTED` y `RETURNED_TO_BUSINESS`, verificando el comentario obligatorio.

## Base de datos

Esta estabilizacion no requiere migracion nueva. Usa las tablas y columnas ya presentes en el SQL revisado. Si un ambiente antiguo no tiene las extensiones de ubicacion/contexto documentadas, aplicar manualmente y con revision previa el SQL ya existente indicado en `STORE_LOGISTICS_DRIVER_MODE_AND_PREPARATION_SPRINT.md`.

## QA visible en Chromium

Ejecucion local: 2026-07-16.

Se instalaron Playwright y Chromium y se recorrieron en pantalla:

```text
signup Level 2
perfil empresarial obligatorio
bloqueo del modulo antes de activacion
creacion de Level 4 delivery
creacion de Level 5 cliente
creacion de categoria y producto
orden manual con payment link
asignacion Preparation + Delivery
My Work y Driver Mode mobile
impresion/render del QR
foto de salida y GPS
portal Level 5 y tracking
chat delivery <-> cliente
acceso publico mediante token QR
registro manual de pago externo por Level 2
```

Datos QA:

```text
owner: qa.owner.20260716a@example.test
delivery: qa.delivery.20260716a@example.test
client: qa.client.20260716a@example.test
order: #4
```

El modulo del owner QA fue concedido temporalmente hasta 2026-07-23 mediante el script restringido a cuentas `@example.test`. No se registro un pago para esta concesion.

### Correcciones descubiertas durante el recorrido

- `panel/cards` contenia una llamada JavaScript corrupta a `preventDefault`; fue reparada.
- La ruta publica del QR quedaba en blanco porque Twig no tenia el filtro `json_decode`; fue agregado de forma segura.
- `TemplateResponse` emitia warnings por variables de chat no inicializadas; ahora tiene valores seguros por defecto.
- La reanudacion del submit en Driver Mode conserva el boton/accion original usando `requestSubmit`.
- El `APP_URL` local apuntaba a `/vnv-venue`; ahora apunta a `/ophyra`.
- El cambio manual de pago no generaba un asiento auditable y el controlador POST no inicializaba su repositorio. Ahora registra metodo, referencia, notas, importe, moneda, pagador y fecha, sin retroceder el estado logistico.

### Bloqueos de ambiente confirmados

El owner QA Level 2 configuro y verifico Stripe sandbox desde Payment Providers. Square no fue configurado ni probado en este ambiente.

El checkout de modulos Level 2 tambien continua acoplado a `StripeService`. Debe migrarse a un proveedor de billing de plataforma antes de afirmar que la activacion del modulo se cobra con Square.

SMTP local falla autenticacion. La operacion principal se conserva, pero las notificaciones por email no se pueden certificar con esta configuracion.

La alternativa sin gateway fue validada visiblemente sobre la orden `#4`: el pago cambio de `PENDING` a `PAID`, se creo el registro externo y la orden conservo `OUT_FOR_DELIVERY`.

## Cierre local sin proveedores externos

La revision adicional del 2026-07-16 completo y valido:

- Orden QA `#4` cerrada como `DELIVERED` con tarea Delivery `COMPLETED`, foto local, notas, coordenadas y fecha de entrega.
- `FILE_UPLOAD_DRIVER=local` permite guardar evidencias sin Cloudinary; Cloudinary sigue disponible cuando el driver se configura para produccion.
- Driver Mode bloquea doble submit, informa falta de conexion y mantiene obligatoria la geolocalizacion para acciones operativas.
- `DELIVERY_ATTEMPTED` y `RETURNED_TO_BUSINESS` solo aceptan pedidos realmente `OUT_FOR_DELIVERY`, requieren comentarios y registran el tipo de evento GPS correcto.
- La restitucion de stock de una devolucion usa `store_order_stock_returns` con clave unica por owner/pedido. La prueba ejecuta la restitucion dos veces y confirma que el stock aumenta una sola vez.
- Warehouse ahora tiene categorias de contenedores por owner, asociacion al crear/editar, conteo de uso y bloqueo de eliminacion cuando estan asignadas.
- El portal Level 5 diferencia visualmente Ordenes de servicio de Contratos/Archivos, conservando los documentos asociados a su orden original.
- `MAIL_ENABLED=false` evita intentos SMTP externos y convierte la falta de email en notificacion interna al owner. La operacion principal nunca depende del correo.

Pruebas aprobadas:

```text
Store logistics contracts OK
Store return stock idempotency OK
visible_warehouse_categories.spec.js
visible_client_documents.spec.js
delivery_controller_close.spec.js
visible_client_chat.spec.js
visible_delivery_exceptions.spec.js
visible_configuration_guidance.spec.js
Store logistics query performance OK (300/1000 rows)
```

La configuracion Level 2 ahora explica alternativas obligatorias:

- Pagos: configurar/verificar Stripe, Square o PayPal, o usar pedidos con pago manual y referencia auditable.
- SMTP: configurar/verificar SMTP, o comunicarse mediante chat privado de Ophyra/app mientras no exista correo saliente.
- Los nombres Stripe, Square y PayPal se muestran sin prefijos Unicode que puedan producir caracteres dañados.
- Google Maps conserva la clave global de plataforma mediante `env.GOOGLE_KEY` en el layout administrativo; no se solicita una clave por owner.

Certificacion de excepciones con ordenes independientes:

```text
order #7 = DELIVERY_ATTEMPTED, task WAITING_REVIEW, comentario obligatorio
order #8 = RETURNED_TO_BUSINESS y luego REDELIVERY_SCHEDULED por Level 2
```

SQL aplicado localmente y requerido en otros ambientes:

```text
db/storage_container_categories_required.sql
db/store_return_stock_idempotency_required.sql
```
