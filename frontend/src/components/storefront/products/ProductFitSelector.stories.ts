/**
 * ProductFitSelector — pill segmentado (ej. "Nuevo | Usado").
 *
 * Variantes: Default (2 opciones), Selected (cambia la seleccionada),
 * Empty (una sola opción de fallback).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductFitSelector from './ProductFitSelector.astro';

const meta: Meta<typeof ProductFitSelector> = {
  title: 'Storefront/Products/ProductFitSelector',
  component: ProductFitSelector,
  tags: ['autodocs'],
  argTypes: {
    legend: { control: 'text' },
    selected: { control: 'text' },
    name: { control: 'text' },
  },
  args: {
    legend: 'Condición',
    name: 'condition',
    options: [
      { id: 'new', label: 'Nuevo' },
      { id: 'used', label: 'Usado' },
    ],
    selected: 'new',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Selected: Story = {
  args: { selected: 'used' },
};

export const ThreeOptions: Story = {
  args: {
    legend: 'Tipo',
    name: 'type',
    options: [
      { id: 'acoustic', label: 'Acústica' },
      { id: 'electric', label: 'Eléctrica' },
      { id: 'electroacoustic', label: 'Electroacústica' },
    ],
  },
};

export const Empty: Story = {
  args: { options: [{ id: 'new', label: 'Nuevo' }] },
};
