// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

import CheckoutOrderSummary from '../../../../../src/components/storefront/checkout/CheckoutOrderSummary';
import { addToCart, clearCart, type CartLine } from '../../../../../src/stores/cart';
import { formatCurrency } from '../../../../../src/lib/formatters';

const labels = {
  summary: 'Resumen del pedido',
  emptyItems: 'No hay productos en tu carrito.',
  subtotal: 'Subtotal',
  shippingCost: 'Envío',
  shippingFree: 'Gratis',
  tax: 'Impuestos',
  total: 'Total',
  cta: 'Realizar pedido',
  termsHint: 'Al continuar aceptás los términos de compra.',
};

const baseProps = {
  labels,
  ctaHref: '/es/checkout?step=success',
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

describe('CheckoutOrderSummary', (): void => {
  beforeEach((): void => {
    clearCart();
  });

  it('renders the empty item state and the CTA when the cart has no lines', (): void => {
    render(<CheckoutOrderSummary {...baseProps} />);

    expect(screen.getByText(labels.emptyItems)).toBeTruthy();
    const cta = screen.getByRole('link', { name: labels.cta });
    expect(cta.getAttribute('href')).toBe('/es/checkout?step=success');
  });

  it('renders each line with quantity badge and line total', (): void => {
    seedLine(2, { variantLabel: 'Negro' });
    render(<CheckoutOrderSummary {...baseProps} />);

    expect(screen.getByText('Remera Panda')).toBeTruthy();
    expect(screen.getByText('2')).toBeTruthy();
    // Uno en la fila del item, otro en la fila de subtotal.
    expect(screen.getAllByText(formatCurrency(2000, 'USD'))).toHaveLength(2);
    expect(screen.queryByText(labels.emptyItems)).toBeNull();
  });

  it('shows the store totals breakdown', (): void => {
    // 1000 × 2 = 2000 subtotal; envío plano 400; tax 15% = 300; total 2700.
    seedLine(2);
    render(<CheckoutOrderSummary {...baseProps} />);

    expect(screen.getByText(labels.subtotal)).toBeTruthy();
    expect(screen.getAllByText(formatCurrency(2000, 'USD'))).toHaveLength(2);
    expect(screen.getByText(formatCurrency(400, 'USD'))).toBeTruthy();
    expect(screen.getByText(formatCurrency(300, 'USD'))).toBeTruthy();
    expect(screen.getByText(formatCurrency(2700, 'USD'))).toBeTruthy();
  });

  it('shows the free shipping label above the threshold', (): void => {
    // 3000 × 2 = 6000 ≥ 5000 → envío gratis.
    seedLine(2, { priceCents: 3000 });
    render(<CheckoutOrderSummary {...baseProps} />);

    expect(screen.getByText(labels.shippingFree)).toBeTruthy();
  });
});
