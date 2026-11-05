/**
 * ProductCTAs — botones de acción del detail (Add to Bag + Favorite).
 *
 * Variantes: Default (in stock), OutOfStock, Loading, WithoutFavorite.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductCTAs from './ProductCTAs.astro';

const meta: Meta<typeof ProductCTAs> = {
  title: 'Storefront/Products/ProductCTAs',
  component: ProductCTAs,
  tags: ['autodocs'],
  argTypes: {
    inStock: { control: 'boolean' },
    isFavorite: { control: 'boolean' },
    showFavorite: { control: 'boolean' },
    showAddToBag: { control: 'boolean' },
  },
  args: {
    addToCartLabel: 'Agregar al carrito',
    favoriteLabel: 'Agregar a favoritos',
    inStock: true,
    isFavorite: false,
    showFavorite: true,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Selected: Story = {
  args: { isFavorite: true },
};

export const Unselected: Story = {
  args: { isFavorite: false },
};

export const OutOfStock: Story = {
  args: {
    inStock: false,
    addToCartLabel: 'Sin stock',
  },
};

export const WithoutFavorite: Story = {
  args: { showFavorite: false },
};

export const WithoutAddToBag: Story = {
  args: { showAddToBag: false },
};

export const Loading: Story = {
  args: { addToCartLabel: 'Agregando…' },
};
