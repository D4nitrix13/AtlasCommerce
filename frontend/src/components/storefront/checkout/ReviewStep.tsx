/**
 * ReviewStep — paso 3 (revisión) del checkout.
 *
 * Filas compactas de items (lenguaje visual de CartLineRow) + accesos
 * "Editar" que vuelven a cada paso con los valores preservados.
 */
import type { ReactElement } from 'react';

import { useCart, getCartTotals } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';
import type { CheckoutStepId } from './checkoutSchemas';

export interface ReviewStepLabels {
  heading: string;
  hint: string;
  edit: string;
  qty: string;
  subtotal: string;
  emptyItems: string;
  shippingStep: string;
  paymentStep: string;
}

interface ReviewStepProps {
  labels: ReviewStepLabels;
  onEditStep: (step: CheckoutStepId) => void;
}

export default function ReviewStep({ labels, onEditStep }: ReviewStepProps): ReactElement {
  const { lines } = useCart();
  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h2 className="text-lg font-semibold text-[var(--ink,#1c1917)]">{labels.heading}</h2>
        <p className="mt-1 text-sm text-[var(--ink,#1c1917)]/60">{labels.hint}</p>
      </div>

      <div className="flex flex-col divide-y divide-[var(--line,#e7e5e4)]/60">
        {(
          [
            { step: 'shipping' as const, label: labels.shippingStep },
            { step: 'payment' as const, label: labels.paymentStep },
          ]
        ).map((row) => (
          <div key={row.step} className="flex items-center justify-between gap-4 py-3">
            <span className="text-sm font-medium text-[var(--ink,#1c1917)]">{row.label}</span>
            <button
              type="button"
              aria-label={`${labels.edit}: ${row.label}`}
              onClick={(): void => onEditStep(row.step)}
              className="text-xs font-semibold text-[var(--primary,#475569)] underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)]"
            >
              {labels.edit}
            </button>
          </div>
        ))}
      </div>

      {lines.length > 0 ? (
        <ul className="flex list-none flex-col gap-0 divide-y divide-[var(--line,#e7e5e4)]/60 rounded-2xl border border-[var(--line,#e7e5e4)] bg-white p-0">
          {lines.map((line) => {
            const lineTotal = line.priceCents * line.quantity;
            return (
              <li
                key={`${line.productSlug}:${line.variantLabel ?? ''}`}
                className="flex items-center gap-3 px-4 py-3"
              >
                <img
                  src={line.imageUrl}
                  alt={line.name}
                  loading="lazy"
                  decoding="async"
                  className="h-16 w-14 shrink-0 rounded-lg object-cover shadow-[inset_0_0_0_1px_rgba(0,0,0,0.1)]"
                />
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                  <span className="truncate text-[15px] font-semibold text-[var(--ink,#1c1917)]">
                    {line.name}
                  </span>
                  <div className="flex items-center gap-2">
                    {line.variantLabel !== undefined && line.variantLabel !== '' && (
                      <span className="rounded-full bg-[var(--primary-soft,#e6f7f4)] px-2 py-0.5 text-xs text-[var(--ink,#1c1917)]/80">
                        {line.variantLabel}
                      </span>
                    )}
                    <span className="text-xs text-[var(--ink,#1c1917)]/60">
                      {labels.qty}: {line.quantity}
                    </span>
                  </div>
                </div>
                <span className="text-[15px] font-semibold tabular-nums text-[var(--ink,#1c1917)]">
                  {formatCurrency(lineTotal, line.currency)}
                </span>
              </li>
            );
          })}
        </ul>
      ) : (
        <p className="text-sm text-[var(--ink,#1c1917)]/60">{labels.emptyItems}</p>
      )}

      <div className="flex items-center justify-between gap-4">
        <span className="text-xs font-medium uppercase tracking-wide text-[var(--ink,#1c1917)]/60">
          {labels.subtotal}
        </span>
        <span className="text-sm font-medium tabular-nums text-[var(--ink,#1c1917)]">
          {formatCurrency(totals.subtotalCents, currencyCode)}
        </span>
      </div>
    </div>
  );
}
