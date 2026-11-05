/**
 * ProductPurchaseBar — barra de compra del PDP genérico.
 *
 * Fila `[ Cantidad ▾ ] [ Añadir al carrito ] [ Comprar ya ]` con la
 * cantidad acotada al stock de la variante seleccionada. A diferencia
 * de `AddToCart` (Panda), NO renderiza selector de acabado: el PDP ya
 * muestra los swatches en su dl "Acabado" y duplicarlos rompería el
 * flujo `?finish=`.
 *
 * Labels por props con fallback español (mismo patrón que `AddToCart`):
 * el componente no importa el diccionario i18n.
 */
import type { ChangeEvent, ReactElement } from 'react';
import { useEffect, useRef, useState } from 'react';
import { clampToStock, quantityOptions } from '../../../lib/purchase-quantity';
import { addToCart } from '../../../stores/cart';

export interface PurchaseBarLabels {
  quantity: string;
  addToCart: string;
  buyNow: string;
  outOfStock: string;
  added: string;
}

interface ProductPurchaseBarProps {
  productSlug: string;
  name: string;
  imageUrl: string;
  priceCents: number;
  currency: string;
  /** Acabado elegido — distingue líneas de la misma pieza en el carrito. */
  variantLabel?: string;
  /** Unidades disponibles de la variante seleccionada. */
  stock: number;
  /** Checkout locale-prefixed, resuelto en el call site. */
  buyNowHref: string;
  labels?: Partial<PurchaseBarLabels>;
}

const ADDED_FEEDBACK_MS = 1500;

const DEFAULT_LABELS: PurchaseBarLabels = {
  quantity: 'Cantidad',
  addToCart: 'Añadir al carrito',
  buyNow: 'Comprar ya',
  outOfStock: 'Agotado',
  added: 'Agregado',
};

export default function ProductPurchaseBar({
  productSlug,
  name,
  imageUrl,
  priceCents,
  currency,
  variantLabel,
  stock,
  buyNowHref,
  labels,
}: ProductPurchaseBarProps): ReactElement {
  const resolved: PurchaseBarLabels = { ...DEFAULT_LABELS, ...labels };
  const isOutOfStock = stock <= 0;
  const options = quantityOptions(stock);
  const [quantity, setQuantity] = useState<number>(options[0] ?? 1);
  const [added, setAdded] = useState(false);
  const feedbackTimer = useRef<number | undefined>(undefined);

  useEffect(() => {
    return () => {
      window.clearTimeout(feedbackTimer.current);
    };
  }, []);

  function handleQuantityChange(event: ChangeEvent<HTMLSelectElement>): void {
    setQuantity(clampToStock(Number.parseInt(event.target.value, 10) || 1, stock));
  }

  function handleAdd(): void {
    if (isOutOfStock) return;
    addToCart(
      { productSlug, name, imageUrl, priceCents, currency, variantLabel },
      clampToStock(quantity, stock),
    );
    setAdded(true);
    window.clearTimeout(feedbackTimer.current);
    feedbackTimer.current = window.setTimeout(() => {
      setAdded(false);
    }, ADDED_FEEDBACK_MS);
  }

  function handleBuyNow(): void {
    if (isOutOfStock) return;
    addToCart(
      { productSlug, name, imageUrl, priceCents, currency, variantLabel },
      clampToStock(quantity, stock),
    );
    window.location.assign(buyNowHref);
  }

  return (
    <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
      <label className="flex flex-col gap-1.5 text-sm font-semibold text-slate-900">
        {resolved.quantity}
        <select
          name="quantity"
          value={quantity}
          disabled={isOutOfStock}
          onChange={handleQuantityChange}
          className="h-12 w-24 rounded-xl border border-slate-200 bg-white px-3 text-base font-normal tabular-nums text-slate-900 transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
        >
          {options.map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </select>
      </label>

      <button
        type="button"
        disabled={isOutOfStock}
        onClick={handleAdd}
        className="inline-flex h-12 flex-1 items-center justify-center rounded-full bg-slate-900 px-6 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500"
      >
        {added ? resolved.added : resolved.addToCart}
      </button>

      <button
        type="button"
        disabled={isOutOfStock}
        onClick={handleBuyNow}
        className="inline-flex h-12 flex-1 items-center justify-center rounded-full border border-slate-900 bg-white px-6 text-sm font-semibold text-slate-900 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-500"
      >
        {resolved.buyNow}
      </button>

      {isOutOfStock && (
        <span className="inline-flex h-12 items-center justify-center rounded-full bg-slate-200 px-6 text-sm font-semibold text-slate-500">
          {resolved.outOfStock}
        </span>
      )}

      <span role="status" aria-live="polite" className="sr-only">
        {added ? resolved.added : ''}
      </span>
    </div>
  );
}
