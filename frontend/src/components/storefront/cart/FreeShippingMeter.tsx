/**
 * FreeShippingMeter — progreso hacia el envío gratis en el resumen.
 *
 * El umbral sale de `stores/cart.ts` (misma fuente que `getCartTotals`)
 * para que el medidor y los totales no diverjan.
 */
import type { ReactElement } from 'react';
import {
  FREE_SHIPPING_THRESHOLD_CENTS,
} from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';

interface FreeShippingMeterProps {
  subtotalCents: number;
  currencyCode: string;
  /** Plantilla con `{amount}` (convención CouponInput). */
  progressLabel: string;
  reachedLabel: string;
}

interface FreeShippingMeterState {
  reached: boolean;
  remainingCents: number;
  /** 0..1, clampado en 1 al llegar al umbral. */
  progressRatio: number;
}

export function getFreeShippingMeterState(subtotalCents: number): FreeShippingMeterState {
  const remainingCents = Math.max(0, FREE_SHIPPING_THRESHOLD_CENTS - subtotalCents);
  return {
    reached: remainingCents === 0,
    remainingCents,
    progressRatio: Math.min(1, subtotalCents / FREE_SHIPPING_THRESHOLD_CENTS),
  };
}

export default function FreeShippingMeter({
  subtotalCents,
  currencyCode,
  progressLabel,
  reachedLabel,
}: FreeShippingMeterProps): ReactElement {
  const { reached, remainingCents, progressRatio } = getFreeShippingMeterState(subtotalCents);
  const message = reached
    ? reachedLabel
    : progressLabel.replace('{amount}', formatCurrency(remainingCents, currencyCode));

  return (
    <div className="flex flex-col gap-2">
      <p
        className={
          reached
            ? 'flex items-center gap-1.5 text-xs font-semibold text-[var(--primary,#475569)]'
            : 'text-xs font-medium text-[var(--ink,#1c1917)]/70'
        }
      >
        {reached && (
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className="h-3.5 w-3.5 shrink-0"
          >
            <path d="M20 6 9 17l-5-5" />
          </svg>
        )}
        {message}
      </p>
      <div
        aria-hidden="true"
        className="h-1.5 w-full overflow-hidden rounded-full bg-[var(--primary-soft,#e6f7f4)]"
      >
        <div
          className="h-full rounded-full bg-[var(--primary,#475569)] transition-[width] duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] motion-reduce:transition-none"
          style={{ width: `${progressRatio * 100}%` }}
        />
      </div>
    </div>
  );
}
