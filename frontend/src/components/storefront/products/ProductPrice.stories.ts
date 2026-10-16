/**
 * ProductPrice — bloque de precio del producto.
 *
 * Variantes: default, compare-at tachado, con chip de descuento,
 * vacío (precio sin compare-at ni chip).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductPrice from './ProductPrice.astro';

const meta: Meta<typeof ProductPrice> = {
  title: 'Storefront/Products/ProductPrice',
  component: ProductPrice,
  tags: ['autodocs'],
  argTypes: {
    currentCents: { control: 'number' },
    compareAtCents: { control: 'number' },
    currency: { control: 'text' },
  },
  args: {
    currency: 'USD',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: { currentCents: 1450000 },
};

export const OnSale: Story = {
  args: { currentCents: 1250000, compareAtCents: 1450000 },
};

export const HighDiscount: Story = {
  args: { currentCents: 45000, compareAtCents: 120000 },
};

export const NoCompareAt: Story = {
  args: { currentCents: 280000 },
};
