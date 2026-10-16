import type { Meta, StoryObj } from '@storybook/astro';
import FiltersSidebar, {
  type CategoryFilterOption,
  type PriceFilterOption,
  type ColorFilterOption,
  type SizeFilterOption,
} from './FiltersSidebar.astro';

const CATEGORIES: CategoryFilterOption[] = [
  { slug: 'all', label: 'Todo', count: 148 },
  { slug: 'figures', label: 'Figures', count: 42 },
  { slug: 'manga', label: 'Manga', count: 28 },
  { slug: 'apparel', label: 'Apparel', count: 19 },
  { slug: 'accessories', label: 'Accessories', count: 22 },
];

const PRICES: PriceFilterOption[] = [
  { slug: '0-30', label: '$0 – $30' },
  { slug: '30-60', label: '$30 – $60' },
  { slug: '60-100', label: '$60 – $100' },
  { slug: '100+', label: '$100+' },
];

const COLORS: ColorFilterOption[] = [
  { slug: 'black', label: 'Negro', hex: '#132028', count: 24 },
  { slug: 'primary', label: 'Verde azulado', hex: '#2D9D8E', count: 18 },
  { slug: 'pink', label: 'Rosa', hex: '#DB2777', count: 12 },
  { slug: 'blue', label: 'Azul', hex: '#2563EB', count: 9 },
  { slug: 'amber', label: 'Amarillo', hex: '#EAB24B', count: 7 },
  { slug: 'purple', label: 'Violeta', hex: '#7C3AED', count: 5 },
  { slug: 'cream', label: 'Crema', hex: '#F2EDE4', count: 14 },
];

const SIZES: SizeFilterOption[] = [
  { slug: 'ch', label: 'CH', count: 32 },
  { slug: 'm', label: 'M', count: 41 },
  { slug: 'g', label: 'G', count: 38 },
  { slug: 'xg', label: 'XG', count: 19 },
];

const LABELS = {
  heading: 'Filtros',
  clear: 'Limpiar',
  categoryHeading: 'Categoría',
  priceHeading: 'Precio',
  colorHeading: 'Color',
  sizeHeading: 'Talla',
  colorSwatchAltTemplate: 'Color {name}',
};

const meta: Meta<typeof FiltersSidebar> = {
  title: 'Storefront/Catalog/FiltersSidebar',
  component: FiltersSidebar,
  tags: ['autodocs'],
  args: {
    labels: LABELS,
    categories: CATEGORIES,
    prices: PRICES,
    colors: COLORS,
    sizes: SIZES,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithActiveFilters: Story = {
  args: {
    activeCategory: 'figures',
    activePrice: '30-60',
    activeColors: ['primary', 'cream'],
    activeSizes: ['m', 'g'],
  },
};

export const SingleCategorySelected: Story = {
  args: {
    activeCategory: 'manga',
  },
};
