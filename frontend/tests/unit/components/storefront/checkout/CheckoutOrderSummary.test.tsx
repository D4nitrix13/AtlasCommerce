// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

import CheckoutOrderSummary from '../../../../../src/components/storefront/checkout/CheckoutOrderSummary';
import { addToCart, clearCart, type CartLine } from '../../../../../src/stores/cart';
import { formatCurrency } from '../../../../../src/lib/formatters';

const labels = {
  summary: 'Resumen del pedido',
  emptyItems: 'No hay productos en el carrito.',
  subtotal: 'Subtotal',
  shippingCost: 'Envío',
  shippingFree: 'Gratis',
  tax: 'Impuestos',
  total: 'Total',
  freeShippingProgress: 'Te faltan {amount} para envío gratis',
  freeShippingReached: 'Tenés envío gratis',
  trust: {
    shipping: 'Envío gratis sobre $50',
    guarantee: 'Garantía oficial',
    returns: 'Devolución 30 días',
    secure: 'Pago 100% seguro',
  },
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

describe('CheckoutOrderSummary · panel-recibo', (): void => {
  beforeEach((): void => {
    clearCart();
  });

  it('is informational: renders the empty state with no checkout CTA', (): void => {
    render(<CheckoutOrderSummary labels={labels} />);

    expect(screen.getByText(labels.emptyItems)).toBeTruthy();
    expect(screen.queryByRole('link', { name: /realizar pedido/i })).toBeNull();
  });

  it('shows the store totals breakdown with the free shipping accent', (): void => {
    // 3000 × 2 = 6000 ≥ 5000 → envío gratis; tax 15% = 900; total 6900.
    seedLine(2, { priceCents: 3000 });
    render(<CheckoutOrderSummary labels={labels} />);

    expect(screen.getByText(labels.subtotal)).toBeTruthy();
    expect(screen.getByText(formatCurrency(6000, 'USD'))).toBeTruthy();
    expect(screen.getByText(labels.shippingFree)).toBeTruthy();
    expect(screen.getByText(formatCurrency(900, 'USD'))).toBeTruthy();
    expect(screen.getByText(formatCurrency(6900, 'USD'))).toBeTruthy();
  });

  it('shows the flat shipping cost below the threshold', (): void => {
    // 1000 × 2 = 2000 subtotal; envío plano 400; tax 300; total 2700.
    seedLine(2);
    render(<CheckoutOrderSummary labels={labels} />);

    expect(screen.getByText(formatCurrency(400, 'USD'))).toBeTruthy();
    expect(screen.getByText(formatCurrency(2700, 'USD'))).toBeTruthy();
    expect(screen.queryByText(labels.shippingFree)).toBeNull();
  });

  it('renders the trust chips with the shared cart i18n labels', (): void => {
    seedLine(1);
    render(<CheckoutOrderSummary labels={labels} />);

    expect(screen.getByText(labels.trust.shipping)).toBeTruthy();
    expect(screen.getByText(labels.trust.guarantee)).toBeTruthy();
    expect(screen.getByText(labels.trust.returns)).toBeTruthy();
    expect(screen.getByText(labels.trust.secure)).toBeTruthy();
  });

  it('renders the free shipping meter progress below the threshold', (): void => {
    // 1000 × 1 = 1000 → faltan 4000.
    seedLine(1);
    render(<CheckoutOrderSummary labels={labels} />);

    expect(
      screen.getByText('Te faltan ' + formatCurrency(4000, 'USD') + ' para envío gratis'),
    ).toBeTruthy();
  });
});
