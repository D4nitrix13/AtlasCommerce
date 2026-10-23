# Plan — Pipeline de contenido de catálogo (admin → API → storefront)

> **Cómo leer este documento:** cada etapa tiene un estado. Actualizo el estado
> al terminar cada trabajo verificable. Nada se marca hecho sin evidencia
> observada (comando con salida real, o navegador real).

**Última actualización:** 2026-10-23 · **Etapa actual:** E1.5 — `is_hover` + orden de galería

**E1.4 — ✅ HECHO.** `finish_slug`, `finish_label`, `finish_hex`, `condition`,
`stock`, `handedness` escribibles por API. Commit `57766e0c`. 586 tests verdes.

Decisión de dominio: `finish_slug` **obligatorio** en categorías finishables
(`VariantFinishDeriver::isFinishableCategory()`: guitars, basses) y opcional en
el resto, donde el color es atributo del producto y no acabado. El criterio ya
existía en el repo; no se duplicó la lista de slugs.

**Por qué importa:** `StorefrontProductResource::resolveVariants()` descarta
toda variante sin `finish_slug`. Con la API able de crear variantes, eso era
una bomba: una variante sin acabado desaparecía del PDP sin error en ninguna
parte. El fix va en el borde de escritura, **no** en el storefront — cambiarlo
no movería ninguna de las 421 variantes actuales y obligaría a revisar el
picker de la PDP.

Enums reutilizados, no inventados: `condition` ∈ {nuevo, usado, ambos} según la
migración; `handedness` reutiliza `VariantHandednessResolver::LEFT_HANDED`, con
`NULL` = diestro. Sin cast al modelo: el resource hace `(string) $condition` y
castear a `BackedEnum` lo rompería.

Añadido índice único parcial `(product_id, finish_slug) WHERE finish_slug IS NOT
NULL AND deleted_at IS NULL`: la unicidad la garantizaba sólo la validación, que
deja pasar SQL directo y carreras. 0 duplicados antes de aplicar.

**E1.2 — ✅ HECHO.** Anchos en `config/assets.upload.widths`. Config inválida se
**rechaza al leer** (no se sanitiza, no se valida en el boot, lista vacía =
rechazo). `ProductImageUploader::WIDTHS` eliminado: una sola fuente de verdad.

**E1.3 — ✅ HECHO.** `variant_id` en los tres endpoints, validado con que la
variante pertenezca al producto **de la ruta**, nunca del body. Regla en un
trait para que los tres FormRequests la apliquen por estructura.

Sin esa regla la galería miente en las dos direcciones: el PDP muestra la foto
cuando no hay acabado seleccionado, y el panel la etiqueta con un acabado que
nunca se cumple. El storefront **no necesitó cambios**: `galleryPerRole()` ya
agrupaba por `(variant_id, sort_order)`.

Commits `413cf9f4`. 453 tests verdes (265 admin + 188 assets/storefront).

Evidencia real de 1.3 (JPEG real, producto con 2 variantes, disco real):
```
producto 98 · variante 12 (heritage-cherry) · la otra variante 13 sin imágenes
  variant_id=12  w=400 / 800 / 1200 / 1600  →  4 WebP
0 filas con variant_id = 13
```

**E1.1 — ✅ HECHO y verificado.** Commit `c1d9e451`. Subida por multipart →
WebP en 400/800/1200/1600, aspect preservado, una fila por ancho, `is_primary`
solo en 1600, sin upscale, reutilizando `StorageKeyDeriver`. Extensión `gd`
con `--with-webp` agregada a los **7** Dockerfiles del backend (el container no
la tenía: la feature era código muerto).

Evidencia real (JPEG 1600x1200 por HTTP, **disco real**, sin `Storage::fake`):
```
w=400   webp   400x300    11.296 bytes
w=800   webp   800x600    28.658 bytes
w=1200  webp   1200x900   46.738 bytes
w=1600  webp   1600x1200  71.170 bytes
```
336 tests verdes en `tests/Feature/Admin` + `tests/Unit/Services/Assets`.

---

## D3 · Subida guiada: recomendar sin exigir

**Idea del usuario:** el admin sube "cuerpo completo" como imagen 1, y el sistema
le recomienda que la 2 sea "la misma guitarra de espaldas" para el hover.

**Por qué sí:** la diferencia entre **exigir** un rol y **recomendar** uno es la
diferencia entre un sistema que se rompe y uno que acompaña. Hoy el sistema exige
y por eso el admin tiene que mentir. Si la sugerencia se puede ignorar sin
consecuencias, es ayuda, no Candidatura.

**Lo que NO se hace en v1:** clasificar el contenido de la foto. Requiere visión,
es caro y se equivoca. La sugerencia es **texto asociado a la ranura**, nunca un
clasificador.

### Los 19 tipos de foto que tiene el catálogo → 3 ranuras

Medido sobre las 10.244 imágenes de `instrumentos-musicales-la-voz`:

| Grupo del catálogo | Tipos (conteo) |
|---|---|
| Vista general | `body` 410 · `backbody` 393 · `front` 365 · `back` 354 |
| Clavijero | `headstock` 266 · `back-headstock` 230 |
| Detalle | `pickup` 209 · `knobs` 165 · `fingerboard` 6 · `tailpiece` 1 |
| Acabado | `binding` 6 · `finish-back` 3 · `finish-peghead` 3 · `finish-top` 1 · `finish-sides` 1 |
| Otros | `side` 72 · `beauty` 49 · `case` 23 · `sn-label` 1 |

| Ranura | Qué poner | Sugerencia al admin |
|---|---|---|
| **Portada** | El producto entero, de frente | *"El producto completo, de frente, centrado"* |
| **Hover** | El mismo producto de otro ángulo | *"El mismo producto de otro ángulo — atrás, o uno que muestre el cuerpo entero"* |
| **Galería** | Cualquier otra foto, sin límite | Sin sugerencia que limite |

### Sugerencias automáticas que sí son gratis (tienen dato real)

- **Catálogo migrado:** se sabe qué `source_role` tenía cada imagen. Se puede
  decir *"esta se importó como `back`; ¿la querés como hover?"*. No es adivinar.
- **Producto con varias variantes:** si ya hay imágenes de otra variante,
  *"continuá con la variante X"*.
- **Ranura vacía:** *"necesitamos una portada"* / *"si no subís una segunda, la
  card no cambia al pasar el cursor"*. El efecto observable, no una regla.

### Nombres

El admin puede escribir lo que quiera (`imagen-1`, `mi-guitarita`, nada). El
sistema propone un nombre por ranura y el admin lo acepta o lo pisa. **El nombre
no se valida contra ningún vocabulario y no decide nada**: orden y ranuras
vienen de los flags.

---

## D4 · Contrato del frontend (nombres genéricos)

El frontend no sabe de roles. Ni `backbody`, ni `pickup`, ni `knobs`.

```ts
interface ProductImage {
  url: string
  srcset: string
  alt: string
  isPrimary: boolean   // ranura portada
  isHover: boolean     // ranura hover
  sortOrder: number    // orden de galería
  variantId: string | null
}
```

- `ProductCard` → `isPrimary` + `isHover`
- `ProductGallery` → todas, ordenadas por `sortOrder`, filtrables por `variantId`
- Nadie lee `source_role`.

**Trabajo pendiente que esto dispara:** hoy `ProductCard` saca el hover de la
imagen con rol `back` (`resolveBackImage`, `lib/product-card-swap.ts:115-123`) y el
PDP idem. Ambos pasan a leer `isHover`, lo que elimina `BACK_ROLES` del runtime.

---

## Capacitación del administrador

No es un curso: es la interfaz explicándose sola.

- [ ] **Estado vacío que enseña.** Sin imágenes: *"Subí la foto que querés que se vea en la card"*. Sin hover: *"Si no subís una segunda, la card no cambia al pasar el cursor"* — la verdad del efecto, no una regla.
- [ ] **Ayuda contextual en cada ranura**, una línea, visible mientras se sube.
- [ ] **Vista previa de la card antes de guardar.** Es lo que más previene errores: el admin no tiene que imaginarse cómo queda.
- [ ] **Checklist de completitud** al final: portada ✅, hover ✅, galería ○, con lo que falta en una frase.
- [ ] **Sin modales de "confirmá que entendiste".** Si hay que explicar algo, va en la pantalla donde hace falta.

---

## Mi recomendación sobre D1

**Conservar `source_role`, como metadato de procedencia, invisible en el dominio.**

El razonamiento, sin adornos:

1. **El vocabulario sirve para dos cosas, y solo una sigue viva.** Servía para
   (a) que el scraper eligiera portada y (b) que el auditor detectara
   etiquetas mentirosas. Con la UI de admin, (a) la resuelve el humano
   marcando "esta es la principal". (b) sigue siendo necesaria: es lo único
   que permite comparar "qué dice el metadata" contra "qué se ve en la foto".
2. **Tirar `source_role` mata la auditoría de las 345 `front`.** Si la columna
   no existe, cuando后发现 una foto mintiendo no hay forma de comparar ni de
   reimportar. Es perder información que ya se pagó.
3. **Lo que hay que garantizar no es que la columna no exista, sino que NADIE
   la lee en runtime.** El storefront, el frontend y la UI de admin no la
   tocan. Vive entre el importador y el auditor.

Si preferís que desaparezca del todo, se puede — pero perdés la trazabilidad y
la auditoría de las 345 pasa a ser manual sin referencia.

**Decisión tomada: `source_role` nullable, escrita solo por el importador,
leída solo por el auditor. Fuera del runtime.**

---

## Secuencia de ejecución

El criterio de orden no es "lo más urgente", es **el camino crítico**: qué
desbloquea lo demás.

> **La API de admin es el cuello de botella de TODO lo pendiente.** Los
> círculos no aparecen porque faltan finishes; la herramienta de color la
> maneja el admin; y si creo finishes a mano por SQL, es trabajo descartable
> porque el admin no podría mantenerlos. Construir la API primero convierte
> tres tareaspendientes en "hacerlas por el camino real" en vez de
> "hacerlas y después tirarlas".

### Fase 1 — API admin (el cimiento)

| # | Slice | Entrega |
|---|---|---|
| **1.1** | Subida de **una** imagen por API, extremo a extremo | `POST` multipart → WebP en 4 anchos → storage → fila. **Vertical delgada: prueba el cimiento sin diffarlo** |
| 1.2 | WebP y `srcset` por configuración |一次es widths configurables, no hardcodeados |
| 1.3 | `variant_id` en imágenes | La galería del PDP puede filtrar por acabado |
| 1.4 | Campos de acabado en variantes | `finish_slug`, `finish_label`, `finish_hex`, `condition`, `stock`, `handedness` |
| 1.5 | `is_hover` + orden de galería | La card tiene hover explícito, no por convención |
| 1.6 | Idempotencia | Re-subir la misma imagen no duplica |

### Fase 2 — UI del admin

| # | Slice | Entrega |
|---|---|---|
| 2.1 | Formulario de subida | Primary / hover / galería. **El admin nunca elige "front"** |
| 2.2 | Reordenar galería | Drag & drop → `sort_order` |
| 2.3 | Editor de variantes | Acabado + hex, condición, mano, exclusiva |
| 2.4 | Vista previa de la card | Ver cómo queda con esa primary + hover, antes de guardar |
| 2.5 | Cuenta gotas en el color | **Entra acá**, no aparte: el editor de variantes ya pide el hex |

### Fase 3 — Datos por el camino real

| # | Slice | Entrega |
|---|---|---|
| 3.1 | Importador que habla HTTP | Reemplaza al seeder como camino de escritura. El importador ejercita la API |
| 3.2 | Finishes reales por la API | **Acá es donde aparecen los círculos**: 327 productos con una sola variante reciben finishes reales |
| 3.3 | Scrapear `blueberry-burst` | El único producto con 0 imágenes |

### Fase 4 — Calidad de catálogo

| # | Slice | Entrega |
|---|---|---|
| 4.1 | Auditar los 345 `front` | Leo las imágenes y paso la lista de las que mienten |
| 4.2 | Corregir las mentirosas | Con criterio acordado (¿cambiar primary? ¿re-etiquetar?) |
| 4.3 | Descripciones que filtran el slug | `"Producto 1962-es-335-... de la marca..."` |
| 4.4 | Migrar los otros tenants | El fix del parser y la migración de `finish_slug` |

---

## Cómo mido el avance

- [ ] API: un `curl -F` sube un archivo y devuelve la fila con sus 4 URLs
- [ ] UI: screenshot de la card con primary + hover, en navegador real
- [ ] Círculos: conteo de productos con ≥2 variantes, antes y después
- [ ] Auditoría: lista de los `front` que no son `front`

---

## E0 · Decisiones de diseño — **RESUELTO**

### D1 · El vocabulario `front/back/backbody/headstock` sale del dominio

**Contexto:** hoy `product_images.alt_text` = `"{nombre} - vista {rol}"` y el rol
sale de `CatalogAssetsSeeder::ROLE_ORDER` (19 roles). Ese vocabulario existe
porque el **scraper** necesita etiquetar fotos de catálogo del fabricante
(Gibson las llama `_1_Front.png`, `_4_Back.png`).

**El problema:** el administrador no piensa "esto es un backbody". Sube "la foto
3 de mi guitarra". Si el dominio exige un rol de fabricante, la UI de admin tiene
que exponer un concepto que no le pertenece al negocio, y cada image que sube
tiene que mentir en un vocabulario ajeno.

**Decisión propuesta:** el vocabulario **sobrevive como metadato de procedencia
del importador** (`source_role`, nullable) y **muere como concepto de dominio**.
Lo que el dominio —y la UI— consumen es sólo para qué se usa cada imagen:

| Campo | Significado | Quién lo decide |
|---|---|---|
| `is_primary` | La imagen que muestra la card del catálogo | el admin |
| `is_hover` | La que aparece al pasar el cursor sobre la card | el admin |
| `sort_order` | Orden dentro de la galería del PDP | el admin (drag & drop) |
| `source_role` | De qué scraper vino, si vino de un scraper | el importador, **no** el admin |

**Consecuencia:** `GalleryRoleAuditor`, `VariantFinishDeriver`, `resolveBackImage`
y el `ROLE_ORDER` quedan como herramientas **del importador**, no del runtime.

**Pendiente de tu visto bueno:** ¿te cierra que `source_role` quede solo para
trazabilidad del import?

### D2 · Names de componente genéricos

Nada de `backbody`, `pickup`, `knobs` en el dominio ni en los nombres de
componente. El vocabulario de fabricante se queda adentro del importador.

Nombres acreening: `ProductImage` (con sus flags de uso), `ProductGallery`,
`ProductImageUpload`, no `GalleryRole*` ni `BackbodyImage`.

---

## E1 · API admin de catálogo — **PENDIENTE**

Hoy la API admin existe pero está hueca. Medido:

| Pieza | Estado real |
|---|---|
| Subida de binario | **no existe** (`grep putFile\|storeAs\|UploadedFile` → 0 en todo `backend/app/Http/Controllers`) |
| `POST /admin/products/{p}/images` | persiste un **string** `image_url`; no sube nada |
| `ProductImageStoreRequest` | acepta `image_url`, `alt_text`, `is_primary`, `sort_order`. **Sin `variant_id`, sin `width`, sin archivo** |
| `ProductVariantStoreRequest` | acepta 7 campos. **Cero `finish_*`**, sin `condition`, `stock`, `handedness` |

**Trabajo:**
- [ ] `POST /admin/products/{p}/images` acepta **archivo** (multipart), no URL
- [ ] Conversión a WebP en la subida (400/800/1200/1600) + `srcset`
- [ ] `variant_id` en imágenes, para que la galería filtre por acabado
- [ ] Variantes aceptan `finish_slug`, `finish_label`, `finish_hex`, `condition`, `stock`, `handedness`
- [ ] `is_hover` + orden explícito de galería
- [ ] Idempotencia: re-subir la misma imagen no duplica
- [ ] Tests de API para cada campo nuevo

**Herramienta de conversión:** `spatie/image` ya está instalada (vía
`spatie/laravel-medialibrary`) con **0 usos**. No hace falta dependencia nueva.

---

## E2 · Formulario de galería del admin — **PENDIENTE**

Lo que describís, en la interfaz:

- [ ] Zona de subida: primary / hover / galería — **el admin no elige "front" ni "back"**
- [ ] Reordenar la galería (drag & drop → `sort_order`)
- [ ] Nombres libres: `imagen-1`, `imagen-x`, cualquier nombre. El nombre del
      archivo es del admin, no un rol
- [ ] Por cada variante: acabado (nombre + color hex), condición (nuevo/usado),
      mano (izquierda/derecha), exclusiva
- [ ] Vista previa inmediata de cómo se ve la card con esa primary + hover
- [ ] Empty / loading / error states (frontend/AGENTS §11)

---

## E3 · Migrar el catálogo por la API — **PENDIENTE**

**Pregunta que hiciste, y la respuesta:** hoy el catálogo se puebla con un
**seeder que escribe directo a la BD** (`php artisan assets:seed-catalog`).
La API admin no podría hacerlo aunque quisiéramos: no acepta archivos.

**Decisión propuesta:** la **API es el único camino de escritura**. El seeder
queda sólo como herramienta de importación de una vez.

Para no tener dos verdades, el importador **llama a la misma API HTTP que la UI
del admin**. Beneficio: importar ejercita la API de verdad, así que cualquier
hueco aparece durante la importación y no en producción. El admin y el importador
comparten un único contrato.

- [ ] Importador que habla HTTP contra la API admin (no INSERT directo)
- [ ] Reporte de lo que la API rechazó, con el motivo
- [ ] Idempotente: se puede correr dos veces sin duplicar

---

## Pendientes que ya conocés — **PENDIENTE**

| # | Qué | Por qué importa |
|---|---|---|
| P1 | Scrapear `gibson-les-paul-supreme-blueberry-burst` | Único producto con 0 imágenes. Su archivo es `...-1600x1600-01.webp`: sufijo `-01` y sin rol, el parser no lo entiende. El `products.json` de Gibson responde 200 con 8 imágenes |
| P2 | Auditar los ~345 `front` | Dicen ser `front` en el metadata pero el archivo puede ser un clavijero. **Se puede hacer: sé leer imágenes** |
| P3 | Círculos en 345 productos | 327 de 356 tienen **una sola variante**; el swatch exige ≥2. Es carga de datos, no código |
| P4 | Herramienta de color del admin | Decidido: selector hex + cuenta gotas, **manual**. Ya entra en E1/E2 |
| P5 | Descripciones autogeneradas que filtran el slug | La card muestra `"Producto 1962-es-335-reissue-sixties-cherry de la marca..."`. Cosmético pero feo |
| P6 | Migración pendiente en otros tenants | El ensanchado de `finish_slug` y el fix del parser se aplicaron sólo al tenant La Voz |

---

## Verificado en esta sesión (no pendiente)

- Paginación real del catálogo: `index` ahora pagina, `{data, meta}`. 2,34 MB → 200 KB
- Parser de assets: leía desde la izquierda y rechazaba todo slug con dígitos.
  57 `null` + 28 rol basura → **5 `null` + 0 basura** sobre 10.249 archivos
- SKU duplicado: 10-11 productos abortaban el INSERT. **0 errores** ahora
- `finish_slug` de 64 a 180 (copiaba `products.slug`, que es 180)
- Re-seed: 356 productos, 421 variantes, 10.244 imágenes, 0 productos sin precio