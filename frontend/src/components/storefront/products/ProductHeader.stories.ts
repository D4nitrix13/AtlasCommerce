/**
 * ProductHeader — cabecera del panel de info del producto.
 *
 * Slots:
 *   - eyebrow (categoría) arriba del título.
 *   - h1 con el nombre del producto.
 *   - Badge "Highly Rated" opcional cerca del título.
 *   - Rating row con estrella + promedio + count (cuando hay reviews).
 *
 * Adaptado del patrón Nike pero localizado al dominio de La Voz
 * Instrumentos (eyebrow = categoría del instrumento: guitarras, bajos,
 * baterías, amplificadores, accesorios).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductHeader from './ProductHeader.astro';

const meta: Meta<typeof ProductHeader> = {
  title: 'Storefront/Products/ProductHeader',
  component: ProductHeader,
  tags: ['autodocs'],
  argTypes: {
    eyebrow: { control: 'text' },
    title: { control: 'text' },
    highlyRated: { control: 'boolean' },
    highlyRatedLabel: { control: 'text' },
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    eyebrow: 'Guitarras eléctricas',
    title: 'Fender American Professional II Stratocaster',
    rating: { average: 4.7, count: 124 },
  },
};

export const WithHighlyRatedBadge: Story = {
  args: {
    eyebrow: 'Amplificadores',
    title: 'Marshall JCM800 2203 — 50W Tube Head',
    rating: { average: 4.9, count: 86 },
    highlyRated: true,
    highlyRatedLabel: 'Muy bien valorado',
  },
};

export const Loading: Story = {
  args: {
    title: 'Cargando producto…',
  },
};

export const Empty: Story = {
  args: {
    eyebrow: 'Accesorios',
    title: 'Cuerdas D\'Addario NYXL .010–.046',
  },
};
