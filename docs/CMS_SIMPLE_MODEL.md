# CMS Simple Model

## Idea Principal

El CMS no debe sentirse como llenar muchos campos sueltos. Debe sentirse como crear una pagina con ayuda:

1. elegir marca;
2. elegir categoria;
3. elegir template;
4. escribir una instruccion clara;
5. previsualizar;
6. pedir cambios con IA;
7. aprobar y publicar.

La parte tecnica sigue existiendo, pero Ophyra debe esconderla detras de un flujo simple.

## Flujo Visual Recomendado

Growth Hub debe separarse visualmente en tres pantallas o pasos:

1. `Research Lab`: keywords, competidores, locations, SERP y datos de mercado.
2. `Opportunities`: ideas recomendadas a partir del analisis anterior.
3. `Article Builder`: categoria, template, brief, imagenes generadas, mapa, FAQ, preview y aprobacion.

El usuario no debe ver el analisis, las oportunidades y el builder como un formulario gigante en una sola pantalla. El paso 2 debe poder llenar el paso 3. El paso 3 debe poder usar un draft anterior como base para crear uno nuevo.

El paso 1 debe construir una lista final de keywords. El usuario puede empezar por:

- keyword + location;
- competidor + location;
- keyword + competidor.

El sistema debe sugerir alternativas, mostrar volumen/competencia/prioridad cuando el proveedor lo permita, y permitir agregar solo los keywords elegidos a la lista final. Esa lista final es la que alimenta las oportunidades del paso 2.

Hasta conectar Google Trends, Search Console o SERP provider, las recomendaciones pueden mostrarse como estimaciones de IA o estimaciones locales, pero deben indicar su fuente.

## Como Pensarlo

Una pagina CMS tiene cuatro piezas principales:

- `content`: lo que la pagina dice.
- `template`: como se ve y que estructura usa.
- `category`: donde vive editorialmente.
- `route`: donde aparece publicamente.

Ejemplo:

```text
Marca: VNV Events
Categoria: Weddings
Template: Event service landing
Instruccion: Crear una pagina para wedding planning en Doral con FAQ, mapa e imagen generada.
Ruta final: /locations/wedding-planning-doral
```

El usuario no deberia tener que decidir manualmente cada bloque tecnico. El generador puede proponerlos usando la categoria, el template y la instruccion.

## Campos Minimos Para Crear Una Pagina

Para el flujo humano, los campos visibles pueden ser:

- Marca / sitio.
- Tipo de contenido: pagina, blog o location.
  - `landing` y `custom` se usan como alias legacy de `page`.
- Categoria.
- Template.
- Titulo o idea principal.
- Instruccion para IA.
- Keyword o location, si aplica.
- Opciones rapidas: incluir FAQ, mapa, imagenes, citas revisadas.

Todo lo demas puede ser generado, sugerido o editable en modo avanzado.

## Lo Que El Generador Crea

Cuando el usuario presiona crear, Ophyra puede generar:

- titulo final;
- slug;
- ruta publica;
- intro;
- cuerpo;
- bloques visuales;
- FAQ;
- metadata SEO;
- schema JSON-LD;
- imagenes o prompts de imagen;
- mapa si la pagina es local;
- citas revisadas si fueron solicitadas;
- estado inicial `DRAFT`.

Nada debe publicarse automaticamente sin revision.

## Templates

Un template no debe ser solamente una pagina duplicada. Debe ser una receta visual:

- estructura;
- slots;
- bloques permitidos;
- CSS;
- tono visual;
- reglas de preview;
- tipo de contenido compatible.

El template debe vivir en la base de datos y puede ser creado desde el CMS. Si alguna vez se exporta a `.twig`, debe conservar metadata de origen para saber desde que template y marca fue generado.

En Growth Hub, la fuente de verdad es:

```text
cms_templates
cms_categories
cms_contents.id_template
cms_contents.id_cms_category
```

`metadata_json.template_key` y `metadata_json.content_category` pueden quedar como snapshot legible, pero no deben reemplazar la relacion real en BD.

El CSS del template se edita desde Growth Hub, paso 3, en la seccion `Template CSS`. Ese CSS vive en `cms_templates.css_text` y no debe mezclarse con el prompt del articulo. La IA genera bloques/contenido; el template define la piel visual.

Los templates actuales fueron actualizados revisando los repos locales:

- `vnv-events`: estilo oscuro premium, turquoise, gold, Playfair, secciones de servicio/eventos.
- `vnv-gourmet`: estilo editorial food, cream, brown ink, accent warm, cards legibles.
- `jonnys-media`: estilo dark audiovisual/profesional con cyan/blue y grids de media.

## Categorias

Las categorias ayudan a que el generador entienda contexto editorial.

Ejemplos para VNV Events:

- Weddings
- Quinceaneras
- Corporate Events
- Event Locations
- Planning Guides
- Vendor Tips

La categoria debe tener `site_key`. Una categoria de VNV Events no debe aparecer editando Avomeal, salvo que sea una categoria compartida de forma explicita.

## Preview

Ophyra debe poder mostrar preview sin mandar al usuario a VNV Events.

La preview usa:

- el contenido en borrador;
- el template seleccionado;
- los bloques generados;
- el CSS del template;
- la ruta/canonical simulada.

Asi el usuario puede decir:

```text
Hazlo mas premium.
Agrega una seccion para quinceaneras.
Cambia el template a uno mas editorial.
Reduce el texto y pon mas CTA.
```

Las imagenes deben generarse o seleccionarse dentro del flujo del articulo, no como una carga separada obligatoria. Una media library puede existir, pero debe sentirse secundaria.

## Publicacion

Publicar significa:

1. el contenido pasa a `PUBLISHED`;
2. la aprobacion pasa a `APPROVED` o `PUBLISHED`;
3. la ruta queda activa;
4. el sitemap de la marca lo incluye;
5. VNV Events, Avomeal o Jonnys Media lo pueden consumir por API.

VNV Events solo debe ver contenido con:

```text
site_key = vnvevents
```

## Regla De Seguridad Editorial

El generador puede proponer contenido, pero no debe inventar hechos.

Especialmente:

- citas de Reddit, Quora o foros deben quedar como fuentes revisables;
- imagenes generadas deben marcarse como generadas por IA cuando aplique;
- mapas deben usar location real;
- claims, precios, premios y disponibilidad deben venir de datos aprobados.

## Direccion Del Producto

La direccion correcta es:

```text
menos campos visibles
mas contexto por categoria
mas estructura por template
mas preview
mas iteracion con IA
mas control antes de publicar
```

El CMS debe ser facil de usar, pero el backend debe seguir guardando datos estructurados para SEO, sitemap, auditoria, marca y consumo por sitios externos.

## Estado Actual Del Panel

El documento operativo del panel esta en:

```text
docs/ophyra-growth-hub.md
```

Estado actual:

- Search Console ya esta conectado por OAuth.
- Las propiedades correctas son `vnvevents.com`, `avomeal.com` y `jonnys.media`.
- `ophyra.com` no debe ser tratado como sitio de contenido objetivo en Growth Hub.
- DataForSEO esta implementado como proveedor SERP, pero sigue bloqueado por credenciales no autorizadas.
- El paso 1 debe partir de servicios reales, datos de Search Console, markets con autocomplete y validacion SERP.
- El paso 2 ya agrupa oportunidades de Search Console, sugeridas y guardadas, y puede llenar el builder.
- El paso 3 ya crea un draft preview-ready usando categoria, template, brief, imagenes solicitadas, FAQ, mapa y bloques CMS.
- La previsualizacion privada ya renderiza los bloques guardados antes de aprobar o publicar.
- Las categorias/templates del builder ya salen de BD y el draft guarda `id_template` / `id_cms_category`.
- El CSS del template se lee desde `cms_templates.css_text` en la previsualizacion.

## Cierre QA Del Flujo

Revision cerrada el 2026-06-06:

- Step 1 probado guardando keyword final con location seleccionada y priority.
- Step 2 probado guardando oportunidad basada en keyword/location.
- Step 3 probado generando draft con categoria/template reales desde BD.
- Preview probado con bloques CMS y CSS del template.
- Edicion probada sin perder `id_template` ni `id_cms_category`.
- Multi-site probado: VNV Events y Avomeal pueden usar el mismo slug/titulo sin chocar porque el scope correcto es `id_owner + site_key + slug`.
- No quedaron filas temporales de QA.

La parte que queda pendiente no es de estructura CMS: DataForSEO sigue devolviendo 401 hasta reemplazar el password por el API Access password correcto.
