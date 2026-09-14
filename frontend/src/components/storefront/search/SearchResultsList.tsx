import type { ReactElement } from 'react';
import type { StorefrontProduct } from './SearchPage.types';

/**
 * Lista de resultados — sólo se renderiza si hay resultados.
 *
 * El render de un ProductCard más rico (imagen, precio, etc.) queda
 * fuera del MVP — hoy mostramos nombre + descripción placeholder porque
 * el endpoint backend aún no expone esos campos en la respuesta de
 * search. Cuando el backend exponga `image_url`, `price_cents` y
 * `currency`, este componente los enchufará sin tocar SearchPage.
 */
interface Props {
  products: StorefrontProduct[];
  ariaLabel: string;
}

export default function SearchResultsList({ products, ariaLabel }: Props): ReactElement {
  return (
    <ul
      aria-label={ariaLabel}
      className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
    >
      {products.map((product) => (
        <li
          key={product.id}
          className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md"
        >
          <h2 className="line-clamp-2 text-sm font-semibold text-slate-900">
            {product.name}
          </h2>
          <p className="mt-1 line-clamp-2 text-xs text-slate-500">
            {product.description ?? '\u2014'}
          </p>
        </li>
      ))}
    </ul>
  );
}