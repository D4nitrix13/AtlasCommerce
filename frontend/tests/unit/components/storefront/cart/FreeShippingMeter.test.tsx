// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';

import FreeShippingMeter, {
  getFreeShippingMeterState,
} from '../../../../../src/components/storefront/cart/FreeShippingMeter';
import { FREE_SHIPPING_THRESHOLD_CENTS } from '../../../../../src/stores/cart';
import { formatCurrency } from '../../../../../src/lib/formatters';

const labels = {
  progress: 'Te faltan {amount} para envío gratis',
  reached: 'Tenés envío gratis',
};

function renderMeter(subtotalCents: number): void {
  render(
    <FreeShippingMeter
      subtotalCents={subtotalCents}
      currencyCode="USD"
      progressLabel={labels.progress}
      reachedLabel={labels.reached}
    />,
  );
}

describe('getFreeShippingMeterState', (): void => {
  it('reports remaining cents just under the threshold', (): void => {
    const state = getFreeShippingMeterState(FREE_SHIPPING_THRESHOLD_CENTS - 1);
    expect(state.reached).toBe(false);
    expect(state.remainingCents).toBe(1);
    expect(state.progressRatio).toBeLessThan(1);
  });

  it('reports reached exactly at the threshold', (): void => {
    const state = getFreeShippingMeterState(FREE_SHIPPING_THRESHOLD_CENTS);
    expect(state.reached).toBe(true);
    expect(state.remainingCents).toBe(0);
    expect(state.progressRatio).toBe(1);
  });

  it('clamps the progress ratio over the threshold', (): void => {
    const state = getFreeShippingMeterState(FREE_SHIPPING_THRESHOLD_CENTS + 2200);
    expect(state.reached).toBe(true);
    expect(state.remainingCents).toBe(0);
    expect(state.progressRatio).toBe(1);
  });
});

describe('FreeShippingMeter', (): void => {
  it('shows the remaining amount just under the threshold', (): void => {
    renderMeter(FREE_SHIPPING_THRESHOLD_CENTS - 1);

    const expected = labels.progress.replace(
      '{amount}',
      formatCurrency(1, 'USD'),
    );
    expect(screen.getByText(expected)).toBeTruthy();
    expect(screen.queryByText(labels.reached)).toBeNull();
  });

  it('shows the reached state exactly at the threshold', (): void => {
    renderMeter(FREE_SHIPPING_THRESHOLD_CENTS);

    expect(screen.getByText(labels.reached)).toBeTruthy();
  });

  it('stays reached over the threshold', (): void => {
    renderMeter(FREE_SHIPPING_THRESHOLD_CENTS + 2200);

    expect(screen.getByText(labels.reached)).toBeTruthy();
  });
});
