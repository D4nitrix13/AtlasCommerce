import type { Meta, StoryObj } from '@storybook/astro';
import SortDropdown from './SortDropdown.astro';

const LABELS = {
  label: 'Ordenar por',
  relevancia: 'Relevancia',
  precioAsc: 'Menor precio',
  precioDesc: 'Mayor precio',
  nombre: 'Nombre',
};

const meta: Meta<typeof SortDropdown> = {
  title: 'Storefront/Catalog/SortDropdown',
  component: SortDropdown,
  tags: ['autodocs'],
  args: {
    labels: LABELS,
    value: 'relevancia',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const PriceAsc: Story = {
  args: { value: 'precioAsc' },
};

export const PriceDesc: Story = {
  args: { value: 'precioDesc' },
};

export const Name: Story = {
  args: { value: 'nombre' },
};