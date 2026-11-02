/**
 * CartView — isla que renderiza el carrito completo (mockup Bonfire 08).
 *
 * Lee el cart store (`stores/cart.ts`, localStorage) y muestra el empty
 * state, las líneas con controles de cantidad y el resumen de totales
 * con CTA a checkout. Los labels llegan resueltos por props desde
 * `getServerDictionary` — este componente no importa el diccionario i18n.
 *
 * Paridad visual con `CartItem.astro` / `CartSummary.astro` (cards
 * blancas sobre `bg-slate-50`, resumen sticky en lg+). Accesibilidad
 * igual: botones `type="button"`, `aria-label` en controles y
 * `aria-live="polite"` en el readout de cantidad.
 */
import type { ReactElement, ReactNode } from 'react';
import {
  useCart,
  getCartTotals,
  updateQuantity,
  removeFromCart,
  type CartLine,
} from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';

const MIN_LINE_QUANTITY = 1;
const MAX_LINE_QUANTITY = 99;

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
  emptyCta: string;
  continueShopping: string;
  /** Plantilla con `{count}` (misma convención que CouponInput). */
  itemsCount: string;
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

interface CartLineRowProps {
  line: CartLine;
  locale: string;
  labels: CartViewLabels;
}

function CartLineRow({ line, locale, labels }: CartLineRowProps): ReactElement {
  const detailHref = `/${locale}/products/${line.productSlug}`;
  const unitPriceFormatted = formatCurrency(line.priceCents, line.currency);
  const lineTotalFormatted = formatCurrency(line.priceCents * line.quantity, line.currency);
  const isDecrementDisabled = line.quantity <= MIN_LINE_QUANTITY;
  const isIncrementDisabled = line.quantity >= MAX_LINE_QUANTITY;

  return (
    <article className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
      <a
        href={detailHref}
        className="relative block h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-slate-50 sm:h-28 sm:w-28"
        aria-label={line.name}
      >
        <img
          src={line.imageUrl}
          alt={line.name}
          loading="lazy"
          decoding="async"
          className="h-full w-full object-cover"
        />
      </a>

      <div className="flex flex-1 flex-col gap-1">
        <h3 className="text-sm font-semibold text-slate-900">
          <a
            href={detailHref}
            className="transition hover:text-[var(--primary,#475569)] focus:outline-none focus-visible:underline"
          >
            {line.name}
          </a>
        </h3>
        {line.variantLabel !== undefined && line.variantLabel !== '' && (
          <p className="text-xs text-slate-500">{line.variantLabel}</p>
        )}
        <p className="text-xs text-slate-500">{unitPriceFormatted}</p>

        <div className="mt-2 flex items-center gap-2">
          <span className="text-xs font-medium text-slate-600">{labels.qty}:</span>
          <div className="inline-flex items-center overflow-hidden rounded-lg border border-slate-200">
            <button
              type="button"
              aria-label={labels.decrement}
              disabled={isDecrementDisabled}
              onClick={() => updateQuantity(line.productSlug, line.quantity - 1, line.variantLabel)}
              className="flex h-8 w-8 items-center justify-center text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
            >
              <span aria-hidden="true">−</span>
            </button>
            <span
              className="flex h-8 min-w-[2.5rem] items-center justify-center border-x border-slate-200 px-2 text-sm font-medium tabular-nums text-slate-900"
              aria-live="polite"
            >
              {line.quantity}
            </span>
            <button
              type="button"
              aria-label={labels.increment}
              disabled={isIncrementDisabled}
              onClick={() => updateQuantity(line.productSlug, line.quantity + 1, line.variantLabel)}
              className="flex h-8 w-8 items-center justify-center text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
            >
              <span aria-hidden="true">+</span>
            </button>
          </div>
        </div>
      </div>

      <div className="flex items-center justify-between gap-3 sm:flex-col sm:items-end sm:justify-center">
        <span className="text-base font-bold tabular-nums text-slate-900">
          {lineTotalFormatted}
        </span>
        <button
          type="button"
          aria-label={labels.remove}
          onClick={() => removeFromCart(line.productSlug, line.variantLabel)}
          className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-500 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
        >
          <span aria-hidden="true">×</span>
          <span>{labels.remove}</span>
        </button>
      </div>
    </article>
  );
}

interface CartSummaryBlockProps {
  lines: CartLine[];
  labels: CartViewLabels;
  checkoutHref: string;
  discountCents: number;
}

function CartSummaryBlock({
  lines,
  labels,
  checkoutHref,
  discountCents,
}: CartSummaryBlockProps): ReactElement {
  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';
  const shippingFormatted =
    totals.shippingCents === 0
      ? labels.shippingFree
      : formatCurrency(totals.shippingCents ?? 0, currencyCode);
  const discountFormatted = discountCents > 0 ? formatCurrency(discountCents, currencyCode) : null;
  const totalCents = Math.max(
    0,
    totals.subtotalCents - discountCents + (totals.shippingCents ?? 0) + totals.taxCents,
  );

  return (
    <aside
      className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-24"
      aria-label={labels.total}
    >
      <h2 className="text-base font-semibold text-slate-900">{labels.total}</h2>

      <dl className="flex flex-col gap-2 text-sm">
        <div className="flex items-center justify-between">
          <dt className="text-slate-600">{labels.subtotal}</dt>
          <dd className="tabular-nums text-slate-900">
            {formatCurrency(totals.subtotalCents, currencyCode)}
          </dd>
        </div>

        {discountFormatted !== null && (
          <div className="flex items-center justify-between">
            <dt className="text-slate-600">{labels.discount}</dt>
            <dd className="tabular-nums text-emerald-600">−{discountFormatted}</dd>
          </div>
        )}

        {totals.shippingCents !== null && (
          <div className="flex items-center justify-between">
            <dt className="text-slate-600">{labels.shipping}</dt>
            <dd className="tabular-nums text-slate-900">{shippingFormatted}</dd>
          </div>
        )}

        {totals.taxCents > 0 && (
          <div className="flex items-center justify-between">
            <dt className="text-slate-600">{labels.tax}</dt>
            <dd className="tabular-nums text-slate-900">
              {formatCurrency(totals.taxCents, currencyCode)}
            </dd>
          </div>
        )}

        <div className="mt-2 flex items-center justify-between border-t border-slate-200 pt-3">
          <dt className="text-base font-semibold text-slate-900">{labels.total}</dt>
          <dd className="text-lg font-bold tabular-nums text-slate-900">
            {formatCurrency(totalCents, currencyCode)}
          </dd>
        </div>
      </dl>

      <a
        href={checkoutHref}
        className="mt-2 inline-flex h-11 items-center justify-center rounded-lg bg-[var(--primary,#475569)] px-4 text-sm font-semibold text-white transition hover:bg-[var(--primary-hover,#334155)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2"
      >
        {labels.checkout}
      </a>
    </aside>
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
    return (
      <section className="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white p-10 text-center sm:p-16">
        <p className="text-2xl font-bold text-slate-900">{labels.empty}</p>
        <a
          href={productsHref}
          className="mt-2 inline-flex h-11 items-center justify-center rounded-lg bg-[var(--primary,#475569)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--primary-hover,#334155)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2"
        >
          {labels.emptyCta}
        </a>
      </section>
    );
  }

  const totals = getCartTotals(lines);

  return (
    <>
      <p className="mt-2 text-sm text-slate-500">
        {labels.itemsCount.replace('{count}', String(totals.itemCount))}
      </p>

      <div className="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div>
          <ul data-reveal-stagger className="flex list-none flex-col gap-4 p-0">
            {lines.map((line) => (
              <li key={`${line.productSlug}:${line.variantLabel ?? ''}`} className="list-none">
                <CartLineRow line={line} locale={locale} labels={labels} />
              </li>
            ))}
          </ul>

          <a
            href={productsHref}
            className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900"
          >
            <span aria-hidden="true">←</span>
            {labels.continueShopping}
          </a>
        </div>

        <div className="flex flex-col gap-5">
          <CartSummaryBlock
            lines={lines}
            labels={labels}
            checkoutHref={checkoutHref}
            discountCents={discountCents}
          />
          {children}
        </div>
      </div>
    </>
  );
}
