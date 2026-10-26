# Recolección de etiquetas externas — 26 de octubre de 2026

## Corrección

La recolección detallada conservaba identificadores Shopee `BR…` pero enviaba esos valores a `claimByQr`, que solo buscaba el token público interno del pedido. El QR JSON de Mercado Livre tampoco producía un token: caía en la búsqueda de tareas DELIVERY y mostraba una falsa necesidad de asignación administrativa.

- La lectura de recolección se resuelve antes de buscar tareas de entrega del vendedor.
- Se reconocen tokens internos, URL interna de acceso, identificadores Shopee y etiquetas JSON de Mercado Livre con id y metadatos del remitente/seguridad. No se sigue ninguna URL escaneada.
- Se buscan primero los paquetes existentes dentro de la empresa/custodia/relación activa permitida. No se eliminan las restricciones entre empresas ni la pertenencia del entregador a su transportadora.
- Una etiqueta externa nueva crea una recepción logística de la propia transportadora, en PICKED_UP, asignada al entregador y con evento de recolección. No inventa destinatario, dirección, pago ni venta importada. Se conservan referencia, plataforma, persona que entrega y firma. No se genera un cobro automático por este registro mínimo.
- El historial y el contador muestran una recepción por paquete, aunque posteriormente tenga asignaciones PICKUP y DELIVERY.
- Los reintentos se serializan por empresa/referencia mediante GET_LOCK. Confirmar otra vez no duplica la recepción ni la convierte en recepción de almacén. Una etapa posterior no se altera desde la confirmación de recolección.
- La misma etiqueta externa puede resolverse para las siguientes etapas de los paquetes ya registrados. La generación de una ruta se detiene si una recepción mínima sigue sin destinatario/dirección/CEP reales.
- La confirmación bloquea doble clic, muestra errores individuales con la referencia, conserva los fallidos para reintentar y no recarga una confirmación parcial. Los mensajes se insertan como texto, no HTML.

No requiere migración SQL, nuevas credenciales ni instalación de dependencias.

## Pruebas locales

- `php tests/carrier_collection_labels_integration_test.php`: 43 comprobaciones sobre MariaDB local. Usa usuarios de prueba nuevos y elimina únicamente esos fixtures en finally. Incluye los cuatro identificadores del reporte, Mercado Livre, recepción de almacén, idempotencia, historial, evidencia de entrega de custodia, rechazo de otra empresa y prohibición de ruta sin datos reales.
- Playwright: `driver_qr_token_routing.spec.js` y `collection_confirmation_feedback.spec.js`: 12 pruebas pasan. Ejecutan las funciones reales del Twig en un entorno controlado; no acreditan una cámara física.
- Contratos de logística, importación marketplace, moneda BRL e idiomas: pasan.
- PHP lint de los cuatro archivos PHP de producción modificados: pasa. `git diff --check`: sin errores.
- Se ejecutó además `ophytrack_driver_requested_changes_contract.spec.js`: falla una expectativa antigua de `capture="user"` en Settings. Se comprobó que ese atributo ya falta en HEAD 8431d44; Settings no fue modificado en esta corrección. No se presenta esa suite como aprobada.

## Despliegue

Repositorio de producción comprobado por terminal de cPanel: `/home/ophyra/public_html/ophytrack.com`, rama main, remoto SSH `morer62/ophytrak.git`. Antes del cambio estaba limpio en 8431d44.

Actualizar únicamente por fast-forward:

```sh
git -C /home/ophyra/public_html/ophytrack.com pull --ff-only origin main
```

La prueba de recolección se realiza localmente para no registrar ni mover paquetes reales durante QA. La instalación en producción debe comprobarse con el hash Git y lint; el escaneo físico de la etiqueta original requiere el teléfono del entregador.
