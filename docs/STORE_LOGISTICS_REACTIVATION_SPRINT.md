# Store + Logistics Reactivation Sprint

## Objetivo

Reactivar y revisar Store + Logistics sin contaminar el panel de Services.

El trabajo debe separar claramente:

```text
Services
Store + Logistics
Warehouse
Store Inventory
```

La reactivacion forma parte de un sprint mayor de revision por pantallas y niveles de usuario. Este documento define el primer sprint operativo para limpiar nombres, navegacion, carga de productos, venta, postventa, preparacion y envio.

## Decision De Lenguaje

### Warehouse

El modulo actual:

```text
inventory_storage
advanced_storage_qr_inventory
```

debe presentarse al usuario como:

```text
Warehouse
Almacen
Entrepot
Armazem
```

Este modulo representa contenedores, ubicaciones, items fisicos, QR, equipos, activos, materiales y almacenamiento operativo.

No debe confundirse con productos publicados en la tienda.

### Store Inventory

Dentro de Store + Logistics, el inventario de productos debe presentarse como:

```text
Store Inventory
Inventario de tienda
Inventaire boutique
Estoque da loja
```

Este concepto vive dentro de:

```text
store_products
store_product_variations
store_products_categories
store_attributes
store_attribute_values
store_products_attributes
store_product_variation_values
```

Debe comunicar stock vendible, productos, variaciones, precios, visibilidad publica y disponibilidad de compra.

### Services

Services debe mantenerse separado. No debe usar lenguaje de tienda, productos, fulfillment o envio salvo cuando una orden de servicio tenga una integracion futura explicitamente definida.

Services sigue cubriendo:

```text
CRM
clients
service orders
contracts
team execution
payments
files
service reports
```

## Naming Matrix

| Area | Slug / tablas | Nombre UI EN | Nombre UI ES | Uso |
| --- | --- | --- | --- | --- |
| Services | `services`, `orders`, `orders_team_tasks` | Service Operations | Operaciones de servicios | Servicios, contratos, equipo, pagos de servicios |
| Warehouse | `inventory_storage`, `storage_containers`, `storage_items` | Warehouse | Almacen | Contenedores, ubicaciones, activos, QR, materiales |
| Store + Logistics | `store_delivery_tracking`, `store_orders` | Store + Logistics | Tienda + Logistica | Tienda, pedidos, preparacion, fulfillment, delivery |
| Store Inventory | `store_products`, variations | Store Inventory | Inventario de tienda | Productos vendibles, stock, variaciones, catalogo |
| Delivery | `store_order_tasks`, `store_delivery_location_logs` | Delivery Operations | Operaciones de envio | Rutas, driver mode, tracking, proof of delivery |

## Alcance Por Nivel

### Level 1 - Super Administrador

Debe poder:

```text
Ver Store + Logistics por owner/operacion.
Cambiar entre VNV Events, Avomeal u otros owners.
Gestionar productos, Store Inventory, pedidos, pagos, preparacion y envio.
Acceder a Warehouse como modulo separado.
Auditar si un owner tiene store_delivery_tracking activo.
Ver reportes sin mezclar SaaS revenue con revenue operacional.
```

Riesgo principal:

```text
Level 1 puede ver todo, pero las pantallas deben mostrar claramente el owner seleccionado para no mezclar operaciones.
```

### Level 2 - Business Owner

Debe poder:

```text
Entrar a Store + Logistics solo si el modulo esta activo.
Ver Store Inventory separado de Warehouse.
Crear productos simples sin entender variaciones avanzadas.
Crear productos variables con un flujo guiado.
Ver pedidos por estado.
Asignar preparacion y delivery.
Revisar pagos y postventa.
Ver Warehouse solo si inventory_storage esta activo.
```

Riesgo principal:

```text
Que el dueno del negocio piense que Warehouse es el inventario vendible de la tienda.
```

### Level 4 - Team Member

Debe poder:

```text
Ver trabajo asignado por company workspace.
Ver tareas de Services y Store sin mezclar los modelos.
Ver Driver Mode cuando tenga tareas DELIVERY.
Ver tareas de Preparation cuando este asignado.
Usar Warehouse solo si su rol/permisos lo permiten.
Registrar ubicacion solo en tareas que lo requieren.
```

Riesgo principal:

```text
Driver Mode no debe aparecer como una herramienta generica para usuarios sin trabajo de delivery.
```

### Level 5 - Client

Debe poder:

```text
Contratar servicios.
Comprar productos si la tienda esta activa.
Ver sus Store orders.
Ver tracking seguro de su orden.
Contactar al negocio.
Ver proof of delivery cuando aplique.
```

No debe poder:

```text
Ver Warehouse.
Ver rutas completas de driver.
Ver otros stops.
Ver notas internas.
Ver tareas internas.
Ver otros clientes.
```

## Sprint 1 - Claridad De Producto Y Navegacion

### Meta

Eliminar confusion entre Warehouse, Store Inventory y Services antes de tocar flujos complejos.

### Tareas

1. Auditar labels actuales en:

```text
src/Languages/en.json
src/Languages/es.json
src/Languages/fr.json
src/Languages/pt.json
src/views/templates/layout/sidebars
src/views/panel/level1
src/views/panel/level2
src/views/panel/level4
src/views/panel/level5
```

2. Cambiar labels de `inventory_storage` a Warehouse / Almacen.

3. Cambiar descripciones del modulo Warehouse para que hablen de:

```text
containers
items
locations
QR labels
equipment
physical assets
```

4. Cambiar labels de productos/stock dentro de tienda a Store Inventory / Inventario de tienda.

5. Revisar sidebars:

```text
Level 1: Store + Logistics y Warehouse separados.
Level 2: Store + Logistics separado de Warehouse.
Level 4: My Work, Driver Mode y Warehouse separados por permiso.
Level 5: Store orders / delivery tracking, sin Warehouse.
```

6. Revisar command search para que:

```text
warehouse no apunte a Store products
store inventory no apunte a storage containers
products no apunte a Warehouse
services no apunte a Store + Logistics
```

### Criterios De Aceptacion

```text
Un usuario puede distinguir Warehouse de Store Inventory sin leer documentacion.
Services no muestra lenguaje de productos/envios.
Store + Logistics puede estar activo sin cambiar el significado de Warehouse.
Las traducciones EN/ES/FR/PT mantienen el mismo contrato conceptual.
```

## Sprint 2 - Store Inventory Simplificado

### Meta

Hacer que la carga de productos sea entendible antes de revisar venta y envio.

### Problemas A Revisar

```text
Productos simples versus variables.
Atributos versus valores.
Variaciones y stock por variacion.
Visibilidad publica.
Precio normal versus precio promocional.
Campos Avomeal/nutricion que no aplican a todos los negocios.
```

### Propuesta UX

La pantalla de productos debe tener dos modos:

```text
Simple product
Variable product
```

Simple product debe pedir solo:

```text
Name
Description
Category
Price
Stock
Image
Public visibility
Status
```

Variable product debe abrir despues:

```text
Options
Variations
Price per variation
Stock per variation
SKU per variation
```

Campos especializados como nutrition, weekly menu, meal style y audience deben aparecer solo cuando el owner/operation sea Avomeal o cuando el negocio tenga configuracion de food/meal prep.

### Criterios De Aceptacion

```text
Se puede crear un producto simple en menos de 2 minutos.
Crear un producto simple no requiere entender atributos.
Un producto variable explica claramente que el stock puede vivir en cada variacion.
Store Inventory no usa el nombre Warehouse.
```

## Sprint 3 - Venta Y Checkout

### Meta

Revisar el proceso desde catalogo hasta pago aprobado.

### Flujo

```text
Public Store
Cart
Checkout
Payment provider
Store order
Store payment
Order access token
Client association
```

### Puntos De Control

```text
Owner scope correcto desde catalogo hasta checkout.
Provider correcto por owner.
Stock decrece solo despues de pago aprobado.
Store payments no se mezclan con payments_all salvo migracion futura.
Cliente Level 5 se asocia por email/id_user sin duplicar.
Order access token sigue funcionando.
```

## Sprint 4 - Postventa, Preparacion Y Fulfillment

### Meta

Revisar la operacion despues de la venta.

### Flujo

```text
NEW
CONFIRMED
PROCESSING
IN_PREPARATION
READY
READY_FOR_DELIVERY
OUT_FOR_DELIVERY
DELIVERED
COMPLETED
```

### Tareas

```text
Revisar Store Orders board.
Revisar asignacion de Preparation user.
Revisar asignacion de Delivery user.
Revisar store_order_workflow.
Revisar store_order_tasks.
Revisar tareas adicionales.
Revisar permisos allow_team_close_delivery y allow_chat_with_client.
```

### Regla De Lenguaje

Usar:

```text
Preparation
Preparation user
Preparation ready
```

No usar Kitchen como nombre universal. `store_order_workflow.kitchen_user_id` queda solo como compatibilidad interna.

## Sprint 5 - Delivery Operations Y Driver Mode

### Meta

Reactivar envio y tracking con seguridad por owner y por tarea.

### Flujo

```text
Ready for delivery
Out for delivery
Arrived
Delivered
Delivery attempted
Returned to business
Redelivery scheduled
```

### Pantallas

```text
Level 1 Store Orders
Level 2 Store Orders
Level 4 My Work
Level 4 Driver Mode
Level 5 Store order tracking
Public token tracking
```

### Datos

```text
store_delivery_location_logs
store_order_workflow.delivery_lat
store_order_workflow.delivery_lng
store_order_workflow.delivery_location_at
store_order_workflow.delivery_photo_url
store_order_workflow.delivery_notes
```

### Criterios De Aceptacion

```text
Driver no puede iniciar delivery sin ubicacion cuando la tarea lo requiere.
Cliente ve solo su orden y ubicacion aproximada/relevante.
Admin ve ultima ubicacion y proof.
No se crean tablas delivery paralelas.
No se rastrea al empleado fuera de una tarea activa.
```

## Sprint 6 - Chat, Notificaciones Y Cliente

### Meta

Conectar comunicacion sin crear chat paralelo.

### Reglas

```text
Usar chat_threads y chat_messages.
Usar notifications.
Contextualizar por owner/order/task cuando las columnas existan.
No exponer notas internas a Level 5.
No mezclar clientes entre owners.
```

### Criterios De Aceptacion

```text
Admin puede contactar cliente o driver desde orden.
Driver puede contactar cliente solo si tiene permiso.
Cliente puede contactar negocio desde tracking/portal.
Los mensajes respetan id_owner y relacion clients_users.
```

## Sprint 7 - QA Por Nivel

### Level 1

```text
Cambiar owner/operation.
Crear producto simple.
Crear producto variable.
Crear pedido manual.
Asignar preparacion.
Asignar delivery.
Ver tracking.
Ver reportes.
```

### Level 2

```text
Sin Store activo: no acceso operacional.
Con Store activo: acceso a Store + Logistics.
Sin Warehouse activo: no acceso a Warehouse.
Con Warehouse activo: acceso a Warehouse.
```

### Level 4

```text
Ver My Work.
Ver tarea Preparation.
Ver tarea Delivery.
Abrir Driver Mode solo cuando aplica.
Mandar location ping.
Cerrar delivery segun permisos.
```

### Level 5

```text
Comprar producto.
Ver pedido.
Ver tracking.
Ver proof.
Contactar negocio.
No ver informacion interna.
```

## No Hacer En Este Sprint

```text
No crear tablas paralelas de delivery.
No mover Store orders a tablas de Services.
No renombrar columnas destructivamente.
No borrar rutas legacy que usan mobile/WebView.
No cambiar contratos de pagos sin revisar payment docs.
No mezclar Warehouse con Store Inventory.
```

## Orden Recomendado De Implementacion

1. Naming y traducciones.
2. Sidebars y command search.
3. Store Inventory simple product flow.
4. Product variations cleanup.
5. Store orders board review.
6. Preparation assignment review.
7. Delivery assignment and Driver Mode review.
8. Client tracking review.
9. Chat/notifications review.
10. QA por niveles.

## Resultado Esperado

Al final de este frente, Ophyra debe comunicar:

```text
Services = operaciones de servicios.
Warehouse = almacen fisico, contenedores, equipos, QR.
Store Inventory = productos vendibles dentro de Store.
Store + Logistics = venta, postventa, preparacion, fulfillment, envio y tracking.
```

Con esa separacion clara, la reactivacion de Store + Logistics puede avanzar pantalla por pantalla sin romper Services ni confundir inventario fisico con catalogo vendible.
