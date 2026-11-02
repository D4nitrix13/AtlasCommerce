// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

import CheckoutReviewItems from '../../../../../src/components/storefront/checkout/CheckoutReviewItems';
import { addToCart, clearCart, type CartLine } from '../../../../../src/stores/cart';
import { formatCurrency } from '../../../../../src/lib/formatters';

const labels = {
  qty: 'Cantidad',
  subtotal: 'Subtotal',
};

function seedLine(
  quantity: number,
  overrides: Partial<Omit<CartLine, 'quantity'>> = {},
): void {
  addToCart(
    {
      productSlug: 'remera-panda',
      name: 'Remera Panda',
      imageUrl: '/img/remera.png',
      priceCents: 1000,
      currency: 'USD',
      ...overrides,
    },
    quantity,
  );
}

describe('CheckoutReviewItems', (): void => {
  beforeEach((): void => {
    clearCart();
  });

  it('renders the empty state when the cart has no lines', (): void => {
    render(<CheckoutReviewItems labels={labels} />);

    expect(screen.getByText(labels.subtotal)).toBeTruthy();
    expect(screen.queryByText('Remera Panda')).toBeNull();
  });

  it('lists every line with name, quantity and line total', (): void => {
    seedLine(2, { variantLabel: 'Negro' });
    seedLine(1, { productSlug: 'buzo-kitsune', name: 'Buzo Kitsune', priceCents: 3000 });
    render(<CheckoutReviewItems labels={labels} />);

    expect(screen.getByText('Remera Panda')).toBeTruthy();
    expect(screen.getByText('Buzo Kitsune')).toBeTruthy();
    expect(screen.getByText(/Negro/)).toBeTruthy();
    expect(screen.getByText(formatCurrency(2000, 'USD'))).toBeTruthy();
    expect(screen.getByText(formatCurrency(3000, 'USD'))).toBeTruthy();
  });

  it('shows the subtotal of all lines', (): void => {
    // 1000 × 2 + 3000 × 1 = 5000.
    seedLine(2);
    seedLine(1, { productSlug: 'buzo-kitsune', name: 'Buzo Kitsune', priceCents: 3000 });
    render(<CheckoutReviewItems labels={labels} />);

    expect(screen.getByText(formatCurrency(5000, 'USD'))).toBeTruthy();
  });
});
