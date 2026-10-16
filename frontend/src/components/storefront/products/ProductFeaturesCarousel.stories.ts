/**
 * ProductFeaturesCarousel — grid 3 imágenes de features del producto.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductFeaturesCarousel from './ProductFeaturesCarousel.astro';

const meta: Meta<typeof ProductFeaturesCarousel> = {
  title: 'Storefront/Products/ProductFeaturesCarousel',
  component: ProductFeaturesCarousel,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

const PLACEHOLDER =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><rect width="400" height="500" fill="#e2e8f0"/><circle cx="200" cy="220" r="80" fill="#cbd5e1"/></svg>`
  );

const SAMPLE_ITEMS = [
  {
    image: PLACEHOLDER,
    alt: 'Pastillas V-Mod II',
    caption: 'Pastillas V-Mod II con calibrado premium.',
  },
  {
    image: PLACEHOLDER,
    alt: 'Puente tremolo',
    caption: 'Puente Pop-In de 2 puntos con selletas de acero.',
  },
  { image: PLACEHOLDER, alt: 'Brazo de arce', caption: 'Brazo de arce con perfil Modern Deep C.' },
];

export const Default: Story = {
  args: {
    heading: 'Características',
    items: SAMPLE_ITEMS,
  },
};

export const WithoutHeading: Story = {
  args: { items: SAMPLE_ITEMS },
};

export const Loading: Story = {
  args: { items: [] },
};
