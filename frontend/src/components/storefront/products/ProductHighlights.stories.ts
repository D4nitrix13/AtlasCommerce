/**
 * ProductHighlights — bullets visuales con icono + label (estilo Gibson).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductHighlights from './ProductHighlights.astro';

const meta: Meta<typeof ProductHighlights> = {
  title: 'Storefront/Products/ProductHighlights',
  component: ProductHighlights,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

const SAMPLE_ITEMS = [
  { icon: 'award' as const, label: 'Hecho a mano en California' },
  { icon: 'sparkle' as const, label: 'Pastillas hand-wound calibradas a mano' },
  { icon: 'ruler' as const, label: 'Escala 25.5" con trastes Narrow Tall' },
  { icon: 'shield' as const, label: 'Garantía limitada de por vida' },
  { icon: 'star' as const, label: 'El más vendido del 2025' },
  { icon: 'music' as const, label: 'Setup profesional incluido' },
];

export const Default: Story = {
  args: {
    heading: 'Características destacadas',
    items: SAMPLE_ITEMS,
  },
};

export const Single: Story = {
  args: {
    heading: 'Características destacadas',
    items: [SAMPLE_ITEMS[0]],
  },
};

export const Empty: Story = {
  args: {
    heading: 'Características destacadas',
    items: [],
  },
};
