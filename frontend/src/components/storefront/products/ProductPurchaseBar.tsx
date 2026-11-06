/**
 * ProductPurchaseBar — barra de compra del PDP genérico.
 *
 * Fila `[ − Cantidad + ] [ Añadir al carrito ] [ Comprar ya ]` con la
 * cantidad acotada al stock de la variante seleccionada. El stepper
 * usa los botones − / + reales de `ProductQuantitySelector` (patrón
 * Baymard) en vez del dropdown nativo de cantidad. A diferencia de
 * `AddToCart` (Panda), NO renderiza selector de acabado: el PDP ya
 * muestra los swatches en su dl "Acabado" y duplicarlos rompería el
 * flujo `?finish=`.
 *
 * Labels por props con fallback español (mismo patrón que `AddToCart`):
 * el componente no importa el diccionario i18n.
 */
import type { ReactElement } from 'react';
import { useEffect, useRef, useState } from 'react';
import { clampToStock, quantityStepperState } from '../../../lib/purchase-quantity';
import { addToCart } from '../../../stores/cart';

export interface PurchaseBarLabels {
  quantity: string;
  decrement: string;
  increment: string;
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
  decrement: 'Disminuir cantidad',
  increment: 'Aumentar cantidad',
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
  const [quantity, setQuantity] = useState<number>(1);
  const [added, setAdded] = useState(false);
  const feedbackTimer = useRef<number | undefined>(undefined);

  useEffect(() => {
    return () => {
      window.clearTimeout(feedbackTimer.current);
    };
  }, []);

  const stepState = quantityStepperState(quantity, 0, stock);

  function handleDecrement(): void {
    setQuantity(quantityStepperState(quantity, -1, stock).value);
  }

  function handleIncrement(): void {
    setQuantity(quantityStepperState(quantity, 1, stock).value);
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
      <div className="flex flex-col gap-1.5">
        <span className="micro-label">{resolved.quantity}</span>
        <div
          className="pressable inline-flex w-fit items-center overflow-hidden rounded-full border border-[var(--line,#d6d3d1)] bg-white"
          role="group"
          aria-label={resolved.quantity}
        >
          <button
            type="button"
            aria-label={resolved.decrement}
            disabled={stepState.decrementDisabled}
            onClick={handleDecrement}
            className="flex h-10 w-10 items-center justify-center text-[var(--ink-soft,#57534e)] transition hover:bg-[var(--primary-soft,#e6f2f0)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#2D9D8E)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <span aria-hidden="true">−</span>
          </button>
          <span
            className="flex h-10 min-w-[3rem] items-center justify-center border-x border-[var(--line,#d6d3d1)] px-3 text-sm font-semibold tabular-nums text-[var(--ink,#1c1917)]"
            aria-live="polite"
          >
            {stepState.value}
          </span>
          <button
            type="button"
            aria-label={resolved.increment}
            disabled={stepState.incrementDisabled}
            onClick={handleIncrement}
            className="flex h-10 w-10 items-center justify-center text-[var(--ink-soft,#57534e)] transition hover:bg-[var(--primary-soft,#e6f2f0)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#2D9D8E)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <span aria-hidden="true">+</span>
          </button>
          <input type="hidden" name="quantity" value={stepState.value} />
        </div>
      </div>

      <button
        type="button"
        disabled={isOutOfStock}
        onClick={handleAdd}
        className="pressable inline-flex h-12 flex-1 items-center justify-center rounded-lg bg-[var(--primary,#2D9D8E)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--primary-hover,#258777)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#2D9D8E)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-[var(--paper-sunken,#ebe6dd)] disabled:text-[var(--ink-faint,#a8a29e)]"
      >
        {added ? resolved.added : resolved.addToCart}
      </button>

      <button
        type="button"
        disabled={isOutOfStock}
        onClick={handleBuyNow}
        className="pressable inline-flex h-12 flex-1 items-center justify-center rounded-lg border border-[var(--line,#d6d3d1)] bg-transparent px-6 text-sm font-semibold text-[var(--ink,#1c1917)] transition hover:bg-[var(--primary-soft,#e6f2f0)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#2D9D8E)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:border-[var(--edge,#e7e5e1)] disabled:text-[var(--ink-faint,#a8a29e)]"
      >
        {resolved.buyNow}
      </button>

      {isOutOfStock && (
        <span className="inline-flex h-12 items-center justify-center rounded-lg bg-[var(--paper-sunken,#ebe6dd)] px-6 text-sm font-semibold text-[var(--ink-faint,#a8a29e)]">
          {resolved.outOfStock}
        </span>
      )}

      <span role="status" aria-live="polite" className="sr-only">
        {added ? resolved.added : ''}
      </span>
    </div>
  );
}
