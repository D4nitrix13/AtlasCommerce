import type { Meta, StoryObj } from '@storybook/astro';
import Dropdown from './Dropdown.astro';

/**
 * Dropdown — primitive UI.
 *
 * Menú accesible construido con `<details>`/`<summary>`. Items con
 * role="menuitem" dentro de `<ul role="menu">`. Soporta alineación
 * izquierda/derecha.
 */
const meta: Meta<typeof Dropdown> = {
  title: 'UI/Dropdown',
  component: Dropdown,
  tags: ['autodocs'],
  argTypes: {
    label: { control: 'text' },
    align: {
      control: { type: 'inline-radio' },
      options: ['left', 'right'],
    },
  },
  args: {
    label: 'Cuenta',
    align: 'right',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  render: () => `
    <details class="relative">
      <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] [&::-webkit-details-marker]:hidden">
        Cuenta
        <span aria-hidden="true" class="text-xs">▾</span>
      </summary>
      <ul role="menu" class="absolute z-20 mt-1 min-w-[10rem] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg right-0">
        <li role="menuitem"><a href="/account" class="block px-3 py-1.5 hover:bg-slate-50">Mi cuenta</a></li>
        <li role="menuitem"><a href="/orders" class="block px-3 py-1.5 hover:bg-slate-50">Mis pedidos</a></li>
        <li role="menuitem"><a href="/wishlist" class="block px-3 py-1.5 hover:bg-slate-50">Wishlist</a></li>
        <li role="separator" class="my-1 border-t border-slate-100"></li>
        <li role="menuitem"><a href="/logout" class="block px-3 py-1.5 text-red-600 hover:bg-red-50">Cerrar sesión</a></li>
      </ul>
    </details>
  `,
};

export const AlignLeft: Story = {
  args: {
    align: 'left',
    label: 'Ordenar por',
  },
  render: () => `
    <details class="relative">
      <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] [&::-webkit-details-marker]:hidden">
        Ordenar por
        <span aria-hidden="true" class="text-xs">▾</span>
      </summary>
      <ul role="menu" class="absolute z-20 mt-1 min-w-[10rem] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg left-0">
        <li role="menuitem"><a href="?sort=relevance" class="block px-3 py-1.5 hover:bg-slate-50">Relevancia</a></li>
        <li role="menuitem"><a href="?sort=price-asc" class="block px-3 py-1.5 hover:bg-slate-50">Precio: menor primero</a></li>
        <li role="menuitem"><a href="?sort=price-desc" class="block px-3 py-1.5 hover:bg-slate-50">Precio: mayor primero</a></li>
        <li role="menuitem"><a href="?sort=newest" class="block px-3 py-1.5 hover:bg-slate-50">Más nuevos</a></li>
      </ul>
    </details>
  `,
};

export const SimpleMenu: Story = {
  render: () => `
    <details class="relative">
      <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] [&::-webkit-details-marker]:hidden">
        Acciones
        <span aria-hidden="true" class="text-xs">▾</span>
      </summary>
      <ul role="menu" class="absolute z-20 mt-1 min-w-[10rem] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg right-0">
        <li role="menuitem"><button type="button" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50">Editar</button></li>
        <li role="menuitem"><button type="button" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50">Duplicar</button></li>
        <li role="menuitem"><button type="button" class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50">Eliminar</button></li>
      </ul>
    </details>
  `,
};