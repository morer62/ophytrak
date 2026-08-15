# Growth Hub Public Site Rendering Instructions

Este documento es la instruccion que debe copiarse a VNV Events, Avomeal y Jonnys Media para que cada sitio renderice paginas, location pages y articulos creados desde Ophyra Growth Hub.

## Objetivo

Ophyra Growth Hub crea y administra data centralizada de CMS/SEO para varias marcas. Cada sitio publico debe leer esa data desde Ophyra y renderizarla con sus propias vistas.

Marcas actuales:

- `vnvevents`
- `avomeal`
- `jonnysmedia`

El scope real de todo el CMS es:

```text
id_owner + site_key
```

No mezclar contenido entre marcas. Un articulo con `site_key = vnvevents` solo pertenece a VNV Events.

## Donde se crea y guarda la data

La data se crea desde:

```text
/panel/growth-hub?site_key=vnvevents
/panel/growth-hub?site_key=avomeal
/panel/growth-hub?site_key=jonnysmedia
```

El inventario de paginas, articulos y locations se revisa desde:

```text
/panel/growth-hub/content?site_key=vnvevents
```

Tablas principales:

- `growth_sites`: configuracion de marca, dominio, `public_base_url`, carpeta Cloudinary y reglas.
- `cms_contents`: paginas, locations, blogs, drafts, published content, SEO, body y schema.
- `cms_routes`: ruta publica y canonical final.
- `cms_content_blocks`: bloques estructurados para render visual.
- `cms_media`: imagenes Cloudinary registradas para el contenido.
- `seo_keywords`, `growth_competitors`, `growth_target_locations`: insumos de SEO para crear oportunidades.

## Public base URL para local y produccion

Cada marca tiene un campo editable en Growth Hub:

```text
growth_sites.public_base_url
```

Ese valor decide donde se mostrara la pagina en canonical, sitemap y tabla de inventario.

Ejemplos validos:

```text
https://vnvevents.com
http://localhost/vnvevents.com
https://avomeal.com
http://localhost/avomeal.com
https://jonnys.media
http://localhost/jonnys.media
```

Regla:

- En produccion usar el dominio real.
- En local usar la carpeta local del proyecto.
- No agregar slash final; Ophyra lo normaliza.
- Al guardar este valor, Ophyra refresca `cms_routes.canonical_url` de las rutas activas de la marca.

## Reglas oficiales de rutas

Cada sitio publico debe respetar estas rutas:

```text
page/location/blog type       public route
------------------------------------------------
content_type = page           {basicurl}/{slug}
content_type = location       {basicurl}/locations/{slug}
content_type = blog           {basicurl}/blog/{slug}
```

Regla práctica:

```text
content_type = landing -> alias legacy -> trata como page
content_type = custom  -> alias legacy -> trata como page
```

Antes de publicar nuevo contenido, valida colisión contra:

```text
- rutas fisicas de producto/tienda
- rutas fisicas de la web principal
- rutas CMS ya publicadas de la misma marca
- prefijos reservados (panel, api, auth, login, product, checkout, category, etc.)
```

Si existe colisión, no sobre-escribas ni reescribas la ruta existente; asigna un slug/route alternativo o deriva a revisión humana.

Ejemplos:

```text
https://vnvevents.com/wedding-packages/
https://vnvevents.com/locations/doral-event-planning/
https://vnvevents.com/blog/how-to-plan-a-quinceanera-in-miami/
```

En local:

```text
http://localhost/vnvevents.com/wedding-packages/
http://localhost/vnvevents.com/locations/doral-event-planning/
http://localhost/vnvevents.com/blog/how-to-plan-a-quinceanera-in-miami/
```

Store, product pages, checkout, order access, tickets u otras paginas manuales no entran en esta regla si ya existen en el sitio. Esta regla es solo para contenido CMS creado por Growth Hub.

## Slugs

Ophyra genera el slug por defecto desde el titulo.

Ejemplo:

```text
Title: Wedding Planner in Doral
Slug: wedding-planner-in-doral
```

El slug se puede editar desde Growth Hub, pero Ophyra siempre lo limpia y lo hace unico por marca.

Si el slug ya existe en la misma marca:

```text
wedding-planner-in-doral
wedding-planner-in-doral-2
wedding-planner-in-doral-3
```

El slug debe ser unico por:

```text
id_owner + site_key + slug
```

No depender del tipo de contenido para diferenciar slugs. Evitar tener un blog y una pagina con el mismo slug.

Ophyra normaliza las rutas publicas con slash final al publicar. Los sitios receptores deben aceptar tambien la variante historica sin slash hasta que todas las filas antiguas esten normalizadas.

## Endpoints publicos que debe consumir cada sitio

Listar contenido publicado:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&type=blog
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&type=location
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&type=page
```

Traer una pieza por slug:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&slug=wedding-planner-in-doral
```

Traer una pieza por ruta publica exacta:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&route=/locations/wedding-planner-in-doral
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&route=/blog/how-to-plan-a-quinceanera-in-miami
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key=vnvevents&route=/wedding-packages
```

Rutas publicas:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/routes?site_key=vnvevents
```

Bloques de una pieza:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/blocks?site_key=vnvevents&id_content=123
```

Imagenes:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/media?site_key=vnvevents
GET {OPHYRA_BASE_URL}/api/growth-hub/media?site_key=vnvevents&id_content=123
```

Sitemap Growth Hub:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/sitemap?site_key=vnvevents
```

## Contrato del payload de contenido

La API devuelve contenido ya listo para render:

```json
{
  "id": 123,
  "site_key": "vnvevents",
  "site": {
    "site_key": "vnvevents",
    "site_name": "VNV Events",
    "domain": "vnvevents.com",
    "public_base_url": "https://vnvevents.com"
  },
  "content_type": "location",
  "title": "Wedding Planner in Doral",
  "slug": "wedding-planner-in-doral",
  "excerpt": "...",
  "body": "...",
  "seo_title": "...",
  "meta_description": "...",
  "primary_keyword": "wedding planner doral",
  "target_location": "Doral",
  "route": "/locations/wedding-planner-in-doral",
  "canonical_url": "https://vnvevents.com/locations/wedding-planner-in-doral",
  "seo": {
    "title": "...",
    "description": "...",
    "canonical": "...",
    "robots": "index, follow",
    "og_image": "https://res.cloudinary.com/..."
  },
  "schema_json": {},
  "metadata": {},
  "blocks": [],
  "media": [],
  "quality": {
    "score": 85,
    "needs_review": false
  }
}
```

La API solo expone contenido cuando:

```text
status = PUBLISHED
approval_status IN (APPROVED, PUBLISHED)
```

Drafts, ideas y contenido pendiente no se deben mostrar.

## Archivo que debe existir en cada sitio publico

Cada sitio debe tener un resolver CMS. Puede ser PHP, Laravel, WordPress custom template, Next.js, Astro, etc. Lo importante es que haga esto:

1. Leer la URL actual.
2. Si el sitio corre en una subcarpeta local, remover el base path antes de resolver CMS.
   - `http://localhost/vnvevents.com/blog/example` tiene base path `/vnvevents.com`.
   - La ruta CMS que se consulta a Ophyra debe ser `/blog/example`.
3. Determinar `content_type` por path:
   - `/locations/{slug}` => `location`
   - `/blog/{slug}` => `blog`
- `/{slug}` => `page` (`landing` y `custom` se pueden recibir como alias legacy)
4. Extraer `{slug}`.
5. Llamar a Ophyra usando la ruta CMS exacta:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/content?site_key={SITE_KEY}&route={current_path}
```

6. Validar que el contenido exista y que su `route` coincida con la ruta CMS.
7. Renderizar:
   - `<title>` desde `seo.title`
   - `<meta name="description">` desde `seo.description`
   - canonical desde `seo.canonical`
   - Open Graph desde `seo`
   - JSON-LD desde `schema_json`
   - imagen principal desde `media[0].secure_url` o `seo.og_image`
   - body y blocks
8. Si no existe, devolver 404 real.

Si VNV Events, Avomeal o Jonnys Media estan devolviendo 404 para una pagina publicada desde Ophyra, revisar en este orden:

1. La pagina esta `PUBLISHED` y `APPROVED` en Ophyra.
2. `growth_sites.public_base_url` coincide con el dominio o carpeta local del sitio receptor.
3. `cms_routes.route` existe y esta `published`.
4. El receptor tiene un catch-all o resolver para `/{slug}`, `/locations/{slug}` y `/blog/{slug}`.
5. El receptor consulta Ophyra por `route=/ruta-exacta`, no solo por slug.
6. En local, el receptor elimina su base path antes de consultar Ophyra. Ejemplo: `/vnvevents.com/blog/test` debe consultar `route=/blog/test`.

El error 404 normalmente vive en el receptor cuando no existe ese catch-all/resolver. Growth Hub solo puede publicar y exponer el contenido; el sitio publico debe capturar la ruta y pedir la pagina.

Pseudo codigo:

```php
$siteKey = 'vnvevents';
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$basePath = trim(parse_url(PUBLIC_BASE_URL, PHP_URL_PATH) ?: '', '/');

if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
    $path = substr($path, strlen($basePath) + 1);
} elseif ($basePath !== '' && $path === $basePath) {
    $path = '';
}

if (str_starts_with($path, 'locations/')) {
    $slug = substr($path, strlen('locations/'));
    $expectedPrefix = '/locations/';
} elseif (str_starts_with($path, 'blog/')) {
    $slug = substr($path, strlen('blog/'));
    $expectedPrefix = '/blog/';
} else {
    $slug = $path;
    $expectedPrefix = '/';
}

$currentRoute = '/' . $path;
$content = fetchJson(OPHYRA_BASE_URL . '/api/growth-hub/content?site_key=' . $siteKey . '&route=' . urlencode($currentRoute));

if (!$content || !empty($content['error'])) {
    http_response_code(404);
    exit('Not found');
}

if (!str_starts_with($content['route'], $expectedPrefix)) {
    http_response_code(404);
    exit('Not found');
}

renderCmsPage($content);
```

## Render minimo requerido

Cada pagina CMS debe imprimir:

```html
<title>{seo.title}</title>
<meta name="description" content="{seo.description}">
<link rel="canonical" href="{seo.canonical}">
<meta property="og:title" content="{seo.title}">
<meta property="og:description" content="{seo.description}">
<meta property="og:image" content="{seo.og_image}">
<script type="application/ld+json">{schema_json}</script>
```

Contenido:

- H1: `title`
- Intro: `excerpt`
- Cuerpo: `body`
- Bloques: `blocks`
- Imagenes: `media`
- CTA segun marca
- FAQ si viene en bloques o metadata
- Mapa solo si es location o si metadata lo pide

## Render completo recomendado

El sitio receptor debe renderizar el contenido como una pagina completa, no como texto plano:

1. Envolver todo el HTML en una clase basada en el template:

```html
<article class="cms-preview-document cms-preview-template-{template.key}">
  {body}
</article>
```

2. Inyectar `template.css_text` en la pagina, idealmente scopeado al wrapper del template:

```html
<style>{template.css_text}</style>
```

3. Renderizar `body` como HTML confiable generado/guardado desde Ophyra. Si el sitio tiene un sanitizador, debe permitir:
   - `section`, `article`, `div`, `figure`, `figcaption`
   - `h1`, `h2`, `h3`, `p`, `ul`, `li`
   - `a[href]`
   - `img[src][alt][loading]`
   - `details`, `summary`

4. Si `body` esta vacio, usar `blocks` como fallback.

5. Si `media` tiene imagenes y el `body` ya las contiene, no duplicarlas automaticamente. Solo usar `media` para thumbnail, `og:image`, galerias fallback o administracion.

## Imagenes Cloudinary

Las imagenes no se deben inventar por URL manual. Se usan desde:

```text
cms_media.secure_url
```

Cada media tiene:

- `secure_url`
- `cloudinary_public_id`
- `source_type`
- `usage_type`
- `alt_text`
- `caption`
- `width`
- `height`

Usar siempre `alt_text` si existe.

Para VNV Events, si `source_type = ai_generated`, no mostrar la imagen como si fuera una foto real de un evento pasado.

### Layout obligatorio para imagenes generadas

Cuando Growth Hub genera imagenes dentro del articulo, el HTML usa:

```html
<section class="cms-block cms-block-gallery cms-block-media-text">
  <div class="cms-media-text-grid">
    <div class="cms-media-text-row cms-media-right">
      <div class="cms-image-copy">...</div>
      <figure class="cms-image-generated">...</figure>
    </div>
    <div class="cms-media-text-row cms-media-left">
      <figure class="cms-image-generated">...</figure>
      <div class="cms-image-copy">...</div>
    </div>
  </div>
</section>
```

Regla visual:

- Cada imagen ocupa aproximadamente 50% del ancho en desktop.
- La otra mitad es texto relacionado.
- Si hay 1 imagen: imagen a la derecha.
- Si hay 2 imagenes: derecha, izquierda.
- Si hay 3 imagenes: derecha, izquierda, derecha.
- En mobile, se apila a una columna.

CSS base recomendado para cada receptor:

```css
.cms-media-text-grid {
  display: grid;
  gap: 1.25rem;
}

.cms-media-text-row {
  align-items: center;
  display: grid;
  gap: 1.25rem;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.cms-media-text-row figure {
  margin: 0;
}

.cms-media-text-row img {
  border-radius: 8px;
  display: block;
  height: auto;
  width: 100%;
}

@media (max-width: 760px) {
  .cms-media-text-row {
    grid-template-columns: 1fr;
  }
}
```

## Sitemap por sitio

Cada sitio debe incluir las rutas Growth Hub publicadas en su sitemap.

Puede consumir:

```text
GET {OPHYRA_BASE_URL}/api/growth-hub/sitemap?site_key=vnvevents
```

O puede consumir `/api/growth-hub/routes` y construir su propio sitemap.

## Que debe crear cada pagina externa

En VNV Events:

```text
SITE_KEY=vnvevents
CMS resolver para:
/{slug}
/locations/{slug}
/blog/{slug}
```

En Avomeal:

```text
SITE_KEY=avomeal
CMS resolver para:
/{slug}
/locations/{slug}
/blog/{slug}
```

En Jonnys Media:

```text
SITE_KEY=jonnysmedia
CMS resolver para:
/{slug}
/locations/{slug}
/blog/{slug}
```

Cada sitio puede tener su diseno propio. El contrato obligatorio es la data y las rutas.

## Variables recomendadas por sitio

En cada sitio consumidor:

```env
OPHYRA_BASE_URL=https://ophyra.com
OPHYRA_GROWTH_SITE_KEY=vnvevents
PUBLIC_BASE_URL=https://vnvevents.com
```

En local:

```env
OPHYRA_BASE_URL=http://localhost/ophyra
OPHYRA_GROWTH_SITE_KEY=vnvevents
PUBLIC_BASE_URL=http://localhost/vnvevents.com
```

## Checklist antes de publicar

- `public_base_url` correcto para local o produccion.
- `slug` correcto y unico por marca.
- `route` correcta:
  - page: `/{slug}`
  - location: `/locations/{slug}`
  - blog: `/blog/{slug}`
- `canonical_url` apunta al dominio/base local correcto.
- `status = PUBLISHED`.
- `approval_status = APPROVED` o `PUBLISHED`.
- Meta description unica.
- Schema JSON-LD valido.
- Imagenes Cloudinary registradas.
- Alt text presente.
- Sitemap incluye la URL.
- El sitio externo devuelve 404 si Ophyra no devuelve contenido.
- El sitio externo no devuelve 404 cuando Ophyra si devuelve contenido para `route`.
- El sitio externo aplica `template.css_text`.
- El sitio externo renderiza `body` como HTML, no como texto escapado.
- El sitio externo muestra imagenes generadas con layout 50/50 alternado.

## Search Console

Search Console no lo consume el sitio publico. Lo consume Ophyra backend.

El sitio publico solo renderiza contenido. Ophyra se encarga de:

- Search Console
- SERP
- Google Trends
- competidores
- keywords
- oportunidades
- publicaciones aprobadas

Las credenciales nunca deben vivir en el sitio publico ni en JavaScript.
