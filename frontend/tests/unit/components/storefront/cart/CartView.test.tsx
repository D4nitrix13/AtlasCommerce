// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import CartView from '../../../../../src/components/storefront/cart/CartView';
import { addToCart, clearCart, type CartLine } from '../../../../../src/stores/cart';
import { formatCurrency } from '../../../../../src/lib/formatters';

const labels = {
  remove: 'Quitar',
  decrement: 'Disminuir cantidad',
  increment: 'Aumentar cantidad',
  qty: 'Cantidad',
  subtotal: 'Subtotal',
  discount: 'Descuento',
  shipping: 'Envío',
  shippingFree: 'Gratis',
  tax: 'Impuestos',
  total: 'Total',
  checkout: 'Ir al checkout',
  empty: 'Tu carrito está vacío.',
  emptyHint: 'Explorá el catálogo y agregá productos.',
  emptyCta: 'Explorar productos',
  continueShopping: 'Continuar comprando',
  itemsCount: '{count} productos en tu carrito',
  freeShippingProgress: 'Te faltan {amount} para envío gratis',
  freeShippingReached: 'Tenés envío gratis',
};

const baseProps = {
  locale: 'es',
  labels,
  checkoutHref: '/es/checkout',
  productsHref: '/es/products',
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

function getLineRow(lineName: string): HTMLElement {
  const row = screen.getByText(lineName).closest('article');
  if (row === null) {
    throw new Error(`No se encontró la fila del carrito para "${lineName}"`);
  }
  return row;
}

describe('CartView', (): void => {
  beforeEach((): void => {
    clearCart();
  });

  it('renders the empty state with a catalog CTA when there are no lines', (): void => {
    render(<CartView {...baseProps} />);

    expect(screen.getByText(labels.empty)).toBeTruthy();
    const emptyCta = screen.getByRole('link', { name: labels.emptyCta });
    expect(emptyCta.getAttribute('href')).toBe('/es/products');
    expect(screen.queryByRole('link', { name: labels.checkout })).toBeNull();
  });

  it('renders each line with name, variant, unit price and quantity', (): void => {
    seedLine(2, { variantLabel: 'Negro' });
    render(<CartView {...baseProps} />);

    expect(screen.getByText('Remera Panda')).toBeTruthy();
    expect(screen.getByText('Negro')).toBeTruthy();
    expect(screen.getByText(formatCurrency(1000, 'USD'))).toBeTruthy();
    expect(screen.getByText('2')).toBeTruthy();
  });

  it('shows the correct totals for the accumulated lines', (): void => {
    // 1000 × 2 = 2000 subtotal; envío plano 400; tax 15% = 300; total 2700.
    seedLine(2);
    render(<CartView {...baseProps} />);

    const summary = screen.getByRole('complementary', { name: labels.total });
    expect(within(summary).getByText(formatCurrency(2000, 'USD'))).toBeTruthy();
    expect(within(summary).getByText(formatCurrency(400, 'USD'))).toBeTruthy();
    expect(within(summary).getByText(formatCurrency(300, 'USD'))).toBeTruthy();
    expect(within(summary).getByText(formatCurrency(2700, 'USD'))).toBeTruthy();
  });

  it('shows the free shipping label when the subtotal reaches the threshold', (): void => {
    // 3000 × 2 = 6000 ≥ 5000 → envío gratis.
    seedLine(2, { priceCents: 3000 });
    render(<CartView {...baseProps} />);

    const summary = screen.getByRole('complementary', { name: labels.total });
    expect(within(summary).getByText(labels.shippingFree)).toBeTruthy();
  });

  it('increments the line quantity when the + button is clicked', async (): Promise<void> => {
    seedLine(1);
    const user = userEvent.setup();
    render(<CartView {...baseProps} />);

    await user.click(screen.getByLabelText(labels.increment));

    expect(screen.getByText('2')).toBeTruthy();
  });

  it('decrements the line quantity when the − button is clicked', async (): Promise<void> => {
    seedLine(2);
    const user = userEvent.setup();
    render(<CartView {...baseProps} />);

    await user.click(screen.getByLabelText(labels.decrement));

    expect(screen.getByText('1')).toBeTruthy();
  });

  it('removes the line when the remove button is clicked', async (): Promise<void> => {
    seedLine(1);
    seedLine(1, { productSlug: 'buzo-kitsune', name: 'Buzo Kitsune' });
    const user = userEvent.setup();
    render(<CartView {...baseProps} />);

    await user.click(within(getLineRow('Remera Panda')).getByLabelText(labels.remove));

    expect(screen.queryByText('Remera Panda')).toBeNull();
    expect(screen.getByText('Buzo Kitsune')).toBeTruthy();
  });

  it('links the checkout CTA to the received href', (): void => {
    seedLine(1);
    render(<CartView {...baseProps} />);

    // Resumen + barra sticky mobile comparten el mismo destino.
    const checkoutCtas = screen.getAllByRole('link', { name: labels.checkout });
    expect(checkoutCtas.length).toBeGreaterThan(0);
    for (const cta of checkoutCtas) {
      expect(cta.getAttribute('href')).toBe('/es/checkout');
    }
  });
});
