/**
 * CheckoutOrderSummary — isla del resumen lateral del checkout.
 *
 * Lee el cart store (`stores/cart.ts`) con `useCart()` + `getCartTotals()`
 * y renderiza items + desglose (subtotal · envío · impuesto · total) + CTA.
 * Paralelo a `CheckoutSummaryV3.astro` en markup, pero el cálculo deja de
 * venir pre-computado del SSR: el store vive en el browser.
 */
import type { ReactElement } from 'react';
import { useCart, getCartTotals } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';

export interface CheckoutOrderSummaryLabels {
  summary: string;
  emptyItems: string;
  subtotal: string;
  shippingCost: string;
  shippingFree: string;
  tax: string;
  total: string;
  cta: string;
  termsHint: string;
}

interface CheckoutOrderSummaryProps {
  labels: CheckoutOrderSummaryLabels;
  ctaHref: string;
}

export default function CheckoutOrderSummary({
  labels,
  ctaHref,
}: CheckoutOrderSummaryProps): ReactElement {
  const { lines } = useCart();
  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';

  const shippingDisplay =
    totals.shippingCents === null
      ? '—'
      : totals.shippingCents === 0
        ? labels.shippingFree
        : formatCurrency(totals.shippingCents, currencyCode);
  const shippingIsFree = totals.shippingCents === 0;

  const totalFormatted = formatCurrency(totals.totalCents, currencyCode);
  const totalMajor = totalFormatted.replace(/,[\d]{2}$/, '');
  const totalMinor = (totalFormatted.match(/,(\d{2})$/) ?? [null, ''])[1] ?? '';

  return (
    <aside
      aria-label={labels.summary}
      className="flex flex-col gap-6 self-start rounded-2xl border border-slate-200 bg-white p-7 shadow-sm lg:sticky lg:top-24"
    >
      <h2 className="text-xl font-bold text-slate-900">{labels.summary}</h2>

      {lines.length > 0 ? (
        <ul
          className="flex list-none flex-col gap-4 border-b border-slate-200 pb-6"
          role="list"
        >
          {lines.map((line) => {
            const lineTotal = line.priceCents * line.quantity;
            return (
              <li
                key={`${line.productSlug}:${line.variantLabel ?? ''}`}
                className="flex items-center gap-3"
              >
                <span className="relative inline-flex h-14 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-slate-100">
                  <img
                    src={line.imageUrl}
                    alt={line.name}
                    loading="lazy"
                    decoding="async"
                    className="h-full w-full object-cover"
                  />
                  <span className="absolute -right-1.5 -top-1.5 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[var(--primary,#2D9D8E)] px-1 text-[11px] font-bold text-white">
                    {line.quantity}
                  </span>
                </span>
                <span className="flex-1 text-[14px] font-medium leading-snug text-slate-700">
                  {line.name}
                </span>
                <span className="text-[14px] font-bold tabular-nums text-slate-900">
                  {formatCurrency(lineTotal, line.currency)}
                </span>
              </li>
            );
          })}
        </ul>
      ) : (
        <p className="border-b border-slate-200 pb-6 text-sm text-slate-500">
          {labels.emptyItems}
        </p>
      )}

      <dl className="flex flex-col gap-3 text-[14px]">
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">{labels.subtotal}</dt>
          <dd className="font-semibold tabular-nums text-slate-900">
            {formatCurrency(totals.subtotalCents, currencyCode)}
          </dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">{labels.shippingCost}</dt>
          <dd
            className={
              shippingIsFree
                ? 'font-semibold tabular-nums text-[var(--primary,#2D9D8E)]'
                : 'font-semibold tabular-nums text-slate-900'
            }
          >
            {shippingDisplay}
          </dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">{labels.tax}</dt>
          <dd className="font-semibold tabular-nums text-slate-900">
            {formatCurrency(totals.taxCents, currencyCode)}
          </dd>
        </div>
      </dl>

      <div className="my-2 h-px bg-slate-200" aria-hidden="true"></div>

      <div className="flex items-baseline justify-between">
        <span className="text-[15px] font-bold text-slate-900">{labels.total}</span>
        <span className="text-3xl font-bold tabular-nums text-slate-900">
          {totalMajor}
          {totalMinor !== '' && (
            <span className="text-base font-medium text-slate-400">,{totalMinor}</span>
          )}
        </span>
      </div>

      <a
        href={ctaHref}
        className="mt-2 inline-flex h-12 w-full items-center justify-center rounded-lg bg-[var(--primary,#2D9D8E)] px-6 py-3 text-[15px] font-semibold text-white transition hover:bg-[var(--primary-hover,#258777)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#2D9D8E)] focus-visible:ring-offset-2"
      >
        {labels.cta}
      </a>

      <p className="text-center text-[12px] text-slate-400">{labels.termsHint}</p>
    </aside>
  );
}
