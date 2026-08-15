# Respuesta a los informes de revisión de plataforma

Fecha de cierre: 16 de julio de 2026  
Proyecto: Ophyra — Store, Logistics, Delivery y Warehouse  
Revisor original: Claudio Francesco  
Estado: hallazgos reportados revisados, corregidos y validados

## Propósito

Este documento responde las preguntas y observaciones incluidas en los cinco archivos enviados por el revisor:

1. `Informe de Revisión de Plataforma.pdf`
2. `Informe de Revisión de Plataforma N 2.pdf`
3. `Informe de Revisión de Plataforma 3.pdf`
4. `Informe de Revisión de Plataforma 4.pdf`
5. `Informe de Revisión de Plataforma 5.pdf`

Las correcciones reutilizan las vistas, controladores, repositorios, tablas y flujos ya existentes. No se creó un segundo sistema de tienda, logística, tracking, chat, documentos o inventario.

## Resumen ejecutivo

Los hallazgos concretos de los cinco informes pueden considerarse cerrados.

Se corrigieron y validaron:

- Enlaces públicos de productos y categorías.
- Búsqueda parcial en Warehouse.
- Acceso a categorías de contenedores.
- Cambio de vista y contexto de Level 4.
- Creación y asignación de tareas de Preparation y Delivery.
- Impresión y escaneo del QR del paquete.
- Inicio de ruta, GPS y tracking.
- Foto, comentarios y prueba de entrega.
- Intento fallido, retorno al negocio y reentrega.
- Chat con el negocio, delivery y cliente.
- Navegación del portal Level 5.
- Separación visual entre órdenes de servicio y contratos/archivos.
- Alternativas de pago en línea y pago manual.
- Orientación cuando el negocio no tiene SMTP.

## Respuestas por informe

### Informe de Revisión de Plataforma.pdf

#### 1. Productos de la tienda: “Ver público” o pulsar la imagen produce 404

**Respuesta:** Era un error real en la construcción de las rutas públicas.

**Corrección:**

- Se corrigieron los enlaces de productos para usar las rutas compatibles con el Router actual.
- Se corrigió la navegación desde la imagen y desde “Ver público”.
- Se preservó el scope del negocio propietario.

**Validación:** Producto y categoría QA creados y abiertos en Chromium sin pantalla blanca ni 404.

#### 2. El buscador de almacenamiento no resulta intuitivo

**Respuesta:** La observación era válida. El buscador necesitaba coincidencia parcial.

**Corrección:** Ahora busca mediante coincidencia parcial en:

- Nombre del artículo.
- Nombre del contenedor.

No es necesario escribir el nombre completo; una parte del término puede devolver resultados.

#### 3. La vista pública de categorías abre una pantalla en blanco

**Respuesta:** Era el mismo problema de routing público detectado en productos.

**Corrección:** Se corrigieron los enlaces públicos de categorías y su asociación con los productos visibles del owner correspondiente.

#### 4. ¿Cómo se escanea el QR para la entrega y el seguimiento en tiempo real?

**Respuesta:** El proceso completo es el siguiente:

1. Level 2 recibe o crea el pedido.
2. Level 2 asigna Preparation y Delivery.
3. Preparation prepara el paquete.
4. Preparation imprime el tag con QR desde My Work.
5. Delivery abre Driver Mode desde un dispositivo móvil.
6. Delivery escanea el QR y el sistema identifica el paquete asignado.
7. Delivery registra foto del paquete y ubicación para iniciar la ruta.
8. El pedido cambia a `OUT_FOR_DELIVERY`.
9. Cliente y Level 2 pueden consultar el estado y la última ubicación disponible.
10. Delivery registra entrega, intento fallido o retorno al negocio.

La ubicación usa `store_delivery_location_logs`; la evidencia y comentarios usan `store_order_workflow`; las asignaciones usan `store_order_tasks`.

### Informe de Revisión de Plataforma N 2.pdf

#### 1. Cambiar a vista de equipo produce 404

**Respuesta:** Era un error de navegación y contexto de usuario.

**Corrección:**

- El cambio de vista utiliza el flujo POST de nivel existente.
- Level 4 resuelve la empresa activa mediante `user_institutions`.
- Las tareas se filtran por usuario y owner seleccionado.

**Validación:** Se inició sesión con un Delivery Level 4 y se abrieron My Work y Driver Mode dentro de la empresa QA correcta.

#### 2. No se puede agregar un miembro o crear una tarea

**Respuesta:** Era un problema real de resolución de miembros multiempresa.

**Corrección:**

- Se corrigió la consulta de roles de Store para Level 4.
- Level 2 puede asignar Preparation y Delivery.
- Las tareas aparecen para el miembro asignado.
- No se muestran miembros de otro owner.

**Validación:** Se asignaron tareas reales de Preparation y Delivery a un usuario Level 4 y se comprobaron en pantalla.

#### 3. ¿Cuál es el siguiente paso para escanear QR y hacer tracking?

**Respuesta:** El siguiente paso se realiza en Driver Mode. El delivery pulsa “Escanear paquete”, escanea el tag impreso por Preparation y continúa con foto de salida, GPS, navegación y estado de entrega.

### Informe de Revisión de Plataforma 3.pdf

#### 1. ¿Se puede crear un usuario para realizar pruebas en vivo de inventario?

**Respuesta:** Sí.

El usuario debe ser Level 4, estar asociado al negocio mediante `user_institutions` y tener acceso al workspace correspondiente. Warehouse y Store Inventory permanecen separados:

- Store Inventory administra productos vendibles, SKU, variaciones y stock comercial.
- Warehouse administra contenedores, artículos físicos, categorías y tags QR.

#### 2. No se puede entrar al módulo de categorías de contenedores

**Respuesta:** En la versión revisada originalmente no existía el CRUD real; solamente había referencias visuales incompletas. No era una acción adicional que el usuario tuviera que realizar.

**Corrección implementada:**

- Categorías de contenedores por owner.
- Creación y listado de categorías.
- Asociación al crear o editar un contenedor.
- Conteo de contenedores por categoría.
- Bloqueo de eliminación cuando una categoría está en uso.
- Opción “Sin categoría”.

Esta funcionalidad pertenece exclusivamente a Warehouse y no duplica las categorías de productos de Store.

### Informe de Revisión de Plataforma 4.pdf

#### “Trackear” y “Escribir al negocio” producen 404 o pantalla blanca

**Respuesta:** Eran errores reales de rutas y contexto del cliente.

**Corrección:**

- Se corrigió el tracking Level 5.
- Se corrigió el acceso público mediante token del pedido.
- Se corrigió la apertura del chat con el negocio y con Delivery cuando el permiso lo permite.
- Se inicializaron de forma segura las variables de mensajes no leídos.
- Se añadió soporte seguro del filtro Twig necesario para la página pública del QR.

**Validación:** Portal Level 5, tracking público, chat cliente-negocio y chat cliente-delivery probados en Chromium.

### Informe de Revisión de Plataforma 5.pdf

#### Órdenes de servicios y Contratos/Archivos abren aparentemente la misma pantalla. ¿Es correcto?

**Respuesta:** Los documentos pertenecen a su orden de servicio, por lo que comparten el mismo origen de datos. Sin embargo, la observación sobre la experiencia visual era válida: no quedaba claro que fueran secciones diferentes.

**Corrección:**

- Se añadieron pestañas visibles para “Órdenes de servicio” y “Contratos/Archivos”.
- La sección activa queda identificada.
- Contratos/Archivos muestra una explicación de que los documentos están organizados por orden.
- En la vista documental se ocultan columnas operativas que no corresponden.
- Los archivos y contratos continúan vinculados a la orden original, evitando un repositorio duplicado.

## Flujo de entrega validado

### Entrega exitosa

La orden QA `#4` fue validada con:

- Pago `PAID`.
- Estado final `DELIVERED`.
- Tarea Delivery `COMPLETED`.
- Foto de evidencia.
- Comentarios de entrega.
- Coordenadas y fecha de cierre.
- Visualización del estado para el cliente.

### Intento fallido

La orden QA `#7` fue validada con:

- Estado `DELIVERY_ATTEMPTED`.
- Tarea `WAITING_REVIEW`.
- Comentario obligatorio indicando la causa.
- Registro del evento de ubicación correcto.

### Retorno y reentrega

La orden QA `#8` fue validada con:

- Retorno inicial `RETURNED_TO_BUSINESS`.
- Comentario obligatorio.
- Revisión posterior por Level 2.
- Estado final de prueba `REDELIVERY_SCHEDULED`.

## Inventario y devoluciones

La restitución de stock usa un registro único por owner y pedido en `store_order_stock_returns`.

Esto garantiza que:

- Un retorno aprobado puede devolver unidades al inventario.
- Repetir accidentalmente la acción no incrementa el stock dos veces.
- Si la restitución falla, la transacción se revierte.
- Las variaciones y los productos simples se actualizan en sus tablas correspondientes.

La prueba automatizada ejecutó dos veces la restitución y confirmó un solo aumento de inventario.

## Pagos

Los caracteres dañados que aparecían antes de Stripe, Square y PayPal fueron eliminados. Los nombres se muestran como texto limpio y los iconos utilizan el sistema visual del panel.

La pantalla de configuración explica dos alternativas:

1. Configurar y verificar Stripe, Square o PayPal para pagos en línea.
2. Crear pedidos con pago manual y registrar método, referencia, notas, importe, moneda y responsable.

Un pedido pagado manualmente no retrocede de estado logístico.

## SMTP y comunicaciones

La pantalla SMTP informa claramente:

- El negocio debe configurar y verificar SMTP para enviar correos desde su cuenta.
- Mientras no exista SMTP, debe comunicarse mediante el chat privado de Ophyra o la aplicación conectada.
- Las operaciones, estados y notificaciones internas continúan funcionando sin SMTP.
- Una configuración SMTP propia y activa del Level 2 tiene prioridad sobre el fallback global.

## Google Maps

Google Maps continúa siendo una integración global de la plataforma.

- La clave se obtiene desde `env.GOOGLE_KEY` en el layout administrativo.
- No se solicita una clave de Maps por usuario, negocio o Level 2.
- No se agregó una configuración duplicada de Maps.

## Resiliencia

Se agregaron controles para:

- Geolocalización obligatoria en las acciones que la requieren.
- Mensaje cuando el permiso GPS es denegado.
- Mensaje cuando no existe conexión antes de actualizar una entrega.
- Bloqueo del doble submit en Driver Mode.
- Estados válidos antes de entregar, fallar o retornar un pedido.
- Foto obligatoria para cerrar una entrega autorizada.
- Imagen JPG, PNG o WEBP con límite de 5 MB.
- Almacenamiento local de evidencias cuando no se utiliza Cloudinary.

## Rendimiento

Se realizó una prueba local transaccional con 1,000 pedidos QA. La consulta utilizada por el tablero recuperó 300 filas en aproximadamente 6.13 ms en el ambiente local. Los datos de carga fueron revertidos al terminar la prueba.

## Pruebas aprobadas

```text
Store logistics contracts OK
Store return stock idempotency OK
Store logistics query performance OK
visible_store_logistics.spec.js
visible_store_existing.spec.js
visible_store_build.spec.js
visible_store_order.spec.js
visible_delivery_complete.spec.js / delivery_controller_close.spec.js
visible_delivery_exceptions.spec.js
visible_client_chat.spec.js
visible_client_documents.spec.js
visible_warehouse_categories.spec.js
visible_configuration_guidance.spec.js
```

También se validaron:

- Sintaxis PHP.
- JSON de inglés, español, francés y portugués.
- Rutas públicas del QR.
- Scope por owner.
- Enlace global de Google Maps.
- Ausencia de errores de whitespace mediante `git diff --check`.

## Migraciones requeridas en otros ambientes

Las siguientes migraciones fueron aplicadas en el ambiente local y deben revisarse/aplicarse al desplegar en otro ambiente:

```text
db/storage_container_categories_required.sql
db/store_return_stock_idempotency_required.sql
```

## Conclusión para el revisor

Las observaciones y preguntas incluidas en los cinco informes fueron atendidas. Los errores 404, pantallas blancas, problemas de asignación, ausencia del proceso QR, falta de categorías de contenedores y ambigüedad del portal documental cuentan ahora con corrección implementada y validación.

Stripe, Square, PayPal, SMTP, Cloudinary y otros servicios externos continúan siendo configuraciones opcionales del negocio o del ambiente. La ausencia de esas credenciales no bloquea la operación manual, el tracking interno, las evidencias locales, el chat privado ni la gestión de pedidos.
# Aclaración adicional: cobros manuales y anticipos (Level 2)

Se revisaron conjuntamente los flujos de **Servicios** y **Store & Logistics**. En ambos casos el cliente conserva su enlace público para consultar y pagar el pedido; ese enlace no sustituye el control administrativo del propietario Level 2.

El Level 2 puede registrar dinero recibido fuera de Stripe, Square o PayPal (efectivo, transferencia, Zelle u otro medio), indicar una referencia y adjuntar un comprobante JPG, PNG, WEBP o PDF de hasta 5 MB. El importe puede ser un anticipo o el saldo completo.

- **Servicios:** el registro se incorpora al historial de anticipos de la orden, guarda importe, método, referencia, comprobante, usuario que lo registró y saldo anterior/posterior. Se impide cargar más que el saldo pendiente y se reforzó la verificación para que el Level 2 solo modifique órdenes de su propio workspace.
- **Store & Logistics:** cada cobro manual genera un movimiento independiente. La pantalla muestra acumulado pagado y saldo pendiente. Un abono parcial mantiene el pedido como `PENDING`; solamente al cubrir el total cambia a `PAID`. Por ello no se pierde el historial ni se simula incorrectamente que el primer abono liquidó toda la compra.
- **Sin proveedor de pago configurado:** el negocio puede operar con estos cobros manuales. El cliente recibe la indicación de que no hay pago electrónico activo y Level 2 puede conciliar el pedido con el medio externo realmente utilizado.

La base de datos fue ampliada mediante `db/manual_payment_evidence_and_partial_store_required.sql`: los anticipos de Servicios admiten evidencia y trazabilidad, y Store reconoce explícitamente el tipo de pago `PARTIAL`.

## Prueba visible integral del 17 de julio de 2026

La validación se ejecutó con Chromium visible y una cuenta QA Level 2, primero en inglés y luego en español y portugués.

- Se creó el producto `QA Manual Product 1784296103494` y la orden Store `#1010` por USD 100.
- Level 2 cargó un abono manual de USD 30, referencia y comprobante. La orden permaneció `PENDING` y el saldo quedó en USD 70, como corresponde.
- Se activó Servicios solamente para la cuenta QA, sin registrar un pago de suscripción ficticio.
- Se crearon un servicio por USD 250, un contrato y la orden/estimado de Servicios `#922` para el cliente QA.
- El cliente abrió el enlace público y firmó electrónicamente el contrato.
- Level 2 registró un anticipo manual de USD 50 con referencia y comprobante. La base confirma saldo anterior USD 250 y saldo posterior USD 200.
- No se produjeron errores JavaScript en los recorridos exitosos. Store, Servicios y sus formularios fueron navegados en `en`, `es` y `pt`.
- Se reemplazó el icono de Servicios que se corrompía en la vista inglesa por el icono vectorial de Feather. Español y portugués no presentaron mojibake en la comprobación final.

Las capturas y reportes reproducibles están en `storage/qa-visible/` con los prefijos `manual-payments-trilingual`, `service-contract-visible`, `service-order-visible` y `sign-advance-service`.

## Prueba visible de empaquetado y ruta de cinco entregas

Se ejecutó una ruta completa con cinco órdenes Store pagadas y cinco clientes/direcciones diferentes. Las órdenes finales de control fueron `#1016` a `#1020`.

- Level 2 visualizó simultáneamente las cinco órdenes en estado pagado y asignadas al usuario Level 4.
- El empaquetador abrió y validó individualmente los cinco tags con QR, y completó la preparación en orden del cliente 1 al 5.
- El delivery recibió las cinco paradas en Driver Mode y procesó ordenadamente cada despacho y entrega.
- Cada salida exigió y guardó foto de despacho. Cada cierre exigió y guardó una foto de entrega independiente, notas, coordenadas y el usuario que cerró la entrega.
- Las cinco órdenes terminaron `DELIVERED`, permanecieron `PAID` y registraron tres eventos de ubicación cada una.
- Durante la primera corrida se detectó que `sent_at` no se almacenaba y que la evidencia de despacho se sobrescribía con la foto final. Se corrigió `StoreOrderWorkflowRepository::markDeliveryDispatch()` y se agregó `dispatch_photo_url` mediante `db/store_dispatch_evidence_required.sql`.
- La ruta completa se repitió con cinco órdenes nuevas después de la corrección. Las cinco conservaron por separado `sent_at`, foto de despacho, `delivered_at`, foto final, notas y GPS.

El reporte automatizado está en `storage/qa-visible/five-deliveries-report.json` y las 19 capturas visibles asociadas usan el prefijo `five-deliveries-`.

## Prueba visible de Tickets + RSVP

Se probó el circuito completo con el evento `QA Ticket Event 1784297620258`, creado por Level 2 en el venue QA `#20`.

- Level 2 creó el evento en pantalla, configuró General Admission a USD 10, una etapa de venta vigente, inventario de 25 entradas y publicó la venta.
- El cliente Level 5 abrió el perfil público del venue, seleccionó dos entradas y pagó USD 20 con Stripe sandbox.
- La compra `ticket_sales #1` generó dos códigos independientes: `TKT-6A5A3B7C54650-001` y `TKT-6A5A3B7C547B8-002`.
- El portal del cliente mostró la compra, ambos códigos y dos acciones QR independientes. El modal QR se validó visualmente.
- Level 2 registró la asistencia del primer código, rechazó correctamente su segundo intento y luego registró el segundo código. La tabla `ticket_checkins` contiene exactamente dos asistencias, ambas realizadas por el usuario `1450`.

Correcciones realizadas durante la prueba:

- La API utilizaba erróneamente el ID del evento para buscar su etapa de venta; ahora usa `venue_events_tickets.id`.
- Las peticiones públicas dependían de `APP_URL` y fallaban cuando el host visible era distinto; ahora usan rutas internas del sistema.
- Stripe.js estaba dentro de un condicional de categorías opcionales y no cargaba en venues sin categorías; se movió fuera de ese bloque y se agregó reintento seguro de inicialización.
- El checkout usaba Stripe Connect legado. Ahora emplea el proveedor de pago activo y las credenciales propias del Level 2, igual que los demás módulos.
- La validación anterior marcaba toda la venta mediante `updated_at`; una compra de varias entradas no podía controlar cada acceso correctamente. Se agregó `ticket_checkins`, con unicidad por código.
- Se reforzó la propiedad del workspace al administrar eventos y abrir QR administrativos.
- El portal Level 5 ahora calcula entradas activas/usadas por código, no por la fecha de modificación de toda la venta.
- La pantalla administrativa dejó de mostrar caracteres corruptos en los indicadores e iconos revisados.

Las capturas y reportes reproducibles están en `storage/qa-visible/` con los prefijos `ticket-event-admin`, `ticket-purchase`, `ticket-qr-checkin` y `client-ticket-qr-visible`.
