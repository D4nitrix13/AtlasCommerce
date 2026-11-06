/**
 * ProductDetailsBlock — contenido del panel de descripción.
 *
 * Variantes: WithHeading (heading propio), NoHeading (vive dentro de un
 * tab que ya nombra la sección — el caso del PDP), Short (sin toggle),
 * Inline.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductDetailsBlock from './ProductDetailsBlock.astro';

const meta: Meta<typeof ProductDetailsBlock> = {
  title: 'Storefront/Products/ProductDetailsBlock',
  component: ProductDetailsBlock,
  tags: ['autodocs'],
  args: {
    intro:
      '<p>Taza de cerámica para estampado por sublimación. Apto para horno de transferencia.</p><p>Disponible en 11 oz y 15 oz, en acabado blanco brillante y negro. La impresión se realiza sobre la superficie esmaltada, por lo que conviene evitar el lavavajillas para prolongar la vida del estampado.</p>',
    moreLabel: 'Leer más',
    lessLabel: 'Leer menos',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const NoHeading: Story = {};

export const WithHeading: Story = {
  args: { heading: 'Descripción' },
};

export const Short: Story = {
  args: {
    heading: 'Descripción',
    intro: '<p>Taza de cerámica para estampado. Disponible en 11 oz y 15 oz.</p>',
  },
};

export const Inline: Story = {
  args: {
    variant: 'inline',
    intro: '<p>Taza de cerámica para estampado. Disponible en 11 oz y 15 oz.</p>',
  },
};
