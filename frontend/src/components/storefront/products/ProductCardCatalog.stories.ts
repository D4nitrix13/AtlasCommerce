import type { Meta, StoryObj } from '@storybook/astro';
import ProductCardCatalog from './ProductCardCatalog.astro';
import type { Product } from '../../../types/catalog';

/**
 * ProductCardCatalog — tarjeta de catálogo Bonfire (vista 04).
 *
 * Diferencia con ProductCard.astro: usa ProductMockup (SVG primitives)
 * en vez de <img>, aspect 4/5 (vertical póster), radius 2xl.
 */
const SAMPLE_PRODUCT: Product = {
  id: 1,
  slug: 'saber-alter-figma',
  name: 'Saber Alter Figma',
  description: 'Figma articulada 14cm, base y manos intercambiables',
  priceCents: 14500,
  imageUrl: '',
  category: 'figures',
  stock: 8,
  currency: 'USD',
};

const meta: Meta<typeof ProductCardCatalog> = {
  title: 'Storefront/Catalog/ProductCardCatalog',
  component: ProductCardCatalog,
  tags: ['autodocs'],
  args: {
    product: SAMPLE_PRODUCT,
    locale: 'es',
    labels: {
      viewDetail: 'Ver detalle',
    },
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Hoodie: Story = {
  args: {
    product: {
      ...SAMPLE_PRODUCT,
      slug: 'jujutsu-kaisen-hoodie',
      name: 'Hoodie Jujutsu Kaisen',
      description: 'Sudadera negra bordada',
      priceCents: 4900,
    },
  },
};

export const Mug: Story = {
  args: {
    product: {
      ...SAMPLE_PRODUCT,
      slug: 'totoro-plush',
      name: 'Peluche Totoro mediano',
      description: '30cm, suave al tacto',
      priceCents: 3500,
    },
  },
};

export const Hat: Story = {
  args: {
    product: {
      ...SAMPLE_PRODUCT,
      slug: 'demon-slayer-keychain',
      name: 'Llavero Demon Slayer',
      description: 'Metálico 4cm',
      priceCents: 800,
    },
  },
};

export const OnSale: Story = {
  args: {
    product: {
      ...SAMPLE_PRODUCT,
      compareAtPriceCents: 16900,
      priceCents: 14500,
    },
  },
};
