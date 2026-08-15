# AGENTS.md - Entrada principal del ecosistema

Este archivo es la puerta de entrada para cualquier agente, desarrollador o IA que trabaje en este repositorio.

La documentacion completa vive dentro de `/docs`. Antes de tocar codigo, rutas, endpoints, vistas, base de datos, mobile apps o branding, lee este archivo y luego abre los documentos relevantes dentro de `/docs`.

## Idea central

Ophyra es el proyecto padre/original y la referencia estructural del ecosistema, pero no todos los proyectos derivados deben tratarse como si fueran el mismo producto.

Estos proyectos comparten historia, patrones tecnicos, codigo base o parte de la arquitectura, pero representan marcas y plataformas distintas:

| Proyecto | Repo / dominio | Rol en el ecosistema |
| --- | --- | --- |
| Ophyra | Este repo | Plataforma modular de operaciones y referencia central. |
| VNV Events | `morer62/vnv-events`, `vnvevents.com` | Empresa de eventos, servicios, clientes, ordenes, equipo y ejecucion operativa. |
| Avomeal / VNV Gourmet | `morer62/VNV_Gourmet`, `avomeal.com` | Marca de comida, delivery, meals, kits gastronomicos, productos, ordenes y clientes. |
| Jonnys Media | `jonnys.media` | Marca personal/profesional del desarrollador y lider tecnico del ecosistema. |
| Avomeal mobile app | `morer62/vnv-gourmet-app` | App Expo/React Native conectada a Avomeal/VNV Gourmet. |
| VNV Events mobile app | `morer62/vnv-mobile-app` | App Expo/React Native conectada a VNV Events. |

No trates todos estos repositorios como un solo producto. Ophyra puede funcionar como referencia, central administrativa o base compartida, pero VNV Events, Avomeal y Jonnys Media mantienen identidad, branding, dominio, navegacion, experiencia visual y proposito de negocio propios.

## Orden de lectura recomendado

1. `docs/README.md`
2. `docs/REPOSITORY_ECOSYSTEM_MAP.md`
3. `docs/ECOSYSTEM_OVERVIEW.md`
4. `docs/OPHYRA_BUSINESS_MODEL.md`
5. `docs/OPHYRA_2026_BUSINESS_STRATEGY_UPDATE.md`
6. `docs/LEVEL_2_BUSINESS_ACCOUNT_MODEL.md`
7. `docs/BRAND_SITE_SCOPE_MODEL.md`
7. Documento especifico del proyecto que vas a tocar:
   - `docs/VNV_EVENTS_PROJECT_CONTEXT.md`
   - `docs/VNV_EVENTS_OPHYRA_INTEGRATION_MODEL.md`
   - `docs/AVOMEAL_PROJECT_CONTEXT.md`
   - `docs/JONNYS_MEDIA_PROJECT_CONTEXT.md`
   - `docs/MOBILE_APPS_ECOSYSTEM.md`
7. Documentos de flujo segun el area:
   - `docs/API_ENDPOINTS_CONTRACT.md`
   - `docs/WEBVIEW_TOKEN_CONTRACT.md`
   - `docs/MOBILE_APPS_CONTRACT.md`
   - `docs/CMS_CONTENT_IDENTITY_MODEL.md`
   - `docs/PAYMENT_AND_SMTP_SCOPE.md`
   - `docs/NOTIFICATIONS_CONTRACT.md`
   - `docs/USER_COMPANY_ACCESS_MODEL.md`
   - `docs/STORE_COMMERCE_FLOW.md`
   - `docs/TEAM_CHAT_DELIVERY_OPERATIONS.md`
   - `docs/MOBILE_API_NOTIFICATIONS_FLOW.md`
   - `docs/ADMIN_USER_MODULE_REPORTS_CONTROL.md`
   - `docs/OPHYRA_CMS_ROUTE_CONTRACT_REVIEW_2026_06_09.md`
   - `docs/GROWTH_HUB_CMS_REPLICATION_GUIDE.md`
   - `docs/OPHYRA_COMMERCIAL_LAUNCH_READINESS.md`
   - `docs/OPHYRA_IMMEDIATE_LAUNCH_TASKS.md`
   - `docs/OPHYRA_BILLING_AUTOMATION.md`
   - `docs/OPHYRA_SUPPORT_TEAM_PLAYBOOK.md`
   - `docs/ORDER_ACCESS_PAYMENT_FLOWS.md`
   - `docs/CONTRACT_SIGNATURE_ENGINE.md`
   - `docs/CONTRACT_SECURITY_MODEL.md`
   - `docs/TEAM_MEMBER_CONTRACT_FLOW.md`

## Modelo de usuarios por proyecto

En Ophyra, el modelo actual incluye:

* Level 1: super admin / Ophyra Global Admin / operaciones centrales.
* Level 2: Business Owner / Account Owner. Todos los negocios activos viven aqui.
* Level 3: anulado para el modelo activo de negocio; solo legacy/backward compatibility.
* Level 4: team member / empleado / colaborador.
* Level 5: cliente final.
* Level 6: marketing / CMS / funciones especificas.

Level 4 y Level 5 son relaciones multiempresa:

* Level 4 puede trabajar para varias empresas mediante `user_institutions`; las vistas operativas deben resolver y mostrar la empresa seleccionada antes de listar ordenes, chat, payroll, calendario o archivos.
* Level 5 puede comprar o interactuar con varias empresas mediante `clients_users`; las ordenes, store orders, chat y portales publicos deben mostrar la empresa vendedora o el contexto de empresa seleccionado.

Regla critica: no revivas la separacion antigua `Level 2 = Venue` y `Level 3 = Vendor`. En Ophyra actual todo negocio es Level 2; lo que el negocio hace se guarda en Business Profile, modulos y configuracion operativa. Lee `docs/LEVEL_2_BUSINESS_ACCOUNT_MODEL.md` antes de tocar signup, dashboards, sidebars, modulos o usuarios de negocio.

En proyectos derivados como VNV Events, Avomeal / VNV Gourmet y otras marcas independientes, la estructura fue simplificada:

* Level 1: dueno/admin principal de la marca.
* Level 4: team member / empleado / colaborador.
* Level 5: cliente.
* Level 6: usuario relacionado con marketing o funciones especificas.

En esos proyectos derivados, no asumas que Level 2 y Level 3 siguen siendo parte activa del flujo principal. Pueden existir por herencia historica, pero normalmente se ignoran o quedan como legacy.

## Reglas de identidad

* Ophyra no es VNV Events.
* Ophyra no es Avomeal.
* VNV Events no es Avomeal.
* Jonnys Media no es un cliente generico del sistema.
* Las apps moviles no contienen la logica central del negocio; normalmente conectan login, signup, dashboard, WebView, token de sesion y notificaciones con el backend/web.
* El signup web pertenece al flujo comercial de Ophyra y sirve para registrar nuevos negocios/clientes comerciales. El signup movil no pertenece al flujo comercial de Ophyra; existe unicamente para registrar o asociar clientes finales de una marca especifica, usando `id_user_business` o el identificador equivalente. Ophyra debe mantenerse como plataforma web para registro, billing, membresias, modulos y afiliados.
* Billing Automation debe tratarse como un sistema integrado: Stripe webhook, renovacion de Ophyra Base, renovacion de add-ons, fallos de pago/dunning, `payments_all`, comisiones afiliadas, logs e intervencion manual de Level 1. No implementar esas piezas como parches separados.
* `id_owner` / `id_user_business` define ownership. `site_key` / `brand_key` define identidad publica de sitio/marca cuando aplique. Avomeal usa `id_user_business=2` y `site_key=avomeal`; no todo lo que pertenece al owner `2` debe mostrarse en Avomeal.

Cuando trabajes en una marca, respeta su dominio, lenguaje, visuales, rutas, publico y flujo operativo.

## Contratos que no se deben romper

Antes de cambiar endpoints, mobile auth, WebView, notifications, Store, CMS, payment o SMTP, revisa:

```text
docs/API_ENDPOINTS_CONTRACT.md
docs/WEBVIEW_TOKEN_CONTRACT.md
docs/MOBILE_APPS_CONTRACT.md
docs/BRAND_SITE_SCOPE_MODEL.md
docs/REPOSITORY_ECOSYSTEM_MAP.md
```

Cambios en endpoints, JSON o rutas WebView pueden romper:

```text
morer62/vnv-mobile-app
morer62/vnv-gourmet-app
morer62/vnv-events
morer62/VNV_Gourmet
```

## Estrategia comercial vigente 2026

Antes de tocar home publico, billing, modulos, sidebars, onboarding, Level 2, Level 4, Level 5 o metadata SEO, lee:

```text
docs/OPHYRA_2026_BUSINESS_STRATEGY_UPDATE.md
```

Regla vigente:

* Service Operations es el workspace comercial activo principal.
* Store + Logistics queda en `Coming soon` para Ophyra Level 2, Level 4 y Level 5.
* Marketplace Connectors sigue siendo un add-on separado y activo.
* No borrar tablas/rutas Store solo por esta decision comercial; pueden seguir existiendo para compatibilidad, Level 1, Avomeal/VNV Gourmet o roadmap.
* Eventos siguen siendo una vertical, no la identidad global de Ophyra.

## Reglas tecnicas criticas

Antes de modificar, confirma:

* que modulo o marca estas tocando,
* que usuario/nivel lo usa,
* que tabla o endpoint afecta,
* si hay impacto en mobile/WebView,
* si hay impacto en pagos,
* si hay impacto en SMTP/email branding,
* si hay impacto en CMS, SEO, sitemap, robots o llms,
* si hay impacto en clientes, ordenes o chat,
* si el cambio afecta una marca distinta,
* si la respuesta JSON ya es consumida por una app movil.

No agregues texto visible hardcodeado en la UI. Usa el helper i18n existente (`trans()` / `t()` en Twig o `TranslationService::trans()` en PHP) y actualiza los JSON de idioma en `src/Languages/*.json` en el mismo cambio.

No modificar comportamiento de cron, autopay o backups sin revisar primero `docs/CRON_JOBS.md`.

No modificar contratos, firma electronica, PDFs firmados, tokens publicos de orden o bloqueo de time clock sin revisar primero:

```text
docs/CONTRACT_SIGNATURE_ENGINE.md
docs/CONTRACT_SECURITY_MODEL.md
docs/TEAM_MEMBER_CONTRACT_FLOW.md
```

El flujo de contratos de team members debe reutilizar el motor existente de contratos/firma. No crear un sistema paralelo. Las tablas requeridas viven en `db/team_member_contracts_required.sql` y `db/team_member_contract_templates_required.sql`, y deben aplicarse manualmente despues de revision. Los contratos laborales/de equipo no deben crearse en `orders_contracts`.

No modificar sin justificacion fuerte:

* Kernel / Router,
* `BaseRepository`,
* `UserRepository`,
* login/auth,
* endpoints API usados por mobile,
* pagos,
* Affiliate/referral logic,
* rutas publicas existentes,
* estructura JSON usada por apps moviles.

Si necesitas cambiar una respuesta API usada por mobile, agrega campos nuevos en lugar de eliminar o renombrar campos existentes.

## Principio de trabajo

Cambios pequenos, controlados, reversibles y faciles de probar.

Si tienes duda, lee primero el documento de contexto del proyecto dentro de `/docs` y revisa un modulo similar antes de editar.
# Agent Notes - Currency And Payment Availability

- Los precios comerciales deben tratarse como USD base salvo que una pantalla indique explicitamente otro contrato.
- Para checkout y botones de pago, usar `PaymentAvailabilityService` antes de mostrar proveedores.
- Para conversiones, usar `CurrencyPricingService`; no hardcodear tasas en vistas.
- Si falta tasa cacheada, mantener USD como fallback.
- Transferencia bancaria es fallback global y debe quedar en revision manual, no como pago aprobado.
- Los registros nuevos de pago deben conservar base/display/payment amount y currency.
- La migracion relevante es `db/currency_payment_availability_required.sql`.
