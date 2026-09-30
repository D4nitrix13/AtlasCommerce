import type { Meta, StoryObj } from '@storybook/astro';
import Badge from './Badge.astro';

/**
 * Badge — primitive UI.
 *
 * Pills semánticas. La variant principal es `status` (order status pills):
 * pending / processing / shipped / delivered / cancelled. También soporta
 * `variant` neutral/primary para uso genérico.
 */
const meta: Meta<typeof Badge> = {
  title: 'UI/Badge',
  component: Badge,
  tags: ['autodocs'],
  argTypes: {
    status: {
      control: { type: 'inline-radio' },
      options: ['pending', 'processing', 'shipped', 'delivered', 'cancelled'],
      description: 'Status semántico de pedido (overrides variant)',
    },
    variant: {
      control: { type: 'inline-radio' },
      options: ['neutral', 'primary'],
      description: 'Variante genérica (cuando status no aplica)',
    },
    label: { control: 'text' },
  },
  args: {
    status: 'pending',
    label: 'Pendiente',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Pending: Story = {
  args: { status: 'pending', label: 'Pendiente' },
};

export const Processing: Story = {
  args: { status: 'processing', label: 'Preparando' },
};

export const Shipped: Story = {
  args: { status: 'shipped', label: 'Enviado' },
};

export const Delivered: Story = {
  args: { status: 'delivered', label: 'Entregado' },
};

export const Cancelled: Story = {
  args: { status: 'cancelled', label: 'Cancelado' },
};

export const Neutral: Story = {
  args: { variant: 'neutral', label: 'Genérico' },
};

export const Primary: Story = {
  args: { variant: 'primary', label: 'Destacado' },
};

export const AllStatus: Story = {
  render: () => `
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800">Pendiente</span>
      <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-violet-100 text-violet-800">Preparando</span>
      <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-cyan-100 text-cyan-800">Enviado</span>
      <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">Entregado</span>
      <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800">Cancelado</span>
    </div>
  `,
};