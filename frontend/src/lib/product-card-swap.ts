/**
 * product-card-swap — derives the data the storefront `ProductCard`
 * needs to render the Fender-style finish swatch row and the
 * CSS-only image-swap on hover.
 *
 * Decoupling this from the `.astro` keeps the template short
 * (AGENTS §1) and lets the swap logic be unit-tested without the
 * Astro render pipeline.
 *
 * ── Inputs ────────────────────────────────────────────────────────
 *
 * `Product.images` comes in two shapes:
 *   - `readonly string[]` (legacy / listing without variants eager).
 *   - `readonly ProductImage[]` (PDP / listing with `variantId`).
 *
 * The card only knows how to swap when images are rich objects
 * (legacy strings give us no `variantId`, so swatches cannot match
 * an image and we silently degrade to "no swap, one image").
 *
 * ── Outputs ───────────────────────────────────────────────────────
 *
 *   - `primaryImage`: shown when the card is idle.
 *   - `hoverImage`: shown on card hover (no swatch focus).
 *   - `variantImages`: `Map<variantId, ProductImage>` of the
 *     primary image per variant — shown when THAT swatch is hovered.
 *     OJO: es insumo de `buildStackedImages`, NO la fuente de verdad
 *     para "¿este swatch tiene foto propia?". El mapa puede tener un
 *     variant cuyo archivo fue deduplicado y nunca llega al DOM
 *     (ver `collectVariantIdsInStack`).
 *   - `variantSwatches`: one entry per `Product.variants` for the
 *     swatch row (Fender-style ~20px circles).
 *
 * ── La ranura de hover ────────────────────────────────────────────
 *
 * La foto de hover la marca el administrador (`is_hover` en
 * `product_images`), no el vocabulario del `altText`.
 *
 * Antes esta lib derivaba el hover del texto: buscaba el sufijo
 * " - vista back" y, si no estaba, probaba " - vista backbody" y
 * " - vista body". Ese vocabulario lo escribía el scraper del
 * fabricante, no el catálogo, y por eso la card terminaba
 * interpretando metadata de importación. Peor: el administrador no
 * tenía forma de decir "esta foto es la del hover" sin mentir en ese
 * vocabulario.
 *
 * `BACK_ROLES` y `resolveBackImage()` se eliminaron del runtime en el
 * slice 1.5. El vocabulario de procedencia sigue existiendo, pero
 * únicamente entre el importador y el auditor
 * (`CatalogAssetsSeeder::ROLE_ORDER`, `GalleryRoleAuditor`) y en
 * `HoverImageBackfiller`, que es donde se tradujo UNA vez a la bandera
 * que el storefront consume.
 */

import type { Product, ProductImage, ProductVariant } from '../types/catalog';

/**
 * Color slate-400 usado como fallback cuando la variant no tiene
 * `finishHex`. Valor hardcoded para no arrastrar la dependencia de
 * una clase de Tailwind al runtime (Astro extrae el color de la hoja
 * de estilos, no del bundle JS).
 */
const FALLBACK_SWATCH_COLOR = '#94a3b8';

/**
 * Type guard: el array es de `ProductImage` (no legacy strings).
 * Las cards necesitan el `variantId` para el swatch → image matching.
 * Si el array es de strings legacy, devolvemos `false` y la card cae
 * al render sin swatch.
 */
function isRichImageArray(
  images: readonly string[] | readonly ProductImage[] | undefined,
): images is readonly ProductImage[] {
  if (images === undefined || images.length === 0) return false;
  const first = images[0];
  return typeof first === 'object' && first !== null;
}

/**
 * Imagen principal del producto: la entrada con `isPrimary: true` o,
 * fallback, la primera por `sortOrder`.
 *
 * Esta función NO usa `product.imageUrl`: esa URL es single-source
 * para builds legacy, pero la galería rica la reemplaza y queremos
 * reflejar la imagen "real" de la variante principal en el card.
 */
function resolvePrimaryImage(richImages: readonly ProductImage[]): ProductImage | null {
  for (const image of richImages) {
    if (image.isPrimary) return image;
  }
  if (richImages.length > 0) {
    return [...richImages].sort((a, b) => a.sortOrder - b.sortOrder)[0] ?? null;
  }
  return null;
}

/**
 * Foto de hover: la entrada con `isHover: true`.
 *
 * El backend garantiza "como máximo una foto de hover por producto" y la
 * replica en todas las resoluciones de esa foto, así que el array de
 * galería (una entrada por foto) tiene a lo sumo un flag en `true`. Por
 * eso `find` alcanza y no hace falta un desempate.
 *
 * `null` cuando el producto no tiene foto de hover marcada — el caso de
 * un producto con una sola foto. La card degrada a no-intercambiar: el
 * `<img data-role="back">` no llega al DOM, la regla de hover no matchea
 * nada y la portada queda a la vista. NUNCA a una card en blanco.
 */
function resolveHoverImage(richImages: readonly ProductImage[]): ProductImage | null {
  return richImages.find((image) => image.isHover) ?? null;
}

/**
 * Mapa `variantId → primary image` por variant. La "primary image
 * de un variant" es la primera que aparece con ese `variantId` (sort
 * estable). Si un variant no tiene imagen propia, queda sin entrada.
 */
function buildVariantImageMap(richImages: readonly ProductImage[]): Map<string, ProductImage> {
  const map = new Map<string, ProductImage>();
  for (const image of richImages) {
    if (image.variantId === null) continue;
    if (!map.has(image.variantId)) map.set(image.variantId, image);
  }
  return map;
}

/** Lo que la card necesita para renderizar un swatch. */
export interface VariantSwatch {
  /** ID estable del variant (string). */
  id: string;
  /** Label accesible (`aria-label` y `title`). */
  label: string;
  /** Color hex para el swatch cuando no hay thumbnail propio. */
  hex: string | null;
  /** URL del thumbnail del variant (opcional). */
  thumbnailUrl: string | null;
  /** Srcset del thumbnail cuando viene del backend (opcional). */
  thumbnailSrcset: string | null;
}

/** Imagen que se renderiza en el stack — un `<img>` overlay con metadata. */
export interface StackedImage {
  src: string;
  srcset: string;
  alt: string;
  /** Rol semántico de la imagen en el cross-fade. */
  role: 'primary' | 'back' | 'variant';
  variantId: string | null;
}

export interface ProductCardSwapData {
  /** Imagen mostrada por default (idle). `null` → la card no hace swap. */
  primaryImage: ProductImage | null;
  /**
   * Foto que aparece en hover cuando no hay swatch focus.
   *
   * `null` cuando el producto no tiene foto de hover marcada. No es un
   * error ni un estado que la card tenga que pintar: significa "esta card
   * no hace swap", que es un producto con una sola foto.
   */
  hoverImage: ProductImage | null;
  /**
   * Swatch row de variantes, con su thumbnail y color. Vacío = la
   * card no renderiza la fila (sigue mostrando 1 sola imagen).
   */
  swatches: readonly VariantSwatch[];
  /**
   * Map `variantId → image` que alimenta `buildStackedImages`. Cada regla
   * `card:has([data-swatch-id=X]:hover) img[data-variant-id=X]` necesita
   * que exista la imagen en el DOM para el cross-fade, y el DOM se arma
   * desde acá — por eso el CSS decide sobre el stack, no sobre este mapa.
   */
  variantImages: ReadonlyMap<string, ProductImage>;
  /** ID del variant activo (elegido por la imagen principal). */
  primaryVariantId: string | null;
}

/**
 * Derivación principal: a partir de un `Product`, devuelve todo lo
 * que la card necesita para renderizar swatches + image-swap.
 *
 * Si el producto no tiene imágenes ricas ni multiple variantes,
 * devuelve `swatches: []` y `primaryImage: null` para que la card
 * caiga al render sin swap (preserva compatibilidad con el mock
 * `Kitsune` que usa placeholders data-URI).
 */
export function deriveProductCardSwap(product: Product): ProductCardSwapData {
  const variants: readonly ProductVariant[] = product.variants ?? [];
  const richImages = isRichImageArray(product.images) ? product.images : [];

  const primaryImage = resolvePrimaryImage(richImages);
  const primaryVariantId = primaryImage?.variantId ?? null;
  const hoverImage = resolveHoverImage(richImages);
  const variantImages = buildVariantImageMap(richImages);

  const swatches: VariantSwatch[] = variants.map((variant) => {
    const ownImage = variantImages.get(variant.id);
    return {
      id: variant.id,
      // `name` (Phase 9, backend PDP) > `finishLabel` (Phase 7) > id
      // como última instancia para no mostrar aria-label vacío.
      label: variant.name ?? variant.finishLabel ?? variant.id,
      hex: variant.finishHex ?? null,
      thumbnailUrl: ownImage?.url ?? null,
      thumbnailSrcset: ownImage?.srcset ?? null,
    };
  });

  return {
    primaryImage,
    hoverImage,
    swatches,
    variantImages,
    primaryVariantId,
  };
}

/**
 * Construye el array de `<img>` stackeados que la card renderiza.
 *
 * Si `swap.primaryImage` es `null` (mock legacy sin galería rica),
 * cae al `product.imageUrl` con un solo item — la card no hace swap.
 *
 * Las "variant images" sólo se stackean si hay swatch row (sin
 * swatch nadie las ve, ocuparían ancho de banda al pedo).
 */
export function buildStackedImages(
  product: Product,
  swap: ProductCardSwapData,
  hasSwatchRow: boolean,
): StackedImage[] {
  const images: StackedImage[] = [];
  if (swap.primaryImage !== null) {
    images.push({
      src: swap.primaryImage.url,
      srcset: swap.primaryImage.srcset,
      alt: swap.primaryImage.altText,
      role: 'primary',
      variantId: swap.primaryImage.variantId,
    });
  } else {
    // Legacy sin galería rica: una sola imagen con `imageUrl`. La
    // card no hace swap en este caso — el CSS sólo conoce el primary.
    images.push({
      src: product.imageUrl,
      srcset: '',
      alt: product.name,
      role: 'primary',
      variantId: null,
    });
  }
  // La foto de hover viaja con su propio `srcset`: el cross-fade es un
  // `<img>` más en el mismo rect, y el browser necesita poder pedir la
  // resolución que corresponde al ancho de la card en cada momento
  // (incluido el `scale(105%)` del hover).
  if (swap.hoverImage !== null && swap.hoverImage.url !== images[0]?.src) {
    images.push({
      src: swap.hoverImage.url,
      srcset: swap.hoverImage.srcset,
      alt: swap.hoverImage.altText,
      role: 'back',
      variantId: swap.hoverImage.variantId,
    });
  }
  if (hasSwatchRow) {
    for (const [variantId, image] of swap.variantImages.entries()) {
      if (images.some((entry) => entry.src === image.url)) continue;
      images.push({
        src: image.url,
        srcset: image.srcset,
        alt: image.altText,
        role: 'variant',
        variantId,
      });
    }
  }
  return images;
}

/**
 * Set de `variantId` que tienen una `<img>` REAL en el stack renderizado.
 *
 * Por qué el stack y no `swap.variantImages`: `buildStackedImages`
 * deduplica por `src`, así que un variant puede tener entrada en el mapa
 * y aun así ninguna imagen en el DOM (su archivo ya está en el stack
 * bajo otro `data-variant-id`). Datos legítimos del catálogo producen
 * esto: la versión zurda y la diestra de un mismo acabado comparten la
 * misma fotografía.
 *
 * Los `variantId` nulos no entran al set: la imagen legacy sin galería
 * rica no representa a ningún variant.
 */
export function collectVariantIdsInStack(
  stackedImages: readonly StackedImage[],
): ReadonlySet<string> {
  const idsInStack = new Set<string>();
  for (const image of stackedImages) {
    if (image.variantId === null) continue;
    idsInStack.add(image.variantId);
  }
  return idsInStack;
}

/**
 * Genera el CSS per-variant del `<style is:global>` al final de
 * `ProductCard.astro` y `ProductCardCatalog.astro`.
 *
 * Por qué no Tailwind: cada selector depende del `variantId` del producto
 * concreto (no se puede parametrizar con utilities).
 *
 * Emite DOS familias de reglas, una por swatch:
 *
 *  1. **Swap** (el swatch tiene `<img>` propia en el stack) —
 *     `card:has([data-swatch-id="X"]:hover) img[data-variant-id="X"]`.
 *  2. **Escape** (el swatch NO tiene foto propia) — mantiene visible la
 *     primaria mientras ese swatch está activo.
 *
 * Por qué la 2 es obligatoria: la regla "swatch hover → ocultar
 * primaria/back" matchea para TODOS los swatches, pero la de swap necesita
 * que exista el `data-variant-id` en el DOM. Un swatch sin imagen deja su
 * regla de swap sin matchear y la de ocultar activa → card EN BLANCO
 * (medido en Chromium sobre `usa-texan`).
 *
 * Ambas familias son disjuntas y cubren todos los swatches: el activo
 * siempre tiene su `data-variant-id` en el stack (es el de la primaria) y
 * lo resuelve la regla `data-swatch-active` del `<style>` scoped del
 * componente, no la de escape.
 *
 * `stackedImages` es la fuente de verdad de "¿quién tiene foto propia?",
 * por lo que hace falta pasarlo: `swap.variantImages` puede traer
 * variants ya deduplicados, que nunca llegan al DOM.
 *
 * Especificidad del escape: la regla que oculta la primaria vive en el
 * `<style>` SCOPED del componente, donde Astro suma `[data-astro-cid]` a
 * cada compound (0,7,0). Estas reglas van en un `<style is:global>` sin
 * ese scope (0,6,0), así que el escape necesita el mismo +1 que usan las
 * reglas `data-swatch-active`: una clase más (`.productcard-swap-swatch`)
 * dentro del `:has()` y, en el target, el tipo `img` + el atributo
 * `data-variant-id`, que las cards siempre renderizan (vacío incluido).
 */
export function buildVariantHoverCss(
  swap: ProductCardSwapData,
  stackedImages: readonly StackedImage[],
): string {
  const idsInStack = collectVariantIdsInStack(stackedImages);
  const rules: string[] = [];
  for (const swatch of swap.swatches) {
    const id = cssEscape(swatch.id);
    if (idsInStack.has(swatch.id)) {
      rules.push(
        `.productcard-swap-root:has([data-swatch-id="${id}"]:hover) .productcard-swap-img[data-variant-id="${id}"]{opacity:1;}`,
      );
      continue;
    }
    rules.push(
      `.productcard-swap-root:has(.productcard-swap-swatch[data-swatch-id="${id}"]:hover) img.productcard-swap-img[data-role="primary"][data-variant-id]{opacity:1;}`,
    );
  }
  return rules.join('');
}

/**
 * Escapa caracteres que romperían un selector CSS (id con ".", "/",
 * etc). Backend slugs son kebab-case pero el `variantId` puede traer
 * cualquier caracter — resguardamos el selector.
 *
 * Escapa `\` y `"` para que el valor no pueda cerrar el atributo, y `(` y
 * `)` porque los componentes espejan las reglas con un
 * `replaceAll(':hover)', ':focus-visible)')`: sin escapar el paréntesis de
 * cierre, un id que contenga el literal `:hover)` (p. ej.
 * `x"):hover)[data-y="z`) sería reescrito por dentro del valor de atributo
 * y la regla quedaría inservible. Con `\)` el valor sigue siendo el mismo
 * para el navegador y el replace no encuentra nada que tocar.
 */
function cssEscape(value: string): string {
  return value
    .replace(/\\/g, '\\\\')
    .replace(/"/g, '\\"')
    .replace(/\(/g, '\\(')
    .replace(/\)/g, '\\)');
}

/**
 * Hex color de fallback para un swatch. Centralizado acá para que
 * cambiar el color sea un solo cambio. `null` → `slate-400` para que
 * el swatch siga siendo visible (no transparente).
 */
export function swatchFallbackHex(swatch: Pick<VariantSwatch, 'hex'>): string {
  if (swatch.hex === null || swatch.hex === '') return FALLBACK_SWATCH_COLOR;
  return swatch.hex;
}

/**
 * CSS de cross-fade por variante para el preview de hover/focus de las
 * cards de rieles (`ProductRelated`).
 *
 * El markup stackea una `<img>` por variante: la primaria lleva la
 * clase `.card__img--default` (visible sin hover) y todas —primaria
 * incluida— `.card__img--variant` + `data-variant-id`. Las reglas:
 *
 *  1. base: variantes ocultas, primaria visible (el orden importa:
 *     misma especificidad, gana la última).
 *  2. con `article[data-active-variant]` se oculta todo lo anterior.
 *  3. una regla por id muestra la variante activa.
 *
 * TODO va en un `<style is:global>`: si estas reglas vivieran en el
 * `<style>` scoped del componente, Astro les suma `[data-astro-cid]` a
 * cada compound y les gana en especificidad a las de show — el preview
 * quedaba siempre en `opacity: 0` (bug reportado por el usuario: "al
 * poner el cursor sobre el circulito no se cambia la imagen").
 */
export function buildVariantPreviewCss(variantIds: readonly string[]): string {
  const rules: string[] = [
    '.card__img--variant{opacity:0;transition:opacity 250ms ease;}',
    '.card__img--default{opacity:1;transition:opacity 250ms ease;}',
    'article[data-active-variant] .card__img--default{opacity:0;}',
    'article[data-active-variant] .card__img--variant{opacity:0;}',
  ];
  for (const id of variantIds) {
    const escaped = cssEscape(id);
    rules.push(
      `article[data-active-variant="${escaped}"] .card__img--variant[data-variant-id="${escaped}"]{opacity:1;}`,
    );
  }
  return rules.join('');
}

/**
 * ¿La card debe renderizar el swatch row? Mismo umbral que el PDP:
 * un solo swatch es un control muerto (`MIN_FINISHES_FOR_PICKER = 2`
 * en `lib/product-variant-selection.ts`). Mantener el paralelismo
 * para que la card y el PDP siempre enseñen la misma cantidad de
 * variantes.
 */
export function shouldRenderSwatchRow(swatches: readonly VariantSwatch[]): boolean {
  return swatches.length >= 2;
}
