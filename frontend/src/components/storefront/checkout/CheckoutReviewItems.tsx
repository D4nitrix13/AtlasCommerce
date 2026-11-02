/**
 * CheckoutReviewItems — isla del listado de líneas en el step de revisión.
 *
 * Lee el cart store (`stores/cart.ts`) con `useCart()` y renderiza las
 * líneas dentro del fieldset de review. Lista de sólo lectura: los
 * controles de cantidad viven en `CartView`, y los `<input hidden qty-N>`
 * que esta isla reemplaza se colaban en el GET a `?step=success`.
 */
import type { ReactElement } from 'react';
import { useCart, getCartTotals } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';

export interface CheckoutReviewItemsLabels {
  qty: string;
  subtotal: string;
}

interface CheckoutReviewItemsProps {
  labels: CheckoutReviewItemsLabels;
}

export default function CheckoutReviewItems({
  labels,
}: CheckoutReviewItemsProps): ReactElement {
  const { lines } = useCart();
  const totals = getCartTotals(lines);

  return (
    <div className="flex flex-col gap-4">
      <ul className="flex list-none flex-col gap-3 p-0" role="list">
        {lines.map((line) => (
          <li
            key={`${line.productSlug}:${line.variantLabel ?? ''}`}
            className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3"
          >
            <span className="relative h-14 w-12 shrink-0 overflow-hidden rounded-md bg-white">
              <img
                src={line.imageUrl}
                alt={line.name}
                loading="lazy"
                decoding="async"
                className="h-full w-full object-cover"
              />
            </span>
            <div className="flex flex-1 flex-col gap-0.5">
              <span className="text-[14px] font-semibold text-slate-900">{line.name}</span>
              {line.variantLabel !== undefined && line.variantLabel !== '' && (
                <span className="text-[12px] text-slate-500">{line.variantLabel}</span>
              )}
              <span className="text-[12px] text-slate-500">
                {labels.qty}: {line.quantity}
              </span>
            </div>
            <span className="min-w-[5rem] text-right text-[14px] font-bold tabular-nums text-slate-900">
              {formatCurrency(line.priceCents * line.quantity, line.currency)}
            </span>
          </li>
        ))}
      </ul>

      <div className="flex items-center justify-between border-t border-slate-200 pt-4 text-sm">
        <span className="font-semibold text-slate-700">{labels.subtotal}</span>
        <span className="font-bold tabular-nums text-slate-900">
          {formatCurrency(totals.subtotalCents, lines[0]?.currency ?? 'USD')}
        </span>
      </div>
    </div>
  );
}
