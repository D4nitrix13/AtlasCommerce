/**
 * ProductFinishTiles — fichas de muestra de acabado.
 *
 * Variantes: Default (con imagen), Selected, NoImage (fallback hex).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductFinishTiles from './ProductFinishTiles.astro';

const meta: Meta<typeof ProductFinishTiles> = {
  title: 'Storefront/Products/ProductFinishTiles',
  component: ProductFinishTiles,
  tags: ['autodocs'],
  args: {
    ariaLabel: 'Selector de acabado',
    labelPrefix: 'Cambiar a',
    selectedId: 'blanco',
    options: [
      {
        id: 'blanco',
        label: 'Blanco',
        hex: '#f5f5f4',
        previewSrc:
          'https://placehold.co/160x160/f5f5f4/1c1917?text=Blanco',
      },
      {
        id: 'negro',
        label: 'Negro',
        hex: '#292524',
        previewSrc:
          'https://placehold.co/160x160/292524/ffffff?text=Negro',
      },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Selected: Story = {
  args: { selectedId: 'negro' },
};

export const NoImage: Story = {
  args: {
    selectedId: 'blanco',
    options: [
      { id: 'blanco', label: 'Blanco', hex: '#f5f5f4', previewSrc: '' },
      { id: 'negro', label: 'Negro', hex: '#292524', previewSrc: '' },
      { id: 'arena', label: 'Arena', hex: null, previewSrc: '' },
    ],
  },
};
