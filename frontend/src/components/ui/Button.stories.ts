import type { Meta, StoryObj } from '@storybook/astro';
import Button from './Button.astro';

/**
 * Button — primitive UI.
 *
 * Soporta 4 variantes visuales y 3 tamaños. Estados `loading` y
 * `disabled` se controlan desde fuera. Accesibilidad: `<button>` real,
 * aria-busy cuando loading.
 */
const meta: Meta<typeof Button> = {
  title: 'UI/Button',
  component: Button,
  tags: ['autodocs'],
  argTypes: {
    variant: {
      control: { type: 'select' },
      description: 'Variante visual',
      options: ['primary', 'secondary', 'danger', 'ghost'],
    },
    size: {
      control: { type: 'inline-radio' },
      description: 'Tamaño',
      options: ['sm', 'md', 'lg'],
    },
    type: {
      control: { type: 'inline-radio' },
      options: ['button', 'submit', 'reset'],
    },
    loading: { control: 'boolean', description: 'Spinner + aria-busy' },
    disabled: { control: 'boolean' },
    fullWidth: { control: 'boolean' },
    href: { control: 'text', description: 'Si se pasa, renderiza <a>' },
    ariaLabel: { control: 'text' },
  },
  args: {
    variant: 'primary',
    size: 'md',
    type: 'button',
    loading: false,
    disabled: false,
    fullWidth: false,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    slot: 'Guardar',
  },
};

export const Variants: Story = {
  render: () => `
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white hover:bg-[var(--primary-hover,#334155)] h-10 px-4 text-sm">Primary</button>
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 h-10 px-4 text-sm">Secondary</button>
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-red-600 text-white hover:bg-red-700 h-10 px-4 text-sm">Danger</button>
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-transparent text-slate-700 hover:bg-slate-100 h-10 px-4 text-sm">Ghost</button>
    </div>
  `,
};

export const Sizes: Story = {
  render: () => `
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-8 px-3 text-xs">Pequeño (sm)</button>
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-10 px-4 text-sm">Mediano (md)</button>
      <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-12 px-6 text-base">Grande (lg)</button>
    </div>
  `,
};

export const Loading: Story = {
  render: () => `
    <button type="button" disabled aria-busy="true" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-10 px-4 text-sm cursor-not-allowed opacity-60">
      <span aria-hidden="true" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
      Enviando…
    </button>
  `,
};

export const Disabled: Story = {
  render: () => `
    <button type="button" disabled class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-10 px-4 text-sm cursor-not-allowed opacity-60">
      No disponible
    </button>
  `,
};

export const AsLink: Story = {
  args: {
    variant: 'primary',
    size: 'md',
    href: '/products',
  },
  render: () => `
    <a href="/products" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-[var(--primary,#475569)] text-white h-10 px-4 text-sm hover:bg-[var(--primary-hover,#334155)]">
      Ver productos
    </a>
  `,
};
