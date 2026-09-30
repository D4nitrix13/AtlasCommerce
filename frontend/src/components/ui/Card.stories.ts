import type { Meta, StoryObj } from '@storybook/astro';
import Card from './Card.astro';

/**
 * Card — primitive UI.
 *
 * Caja blanca con slots semánticos (header / default / footer). El
 * padding es opcional (none/sm/md/lg) y soporta shadow configurable.
 */
const meta: Meta<typeof Card> = {
  title: 'UI/Card',
  component: Card,
  tags: ['autodocs'],
  argTypes: {
    padding: {
      control: { type: 'inline-radio' },
      options: ['none', 'sm', 'md', 'lg'],
    },
    bordered: { control: 'boolean' },
    shadow: {
      control: { type: 'inline-radio' },
      options: ['none', 'sm', 'md'],
    },
  },
  args: {
    padding: 'md',
    bordered: true,
    shadow: 'sm',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    slot: 'Contenido principal del card.',
  },
};

export const WithHeaderAndFooter: Story = {
  render: () => `
    <article class="rounded-xl bg-white border border-slate-200 shadow-sm">
      <header class="px-6 pt-6 pb-3 border-b border-slate-100">
        <h3 class="text-base font-semibold text-slate-900">Resumen del pedido</h3>
      </header>
      <div class="p-6">
        <p class="text-sm text-slate-600">3 productos · Total $145.00</p>
      </div>
      <footer class="px-6 pb-6 pt-3 border-t border-slate-100 bg-slate-50/50 rounded-b-xl">
        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-10 px-4 text-sm hover:bg-[var(--primary-hover,#334155)]">Confirmar pedido</button>
      </footer>
    </article>
  `,
};

export const PaddingOptions: Story = {
  render: () => `
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
      <article class="rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="p-3 text-sm text-slate-600">Padding sm — denso (3px 12px).</div>
      </article>
      <article class="rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="p-6 text-sm text-slate-600">Padding md — default (24px).</div>
      </article>
      <article class="rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="p-8 text-sm text-slate-600">Padding lg — amplio (32px).</div>
      </article>
      <article class="rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="p-0 text-sm text-slate-600">Padding none — para imágenes full-bleed.</div>
      </article>
    </div>
  `,
};

export const ShadowOptions: Story = {
  render: () => `
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
      <article class="rounded-xl bg-white border border-slate-200">
        <div class="p-6 text-sm text-slate-600">Sin sombra</div>
      </article>
      <article class="rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="p-6 text-sm text-slate-600">shadow-sm</div>
      </article>
      <article class="rounded-xl bg-white border border-slate-200 shadow-md">
        <div class="p-6 text-sm text-slate-600">shadow-md</div>
      </article>
    </div>
  `,
};

export const Bordered: Story = {
  args: {
    bordered: true,
    shadow: 'none',
    slot: 'Sin sombra, solo borde — para layouts planos.',
  },
};