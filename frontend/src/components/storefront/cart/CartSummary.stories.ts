import type { Meta, StoryObj } from '@storybook/astro';
import CartSummary from './CartSummary.astro';
import type { CartSummaryLabels } from './CartSummary.astro';

/**
 * CartSummary — bloque de totales + checkout en el carrito.
 *
 * Layout: subtotal · descuento (opcional) · envío (opcional) · total.
 * El cálculo del total NO se recalcula aquí — los valores vienen del
 * cart store o backend.
 */
const meta: Meta<typeof CartSummary> = {
  title: 'Storefront/CartSummary',
  component: CartSummary,
  tags: ['autodocs'],
  argTypes: {
    subtotalCents: { control: { type: 'number', min: 0, step: 100 } },
    currencyCode: { control: 'text' },
    discountCents: { control: { type: 'number', min: 0, step: 100 } },
    shippingCents: { control: { type: 'number', min: 0, step: 100 } },
    checkoutHref: { control: 'text' },
    labels: { control: 'object' },
    isEmpty: { control: 'boolean' },
  },
  args: {
    subtotalCents: 14500,
    currencyCode: 'USD',
    discountCents: 0,
    shippingCents: null,
    checkoutHref: '/es/checkout',
    labels: {
      subtotal: 'Subtotal',
      discount: 'Descuento',
      shipping: 'Envío',
      shippingFree: 'Gratis',
      total: 'Total',
      checkout: 'Ir a checkout',
    } as CartSummaryLabels,
    isEmpty: false,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithDiscount: Story = {
  args: {
    discountCents: 1500,
  },
};

export const WithShipping: Story = {
  args: {
    shippingCents: 500,
  },
};

export const WithFreeShipping: Story = {
  args: {
    shippingCents: 0,
  },
};

export const FullBreakdown: Story = {
  args: {
    subtotalCents: 14500,
    discountCents: 2000,
    shippingCents: 500,
  },
};

export const EmptyCart: Story = {
  args: {
    subtotalCents: 0,
    isEmpty: true,
  },
};

export const DifferentCurrency: Story = {
  args: {
    subtotalCents: 19000,
    currencyCode: 'JPY',
    discountCents: 2000,
    shippingCents: 500,
  },
};

export const LargeSubtotal: Story = {
  args: {
    subtotalCents: 245000,
    discountCents: 25000,
    shippingCents: 1500,
  },
};