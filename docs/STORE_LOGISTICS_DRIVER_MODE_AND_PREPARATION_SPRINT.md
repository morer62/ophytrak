# Store + Logistics Driver Mode Sprint

## Objetivo

Convertir Store + Logistics en una operacion completa de preparacion, fulfillment, delivery, tracking, chat, pagos y payroll location, reutilizando la base existente.

La referencia visual es una app mobile de delivery con:

```text
header compacto
tabs por estado
mapa superior
ruta optimizada
pins numerados
lista de entregas
cards operativas
acciones rapidas
tracking de driver
```

No se debe copiar marca, nombre, colores exactos ni assets. La referencia es funcional y compositiva.

## Decision importante: Preparation no es solo Kitchen

Avomeal nacio con una logica de cocina, pero el concepto correcto para Ophyra/VNV/Avomeal es mas amplio:

```text
Preparation
Fulfillment
Delivery
Customer handoff
```

`Kitchen` debe tratarse como un caso particular de `Preparation`, no como el nombre universal.

Por compatibilidad, no se debe renombrar destructivamente la columna:

```text
store_order_workflow.kitchen_user_id
```

Esa columna puede seguir funcionando internamente como el usuario asignado a preparacion. En UI, docs y nuevas funciones debe mostrarse como:

```text
Preparation user
Preparation assignment
Preparation ready
```

La entidad generica real ya existe:

```text
store_order_tasks.task_type = PREPARATION
```

Esto permite que Avomeal use cocina/preparacion, mientras VNV Events u otros negocios usen preparacion operativa, armado, empaque, decoracion, inventario o staging.

## Tablas existentes a reutilizar

No crear tablas nuevas de delivery paralelo.

Base principal:

```text
store_orders
store_order_workflow
store_order_tasks
store_delivery_location_logs
store_delivery_zones
store_payments
payments_all
client_saved_payment_methods
client_auto_charge_consents
authorized_manual_charge_logs
chat_threads
chat_messages
notifications
payroll_hours
payroll_time_logs
users
store_user_roles
clients_users
```

No crear:

```text
delivery_routes
delivery_route_stops
delivery_assignments
delivery_attempts
delivery_proofs
user_location_pings
```

## SQL manual

Archivo:

```text
docs/sql/store_logistics_mobile_location_and_context_extensions.sql
```

Este SQL agrega metadatos minimos a tablas existentes:

```text
store_delivery_location_logs.accuracy
store_delivery_location_logs.platform
store_delivery_location_logs.source
store_delivery_location_logs.permission_status
store_delivery_location_logs.device_id
store_delivery_location_logs.context
payroll_time_logs.location_lat
payroll_time_logs.location_long
payroll_time_logs.location_accuracy
payroll_time_logs.location_source
payroll_time_logs.location_permission_status
chat_threads.id_owner
chat_threads.id_store_order
chat_threads.id_store_order_task
chat_threads.context
chat_threads.visibility
```

El SQL es idempotente y no crea tablas paralelas.

## Endpoint mobile location

Endpoint agregado:

```text
POST /views/api/mobile/location/ping
```

Archivo:

```text
src/views/api/mobile/location/ping/index.php
```

Acepta JSON o form-data.

Payload sugerido:

```json
{
  "user_id": 12,
  "id_owner": 2,
  "id_store_order": 55,
  "id_store_order_task": 91,
  "context": "delivery_driver",
  "event_type": "LOCATION_UPDATE",
  "latitude": 40.7128,
  "longitude": -74.006,
  "accuracy": 12.5,
  "platform": "ios",
  "source": "mobile_webview",
  "permission_status": "granted",
  "device_id": "optional-device-id"
}
```

Contextos esperados:

```text
payroll_clock
delivery_driver
route_tracking
task_start
arrived
delivered
clock_in
clock_out
proof_of_delivery
```

Eventos permitidos:

```text
TASK_START
LOCATION_UPDATE
OUT_FOR_DELIVERY
ARRIVED
DELIVERED
CLOCK_IN
CLOCK_OUT
```

Para delivery:

```text
Inserta en store_delivery_location_logs.
Actualiza store_order_workflow.delivery_lat.
Actualiza store_order_workflow.delivery_lng.
Actualiza store_order_workflow.delivery_location_at.
```

Para payroll:

```text
Actualiza el payroll_time_logs abierto del usuario si existen columnas de ubicacion.
```

## UX mobile Driver Mode

La version mobile debe seguir una composicion similar a la imagen de referencia:

```text
Header fijo
Tabs de estado
Mapa superior
Boton de ruta optimizada
Pins numerados
Lista de entregas
Cards de entrega
Acciones rapidas
Boton flotante principal
```

### Header

Debe mostrar:

```text
Business name
Driver name
Estado GPS
Icono chat/unread
```

### Tabs

```text
Open / Abiertas
In Route / En Ruta
Completed / Completadas
Issues / Intentos o Problemas
Returns / Devoluciones
```

### Mapa

Debe mostrar:

```text
Ubicacion actual del driver
Pins numerados de entregas asignadas
Proxima entrega destacada
Linea de ruta calculada
Boton Open Navigation
Boton Refresh Location
```

La ruta optimizada en primera fase puede ser calculada en frontend/backend con las ordenes asignadas del dia. No se debe crear tabla de rutas salvo que luego se justifique.

### Cards de entrega

Cada card debe mostrar:

```text
Numero visual de parada
Store order id
Cliente
Telefono
Direccion shipping
Payment status
Delivery status
Notas visibles
Boton navegacion
Boton llamar
Boton chat
Boton detalles
Boton arrived
Boton delivered
Boton issue
```

## UX desktop Delivery Operations

Para Level 1 / Level 2, usar dashboard desktop:

```text
KPIs arriba
Filtros laterales o superiores
Mapa amplio
Lista/table de entregas
Panel lateral de detalle
Chat contextual
Acciones de asignacion
```

KPIs:

```text
Ready for delivery
Out for delivery
Delivered today
In preparation
Pending payment
Open delivery chats
Active drivers
Issues
```

Acciones:

```text
Assign Preparation user
Assign Driver
Reassign Driver
Mark In Preparation
Mark Ready
Mark Ready For Delivery
Mark Out For Delivery
Open Route
View Driver Location
Open Chat
Contact Customer
Contact Driver
Charge Authorized Balance
View Proof
Mark Delivered
Mark Cancelled
```

## Tracking para cliente Level 5

El cliente debe ver solo su orden.

Debe mostrar:

```text
Estado actual
Timeline
Mapa limpio
Ubicacion aproximada del driver
Ultima actualizacion
ETA si se puede calcular
Boton Contact Business
Boton Chat
Boton Report Problem
```

Privacidad:

```text
No ve otros stops.
No ve rutas completas.
No ve direcciones de otros clientes.
No ve notas internas.
No ve mensajes internos.
No ve historial completo de GPS.
```

## Chat

Usar:

```text
chat_threads
chat_messages
notifications
```

No crear chat paralelo.

Con el SQL manual, `chat_threads` puede asociarse opcionalmente con:

```text
id_owner
id_store_order
id_store_order_task
context
visibility
```

Contextos sugeridos:

```text
store_delivery_customer
store_delivery_driver
store_delivery_internal
store_delivery_support
```

## Proof of Delivery

No crear `delivery_proofs`.

Usar:

```text
store_order_workflow.delivery_photo_url
store_order_workflow.delivery_notes
store_order_workflow.delivery_lat
store_order_workflow.delivery_lng
store_order_workflow.delivery_location_at
store_order_workflow.delivery_closed_by
store_order_workflow.delivered_at
```

Al marcar delivered:

```text
Guardar foto si existe.
Guardar notas.
Guardar ubicacion.
Actualizar status a DELIVERED.
Registrar event_type DELIVERED.
Notificar cliente.
Registrar mensaje de sistema si aplica.
```

## Payments en delivery

Usar:

```text
store_payments
payments_all
client_saved_payment_methods
client_auto_charge_consents
authorized_manual_charge_logs
```

En la card de delivery mostrar:

```text
Paid
Pending
Failed
Refunded
Authorized saved payment method
```

Si hay saldo pendiente y metodo autorizado:

```text
Level 1 o Level 2 puede cobrar saldo autorizado.
Registrar log.
Actualizar payment_status.
Notificar cliente.
```

## Multi-business scope

Todo debe respetar:

```text
id_owner
id_user_business cuando exista
site_key cuando aplique
```

Reglas:

```text
Level 2 solo ve su negocio.
Level 4 solo ve tareas asignadas.
Level 5 solo ve sus ordenes o token publico seguro.
No mezclar drivers, chats, ubicaciones o pagos entre negocios.
```

## Estado de implementacion inicial

Hecho:

```text
SQL manual para metadatos GPS, payroll location y contexto de chat.
Endpoint mobile location ping.
Endpoint client store order tracking.
Driver Mode UI mobile-first.
My Work envia accuracy, platform, source, permission_status y context.
Documento de arquitectura y UX.
Decision documentada de Preparation como concepto generico.
No se crean tablas paralelas.
```

Pendiente para fases siguientes:

```text
Delivery Operations dashboard avanzado con mapa.
Chat contextual desde delivery cards.
Payroll clock mobile bridge desde React Native / Expo WebView.
Pruebas reales en app mobile.
```

## Rutas implementadas en esta fase

```text
/panel/planner-hub/team/driver-mode
/panel/planner-hub/team/my-work
/views/api/mobile/location/ping
/views/api/client/store-orders/tracking
```

## Endpoint client tracking

Uso publico con token:

```text
GET /views/api/client/store-orders/tracking?token=PUBLIC_TOKEN
```

Uso con sesion Level 5:

```text
GET /views/api/client/store-orders/tracking?order_id=123
```

Respuesta:

```text
order.status
order.payment_status
order.fulfillment_status
tracking.latest
tracking.timeline
proof.photo_url
proof.notes
```

## Driver Mode implementado

La vista `/panel/planner-hub/team/driver-mode` funciona como experiencia mobile-first para Level 4.

Mantiene una estetica tipo app de ruta:

```text
topbar verde
tabs de abiertas/concluidas/devoluciones
map surface visual
delivery cards
acciones circulares
FAB hacia My Work
```

Importante:

```text
Driver Mode no duplica reglas de negocio.
Las acciones reales se procesan en /panel/planner-hub/team/my-work.
Esto conserva una sola fuente de verdad para Start, Out For Delivery, Arrived y Delivered.
```

## Metadata GPS implementada

Cada accion de delivery web/mobile puede guardar:

```text
accuracy
platform
source
permission_status
device_id
context
```

Contextos actuales:

```text
store_delivery
store_delivery_live_tracking
driver_mode
```
