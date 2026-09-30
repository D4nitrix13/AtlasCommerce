import type { Meta, StoryObj } from '@storybook/astro';
import ProductGrid from './ProductGrid.astro';
import type { ProductGridStatus } from './ProductGrid.astro';
import type { Product } from '../../../types/catalog';
import type { ProductCardLabels } from './ProductCard.astro';

/**
 * ProductGrid — grid responsive de ProductCards con estados UI.
 *
 * AGENTS §11: loading · empty · error · success. Filtra por
 * `activeCategory` en cliente si se pasa.
 */
const meta: Meta<typeof ProductGrid> = {
  title: 'Storefront/ProductGrid',
  component: ProductGrid,
  tags: ['autodocs'],
  argTypes: {
    products: { control: 'object' },
    locale: { control: 'text' },
    cardLabels: { control: 'object' },
    status: {
      control: { type: 'inline-radio' },
      options: ['success', 'loading', 'empty', 'error'],
    },
    activeCategory: { control: 'text' },
    loadingLabel: { control: 'text' },
    errorLabel: { control: 'text' },
    emptyLabel: { control: 'text' },
    emptyHint: { control: 'text' },
  },
  args: {
    locale: 'es',
    cardLabels: {
      newBadge: 'Nuevo',
      saleBadge: 'Oferta',
      lowStockBadge: 'Últimas unidades',
      addToCart: 'Añadir',
      outOfStock: 'Agotado',
      viewDetail: 'Ver detalle',
    } as ProductCardLabels,
    status: 'success' as ProductGridStatus,
    loadingLabel: 'Cargando productos…',
    errorLabel: 'No se pudieron cargar los productos.',
    emptyLabel: 'No hay productos',
    emptyHint: 'Vuelve pronto o explora otras categorías.',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

const placeholderImage =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="#f1f5f9"/><circle cx="200" cy="160" r="60" fill="#cbd5e1"/><path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/></svg>`,
  );

const sampleProducts: Product[] = [
  { id: 1, slug: 'saber-alter-figma', name: 'Saber Alter Figma', description: 'Figma articulada.', priceCents: 14500, imageUrl: placeholderImage, category: 'figures', stock: 8, badges: ['new'], currency: 'USD' },
  { id: 2, slug: 'nendoroid-zero-two', name: 'Nendoroid Zero Two', description: '10cm, tres expresiones.', priceCents: 7900, imageUrl: placeholderImage, category: 'figures', stock: 3, badges: ['low_stock'], currency: 'USD' },
  { id: 3, slug: 'chainsaw-man-t1', name: 'Chainsaw Man Tomo 1', description: 'Tankōbon japonés.', priceCents: 1900, imageUrl: placeholderImage, category: 'manga', stock: 24, currency: 'USD' },
  { id: 4, slug: 'spy-x-family-t1', name: 'Spy x Family Tomo 1', description: 'Edición español.', priceCents: 1499, imageUrl: placeholderImage, category: 'manga', stock: 30, currency: 'USD' },
  { id: 5, slug: 'jujutsu-kaisen-hoodie', name: 'Hoodie Jujutsu Kaisen', description: 'Sudadera negra logo Gojo.', priceCents: 4900, imageUrl: placeholderImage, category: 'apparel', stock: 12, currency: 'USD' },
  { id: 6, slug: 'totoro-plush', name: 'Peluche Totoro mediano', description: '30cm, edición oficial.', priceCents: 3500, imageUrl: placeholderImage, category: 'accessories', stock: 18, currency: 'USD' },
];

export const Default: Story = {
  args: {
    products: sampleProducts,
  },
};

export const Loading: Story = {
  args: {
    products: [],
    status: 'loading',
  },
};

export const Empty: Story = {
  args: {
    products: [],
    status: 'empty',
  },
};

export const Error: Story = {
  args: {
    products: [],
    status: 'error',
  },
};

export const FilteredByCategory: Story = {
  args: {
    products: sampleProducts,
    activeCategory: 'figures',
  },
};

export const FilteredEmpty: Story = {
  args: {
    products: sampleProducts,
    activeCategory: 'nonexistent',
  },
};

export const SingleProduct: Story = {
  args: {
    products: [sampleProducts[0]],
  },
};