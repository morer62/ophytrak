# Growth Hub SEO audit

Fecha: 2026-06-05

## Objetivo

Revisar el ecosistema Growth Hub, SEO publico, APIs de contenido, metadata, schema markup y calidad del contenido generado para VNV Events, Avomeal y Jonnys Media.

## Incongruencias detectadas y corregidas

### 1. Contenido publico sin contrato SEO completo

Antes, `/api/growth-hub/content` devolvia contenido publicado, pero no exponia un contrato completo para que cada sitio renderizara SEO correctamente.

Corregido:

- `seo.title`
- `seo.description`
- `seo.canonical`
- `seo.robots`
- `seo.og_image`, cuando hay media
- `schema_json`
- `metadata`
- `quality`

Archivo:

- `src/Services/GrowthHubService.php`

### 2. Schema markup incompleto para paginas Growth Hub

Los drafts podian tener `schema_json`, pero si venian vacios el sitio publico no tenia una estructura confiable.

Corregido:

- Schema automatico por tipo:
  - `Article`
  - `Service`
  - `Product`
  - `FAQPage`
- `publisher`
- `keywords`
- `areaServed` cuando hay ubicacion
- `datePublished`
- `dateModified`

### 3. Metadata de calidad insuficiente

No habia una forma simple de saber si una pagina estaba lista o incompleta.

Corregido:

- Se agrego `quality.score`
- Se agrego `quality.checks`
- Se agrego `quality.needs_review`

Checks actuales:

- titulo
- meta description
- longitud de meta description
- keyword principal
- body con suficiente contenido
- schema
- research plan

### 4. Sitemap por marca no disponible

Las rutas existian como JSON, pero cada sitio externo necesitaba una forma directa de construir indexacion.

Corregido:

```text
GET /api/growth-hub/sitemap?site_key=vnvevents
GET /api/growth-hub/sitemap?site_key=avomeal
GET /api/growth-hub/sitemap?site_key=jonnysmedia
```

Archivo:

- `src/views/api/growth-hub/sitemap/index.php`

### 5. Google Maps cargaba globalmente

El layout publico cargaba Google Maps en todas las paginas aunque no hubiera key o mapa.

Corregido:

- Ahora solo carga si existe `GOOGLE_MAPS_API_KEY` o `GOOGLE_KEY`.

Archivo:

- `src/views/templates/base.twig`

### 6. Briefs de contenido muy basicos

El brief inicial podia crear drafts demasiado genericos.

Corregido:

- Se agrego estructura requerida:
  - H1
  - intro
  - contexto de marca
  - evidencia
  - CTA
  - FAQs
  - plan de imagenes
  - mapa cuando aplica
- Se agregaron reglas de calidad:
  - no inventar claims
  - verificar antes de publicar
  - revisar citas de Reddit/foros
  - usar Cloudinary
  - meta description unica
  - contenido util, no solo keyword stuffing

## Contrato publico actual

Un articulo publicado debe consumirse desde:

```text
GET /api/growth-hub/content?site_key=vnvevents&slug=example-slug
GET /api/growth-hub/content?site_key=vnvevents&route=/blog/example-slug
```

Debe traer:

- contenido
- SEO
- schema
- metadata
- quality
- blocks, si se consulta por slug
- media asociada, si se consulta por slug

## Pendientes reales

Estos puntos no deben fingirse como resueltos:

- Worker real de Search Console.
- Worker real de Google Trends.
- Worker real de SERP por keyword/ubicacion.
- Analisis automatico de competidores.
- Generacion real de imagenes y registro automatico en Cloudinary.
- Publicador/aprobador final para pasar de `DRAFT` a `PUBLISHED`.

La estructura ya permite guardarlos y consumirlos; falta ejecutar los workers.

## Regla de calidad editorial

No se debe publicar una pagina si:

- `quality.needs_review = true`
- no tiene keyword principal
- no tiene meta description unica
- el body es solo un brief
- no tiene schema coherente
- las imagenes no tienen `alt_text`
- el mapa no corresponde con una ubicacion real
- las citas externas no han sido revisadas

## Archivos tocados en esta auditoria

- `src/Services/GrowthHubService.php`
- `src/views/api/growth-hub/sitemap/index.php`
- `src/views/templates/base.twig`
- `docs/GROWTH_HUB_PUBLIC_SITE_CONSUMPTION.md`
- `docs/ophyra-growth-hub.md`
