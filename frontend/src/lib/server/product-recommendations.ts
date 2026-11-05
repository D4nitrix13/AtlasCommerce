/**
 * product-recommendations — fetch SSR de los rieles de recomendación
 * del PDP contra los endpoints nuevos del storefront:
 *
 *   - `POST /storefront/recently-viewed`   registra la visita (204).
 *   - `GET  /storefront/recently-viewed`   historial newest-first.
 *   - `GET  /storefront/products/{slug}/relations`  rieles curados.
 *
 * Mismo patrón que `lib/server/account.ts`: fetch contra `apiOrigin`
 * (resuelve Caddy/nginx según stage) con los headers SSR del caller
 * (Accept, Host del tenant, Cookie de sesión y Origin — sin esos dos
 * últimos Sanctum no resuelve la identidad del usuario).
 *
 * Ninguna función acá rompe el render del PDP: `recordProductView`
 * traga errores a propósito y `fetchRecommendationRails` devuelve
 * listas vacías (o marca el fallback de "Visto recientemente") cuando
 * un endpoint falla o se agota el timeout.
 */
import type { Product } from '../../types/catalog';
import { mapBackendToProduct, type BackendProduct } from '../backend-product-mapper';

/** Shape de `GET /storefront/products/{slug}/relations`. */
interface ProductRelationsPayload {
  bought_together: BackendProduct[];
  you_may_also_like: BackendProduct[];
}

/** Shape de `GET /storefront/recently-viewed`. */
interface RecentlyViewedPayload {
  data: BackendProduct[];
}

export const RECENTLY_VIEWED_LIMIT = 8;
const DEFAULT_TIMEOUT_MS = 5_000;

export interface RecommendationFetchOptions {
  /** Origen del API (Caddy en prod, nginx interno en dev). */
  apiOrigin: string;
  /** Headers SSR: Accept, Host del tenant, Cookie de sesión, Origin. */
  headers: Record<string, string>;
  timeoutMs?: number;
}

export interface RecommendationRailsOptions extends RecommendationFetchOptions {
  /** Slug del producto actual (para armar la URL de relations). */
  slug: string;
  /** Id del producto actual — se excluye de los tres rieles. */
  currentProductId: number;
  /** Token huésped (`rv_guest`) para identificar al visitante anónimo. */
  guestToken: string;
}

export interface RecommendationRails {
  /** Accessories curados de "Frecuentemente Comprados Juntos". */
  boughtTogether: Product[];
  /** Curados de "También Te Puede Gustar" (orden `position`). */
  youMayAlsoLike: Product[];
  /** "Visto recientemente" newest-first, sin el producto actual. */
  recentlyViewed: Product[];
  /** true cuando el GET de vistos falló → el rail cae a localStorage. */
  recentlyViewedFallback: boolean;
}

function buildTimeout(timeoutMs: number): { signal: AbortSignal; clear: () => void } {
  const controller = new AbortController();
  const handle = setTimeout((): void => controller.abort(), timeoutMs);
  return { signal: controller.signal, clear: (): void => clearTimeout(handle) };
}

async function fetchJson<T>(
  url: URL,
  headers: Record<string, string>,
  timeoutMs: number,
  init?: RequestInit,
): Promise<T | null> {
  const timeout = buildTimeout(timeoutMs);
  try {
    const response = await fetch(url, {
      ...init,
      headers,
      signal: timeout.signal,
    });
    if (!response.ok) return null;
    return (await response.json()) as T;
  } catch {
    return null;
  } finally {
    timeout.clear();
  }
}

/**
 * Registra la visita del producto actual (best-effort, nunca lanza).
 * Con sesión Sanctum el backend usa el usuario; sin ella, `guest_token`.
 */
export async function recordProductView(
  options: RecommendationFetchOptions & { productId: number; guestToken: string },
): Promise<void> {
  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const url = new URL('/api/v1/storefront/recently-viewed', options.apiOrigin);
  const timeout = buildTimeout(timeoutMs);
  try {
    await fetch(url, {
      method: 'POST',
      headers: { ...options.headers, 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: options.productId, guest_token: options.guestToken }),
      signal: timeout.signal,
    });
  } catch {
    // La vista no se registra (timeout / red caída): el PDP renderiza igual.
  } finally {
    timeout.clear();
  }
}

/**
 * Fetch de los tres rieles en paralelo (relations + vistos). El
 * producto actual se excluye de todas las listas: un rail no se
 * recomienda a sí mismo.
 */
export async function fetchRecommendationRails(
  options: RecommendationRailsOptions,
): Promise<RecommendationRails> {
  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const { apiOrigin, headers, slug, currentProductId, guestToken } = options;

  const relationsUrl = new URL(
    `/api/v1/storefront/products/${encodeURIComponent(slug)}/relations`,
    apiOrigin,
  );
  const viewedUrl = new URL('/api/v1/storefront/recently-viewed', apiOrigin);
  viewedUrl.searchParams.set('guest_token', guestToken);
  viewedUrl.searchParams.set('exclude', slug);
  viewedUrl.searchParams.set('limit', String(RECENTLY_VIEWED_LIMIT));

  const [relations, viewed] = await Promise.all([
    fetchJson<ProductRelationsPayload>(relationsUrl, headers, timeoutMs),
    fetchJson<RecentlyViewedPayload>(viewedUrl, headers, timeoutMs),
  ]);

  const toProducts = (rows: readonly BackendProduct[] | undefined): Product[] =>
    (rows ?? [])
      .filter((row) => row.id !== currentProductId)
      .map((row) => mapBackendToProduct(row));

  return {
    boughtTogether: toProducts(relations?.bought_together),
    youMayAlsoLike: toProducts(relations?.you_may_also_like),
    recentlyViewed: toProducts(viewed?.data),
    recentlyViewedFallback: viewed === null,
  };
}
