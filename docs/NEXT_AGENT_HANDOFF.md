# Next Agent Handoff

## Growth Hub Current State

- Main panel doc: `docs/ophyra-growth-hub.md`.
- Growth Hub content/SEO targets are `vnvevents`, `avomeal` and `jonnysmedia`.
- Do not use `ophyra.com` as a required Growth Hub Search Console property.
- Correct Search Console properties:
  - `vnvevents:sc-domain:vnvevents.com`
  - `avomeal:sc-domain:avomeal.com`
  - `jonnysmedia:sc-domain:jonnys.media`
- Latest verified Search Console import on 2026-06-06:
  - VNV Events connected, 609 rows.
  - Avomeal connected, 0 rows returned.
  - Jonnys Media connected, 1 row.
- DataForSEO SERP provider is implemented with Basic Auth and the correct Google Organic Standard Queue endpoint, but current credentials return HTTP 401. Replace `DATAFORSEO_PASSWORD` with the API Access password before expecting snapshots.
- VNV Events default SERP market is `Miami, Florida, United States`; Doral is selectable but not the global default.
- Growth Hub templates/categories now come from DB: `cms_templates`, `cms_categories`, `cms_contents.id_template`, `cms_contents.id_cms_category`.
- Preview uses `cms_templates.css_text`; metadata keeps readable keys only as a snapshot.
- Three-step Growth Hub flow was closed and QA-verified on 2026-06-06:
  - Step 1 saved a selected keyword with market and priority.
  - Step 2 saved an opportunity tied to keyword/market.
  - Step 3 generated a preview-ready VNV Events draft with real DB template/category IDs, route, blocks, agent run, preview CSS and quality score.
  - Edit flow preserved `id_template` and `id_cms_category`.
  - Multi-site slug behavior was verified with the same title/slug across VNV Events and Avomeal.
  - QA residue check returned zero temporary contents, keywords and opportunities.
- Current seeded CMS records:
  - `vnvevents`: 5 categories, 4 templates.
  - `avomeal`: 3 categories, 2 templates.
  - `jonnysmedia`: 2 categories, 1 template.
- Do not move template CSS or category/template ownership back into metadata-only fields. Metadata is only a readable snapshot.
- Template CSS is editable from Growth Hub Step 3 through `cms_templates.css_text`.
- Template CSS seeds were rebuilt after reviewing local sibling repos:
  - `../vnv-events` service pages/blog/Growth Hub renderer.
  - `../vnv-gourmet` CMS article test template and store direction.
  - `../jonnys-media` public CMS renderer and audiovisual direction.
- Growth Hub Step 3 now creates AI-assisted public page HTML, stores template/category IDs, applies template CSS in preview, supports edit/delete/publish, and publishes immediately after approval.
- Generated page payloads now include final `body` HTML, template CSS, media, schema, metadata, route/canonical, citation/internal-link plans and quality checks.
- Generated images are placed as alternating 50/50 media/text rows: right, left, right, etc. Public repos must support `cms-media-text-row`, `cms-media-right`, `cms-media-left`, `cms-image-generated` and `cms-image-copy`.
- Public web citations are discovered when possible by finding readable source pages and extracting short title/excerpt signals. Community sources such as Quora, Reddit or forums are tagged separately. If blocked, Growth Hub skips the citation section and does not publish placeholder review links.
- Internal links are generated from published Growth Hub routes and, when needed, from the configured production public site.
- Anti-cannibalization now checks existing content intent before creation, and `cms_routes` blocks route reassignment to another content item.
- Public brand repos returning 404 need a catch-all Growth Hub resolver for `/{slug}`, `/locations/{slug}` and `/blog/{slug}`. See `docs/GROWTH_HUB_PUBLIC_SITE_CONSUMPTION.md`.

## What Was Reviewed

Local Ophyra docs were read first, then sibling repository documentation and code/config were reviewed from:

```text
morer62/vnv-mobile-app
morer62/vnv-gourmet-app
morer62/vnv-events
morer62/VNV_Gourmet
```

`vnv-events`, `vnv-gourmet-app` and `VNV_Gourmet` were also cloned temporarily for file discovery.

## Main Findings

- Ophyra should remain the central documentation and technical reference, not the public identity of every brand.
- VNV Events is a real event operation with CMS, forums, SEO Center, music sessions, event requests, Level 1/4/5/6 dashboards and mobile WebView consumers.
- Avomeal is the active food/store brand. `VNV Gourmet` is legacy naming and still appears in some web repo templates.
- VNV Events app sends `BUSINESS_ID=2` and uses `Panel/Tokenapi`.
- Avomeal app sends `id_user_business=2`, `business_id=2`, `site_key=avomeal` and uses `Panel/Tokenapi`.
- Avomeal app has no full native Store/cart/checkout implementation; it depends on backend WebView routes.
- `VNV_Gourmet` web code appears mostly owner-scoped; full `site_key/site_visibility` backend enforcement is a gap to verify.
- SMTP and payment provider settings are owner-scoped in current web code; site-level fallback should be documented before cross-brand production use.

## Docs Added In This Pass

```text
docs/API_ENDPOINTS_CONTRACT.md
docs/WEBVIEW_TOKEN_CONTRACT.md
docs/MOBILE_APPS_CONTRACT.md
docs/BRAND_SITE_SCOPE_MODEL.md
docs/REPOSITORY_ECOSYSTEM_MAP.md
docs/CMS_CONTENT_IDENTITY_MODEL.md
docs/PAYMENT_AND_SMTP_SCOPE.md
docs/NOTIFICATIONS_CONTRACT.md
docs/VNV_EVENTS_OPHYRA_INTEGRATION_MODEL.md
docs/NEXT_AGENT_HANDOFF.md
docs/I18N_TRANSLATION_SYSTEM.md
```

## I18N Sprint Started

- Existing i18n uses `src/Services/TranslationService.php`.
- Language JSON files are in `src/Languages/en.json`, `es.json`, `fr.json`, `pr.json`.
- Twig helpers are `trans()`, `t()` and `|trans`; PHP uses `TranslationService::trans()`.
- Locale priority is authenticated `ui_language`, session, `app_locale` cookie, browser language, then `en`.
- `pr` is the current app locale code for Portuguese; browser `pt` maps to `pr`.
- Initial audit found 738 Twig templates, 588 already using i18n helpers and 150 without direct helper usage.
- Global layouts started moving visible text to `common.*` keys:
  - `src/views/templates/base.twig`
  - `src/views/templates/base.admin.twig`
  - `src/views/templates/layout/headerLayout.html.twig`
  - `src/views/templates/layout/footerLayout.html.twig`
- Public auth views started moving visible text to `auth.*` keys:
  - `src/views/public/login/index.twig`
  - `src/views/public/signup/index.twig`
  - `src/views/public/signup/choose.twig`
  - `src/views/public/forgot_password/index.twig`
  - `src/views/public/reset-password/index.twig`
- `renderInTemplates()` now receives the same i18n helpers and locale globals as `render()`.
- `src/views/templates/components/i18n-dom-translator.twig` is included in both base layouts to translate common hardcoded UI strings and common attributes across all pages.
- Coverage after the i18n pass: public, templates and panel levels 1 through 5 all have direct i18n helper calls in every Twig file.
- Do not add visible hardcoded UI text. Use the i18n helper and update all language JSON files.

## Critical Compatibility Rules

- Do not rename mobile auth endpoints.
- Do not remove `token`, `api_token` or existing `user` fields.
- Do not remove `Panel/Tokenapi/{token}/{route}`.
- Do not modify cron/autopay/backup behavior without reviewing `docs/CRON_JOBS.md`.
- Do not turn mobile signup into Ophyra business signup.
- Do not expose owner/admin routes to normal Level 5 mobile users.
- Do not show all owner `2` records on Avomeal without `site_key=avomeal` visibility review.
- Do not mix Ophyra SaaS revenue, VNV operations revenue and Avomeal Store revenue.

## Known Gaps

- Confirm database support for `brand_site_settings`, `site_key` and `site_visibility`.
- Confirm Avomeal backend Store filters honor `site_key=avomeal`.
- Confirm SMTP can resolve by site/brand, not only owner.
- Confirm payment provider can resolve by site/brand if VNV Events and Avomeal share owner `2`.
- Replace user-facing legacy `VNV Gourmet` copy with Avomeal where appropriate.
- Run end-to-end QA for Avomeal Store/cart/checkout/subscriptions from the mobile app.
- Implement/verify brand repo catch-all renderers for VNV Events, Avomeal and Jonnys Media so published Growth Hub pages render instead of returning 404.
- Fix DataForSEO API Access credentials; current provider implementation exists but snapshots are blocked by invalid/unauthorized credentials.
- Add scheduled workers for Search Console refresh, SERP refresh and future Google Trends enrichment.
- Add richer automated competitor scanner execution for manually added competitor URLs.

# Handoff - Contract Signature Security + Team Member Contracts

## Estado

- Motor existente auditado:
  - `ContractPdfGenerator`
  - `OrderAcceptancePdfGenerator`
  - `document_logs`
  - `orders_contracts`
  - `orders_acceptance_contracts`
  - rutas publicas `/order-access` y espejos `/commerce/order-access`
- Hardening compatible aplicado:
  - POST valida token/HMAC/expiracion.
  - Comparacion HMAC usa `hash_equals`.
  - Doble firma queda bloqueada por registros existentes.
  - Consentimiento de firma electronica requerido en servidor.
  - Commerce mirror corregido para usar `file_path` y `hash` del generador PDF.
- Time clock:
  - `TeamMemberContractService` revisa `team_member_contracts` si la tabla existe.
  - Si la tabla no existe, mantiene compatibilidad y permite clock-in.
  - Una vez aplicado el SQL, clock-in se bloquea salvo estado `SIGNED`, `VALIDATED` o `MANUALLY_UPLOADED`.
- Docs nuevas:
  - `docs/CONTRACT_SIGNATURE_ENGINE.md`
  - `docs/CONTRACT_SECURITY_MODEL.md`
- SQL propuesto:
  - `db/team_member_contracts_required.sql`

## Flujo operativo ya implementado

1. SQL `team_member_contracts` aplicado por el usuario.
2. Admin Level 1/2 gestiona contrato en:
   - `panel/planner-hub/management/users/contracts?id=TEAM_MEMBER_ID`
3. Listado de users/team members muestra status de contrato y boton `Contracts`.
4. Level 4 firma o ve su contrato en:
   - `panel/planner-hub/team/contracts`
5. Dashboard Level 4 muestra alerta de contrato pendiente/validado/no asignado.
6. Sidebar Level 4 tiene acceso `My Contract`.
7. Clock-in queda bloqueado salvo `SIGNED`, `VALIDATED` o `MANUALLY_UPLOADED`.

## QA pendiente recomendado

1. Asignar template a un team member real.
2. Entrar como Level 4 y firmar.
3. Confirmar PDF/hash/status.
4. Confirmar clock-in bloqueado antes y permitido despues.
5. Subir PDF manual desde admin y confirmar desbloqueo.
# Handoff - Currency, Pricing & Payment Availability

## Estado

Sprint implementado en capa compatible:

- Servicios nuevos:
  - `src/Services/CurrencyPricingService.php`
  - `src/Services/PaymentAvailabilityService.php`
- Checkout de tienda actualizado:
  - Selector de moneda visible.
  - Pais de facturacion para reglas.
  - Botones filtrados por proveedor disponible.
  - Transferencia bancaria siempre disponible como fallback manual.
  - Registro ampliado de montos/monedas si la migracion ya fue aplicada.
- SQL requerido:
  - `db/currency_payment_availability_required.sql`
- Docs nuevos:
  - `docs/CURRENCY_AND_PAYMENT_AVAILABILITY.md`
  - `docs/PAYMENT_PROVIDER_RULES.md`
  - `docs/EXCHANGE_RATE_MODEL.md`
  - `docs/BANK_TRANSFER_WORKFLOW.md`

## Antes de QA funcional

1. Aplicar `db/currency_payment_availability_required.sql`.
2. Insertar tasas reales en `exchange_rates` para monedas no USD.
3. Probar checkout digital con proveedor activo.
4. Probar checkout con transferencia bancaria y confirmar que la orden queda pendiente.
5. Crear/ajustar pantalla admin para aprobar pagos bancarios antes de operar en produccion.

## Notas

- El repositorio de pagos de tienda usa `addCompatible()`, por lo que el checkout no se rompe si la migracion aun no existe.
- Si no hay tasa cacheada para una moneda, el servicio vuelve a USD.
- Square esta restringido por pares pais/moneda en el servicio.
