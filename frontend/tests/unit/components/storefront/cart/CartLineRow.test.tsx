// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import CartLineRow from '../../../../../src/components/storefront/cart/CartLineRow';
import {
  addToCart,
  cartStore,
  clearCart,
  type CartLine,
} from '../../../../../src/stores/cart';

const labels = {
  remove: 'Quitar',
  decrement: 'Disminuir cantidad',
  increment: 'Aumentar cantidad',
  qty: 'Cantidad',
};

const baseLine: CartLine = {
  productSlug: 'remera-panda',
  name: 'Remera Panda',
  imageUrl: '/img/remera.png',
  priceCents: 1000,
  currency: 'USD',
  quantity: 1,
  variantLabel: 'Negro',
};

function seedStore(quantity: number): void {
  const { quantity: _ignored, ...lineWithoutQuantity } = baseLine;
  addToCart(lineWithoutQuantity, quantity);
}

function storedQuantity(): number {
  return cartStore.getSnapshot().lines[0]?.quantity ?? -1;
}

describe('CartLineRow · stepper bounds', (): void => {
  beforeEach((): void => {
    clearCart();
  });

  it('disables the decrement button at quantity 1', (): void => {
    seedStore(1);
    render(<CartLineRow line={{ ...baseLine, quantity: 1 }} locale="es" labels={labels} />);

    expect(screen.getByLabelText(labels.decrement)).toBeDisabled();
    expect(screen.getByLabelText(labels.increment)).not.toBeDisabled();
  });

  it('disables the increment button at the max quantity', (): void => {
    seedStore(99);
    render(<CartLineRow line={{ ...baseLine, quantity: 99 }} locale="es" labels={labels} />);

    expect(screen.getByLabelText(labels.increment)).toBeDisabled();
    expect(screen.getByLabelText(labels.decrement)).not.toBeDisabled();
  });

  it('increments within bounds', async (): Promise<void> => {
    seedStore(98);
    render(<CartLineRow line={{ ...baseLine, quantity: 98 }} locale="es" labels={labels} />);

    await userEvent.setup().click(screen.getByLabelText(labels.increment));

    expect(storedQuantity()).toBe(99);
  });

  it('never exceeds the max when increment is clicked at the bound', async (): Promise<void> => {
    seedStore(99);
    render(<CartLineRow line={{ ...baseLine, quantity: 99 }} locale="es" labels={labels} />);

    await userEvent.setup().click(screen.getByLabelText(labels.increment));

    expect(storedQuantity()).toBe(99);
  });

  it('decrements within bounds', async (): Promise<void> => {
    seedStore(2);
    render(<CartLineRow line={{ ...baseLine, quantity: 2 }} locale="es" labels={labels} />);

    await userEvent.setup().click(screen.getByLabelText(labels.decrement));

    expect(storedQuantity()).toBe(1);
  });

  it('never goes below 1 when decrement is clicked at the bound', async (): Promise<void> => {
    seedStore(1);
    render(<CartLineRow line={{ ...baseLine, quantity: 1 }} locale="es" labels={labels} />);

    await userEvent.setup().click(screen.getByLabelText(labels.decrement));

    expect(storedQuantity()).toBe(1);
  });
});
