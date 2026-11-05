/**
 * CartView — isla que compone la vista de carrito.
 *
 * Lee el cart store (`stores/cart.ts`, localStorage) y compone: empty
 * state, lista de líneas (superficie continua), resumen tipo recibo y
 * barra sticky inferior en mobile. Los labels llegan resueltos por
 * props desde `getServerDictionary` — este componente no importa el
 * diccionario i18n.
 *
 * SSR: la primera pintada puede ser vacía (server snapshot) y luego
 * re-renderiza con las líneas de localStorage; la UI lo tolera.
 */
import type { ReactElement, ReactNode } from 'react';
import { useCart, getCartTotals } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';
import CartLineRow from './CartLineRow';
import CartSummaryBlock from './CartSummaryBlock';

export interface CartViewLabels {
  remove: string;
  decrement: string;
  increment: string;
  qty: string;
  subtotal: string;
  discount: string;
  shipping: string;
  shippingFree: string;
  tax: string;
  total: string;
  checkout: string;
  empty: string;
  emptyHint: string;
  emptyCta: string;
  continueShopping: string;
  /** Plantilla con `{count}` (misma convención que CouponInput). */
  itemsCount: string;
  /** Plantilla con `{amount}` para el medidor de envío gratis. */
  freeShippingProgress: string;
  freeShippingReached: string;
}

interface CartViewProps {
  locale: string;
  labels: CartViewLabels;
  checkoutHref: string;
  productsHref: string;
  discountCents?: number;
  /** TrustSignalsCompact vía slot de Astro: HTML estático sin hidratar. */
  children?: ReactNode;
}

const CTA_CLASSES =
  'inline-flex items-center justify-center rounded-lg bg-[var(--primary,#475569)] px-5 text-sm font-semibold text-white transition-transform hover:bg-[var(--primary-hover,#334155)] active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 motion-reduce:transition-none motion-reduce:active:scale-100';

function CartEmptyState({
  labels,
  productsHref,
}: {
  labels: CartViewLabels;
  productsHref: string;
}): ReactElement {
  return (
    <section className="flex flex-col items-center gap-4 rounded-2xl border border-[var(--line,#e7e5e4)] bg-white px-6 py-14 text-center shadow-[0_0_0_1px_rgba(0,0,0,0.05),0_1px_2px_-1px_rgba(0,0,0,0.06),0_2px_4px_rgba(0,0,0,0.04)]">
      <span className="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--primary-soft,#e6f7f4)] text-[var(--primary,#475569)]">
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
          className="h-7 w-7"
        >
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
          <path d="M3 6h18" />
          <path d="M16 10a4 4 0 0 1-8 0" />
        </svg>
      </span>
      <h2 className="font-display text-[22px] font-medium tracking-tight text-[var(--ink,#1c1917)]">
        {labels.empty}
      </h2>
      <p className="max-w-xs text-sm text-[var(--ink,#1c1917)]/60">{labels.emptyHint}</p>
      <a href={productsHref} className={`h-11 ${CTA_CLASSES}`}>
        {labels.emptyCta}
      </a>
    </section>
  );
}

function CartMobileBar({
  labels,
  checkoutHref,
  totalCents,
  currencyCode,
}: {
  labels: CartViewLabels;
  checkoutHref: string;
  totalCents: number;
  currencyCode: string;
}): ReactElement {
  return (
    <div className="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-4 border-t border-[var(--line,#e7e5e4)] bg-[var(--paper,#f3efe8)]/95 px-4 py-3 backdrop-blur lg:hidden">
      <div className="flex flex-col">
        <span className="text-[11px] font-medium uppercase tracking-wide text-[var(--ink,#1c1917)]/60">
          {labels.total}
        </span>
        <span className="text-base font-semibold tabular-nums text-[var(--ink,#1c1917)]">
          {formatCurrency(totalCents, currencyCode)}
        </span>
      </div>
      <a href={checkoutHref} className={`h-10 ${CTA_CLASSES}`}>
        {labels.checkout}
      </a>
    </div>
  );
}

export default function CartView({
  locale,
  labels,
  checkoutHref,
  productsHref,
  discountCents = 0,
  children,
}: CartViewProps): ReactElement {
  const { lines } = useCart();

  if (lines.length === 0) {
    return <CartEmptyState labels={labels} productsHref={productsHref} />;
  }

  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';
  const totalCents = Math.max(0, totals.totalCents - discountCents);

  return (
    <>
      <p className="mt-2 text-sm text-[var(--ink,#1c1917)]/60">
        {labels.itemsCount.replace('{count}', String(totals.itemCount))}
      </p>

      <div className="mt-6 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <ul
          data-reveal-stagger
          className="flex list-none flex-col overflow-hidden rounded-2xl border border-[var(--line,#e7e5e4)] bg-white p-0 shadow-[0_0_0_1px_rgba(0,0,0,0.05),0_1px_2px_-1px_rgba(0,0,0,0.06),0_2px_4px_rgba(0,0,0,0.04)]"
        >
          {lines.map((line, index) => (
            <li
              key={`${line.productSlug}:${line.variantLabel ?? ''}`}
              className={index > 0 ? 'list-none border-t border-[var(--line,#e7e5e4)]/60' : 'list-none'}
            >
              <CartLineRow line={line} locale={locale} labels={labels} />
            </li>
          ))}
        </ul>

        <CartSummaryBlock
          lines={lines}
          labels={labels}
          checkoutHref={checkoutHref}
          productsHref={productsHref}
          discountCents={discountCents}
        >
          {children}
        </CartSummaryBlock>
      </div>

      <CartMobileBar
        labels={labels}
        checkoutHref={checkoutHref}
        totalCents={totalCents}
        currencyCode={currencyCode}
      />
    </>
  );
}
