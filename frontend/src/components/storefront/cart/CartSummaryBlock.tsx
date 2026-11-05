/**
 * CartSummaryBlock — panel tipo recibo del carrito.
 *
 * Orden: medidor de envío gratis, filas de desglose, divisor punteado,
 * total display (Fraunces), CTA a checkout, "continuar comprando" y el
 * slot de children (trust signals).
 */
import type { ReactElement, ReactNode } from 'react';
import { getCartTotals, type CartLine } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';
import FreeShippingMeter from './FreeShippingMeter';

interface CartSummaryBlockProps {
  lines: CartLine[];
  labels: {
    subtotal: string;
    discount: string;
    shipping: string;
    shippingFree: string;
    tax: string;
    total: string;
    checkout: string;
    continueShopping: string;
    freeShippingProgress: string;
    freeShippingReached: string;
  };
  checkoutHref: string;
  productsHref: string;
  discountCents: number;
  children?: ReactNode;
}

interface BreakdownRowProps {
  label: string;
  value: string;
  isAccent?: boolean;
}

function BreakdownRow({ label, value, isAccent = false }: BreakdownRowProps): ReactElement {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="text-xs font-medium uppercase tracking-wide text-[var(--ink,#1c1917)]/60">
        {label}
      </dt>
      <dd
        className={
          isAccent
            ? 'text-sm font-medium tabular-nums text-[var(--primary,#475569)]'
            : 'text-sm font-medium tabular-nums text-[var(--ink,#1c1917)]'
        }
      >
        {value}
      </dd>
    </div>
  );
}

const CTA_CLASSES =
  'inline-flex h-12 w-full items-center justify-center rounded-lg bg-[var(--primary,#475569)] px-4 text-sm font-semibold text-white transition-transform hover:bg-[var(--primary-hover,#334155)] active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 motion-reduce:transition-none motion-reduce:active:scale-100';

export default function CartSummaryBlock({
  lines,
  labels,
  checkoutHref,
  productsHref,
  discountCents,
  children,
}: CartSummaryBlockProps): ReactElement {
  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';
  const isFreeShipping = totals.shippingCents === 0;
  const totalCents = Math.max(0, totals.totalCents - discountCents);

  return (
    <aside
      aria-label={labels.total}
      className="flex flex-col gap-4 rounded-2xl border border-[var(--line,#e7e5e4)] bg-white p-6 shadow-[0_0_0_1px_rgba(0,0,0,0.05),0_1px_2px_-1px_rgba(0,0,0,0.06),0_2px_4px_rgba(0,0,0,0.04)] sm:p-8 lg:sticky lg:top-24"
    >
      <FreeShippingMeter
        subtotalCents={totals.subtotalCents}
        currencyCode={currencyCode}
        progressLabel={labels.freeShippingProgress}
        reachedLabel={labels.freeShippingReached}
      />

      <dl className="flex flex-col gap-4">
        <BreakdownRow
          label={labels.subtotal}
          value={formatCurrency(totals.subtotalCents, currencyCode)}
        />
        {discountCents > 0 && (
          <BreakdownRow
            label={labels.discount}
            value={`−${formatCurrency(discountCents, currencyCode)}`}
            isAccent
          />
        )}
        <BreakdownRow
          label={labels.shipping}
          value={
            isFreeShipping
              ? labels.shippingFree
              : formatCurrency(totals.shippingCents ?? 0, currencyCode)
          }
          isAccent={isFreeShipping}
        />
        <BreakdownRow label={labels.tax} value={formatCurrency(totals.taxCents, currencyCode)} />
      </dl>

      <div className="flex items-baseline justify-between gap-4 border-t border-dashed border-[var(--line,#e7e5e4)] pt-4">
        <span className="text-xs font-medium uppercase tracking-wide text-[var(--ink,#1c1917)]/60">
          {labels.total}
        </span>
        <span className="font-display text-[32px] font-semibold leading-none tracking-tight tabular-nums text-[var(--ink,#1c1917)]">
          {formatCurrency(totalCents, currencyCode)}
        </span>
      </div>

      <a href={checkoutHref} className={CTA_CLASSES}>
        {labels.checkout}
      </a>

      <a
        href={productsHref}
        className="text-center text-sm text-[var(--ink,#1c1917)]/60 hover:text-[var(--ink,#1c1917)] focus:outline-none focus-visible:underline"
      >
        {labels.continueShopping}
      </a>

      {children}
    </aside>
  );
}
