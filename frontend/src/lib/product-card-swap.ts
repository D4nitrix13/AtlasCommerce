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

/** Clave de agrupación de un acabado: slug|label|hex. */
function finishGroupKey(variant: ProductVariant): string {
  return `${variant.finishSlug ?? ''}|${variant.finishLabel ?? ''}|${variant.finishHex ?? ''}`;
}

/** Agrupa variantes por acabado, preservando el orden de primera aparición. */
function groupVariantsByFinish(
  variants: readonly ProductVariant[],
): Map<string, ProductVariant[]> {
  const groups = new Map<string, ProductVariant[]>();
  for (const variant of variants) {
    const key = finishGroupKey(variant);
    const members = groups.get(key);
    if (members === undefined) groups.set(key, [variant]);
    else members.push(variant);
  }
  return groups;
}

/** Swatch del acabado a partir de su variant representante. */
function swatchFor(rep: ProductVariant, ownImage: ProductImage | undefined): VariantSwatch {
  return {
    id: rep.id,
    // `name` (Phase 9, backend PDP) > `finishLabel` (Phase 7) > id
    // como última instancia para no mostrar aria-label vacío.
    label: rep.name ?? rep.finishLabel ?? rep.id,
    hex: rep.finishHex ?? null,
    thumbnailUrl: ownImage?.url ?? null,
    thumbnailSrcset: ownImage?.srcset ?? null,
  };
}

/**
 * Colapsa las variantes a UN SWATCH POR ACABADO (clave
 * `finishSlug|finishLabel|finishHex`): las tallas del mismo acabado
 * comparten color y foto, y repetirlas producía la fila de círculos
 * idénticos que se leía como "imágenes repetidas" — más un contador
 * "N colores" inflado por las tallas.
 *
 * Representante del grupo: la primera variante con foto propia (sin
 * foto el preview en hover sería invisible); si ninguna tiene, la
 * primera. `repOfVariant` normaliza cada variant a su swatch, para que
 * `primaryVariantId` apunte al círculo que realmente se renderiza.
 */
function buildFinishSwatches(
  variants: readonly ProductVariant[],
  variantImages: ReadonlyMap<string, ProductImage>,
): { swatches: VariantSwatch[]; repOfVariant: Map<string, string> } {
  const repOfVariant = new Map<string, string>();
  const swatches: VariantSwatch[] = [];
  for (const members of groupVariantsByFinish(variants).values()) {
    const rep = members.find((v) => variantImages.has(v.id)) ?? members[0];
    if (rep === undefined) continue;
    for (const member of members) repOfVariant.set(member.id, rep.id);
    swatches.push(swatchFor(rep, variantImages.get(rep.id)));
  }
  return { swatches, repOfVariant };
}

/** Lo que la card necesita para renderizar un swatch. */
export interface VariantSwatch {
  /** ID del variant representante del acabado (string). */
  id: string;
  /** Label accesible (`aria-label` y `title`). */
  label: string;
  /** Color hex para el swatch cuando no hay thumbnail propio. */
  hex: string | null;
  /** URL del thumbnail del acabado (opcional). */
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
   * Swatch row de ACABADOS (uno por finish, no por variant), con su
   * thumbnail y color. Vacío = la card no renderiza la fila (sigue
   * mostrando 1 sola imagen).
   */
  swatches: readonly VariantSwatch[];
  /**
   * Map `variantId → image` que alimenta `buildStackedImages`. Cada regla
   * `card:has([data-swatch-id=X]:hover) img[data-variant-id=X]` necesita
   * que exista la imagen en el DOM para el cross-fade, y el DOM se arma
   * desde acá — por eso el CSS decide sobre el stack, no sobre este mapa.
   */
  variantImages: ReadonlyMap<string, ProductImage>;
  /** ID del swatch activo (representante del acabado de la imagen principal). */
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
  const hoverImage = resolveHoverImage(richImages);
  const variantImages = buildVariantImageMap(richImages);
  const { swatches, repOfVariant } = buildFinishSwatches(variants, variantImages);

  const rawPrimaryId = primaryImage?.variantId ?? null;
  const primaryVariantId =
    rawPrimaryId === null ? null : (repOfVariant.get(rawPrimaryId) ?? rawPrimaryId);

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
/** Portada del stack: la primary rica o el `imageUrl` legacy. */
function primaryStackImage(product: Product, swap: ProductCardSwapData): StackedImage {
  if (swap.primaryImage === null) {
    // Legacy sin galería rica: una sola imagen con `imageUrl`. La
    // card no hace swap en este caso — el CSS sólo conoce el primary.
    return { src: product.imageUrl, srcset: '', alt: product.name, role: 'primary', variantId: null };
  }
  return {
    src: swap.primaryImage.url,
    srcset: swap.primaryImage.srcset,
    alt: swap.primaryImage.altText,
    role: 'primary',
    // El `data-variant-id` del DOM es el del swatch que representa al
    // acabado de la portada; con el id crudo de la talla, su círculo
    // caía a la regla de escape aunque la foto sí estaba en el stack.
    variantId: swap.primaryVariantId,
  };
}

/**
 * `<img>` candidatas de los swatches, en orden de fila. Se emiten por
 * SWATCH (no por `variantImages`) para que cada una quede con el
 * `variantId` del círculo que la gatilla; quien dedupe por `src` decide
 * cuál sobrevive — si dos acabados comparten archivo, el segundo swatch
 * se queda sin `<img>` propia y lo cubre el escape de `buildVariantHoverCss`.
 */
function buildSwatchStackImages(swap: ProductCardSwapData): StackedImage[] {
  const images: StackedImage[] = [];
  for (const swatch of swap.swatches) {
    const image = swap.variantImages.get(swatch.id);
    if (image === undefined) continue;
    images.push({
      src: image.url,
      srcset: image.srcset,
      alt: image.altText,
      role: 'variant',
      variantId: swatch.id,
    });
  }
  return images;
}

export function buildStackedImages(
  product: Product,
  swap: ProductCardSwapData,
  hasSwatchRow: boolean,
): StackedImage[] {
  const images: StackedImage[] = [primaryStackImage(product, swap)];
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
    for (const variantImage of buildSwatchStackImages(swap)) {
      if (images.some((entry) => entry.src === variantImage.src)) continue;
      images.push(variantImage);
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
