/**
 * ProductSizeGrid — fila inline de pills de tamaño.
 *
 * Variantes: Default (4 tallas), Selected, Disabled, Empty.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductSizeGrid from './ProductSizeGrid.astro';

const meta: Meta<typeof ProductSizeGrid> = {
  title: 'Storefront/Products/ProductSizeGrid',
  component: ProductSizeGrid,
  tags: ['autodocs'],
  args: {
    legend: 'Tamaño',
    name: 'size',
    options: [
      { id: '14', label: '14"' },
      { id: '34', label: '3/4' },
      { id: '44', label: '4/4' },
      { id: '716', label: '7/16' },
    ],
    selected: '44',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Selected: Story = {
  args: { selected: '34' },
};

export const Unselected: Story = {
  args: { selected: undefined },
};

export const Disabled: Story = {
  args: {
    disabledIds: ['14', '716'],
  },
};

export const Empty: Story = {
  args: { options: [] },
};
