# Ophyra - Revision General Del Modulo Store + Logistics / Delivery

Fecha de preparacion: 2026-07-06

## Estado De Activacion Revisado

Este bloque de revision se ejecuta sobre el modulo existente, sin reconstruir funcionalidades ya desarrolladas.

La revision confirma que Store + Logistics se valida como modulo privado activo para Level 1, Level 2, Level 4 y Level 5 cuando el acceso corresponde. Las entradas privadas de navegacion, busqueda interna, dashboard del owner, workspace del team member, Driver Mode y portal de cliente deben abrir pantallas existentes del modulo o mostrar bloqueo por modulo cuando no exista activacion.

La revision mantiene intacto el mensaje publico del home, pricing publico, SEO publico y landings publicas. El alcance se limita a dashboards privados, accesos internos, nombres operativos y flujos de QA.

## Resultado De Revision Ejecutada

El modulo privado Store + Logistics queda revisado como flujo activo para navegacion, permisos, productos, pedidos, preparacion, delivery, tracking, proof of delivery, cliente y reportes.

Se confirma:

```text
Store + Logistics abre desde dashboards privados cuando el modulo esta activo.
Los accesos bloqueados siguen usando no-access por modulo.
Services conserva sus rutas, tablas y flujo separado.
Warehouse queda nombrado como modulo fisico separado de Store Inventory.
Store Inventory queda reservado para productos vendibles, variaciones, SKU y stock de tienda.
Level 2 accede a Store Orders, Store Inventory, reportes y postventa.
Level 4 accede a tareas asignadas, Preparation, Delivery y Driver Mode.
Level 5 accede a pedidos de tienda, tracking, proof y devoluciones propias.
Level 1 conserva auditoria multi-owner sin mezclar con Services.
```

No se requieren migraciones automaticas para esta revision. Los ajustes realizados son de activacion, navegacion, traduccion, endurecimiento de consultas por owner, validacion de SKU/variaciones, compatibilidad de esquema y rendimiento de tracking.

## Proposito Del Documento

Este documento define la revision general del modulo Store + Logistics / Delivery como una capacidad activa para validar pantalla por pantalla, nivel por nivel y flujo por flujo.

El documento esta escrito como guia de QA y activacion del modulo completo. Su objetivo es confirmar que la tienda, inventario de tienda, postventa, preparacion, delivery, tracking, chat, notificaciones, time tracking, historial y trazabilidad funcionan como un solo flujo operacional sin afectar el panel de Services.

## Regla Principal De Alcance

Store + Logistics no debe mezclarse con Services.

Services usa:

```text
orders
orders_team_tasks
orders_services
orders_payments
orders_contracts
orders_files
```

Store + Logistics usa:

```text
store_orders
store_order_items
store_payments
store_order_workflow
store_order_tasks
store_delivery_location_logs
store_delivery_zones
store_products
store_product_variations
store_categories
store_carts
store_user_roles
```

Las pantallas pueden convivir en Planner Hub, pero los datos, permisos, etiquetas, reportes y flujos deben quedar separados.

## Lenguaje Oficial Del Modulo

### Store + Logistics

Modulo de tienda, venta, postventa, preparacion, fulfillment, delivery, tracking, devoluciones y reportes de tienda.

### Store Inventory

Inventario vendible de la tienda. Incluye productos, SKU, categorias, atributos, variaciones, precios, stock, peso, dimensiones y visibilidad publica.

### Warehouse / Almacen

Modulo separado para contenedores, items fisicos, materiales, activos, equipos, ubicaciones y etiquetas QR.

Warehouse no es Store Inventory.

### Preparation

Preparation es el nombre operacional universal. Kitchen queda solo como compatibilidad interna donde ya exista, por ejemplo:

```text
store_order_workflow.kitchen_user_id
```

La UI debe decir:

```text
Preparation user
Preparation assignment
Preparation ready
```

## Niveles De Usuario En Revision

### Level 1 - Super Administrador

Debe validar:

```text
Acceso total por owner/operacion.
Seleccion clara de negocio/owner.
Gestion de Store Inventory.
Gestion de Store Orders.
Asignacion de Preparation y Delivery.
Revision de pagos.
Revision de tracking.
Revision de proof of delivery.
Revision de reportes.
Separacion de Store + Logistics, Warehouse y Services.
```

### Level 2 - Business Owner

Debe validar:

```text
Acceso solo a su negocio.
Acceso a Store + Logistics segun modulo activo.
Acceso a Warehouse solo si Warehouse esta activo.
Carga de productos simples.
Carga de productos variables.
Importacion y actualizacion CSV.
Revision de ordenes de tienda.
Asignacion de preparadores y repartidores.
Postventa.
Delivery y tracking.
Chat con cliente y equipo.
Reportes del modulo.
```

### Level 4 - Team Member

Debe validar:

```text
Workspace de compania seleccionado.
Tareas de Preparation asignadas.
Tareas de Delivery asignadas.
Driver Mode.
Registro de ubicacion.
Inicio de ruta.
Estados de entrega.
Proof of delivery.
Chat segun permisos.
Time tracking con ubicacion.
```

### Level 5 - Cliente

Debe validar:

```text
Compra o acceso a orden de tienda.
Historial de Store Orders.
Tracking de su propia orden.
Mapa de seguimiento.
ETA visible cuando aplique.
Chat con negocio.
Confirmacion/proof de entrega.
Comentarios y calificaciones.
```

No debe ver:

```text
Warehouse.
Rutas completas con otros stops.
Otros clientes.
Notas internas.
Tareas internas.
Historial completo de GPS.
Reportes internos.
```

## Sprint 1 - Membresia Y Activacion

### Objetivo

Validar que la activacion comercial y tecnica del modulo funcione correctamente.

### Revision

```text
Crear cuenta.
Confirmar email.
Login.
Recuperar password.
Cambiar password.
Comprar membresia.
Renovar membresia.
Cancelar membresia.
Reactivar membresia.
Activar Store + Logistics.
Desactivar Store + Logistics.
Verificar redireccion sin modulo activo.
Verificar acceso con modulo activo.
```

### Criterios

```text
Level 2 sin modulo activo no accede a operaciones de Store + Logistics.
Level 2 con modulo activo accede a Store + Logistics.
Level 1 puede revisar el modulo por owner.
La activacion no modifica acceso a Services.
```

## Sprint 2 - Configuracion Inicial

### Objetivo

Validar la configuracion base del negocio para operar tienda y delivery.

### Revision

```text
Empresa.
Direccion.
Telefono.
Email operativo.
Zonas de entrega.
Horarios de entrega.
Horarios de pickup si aplica.
Impuestos.
Moneda.
Configuracion general.
Provider de pago por owner.
Reglas de disponibilidad de pago.
```

### Criterios

```text
La configuracion pertenece al owner correcto.
Las zonas de entrega se guardan por id_owner.
La moneda y pagos no afectan Services.
El checkout usa el provider correcto del negocio vendedor.
```

## Sprint 3 - Store Inventory

### Objetivo

Validar productos vendibles de tienda sin confundirlos con Warehouse.

### Revision

```text
Crear producto simple.
Editar producto simple.
Crear producto variable.
Editar producto variable.
SKU.
Categorias.
Atributos.
Valores de atributos.
Variaciones.
Precio.
Costo.
Stock.
Peso.
Dimensiones.
Imagen.
Estado activo/inactivo.
Visibilidad publica.
Importacion CSV.
Actualizacion CSV.
Inventario bajo.
Inventario agotado.
Stock por variacion.
```

### Criterios

```text
Un producto simple se puede crear sin configurar atributos.
Un producto variable muestra claramente opciones, variaciones y stock.
Store Inventory no usa labels de Warehouse.
Warehouse no lista productos vendibles como contenedores.
El stock insuficiente bloquea venta o reduce disponibilidad.
```

## Sprint 4 - Store Orders

### Objetivo

Validar el ciclo administrativo de ordenes de tienda.

### Revision

```text
Crear orden desde checkout.
Crear orden manual.
Editar datos permitidos.
Cancelar orden.
Actualizar estado.
Historial de estado.
Filtros.
Busquedas.
Vista de detalle.
Productos de la orden.
Cliente.
Direccion de shipping.
Payment status.
Operational status.
```

### Criterios

```text
Store Orders usa store_orders.
Services Orders usa orders.
Los filtros no mezclan ordenes de servicios.
El historial conserva trazabilidad.
Payment status y operational status se mantienen separados.
```

## Sprint 5 - Team Members

### Objetivo

Validar que el equipo pueda operar preparacion y delivery con permisos correctos.

### Revision

```text
Crear preparadores.
Crear repartidores.
Asignar roles.
Validar store_user_roles.
Validar user_institutions.
Validar permisos de chat con cliente.
Validar permisos de cierre de delivery.
Validar restricciones por owner.
```

### Criterios

```text
Level 4 ve solo tareas del workspace seleccionado.
Un repartidor no ve tareas de otro negocio.
Un preparador no recibe permisos de delivery si no corresponde.
Los roles de Store no cambian Services.
```

## Sprint 6 - Preparation

### Objetivo

Validar preparacion y readiness antes del envio.

### Revision

```text
Pedido recibido.
Asignar Preparation user.
Marcar en preparacion.
Marcar preparado.
Marcar listo.
Marcar listo para delivery.
Historial.
Notificaciones.
Notas operativas.
```

### Criterios

```text
Preparation usa store_order_tasks.task_type = PREPARATION.
La UI no usa Kitchen como nombre general.
La transicion READY_FOR_DELIVERY requiere estado valido.
El cliente ve estado relevante sin notas internas.
```

## Sprint 7 - Delivery Operations

### Objetivo

Validar el flujo operativo completo de delivery.

### Revision

```text
Asignar Delivery user.
Aceptar entrega.
Iniciar traslado.
Registrar geolocalizacion.
Mostrar ruta.
Marcar arrived.
Marcar delivered.
Marcar delivery attempted.
Marcar returned to business.
Marcar redelivery scheduled.
Cancelar entrega.
Registrar observaciones.
```

### Criterios

```text
Delivery usa store_order_tasks.task_type = DELIVERY.
Delivery usa store_delivery_location_logs.
No se crean tablas paralelas de rutas o pruebas.
El driver no inicia delivery sin ubicacion cuando se requiere.
Las acciones actualizan store_order_workflow y store_orders.
```

## Sprint 8 - Driver Mode

### Objetivo

Validar experiencia mobile-first del repartidor.

### Revision

```text
Header compacto.
Negocio seleccionado.
Driver name.
Estado GPS.
Tabs por estado.
Mapa superior.
Ruta optimizada.
Pins numerados.
Lista de entregas.
Acciones rapidas.
Open navigation.
Refresh location.
Call customer.
Chat.
Arrived.
Delivered.
Issue.
Return.
Proof photo.
```

### Criterios

```text
Driver Mode muestra solo entregas asignadas.
Driver Mode respeta owner/workspace.
Las acciones reales se procesan con la fuente de verdad existente.
La vista es usable en mobile.
No reemplaza My Work, lo complementa.
```

## Sprint 9 - Chat

### Objetivo

Validar comunicacion contextual sin crear chat paralelo.

### Revision

```text
Administrador-Cliente.
Administrador-Delivery.
Delivery-Cliente si autorizado.
Administrador-Preparation user.
Historial.
Notificaciones.
Contexto por owner.
Contexto por orden.
Contexto por tarea.
```

### Criterios

```text
Usa chat_threads y chat_messages.
Usa notifications.
No expone mensajes internos al cliente.
Delivery-Cliente requiere permiso.
El cliente esta asociado al negocio correcto.
```

## Sprint 10 - Seguimiento Y Tracking

### Objetivo

Validar mapa, ETA y ubicacion durante delivery.

### Revision

```text
Mapa para cliente.
Mapa para administrador.
Mapa para driver.
ETA.
Ultima ubicacion.
Ubicacion durante ruta.
Refresh de ubicacion.
Permisos de GPS.
Tracking por token publico.
Tracking por sesion Level 5.
Privacidad del tracking.
```

### Criterios

```text
Cliente ve solo su orden.
Cliente no ve otros stops.
Cliente no ve ruta completa si incluye otros clientes.
Administrador ve ultima ubicacion relevante.
Driver envia pings mientras la tarea esta activa.
ETA se muestra de forma clara y verificable.
La ubicacion en vivo se valida como actualizaciones sucesivas registradas.
```

## Sprint 11 - Entrega Y Proof Of Delivery

### Objetivo

Validar cierre de entrega y evidencia.

### Revision

```text
Foto de evidencia.
Observaciones.
Fecha.
Hora.
Ubicacion de cierre.
Usuario que cerro.
Confirmacion de entrega.
Notificacion al cliente.
Vista del proof para admin.
Vista del proof para cliente.
```

### Criterios

```text
Proof usa store_order_workflow.delivery_photo_url.
Notas usan store_order_workflow.delivery_notes.
Ubicacion usa delivery_lat, delivery_lng y delivery_location_at.
El cierre actualiza DELIVERED cuando corresponde.
El cliente no ve notas internas.
```

## Sprint 12 - Devoluciones Y Reintentos

### Objetivo

Validar excepciones posteriores al intento de entrega.

### Revision

```text
Devolucion parcial.
Devolucion total.
Reintento.
Regreso a almacen.
Returned to business.
Return requested.
Return approved.
Return rejected.
Return closed.
Impacto en Store Inventory.
Impacto operacional en Warehouse cuando aplique.
Notas de devolucion.
Historial de devolucion.
```

### Criterios

```text
Las devoluciones de venta afectan Store Inventory cuando corresponde.
Warehouse solo participa si hay movimiento fisico real.
El cliente ve estado y mensaje adecuado.
Admin conserva auditoria.
```

## Sprint 13 - Cliente

### Objetivo

Validar experiencia final del cliente.

### Revision

```text
Historial de Store Orders.
Detalle de orden.
Estado de pago.
Estado de preparacion.
Estado de envio.
Tracking.
Proof of delivery.
Comentarios.
Calificaciones.
Chat con negocio.
Report problem.
```

### Criterios

```text
Level 5 ve sus ordenes por id_user o guest_email.
Cada orden identifica el negocio vendedor.
No depende de cambiar company context para ver compras propias.
No ve informacion interna.
```

## Sprint 14 - Historicos, Auditoria Y Exportacion

### Objetivo

Validar trazabilidad del modulo.

### Revision

```text
Busqueda.
Filtros.
Exportacion.
Historial de estados.
Historial de pagos.
Historial de delivery.
Historial de chat.
Historial de devoluciones.
Auditoria por owner.
Auditoria por usuario.
```

### Criterios

```text
Los historicos no mezclan Services.
Los reportes Store usan store_payments para revenue de tienda.
Los reportes Services usan sus propias tablas.
Cada export respeta id_owner.
```

## Sprint 15 - Time Tracking

### Objetivo

Validar jornada y ubicacion de team members.

### Revision

```text
Iniciar jornada.
Detener jornada.
Pausas.
Horas.
Geolocalizacion al inicio.
Geolocalizacion al fin.
Owner correcto.
Relacion con delivery activo.
```

### Criterios

```text
payroll_time_logs registra ubicacion cuando aplica.
La ubicacion no rastrea fuera de contexto operacional.
El registro respeta workspace/owner.
Time tracking no cambia estados de Services ni Store salvo accion explicita.
```

## Sprint 16 - Notificaciones

### Objetivo

Validar avisos del modulo.

### Revision

```text
Notificacion a administrador.
Notificacion a cliente.
Notificacion a preparador.
Notificacion a delivery.
Nueva orden.
Pago aprobado.
Pedido en preparacion.
Pedido listo.
Out for delivery.
Delivered.
Delivery attempted.
Return.
Chat message.
```

### Criterios

```text
Cada notificacion respeta id_owner.
No se envian notificaciones Avomeal a VNV Events ni viceversa.
Las notificaciones no duplican mensajes innecesarios.
Mobile/WebView no rompe payloads existentes.
```

## Sprint 17 - Casos Extremos

### Objetivo

Validar resiliencia.

### Revision

```text
Internet lento.
Perdida de conexion.
GPS desactivado.
Permisos denegados.
Imagenes grandes.
Stock insuficiente.
Edicion simultanea.
Sesion expirada.
Cambio de dispositivo.
Cierre inesperado del navegador.
Recarga de pagina durante entrega.
Pago aprobado pero callback tardio.
Duplicado de submit.
CSV con filas invalidas.
Producto eliminado con orden historica.
```

### Criterios

```text
El sistema falla con mensajes claros.
No duplica ordenes ni pagos por submit repetido.
No permite delivery sin permisos requeridos.
No pierde auditoria.
No afecta Services.
```

## Sprint 18 - Rendimiento

### Objetivo

Validar carga operacional.

### Revision

```text
Carga masiva CSV.
Store Inventory grande.
Listado de Store Orders grande.
Carga de mapa.
Pings sucesivos de ubicacion.
Imagenes de proof.
Consumo de memoria.
Consumo de bateria en app movil futura.
Tiempo de respuesta en filtros.
Tiempo de respuesta en busquedas.
```

### Criterios

```text
Las pantallas cargan en tiempos aceptables.
Los filtros no hacen consultas sin scope.
Los mapas no bloquean la operacion.
Los pings de ubicacion no saturan la base.
```

## Sprint 19 - Validacion End-To-End

### Objetivo

Validar el flujo completo como negocio real.

### Escenario

```text
Crear cuenta.
Comprar membresia.
Activar Store + Logistics.
Configurar empresa.
Configurar zonas de entrega.
Crear Store Inventory.
Importar CSV.
Crear producto simple.
Crear producto variable.
Crear team members.
Crear cliente.
Generar pedido.
Procesar pago.
Preparar.
Asignar delivery.
Iniciar ruta.
Compartir ubicacion.
Usar chat.
Mostrar ETA.
Entregar.
Subir fotos.
Confirmar entrega.
Cliente comenta/califica.
Verificar historico completo.
Verificar reportes.
Verificar trazabilidad.
Verificar que Services sigue funcionando.
```

### Criterios

```text
Todo el flujo queda auditado.
Cada pantalla respeta owner y nivel.
Cliente solo ve lo permitido.
Team member solo ve tareas asignadas.
Level 2 solo ve su negocio.
Level 1 puede auditar sin mezclar revenue.
Services no se rompe ni absorbe datos de tienda.
```

## Prueba De No Regresion Para Services

Esta prueba debe ejecutarse durante la revision general.

```text
Entrar al panel de Services.
Crear o abrir una orden de servicio.
Revisar cliente.
Revisar contrato.
Revisar pago de servicio.
Revisar team task de servicio.
Revisar archivos.
Revisar chat si aplica.
Confirmar que no aparecen productos de tienda.
Confirmar que no aparecen Store Orders.
Confirmar que no aparecen estados de delivery de Store.
Confirmar que Warehouse no aparece como Store Inventory.
```

## Checklist Final De Aprobacion

```text
Store + Logistics activo y revisado.
Store Inventory claro y separado.
Warehouse claro y separado.
Services sin regresiones.
Level 1 revisado.
Level 2 revisado.
Level 4 revisado.
Level 5 revisado.
Checkout revisado.
Pagos revisados.
Preparation revisado.
Delivery revisado.
Driver Mode revisado.
Tracking y ETA revisados.
Proof of delivery revisado.
Chat revisado.
Notificaciones revisadas.
Historicos revisados.
Reportes revisados.
Casos extremos revisados.
Rendimiento revisado.
End-to-end aprobado.
```

## Resultado Esperado

Al completar esta revision, el modulo Store + Logistics / Delivery queda validado como un flujo operacional completo:

```text
Activacion
Configuracion
Store Inventory
Venta
Pago
Postventa
Preparation
Fulfillment
Delivery
Tracking
ETA
Chat
Proof of delivery
Returns
Historial
Reportes
Cliente
Auditoria
```

Todo esto debe funcionar sin afectar el panel de Services y manteniendo la separacion entre Store Inventory y Warehouse.
