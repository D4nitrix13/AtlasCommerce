/**
 * ProductDetailFinancingBlock — opciones de financiamiento debajo del CTA.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductDetailFinancingBlock from './ProductDetailFinancingBlock.astro';

const meta: Meta<typeof ProductDetailFinancingBlock> = {
  title: 'Storefront/Products/ProductDetailFinancingBlock',
  component: ProductDetailFinancingBlock,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    syncLine: 'Sin intereses. $223/mes con financiamiento a 36 meses.',
    shopPayLine: 'Desde $721.98/mes con Shop Pay.',
    learnMoreLabel: 'Saber más',
  },
};

export const EnglishLocale: Story = {
  args: {
    syncLine: 'Interest-free. $223/mo with 36-month financing.',
    shopPayLine: 'From $721.98/mo with Shop Pay.',
    learnMoreLabel: 'Learn how',
  },
};