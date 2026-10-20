import { useEffect, useState } from 'react';
import { formatCurrency } from '../../../lib/formatters';
import type { Product } from '../../../types/catalog';

interface StorefrontProductsListProps {
  apiPath: string;
  /** Mensaje "cargando..." que se muestra mientras el fetch está en vuelo. */
  loadingLabel: string;
  /** Mensaje "no hay productos" cuando el fetch retorna array vacío. */
  emptyLabel: string;
  /** Locale activo (es|en) para formateo de currency. */
  locale: string;
}

/**
 * StorefrontProductsList — lista de productos fetched al CLIENTE (CSR).
 *
 * Por que CSR y no SSR:
 *   - SSR requiere PUBLIC_API_URL con URL absoluta accesible desde el
 *     container Astro (nginx sidecar, backend:9000, etc). En multi-tenant
 *     dev/prod con Docker Compose, esto es fragil: el hostname cambia entre
 *     environments y la URL se inlinea en build time, rompiendo el SSR
 *     cuando el dev usa un host y el prod otro.
 *   - CSR elimina ese acoplamiento: el cliente hace fetch a `/api/...` (path
 *     relativo), que Caddy resuelve al backend en cualquier environment.
 *   - SEO: Googlebot ejecuta JS, ve los productos hidratados. El HTML
 *     inicial muestra skeletons con la misma estructura visual.
 *   - A largo plazo: CSR + cache de cliente (stale-while-revalidate) da
 *     UX optima sin sacrificar SEO ni depender de hostname de container.
 *
 * Decisiones:
 *   - `useState<Product[]>` inicial = [] → renderiza skeleton
 *   - `useEffect` con cleanup (mounted flag) para evitar setState en unmount
 *   - Sort por defecto: created_at desc (mas nuevos primero)
 *   - Si backend retorna 0 productos: empty state (no error)
 *   - Si backend retorna error: error state con mensaje discreto
 */
export default function StorefrontProductsList({
  apiPath,
  loadingLabel,
  emptyLabel,
  locale,
}: StorefrontProductsListProps) {
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;
    setIsLoading(true);
    setError(null);

    fetch(apiPath, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data: Product[]) => {
        if (mounted) {
          setProducts(Array.isArray(data) ? data : []);
          setIsLoading(false);
        }
      })
      .catch((e: Error) => {
        if (mounted) {
          setError(e.message);
          setIsLoading(false);
        }
      });

    return () => {
      mounted = false;
    };
  }, [apiPath]);

  if (isLoading) {
    return (
      <ul class="grid list-none grid-cols-1 gap-4 p-0 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <li
            key={i}
            class="list-none rounded-2xl border border-slate-200 bg-slate-50 p-4"
            data-loading-skeleton="true"
          >
            <div class="aspect-[4/5] w-full animate-pulse rounded-xl bg-slate-200" />
            <div class="mt-3 h-4 w-2/3 animate-pulse rounded bg-slate-200" />
            <div class="mt-2 h-3 w-1/2 animate-pulse rounded bg-slate-200" />
          </li>
        ))}
      </ul>
    );
  }

  if (error !== null) {
    return (
      <div
        role="alert"
        class="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700"
      >
        {error}
      </div>
    );
  }

  if (products.length === 0) {
    return (
      <p class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
        {emptyLabel}
      </p>
    );
  }

  return (
    <ul
      data-products-loaded="true"
      class="grid list-none grid-cols-1 gap-4 p-0 sm:grid-cols-2 xl:grid-cols-4"
    >
      {products.map((product) => (
        <li key={product.id} class="list-none">
          <a
            href={`/${locale}/products/${product.slug}`}
            class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            data-product-slug={product.slug}
            data-product-id={product.id}
          >
            <div class="aspect-[4/5] w-full overflow-hidden rounded-xl bg-slate-50">
              <img
                src={product.imageUrl}
                alt={product.name}
                loading="lazy"
                class="h-full w-full object-cover"
                onError="this.onerror=null;this.parentElement?.classList.add('bg-slate-100');this.remove()"
              />
            </div>
            <h3 class="mt-3 text-base font-semibold text-slate-900">
              {product.name}
            </h3>
            {product.isExclusive && (
              <span class="mt-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                ★ Exclusive
              </span>
            )}
            <p class="mt-1 text-sm text-slate-500">
              {product.brand ? `${product.brand} · ` : ''}
              {formatCurrency(product.priceCents, locale)}
            </p>
          </a>
        </li>
      ))}
    </ul>
  );
}
