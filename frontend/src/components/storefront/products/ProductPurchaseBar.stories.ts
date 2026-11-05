/**
 * ProductPurchaseBar — barra de compra del PDP (cantidad + CTAs).
 *
 * Variantes: Default (en stock), LowStock, OutOfStock, Sin acabado.
 */
import type { Meta, StoryObj } from '@storybook/react';
import ProductPurchaseBar from './ProductPurchaseBar';

const meta: Meta<typeof ProductPurchaseBar> = {
  title: 'Storefront/Products/ProductPurchaseBar',
  component: ProductPurchaseBar,
  tags: ['autodocs'],
  argTypes: {
    stock: { control: 'number' },
    priceCents: { control: 'number' },
  },
  args: {
    productSlug: 'gibson-les-paul-standard',
    name: 'Gibson Les Paul Standard',
    imageUrl: '/images/product-placeholder.png',
    priceCents: 249900,
    currency: 'USD',
    variantLabel: 'Sunburst',
    stock: 8,
    buyNowHref: '/es/checkout',
    labels: {
      quantity: 'Cantidad',
      addToCart: 'Añadir al carrito',
      buyNow: 'Comprar ya',
      outOfStock: 'Agotado',
      added: 'Agregado',
    },
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const LowStock: Story = {
  args: { stock: 2 },
};

export const OutOfStock: Story = {
  args: { stock: 0 },
};

export const SinAcabado: Story = {
  args: { variantLabel: undefined },
};
