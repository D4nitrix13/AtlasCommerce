/**
 * ProductShippingAccordion — accordion inline de "Shipping & Returns".
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductShippingAccordion from './ProductShippingAccordion.astro';

const meta: Meta<typeof ProductShippingAccordion> = {
  title: 'Storefront/Products/ProductShippingAccordion',
  component: ProductShippingAccordion,
  tags: ['autodocs'],
  args: {
    label: 'Envío y devoluciones',
    content:
      '<p>Envío gratis a todo el país en compras mayores a $50. Devoluciones dentro de 30 días sin preguntas.</p><p>Garantía oficial del fabricante de 12 meses para todos los instrumentos.</p>',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Loading: Story = {
  args: {
    label: 'Cargando información de envío…',
    content: '<p>Un momento por favor.</p>',
  },
};

export const Empty: Story = {
  args: {
    label: 'Envío y devoluciones',
    content: '<p>Información no disponible todavía.</p>',
  },
};
