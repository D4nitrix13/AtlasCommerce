/**
 * ProductSpecificationsByCategory — specs agrupadas por categoría (estilo Gibson).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductSpecificationsByCategory from './ProductSpecificationsByCategory.astro';

const meta: Meta<typeof ProductSpecificationsByCategory> = {
  title: 'Storefront/Products/ProductSpecificationsByCategory',
  component: ProductSpecificationsByCategory,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

const SAMPLE_GROUPS = [
  {
    category: 'Cuerpo',
    items: [
      { label: 'Madera', value: 'Aliso' },
      { label: 'Acabado', value: 'Gloss urethane' },
      { label: 'Forma', value: 'Stratocaster' },
    ],
  },
  {
    category: 'Mástil',
    items: [
      { label: 'Madera', value: 'Arce' },
      { label: 'Perfil', value: 'Modern Deep C' },
      { label: 'Escala', value: '25.5" (648 mm)' },
      { label: 'Trastes', value: '22, Narrow Tall' },
    ],
  },
  {
    category: 'Electrónica',
    items: [
      { label: 'Pastillas', value: '3 × V-Mod II Single-Coil' },
      { label: 'Controles', value: 'Volumen + 2 tonos + 5-way switch' },
    ],
  },
  {
    category: 'Hardware',
    items: [
      { label: 'Puente', value: '2 puntos con selletas de acero' },
      { label: 'Clavijero', value: 'Standard sealed, 18:1 ratio' },
      { label: 'Cuerdas', value: 'Fender USA 250L .009–.042' },
    ],
  },
];

export const Default: Story = {
  args: {
    heading: 'Especificaciones',
    groups: SAMPLE_GROUPS,
  },
};

export const SingleGroup: Story = {
  args: {
    heading: 'Especificaciones',
    groups: [SAMPLE_GROUPS[0]],
  },
};

export const Empty: Story = {
  args: {
    heading: 'Especificaciones',
    groups: [],
  },
};
