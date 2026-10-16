/**
 * ProductDetailRatingRow — fila compacta de rating estilo Gibson PDP.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductDetailRatingRow from './ProductDetailRatingRow.astro';

const meta: Meta<typeof ProductDetailRatingRow> = {
  title: 'Storefront/Products/ProductDetailRatingRow',
  component: ProductDetailRatingRow,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: { rating: 4.8, count: 186 },
};

export const LowCount: Story = {
  args: { rating: 4.4, count: 5 },
};

export const Empty: Story = {
  args: { rating: 0, count: 0 },
};
