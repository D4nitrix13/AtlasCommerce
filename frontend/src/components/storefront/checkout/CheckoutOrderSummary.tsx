/**
 * CheckoutOrderSummary — panel-recibo del checkout (informativo).
 *
 * Gemelo visual de `CartSummaryBlock`: medidor de envío gratis,
 * desglose, divisor punteado y total display (Fraunces). Sin CTA: el
 * botón de avance vive en el footer del form. Totales siempre vía
 * `getCartTotals` (paridad con el carrito). Chips de confianza al pie
 * con las claves `storefront.cart.v3.trust.*`.
 */
import type { ReactElement } from 'react';
import { useCart, getCartTotals } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';
import FreeShippingMeter from '../cart/FreeShippingMeter';

export interface CheckoutOrderSummaryLabels {
  summary: string;
  emptyItems: string;
  subtotal: string;
  shippingCost: string;
  shippingFree: string;
  tax: string;
  total: string;
  /** Plantilla con `{amount}` (convención CouponInput). */
  freeShippingProgress: string;
  freeShippingReached: string;
  trust: {
    shipping: string;
    guarantee: string;
    returns: string;
    secure: string;
  };
}

interface CheckoutOrderSummaryProps {
  labels: CheckoutOrderSummaryLabels;
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

const TRUST_ICONS = {
  shipping:
    '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
  guarantee:
    '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  returns: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
  secure:
    '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
} as const;

function TrustChips({ labels }: { labels: CheckoutOrderSummaryLabels['trust'] }): ReactElement {
  const chips = [
    { key: 'shipping' as const, label: labels.shipping },
    { key: 'guarantee' as const, label: labels.guarantee },
    { key: 'returns' as const, label: labels.returns },
    { key: 'secure' as const, label: labels.secure },
  ];
  return (
    <ul className="flex list-none flex-wrap items-center gap-2 p-0">
      {chips.map((chip) => (
        <li
          key={chip.key}
          className="inline-flex items-center gap-1.5 rounded-full border border-[var(--line,#e7e5e4)] bg-white px-2.5 py-1 text-[11px] text-[var(--ink,#1c1917)]/60"
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className="h-3.5 w-3.5 shrink-0 text-[var(--primary,#475569)]"
            dangerouslySetInnerHTML={{ __html: TRUST_ICONS[chip.key] }}
          />
          <span>{chip.label}</span>
        </li>
      ))}
    </ul>
  );
}

export default function CheckoutOrderSummary({
  labels,
}: CheckoutOrderSummaryProps): ReactElement {
  const { lines } = useCart();
  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';
  const isFreeShipping = totals.shippingCents === 0;

  return (
    <aside
      aria-label={labels.summary}
      className="flex flex-col gap-5 self-start rounded-2xl border border-[var(--line,#e7e5e4)] bg-white p-6 shadow-[0_0_0_1px_rgba(0,0,0,0.05),0_1px_2px_-1px_rgba(0,0,0,0.06),0_2px_4px_rgba(0,0,0,0.04)] sm:p-8 lg:sticky lg:top-24"
    >
      <h2 className="text-base font-semibold text-[var(--ink,#1c1917)]">{labels.summary}</h2>

      <FreeShippingMeter
        subtotalCents={totals.subtotalCents}
        currencyCode={currencyCode}
        progressLabel={labels.freeShippingProgress}
        reachedLabel={labels.freeShippingReached}
      />

      {lines.length === 0 && (
        <p className="text-sm text-[var(--ink,#1c1917)]/60">{labels.emptyItems}</p>
      )}

      <dl className="flex flex-col gap-4">
        <BreakdownRow
          label={labels.subtotal}
          value={formatCurrency(totals.subtotalCents, currencyCode)}
        />
        <BreakdownRow
          label={labels.shippingCost}
          value={
            isFreeShipping
              ? labels.shippingFree
              : totals.shippingCents === null
                ? '—'
                : formatCurrency(totals.shippingCents, currencyCode)
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
          {formatCurrency(totals.totalCents, currencyCode)}
        </span>
      </div>

      <TrustChips labels={labels.trust} />
    </aside>
  );
}
