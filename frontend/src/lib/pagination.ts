/**
 * Helpers puros de paginación del catálogo — extraídos del frontmatter de
 * `pages/[locale]/products/index.astro` para poder testearlos en isolation.
 *
 * `?page=` viene del usuario (link compartido, crawler, typo, URL vieja):
 * nunca llega como número confiable. Estos helpers son la única
 * frontera donde se sanea, para que el resto de la página pueda asumir
 * `page >= 1` sin repetir guards.
 */

/**
 * La paginación es 1-indexed: `?page=1` es la primera página, igual que en
 * el `LengthAwarePaginator` del backend.
 */
export const FIRST_PAGE = 1;

/**
 * Meta de paginación que emite `GET /api/v1/storefront/products`.
 *
 * El shape es idéntico al de `GET /api/v1/storefront/products/search`
 * (salvo la clave `query`, que sólo existe en búsqueda): es el backend la
 * fuente de verdad de `total` y `last_page`, nunca la longitud del array
 * de la página.
 */
export interface StorefrontPaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

/**
 * Convierte el `?page=` crudo de la URL en un número de página usable.
 *
 * Cae a `FIRST_PAGE` ante `null`, string vacío, no-entero (`abc`, `1.5`,
 * `1e3`, `-3`), cero y valores fuera del rango seguro de JS. La
 * validación va en el backend también (`integer|min:1`), pero duplicarla
 * acá evita que un `NaN` viaje al query string de la API.
 */
export function parsePageParam(rawPage: string | null): number {
  if (rawPage === null) {
    return FIRST_PAGE;
  }

  const trimmed = rawPage.trim();
  // `^\d+$` (y no `Number()`) porque "1e3", "0x10" y "  " parsean a
  // números en JS pero no son páginas: son entradas malformadas.
  if (!/^\d+$/.test(trimmed)) {
    return FIRST_PAGE;
  }

  const parsed = Number(trimmed);

  return Number.isSafeInteger(parsed) && parsed >= FIRST_PAGE ? parsed : FIRST_PAGE;
}

/**
 * Limita la página solicitada al rango realmente existente `[1, lastPage]`.
 *
 * Por qué clamp y no 404: el catálogo es navegación pública. Un
 * `?page=9999` llega de links viejos, crawlers o manos; el contenido de
 * la última página EXISTE y responder error ahí convierte un link
 * compartible en página rota. El backend devuelve `data: []` +
 * `meta.last_page` real, así que el clamp se puede resolver en SSR sin
 * adivinar.
 *
 * `lastPage` defensivo: si el backend no manda un número usable (0,
 * negativo, `NaN`, ausente), se trata como catálogo de una sola página.
 */
export function clampPage(page: number, lastPage: number): number {
  const isUsableLastPage = Number.isFinite(lastPage) && lastPage >= FIRST_PAGE;
  const safeLastPage = isUsableLastPage ? Math.floor(lastPage) : FIRST_PAGE;

  return Math.min(Math.max(page, FIRST_PAGE), safeLastPage);
}