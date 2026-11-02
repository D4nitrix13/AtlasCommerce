/**
 * Lógica pura de filtros del catálogo (`/[locale]/products`).
 *
 * El PLP filtra en SSR por query params: los links del sidebar arman la
 * query string, esta librería la parsea, aplica la selección sobre el
 * listado y reconstruye los hrefs. Sin estado en el cliente — la misma
 * URL renderiza el resultado (funciona sin JS).
 *
 * Unidades: los params `minPrice`/`maxPrice` viajan en **USD** (los
 * labels del sidebar son "$0 – $30"); el dominio (`CatalogFilters`)
 * trabaja en **cents** (`priceCents`).
 *
 * `page` vive en `CatalogFilters` sólo para que `buildCatalogHref` pueda
 * resetearlo al cambiar un filtro: el corte por página lo hace la página,
 * no `applyCatalogFilters`.
 */

export interface CatalogFilters {
  category?: string;
  color?: string;
  minPriceCents?: number;
  maxPriceCents?: number;
  sort?: 'relevancia' | 'price-asc' | 'price-desc' | 'name-asc';
  page?: number;
}

const SORT_VALUES = new Set<CatalogFilters['sort']>([
  'relevancia',
  'price-asc',
  'price-desc',
  'name-asc',
]);

/** Página default cuando el filtro no trae (o trae basura) `page`. */
const DEFAULT_PAGE = 1;

/**
 * Precio en USD de la URL → cents del dominio. Inválido (vacío, negativo,
 * no numérico, notación exponencial) → `undefined` (se ignora el param).
 */
function parsePriceParam(raw: string | null): number | undefined {
  if (raw === null) return undefined;
  const trimmed = raw.trim();
  if (!/^\d+(\.\d+)?$/.test(trimmed)) return undefined;
  return Math.round(Number(trimmed) * 100);
}

/** `page` de la URL → entero ≥ 1, o `undefined` si no lo es. */
function parsePageParam(raw: string | null): number | undefined {
  if (raw === null) return undefined;
  const trimmed = raw.trim();
  if (!/^\d+$/.test(trimmed)) return undefined;
  const parsed = Number(trimmed);
  return Number.isSafeInteger(parsed) && parsed >= DEFAULT_PAGE ? parsed : undefined;
}

/**
 * Lee los filtros desde la query string de la URL. Tolera valores
 * inválidos (precios negativos, sort desconocido, page no numérico):
 * cada param malformado se ignora en vez de romper el listado.
 */
export function parseCatalogFilters(searchParams: URLSearchParams): CatalogFilters {
  const filters: CatalogFilters = {};

  const category = searchParams.get('category')?.trim();
  if (category) filters.category = category;

  const color = searchParams.get('color')?.trim();
  if (color) filters.color = color;

  const minPriceCents = parsePriceParam(searchParams.get('minPrice'));
  if (minPriceCents !== undefined) filters.minPriceCents = minPriceCents;

  const maxPriceCents = parsePriceParam(searchParams.get('maxPrice'));
  if (maxPriceCents !== undefined) filters.maxPriceCents = maxPriceCents;

  const sort = searchParams.get('sort')?.trim() as CatalogFilters['sort'] | undefined;
  if (sort && SORT_VALUES.has(sort)) filters.sort = sort;

  const page = parsePageParam(searchParams.get('page'));
  if (page !== undefined) filters.page = page;

  return filters;
}

/** Campos que `applyCatalogFilters` necesita de cada producto. */
export interface CatalogFilterable {
  priceCents: number;
  name: string;
  category?: string;
  variants?: readonly { finishSlug?: string }[];
}

/**
 * Filtra y ordena el listado según los filtros. No pagina: el corte por
 * página vive en la página (`?page=N`), para que los totales del filtrado
 * se calculen sobre el conjunto completo.
 */
export function applyCatalogFilters<T extends CatalogFilterable>(
  products: readonly T[],
  filters: CatalogFilters,
): T[] {
  const result = products.filter((product) => {
    if (filters.category !== undefined && product.category !== filters.category) {
      return false;
    }
    if (
      filters.color !== undefined &&
      !(product.variants ?? []).some((variant) => variant.finishSlug === filters.color)
    ) {
      return false;
    }
    if (filters.minPriceCents !== undefined && product.priceCents < filters.minPriceCents) {
      return false;
    }
    if (filters.maxPriceCents !== undefined && product.priceCents > filters.maxPriceCents) {
      return false;
    }
    return true;
  });

  switch (filters.sort) {
    case 'price-asc':
      return result.sort((a, b) => a.priceCents - b.priceCents);
    case 'price-desc':
      return result.sort((a, b) => b.priceCents - a.priceCents);
    case 'name-asc':
      return result.sort((a, b) => a.name.localeCompare(b.name));
    default:
      // 'relevancia' o sin sort: se preserva el orden de origen.
      return result;
  }
}

/**
 * Arma el href de un link de filtro: `current` con el `patch` encima, sin
 * params vacíos y con `page` reseteado a 1 (elegir un filtro vuelve a la
 * primera página) salvo que el patch traiga su propio `page`.
 *
 * `page: undefined` en el patch limpia el param sin forzar page 1 — es la
 * base que necesita `Pagination.astro` para agregar `&page=N`.
 *
 * `sort=relevancia` no se serializa: es el default (URL limpia).
 */
export function buildCatalogHref(
  base: string,
  current: CatalogFilters,
  patch: Partial<CatalogFilters>,
): string {
  const merged: CatalogFilters = { ...current, ...patch };
  if (!('page' in patch)) merged.page = DEFAULT_PAGE;

  const params = new URLSearchParams();
  if (merged.category) params.set('category', merged.category);
  if (merged.color) params.set('color', merged.color);
  if (merged.minPriceCents !== undefined) params.set('minPrice', String(merged.minPriceCents / 100));
  if (merged.maxPriceCents !== undefined) params.set('maxPrice', String(merged.maxPriceCents / 100));
  if (merged.sort && merged.sort !== 'relevancia') params.set('sort', merged.sort);
  if (merged.page !== undefined) params.set('page', String(merged.page));

  const query = params.toString();
  if (!query) return base;
  return `${base}${base.includes('?') ? '&' : '?'}${query}`;
}
