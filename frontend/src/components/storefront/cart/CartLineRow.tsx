/**
 * CartLineRow — una fila de la lista del carrito (superficie continua).
 *
 * Imagen, nombre link, chip de variante, stepper pill 1..99, total de
 * línea tabular y quitar. Sin chrome de card: los divisores los pone
 * la lista (`<ul>`) que contiene las filas.
 */
import type { ReactElement } from 'react';
import {
  updateQuantity,
  removeFromCart,
  type CartLine,
} from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';

const MIN_LINE_QUANTITY = 1;
const MAX_LINE_QUANTITY = 99;

export interface CartLineRowLabels {
  remove: string;
  decrement: string;
  increment: string;
  qty: string;
}

interface CartLineRowProps {
  line: CartLine;
  locale: string;
  labels: CartLineRowLabels;
}

const BUTTON_FOCUS =
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2';
const PRESS =
  'transition-transform active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100';

export default function CartLineRow({ line, locale, labels }: CartLineRowProps): ReactElement {
  const detailHref = `/${locale}/products/${line.productSlug}`;
  const unitPriceFormatted = formatCurrency(line.priceCents, line.currency);
  const lineTotalFormatted = formatCurrency(line.priceCents * line.quantity, line.currency);
  const isDecrementDisabled = line.quantity <= MIN_LINE_QUANTITY;
  const isIncrementDisabled = line.quantity >= MAX_LINE_QUANTITY;
  const hasVariant = line.variantLabel !== undefined && line.variantLabel !== '';

  return (
    <article className="flex items-start gap-4 px-4 py-4 hover:bg-[var(--paper,#f3efe8)]/40 sm:items-center sm:px-6 sm:py-5">
      <a
        href={detailHref}
        aria-label={line.name}
        className={`relative block h-[88px] w-[88px] shrink-0 overflow-hidden rounded-lg bg-[var(--paper,#f3efe8)]/60 shadow-[inset_0_0_0_1px_rgba(0,0,0,0.1)] ${BUTTON_FOCUS}`}
      >
        <img
          src={line.imageUrl}
          alt={line.name}
          loading="lazy"
          decoding="async"
          className="h-full w-full object-cover"
        />
      </a>

      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <h3 className="text-[15px] font-semibold leading-snug text-[var(--ink,#1c1917)]">
          <a
            href={detailHref}
            className={`hover:text-[var(--primary,#475569)] focus:outline-none focus-visible:underline`}
          >
            {line.name}
          </a>
        </h3>

        {hasVariant && (
          <p className="w-fit rounded-full bg-[var(--primary-soft,#e6f7f4)] px-2 py-0.5 text-xs text-[var(--ink,#1c1917)]/80">
            {line.variantLabel}
          </p>
        )}

        <p className="text-xs tabular-nums text-[var(--ink,#1c1917)]/50">{unitPriceFormatted}</p>

        <div role="group" aria-label={labels.qty} className="mt-1">
          <div className="inline-flex items-center rounded-full border border-[var(--line,#e7e5e4)] bg-white">
            <button
              type="button"
              aria-label={labels.decrement}
              disabled={isDecrementDisabled}
              onClick={() => updateQuantity(line.productSlug, line.quantity - 1, line.variantLabel)}
              className={`flex h-8 w-8 items-center justify-center rounded-full text-[var(--ink,#1c1917)]/70 hover:bg-[var(--primary-soft,#e6f7f4)] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent ${PRESS} ${BUTTON_FOCUS}`}
            >
              <span aria-hidden="true">−</span>
            </button>
            <span
              aria-live="polite"
              className="min-w-[2.25rem] px-1 text-center text-sm font-medium tabular-nums text-[var(--ink,#1c1917)]"
            >
              {line.quantity}
            </span>
            <button
              type="button"
              aria-label={labels.increment}
              disabled={isIncrementDisabled}
              onClick={() => updateQuantity(line.productSlug, line.quantity + 1, line.variantLabel)}
              className={`flex h-8 w-8 items-center justify-center rounded-full text-[var(--ink,#1c1917)]/70 hover:bg-[var(--primary-soft,#e6f7f4)] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent ${PRESS} ${BUTTON_FOCUS}`}
            >
              <span aria-hidden="true">+</span>
            </button>
          </div>
        </div>
      </div>

      <div className="flex flex-col items-end gap-3">
        <span className="text-[15px] font-semibold tabular-nums text-[var(--ink,#1c1917)]">
          {lineTotalFormatted}
        </span>
        <button
          type="button"
          aria-label={labels.remove}
          onClick={() => removeFromCart(line.productSlug, line.variantLabel)}
          className={`flex h-8 w-8 items-center justify-center rounded-full text-[var(--ink,#1c1917)]/40 hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 ${PRESS}`}
        >
          <span aria-hidden="true" className="text-base leading-none">
            ×
          </span>
        </button>
      </div>
    </article>
  );
}
