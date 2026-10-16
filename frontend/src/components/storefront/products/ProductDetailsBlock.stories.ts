/**
 * ProductDetailsBlock — bloque "Tech specs" bajo la gallery.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductDetailsBlock from './ProductDetailsBlock.astro';

const meta: Meta<typeof ProductDetailsBlock> = {
  title: 'Storefront/Products/ProductDetailsBlock',
  component: ProductDetailsBlock,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

const SAMPLE_SPECS = [
  { label: 'Cuerpo', value: 'Aliso' },
  { label: 'Brazo', value: 'Arce con perfil "Modern Deep C"' },
  { label: 'Escala', value: '25.5" (648 mm)' },
  { label: 'Trastes', value: '22, Narrow Tall' },
  { label: 'Pastillas', value: '3 × V-Mod II Single-Coil' },
  { label: 'Acabado', value: 'Gloss urethane' },
];

const SAMPLE_INTRO = `<p>Construida en Corona (California), la Stratocaster American Pro II incorpora décadas de feedback de artistas profesionales.</p><p>El cuerpo de aliso y el brazo de arce con perfil Modern Deep C ofrecen un balance familiar pero con un sustain mejorado.</p>`;

export const Default: Story = {
  args: {
    introLabel: 'Tecnología y especificaciones',
    intro: SAMPLE_INTRO,
    specs: SAMPLE_SPECS,
    specsHeading: 'Ficha técnica',
  },
};

export const Loading: Story = {
  args: {
    introLabel: 'Cargando…',
    intro: '<p>Cargando detalles del instrumento.</p>',
    specs: [],
  },
};

export const Empty: Story = {
  args: {
    introLabel: 'Tecnología y especificaciones',
    intro: '<p>El fabricante no publicó descripción extendida para este instrumento.</p>',
    specs: [],
  },
};
