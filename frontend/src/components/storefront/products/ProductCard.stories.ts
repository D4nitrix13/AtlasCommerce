import type { Meta, StoryObj } from '@storybook/astro';
import ProductCard from './ProductCard.astro';
import type { Product } from '../../../types/catalog';
import type { ProductCardLabels } from './ProductCard.astro';

/**
 * ProductCard — tarjeta de producto para grids del storefront.
 *
 * Patrón B2C kitsune (design-refs §3.1): badges contextuales
 * ("Novedades", "Oferta", "Últimas unidades") + hover state sutil.
 */
const meta: Meta<typeof ProductCard> = {
  title: 'Storefront/ProductCard',
  component: ProductCard,
  tags: ['autodocs'],
  argTypes: {
    product: { control: 'object' },
    locale: { control: 'text' },
    labels: { control: 'object' },
    showAddToCart: { control: 'boolean' },
  },
  args: {
    locale: 'es',
    labels: {
      newBadge: 'Nuevo',
      saleBadge: 'Oferta',
      lowStockBadge: 'Últimas unidades',
      addToCart: 'Añadir',
      outOfStock: 'Agotado',
      viewDetail: 'Ver detalle',
    } as ProductCardLabels,
    showAddToCart: true,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

const placeholderImage =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="#f1f5f9"/><circle cx="200" cy="160" r="60" fill="#cbd5e1"/><path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/></svg>`
  );

const baseProduct: Product = {
  id: 1,
  slug: 'saber-alter-figma',
  name: 'Saber Alter Figma',
  description: 'Figma articulada 14cm, incluye base y manos intercambiables.',
  priceCents: 14500,
  imageUrl: placeholderImage,
  category: 'figures',
  stock: 8,
  currency: 'USD',
};

export const Default: Story = {
  args: {
    product: { ...baseProduct },
  },
};

export const OnSale: Story = {
  args: {
    product: { ...baseProduct, compareAtPriceCents: 16900 },
  },
};

export const New: Story = {
  args: {
    product: { ...baseProduct, badges: ['new'] },
  },
};

export const LowStock: Story = {
  args: {
    product: { ...baseProduct, name: 'Nendoroid Zero Two', stock: 2, badges: ['low_stock'] },
  },
};

export const OutOfStock: Story = {
  args: {
    product: { ...baseProduct, name: 'Evangelion Unit-01', stock: 0, badges: ['sale'] },
  },
};

export const AllBadges: Story = {
  args: {
    product: {
      ...baseProduct,
      badges: ['new', 'sale', 'low_stock'],
      compareAtPriceCents: 16900,
      stock: 2,
    },
  },
};

export const LongTitle: Story = {
  args: {
    product: {
      ...baseProduct,
      name: 'Evangelion Unit-01 1/6 — Edición limitada con luz LED y piezas articuladas premium',
      description:
        'Figura escala 1/6 del Eva-01. 30cm, piezas articuladas, base con luz LED opcional. Incluye manual y certificado de autenticidad.',
    },
  },
};

export const WithoutAddToCart: Story = {
  args: {
    product: { ...baseProduct },
    showAddToCart: false,
  },
};
