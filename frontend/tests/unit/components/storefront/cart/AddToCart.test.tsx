// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import AddToCart, {
  type AddToCartLabels,
} from '../../../../../src/components/storefront/cart/AddToCart';
import { cartStore, clearCart, type CartLine } from '../../../../../src/stores/cart';

const finishes = [
  { slug: 'negro', label: 'Negro', hex: '#111111' },
  { slug: 'sunburst', label: 'Sunburst', hex: '#7a4a1f' },
];

const baseProps = {
  productSlug: 'remera-panda',
  name: 'Remera Panda',
  imageUrl: '/img/remera.png',
  priceCents: 1000,
  currency: 'USD',
  finishes,
  mode: 'full' as const,
};

const labels: AddToCartLabels = {
  add: 'Agregar al carrito',
  added: 'Agregado',
  qty: 'Cantidad',
  variant: 'Acabado',
};

function seededLines(): CartLine[] {
  return cartStore.getSnapshot().lines;
}

describe('AddToCart', (): void => {
  beforeEach((): void => {
    clearCart();
  });

  afterEach((): void => {
    vi.useRealTimers();
  });

  it('renders the full mode with finish swatches, quantity input and add button', (): void => {
    render(<AddToCart {...baseProps} labels={labels} />);

    for (const finish of finishes) {
      expect(screen.getByRole('button', { name: finish.label })).toBeTruthy();
    }
    expect(screen.getByLabelText(labels.qty)).toBeTruthy();
    expect(screen.getByRole('button', { name: labels.add })).toBeTruthy();
  });

  it('adds the chosen finish and quantity to the cart', async (): Promise<void> => {
    const user = userEvent.setup();
    render(<AddToCart {...baseProps} labels={labels} />);

    await user.click(screen.getByRole('button', { name: 'Sunburst' }));
    const qtyInput = screen.getByLabelText(labels.qty) as HTMLInputElement;
    await user.clear(qtyInput);
    await user.type(qtyInput, '3');
    await user.click(screen.getByRole('button', { name: labels.add }));

    expect(seededLines()).toEqual([
      {
        productSlug: 'remera-panda',
        name: 'Remera Panda',
        imageUrl: '/img/remera.png',
        priceCents: 1000,
        currency: 'USD',
        quantity: 3,
        variantLabel: 'Sunburst',
      },
    ]);
  });

  it('clamps the quantity input between 1 and 99', async (): Promise<void> => {
    const user = userEvent.setup();
    render(<AddToCart {...baseProps} labels={labels} />);

    const qtyInput = screen.getByLabelText(labels.qty) as HTMLInputElement;
    expect(qtyInput.getAttribute('min')).toBe('1');
    expect(qtyInput.getAttribute('max')).toBe('99');

    await user.clear(qtyInput);
    await user.type(qtyInput, '0');
    expect(qtyInput.value).toBe('1');

    await user.clear(qtyInput);
    await user.type(qtyInput, '150');
    expect(qtyInput.value).toBe('99');
  });

  it('adds quantity 1 with the first finish in compact mode', async (): Promise<void> => {
    const user = userEvent.setup();
    render(<AddToCart {...baseProps} mode="compact" labels={labels} />);

    await user.click(screen.getByRole('button', { name: labels.add }));

    expect(seededLines()).toEqual([
      expect.objectContaining({
        productSlug: 'remera-panda',
        quantity: 1,
        variantLabel: 'Negro',
      }),
    ]);
  });

  it('adds without variantLabel in compact mode when there are no finishes', async (): Promise<void> => {
    const user = userEvent.setup();
    render(<AddToCart {...baseProps} mode="compact" finishes={[]} labels={labels} />);

    await user.click(screen.getByRole('button', { name: labels.add }));

    expect(seededLines()).toEqual([
      expect.objectContaining({
        productSlug: 'remera-panda',
        quantity: 1,
        variantLabel: undefined,
      }),
    ]);
  });

  it('disables the add button in full mode when there are no finishes', (): void => {
    render(<AddToCart {...baseProps} finishes={[]} labels={labels} />);

    const addButton = screen.getByRole('button', { name: labels.add });
    expect(addButton.hasAttribute('disabled')).toBe(true);
  });

  it('shows the added feedback and reverts it after the timeout', (): void => {
    vi.useFakeTimers();
    render(<AddToCart {...baseProps} labels={labels} />);

    fireEvent.click(screen.getByRole('button', { name: labels.add }));

    expect(screen.getByRole('button', { name: labels.added })).toBeTruthy();
    expect(screen.getByRole('status').textContent).toBe(labels.added);

    act((): void => {
      vi.advanceTimersByTime(1600);
    });

    expect(screen.getByRole('button', { name: labels.add })).toBeTruthy();
    expect(screen.queryByRole('button', { name: labels.added })).toBeNull();
    vi.useRealTimers();
  });

  it('falls back to Spanish labels when none are provided', (): void => {
    render(<AddToCart {...baseProps} />);

    expect(screen.getByRole('button', { name: 'Agregar al carrito' })).toBeTruthy();
    expect(screen.getByLabelText('Cantidad')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Negro' })).toBeTruthy();
  });
});
