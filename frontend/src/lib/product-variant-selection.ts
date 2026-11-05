/**
 * Modelo de variantes del PDP — acabado y mano.
 *
 * Extraído de `[slug].astro` en Oct 2026. La página ya estaba en ~1100
 * líneas (muy por encima del límite de `frontend/AGENTS.md` §1) y la
 * lógica de "cuál acabado se ve por defecto" es exactamente el tipo de
 * decisión que necesita tests own: es estado de render, no markup.
 *
 * Todo son funciones puras sobre las variantes que devuelve la API. Sin
 * DOM, sin fetch, sin i18n: los labels entran como argumento.
 *
 * ── Contrato de datos ────────────────────────────────────────────────
 *
 * `finishSlug` es el slug del acabado y también el valor del query
 * param `?finish=`. `handedness` viene VERBATIM del backend: `'left-
 * handed'` o `null`, donde `null` significa "regular / diestro"
 * (convención de la migración `2026_10_19_160000_add_left_handed_
 * flags_to_products`). Por eso `normalizeHandedness()` existe: el
 * backend no manda la palabra "right-handed" porque no está en el
 * dominio, pero la UI necesita un valor comparable y un slug de URL.
 */

/** Valor de dominio para la mano izquierda. Espeja `VariantHandednessResolver::LEFT_HANDED`. */
export const HANDEDNESS_LEFT = 'left-handed';

/**
 * Valor de display para `handedness = null`.
 *
 * NO es un valor de dominio: es el lado derecho del par que el backend
 * representa con NULL. Existe sólo para que la UI pueda offercer una
 * opción comparable y un slug de URL estable.
 */
export const HANDEDNESS_RIGHT = 'right-handed';

/** Variante del API tal como la expone `StorefrontProductResource`. */
export interface ProductApiVariant {
  readonly id: number;
  readonly name: string;
  readonly finishSlug: string;
  readonly finishLabel: string;
  readonly finishHex: string | null;
  readonly condition: string;
  readonly stock: number;
  /**
   * Mano de la variante, verbatim del backend.
   *
   * Opcional a propósito: el campo se agregó al resource en Oct 2026 y
   * un backend viejo (o un fixture de test previo) no lo manda.
   * `undefined` y `null` significan lo mismo: diestro.
   */
  readonly handedness?: string | null;
}

/** Opción de acabado lista para renderizar. */
export interface FinishOption {
  readonly id: string;
  readonly label: string;
  readonly hex?: string;
}

/** Opción de mano lista para renderizar. */
export interface HandednessOption {
  readonly id: string;
  readonly label: string;
}

/** Mínimo de acabados para que un selector tenga sentido. */
const MIN_FINISHES_FOR_PICKER = 2;

/** Mínimo de manos para que un selector de mano tenga sentido. */
const MIN_HANDEDNESS_FOR_PICKER = 2;

/**
 * Normaliza la mano de una variante al par comparable por la UI.
 *
 * `null` / `undefined` → `'right-handed'`. Cualquier otro valor se pasa
 * tal cual: si el backend empieza a emitir `'left'` o `'zurdo'`, esta
 * función no lo pisa ni lo adivina.
 */
export function normalizeHandedness(variant: ProductApiVariant): string {
  const raw = variant.handedness;
  if (raw === undefined || raw === null || raw === '') return HANDEDNESS_RIGHT;
  return raw;
}

/**
 * Opciones de mano del producto, deduplicadas y en orden estable.
 *
 * Con el catálogo actual devuelve 1 sola opción en el 100% de los
 * productos (la mano es una propiedad del modelo completo, no de la
 * variante), así que el selector no se renderiza y sólo se muestra el
 * dato. Cuando aparezca el primer producto offered en ambas manos,
 * esta función ya devuelve las 2 y el selector aparece sin tocar la
 * página.
 */
export function collectHandednessOptions(
  variants: readonly ProductApiVariant[],
  labels: Readonly<Record<string, string>>,
): readonly HandednessOption[] {
  const seen = new Map<string, HandednessOption>();
  for (const variant of variants) {
    const id = normalizeHandedness(variant);
    if (seen.has(id)) continue;
    seen.set(id, { id, label: labels[id] ?? id });
  }
  return Array.from(seen.values());
}

/** ¿La mano ofrece un selector real, o hay que ignorarla? */
export function hasHandednessPicker(options: readonly HandednessOption[]): boolean {
  return options.length >= MIN_HANDEDNESS_FOR_PICKER;
}

/**
 * Acabado que se ve por defecto al abrir el PDP.
 *
 * Regla: la PRIMERA variante con `stock > 0`; si ninguna tiene stock,
 * la primera de la lista. Es lo que define qué imágenes se muestran en
 * la primera pintura, así que importa que sea una comprable antes que
 * una agotada.
 *
 * Si `selectedFromUrl` matchea alguna opción gana sobre el default —
 * un link compartido a `?finish=ebony` tiene que respectedse siempre.
 */
export function resolveSelectedFinishId(
  options: readonly FinishOption[],
  variants: readonly ProductApiVariant[],
  selectedFromUrl: string | null,
): string | undefined {
  const fromUrl = selectedFromUrl !== null
    ? options.find((option) => option.id === selectedFromUrl)
    : undefined;
  if (fromUrl !== undefined) return fromUrl.id;

  const firstInStock = variants.find((variant) => variant.stock > 0);
  if (firstInStock !== undefined) return firstInStock.finishSlug;

  return options[0]?.id;
}

/**
 * Opciones de acabado, opcionalmente filtradas por mano.
 *
 * El filtro por mano sólo se aplica si el producto realmente ofrece más
 * de una: con una sola mano, filtrar deja la lista igual y lo único que
 * gana es una URL con un parámetro que no cambia nada.
 */
export function buildFinishOptions(
  variants: readonly ProductApiVariant[],
  selectedHandedness: string | null,
): readonly FinishOption[] {
  const source =
    selectedHandedness === null
      ? variants
      : variants.filter((variant) => normalizeHandedness(variant) === selectedHandedness);

  const byFinish = new Map<string, FinishOption>();
  for (const variant of source) {
    const existing = byFinish.get(variant.finishSlug);
    if (existing !== undefined) continue;
    byFinish.set(variant.finishSlug, {
      id: variant.finishSlug,
      label: variant.finishLabel,
      ...(variant.finishHex !== null && { hex: variant.finishHex }),
    });
  }
  return Array.from(byFinish.values());
}

/**
 * ¿El selector de acabado tiene algo que ofrecer?
 *
 * Un producto con un solo acabado real NO necesita swatches: el único
 * chip posible sería un botón que no cambia nada. El dato se sigue
 * mostrando como texto (`Acabado: Iced Tea Burst`), pero el control
 * se oculta. Esto cubre los 188/346 productos que siguen con el
 * `finish_slug` de fallback y los que tienen un único acabado real.
 */
export function hasFinishPicker(options: readonly FinishOption[]): boolean {
  return options.length >= MIN_FINISHES_FOR_PICKER;
}

/**
 * Imagen de preview de un acabado para el hover/focus del swatch del PDP.
 *
 * El mapa es `finishSlug → primera imagen del variant` (la misma fuente
 * que usa el thumbnail del picker). Un acabado sin imagen (camino
 * legacy, o variant sin fotos asociadas) devuelve `undefined` y el
 * swatch no ofrece preview — la navegación `?finish=` sigue funcionando.
 */
export function resolveFinishPreviewImage(
  finishId: string,
  previewImages: Readonly<Record<string, string | undefined>>,
): string | undefined {
  const url = previewImages[finishId];
  return url !== undefined && url !== '' ? url : undefined;
}