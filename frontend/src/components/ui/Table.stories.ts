import type { Meta, StoryObj } from '@storybook/astro';
import Table from './Table.astro';

/**
 * Table — primitive UI.
 *
 * Wrapper semántico de `<table>` con slots para thead/tbody/tfoot.
 * Soporta header sticky, filas striped y borde opcional.
 */
const meta: Meta<typeof Table> = {
  title: 'UI/Table',
  component: Table,
  tags: ['autodocs'],
  argTypes: {
    caption: { control: 'text' },
    sticky: { control: 'boolean', description: 'Header sticky en scroll vertical' },
    striped: { control: 'boolean' },
    bordered: { control: 'boolean' },
  },
  args: {
    caption: 'Pedidos recientes',
    sticky: false,
    striped: true,
    bordered: true,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    'header:0': '<tr><th class="px-4 py-2 text-left">ID</th><th class="px-4 py-2 text-left">Cliente</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-left">Status</th></tr>',
    'row:0': '<tr><td class="px-4 py-2 font-medium">#12345</td><td class="px-4 py-2">María G.</td><td class="px-4 py-2 text-right">$45.00</td><td class="px-4 py-2">Entregado</td></tr>',
    'row:1': '<tr><td class="px-4 py-2 font-medium">#12340</td><td class="px-4 py-2">Carlos R.</td><td class="px-4 py-2 text-right">$120.00</td><td class="px-4 py-2">Enviado</td></tr>',
    'row:2': '<tr><td class="px-4 py-2 font-medium">#12338</td><td class="px-4 py-2">Ana P.</td><td class="px-4 py-2 text-right">$34.00</td><td class="px-4 py-2">Entregado</td></tr>',
  },
  render: () => `
    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <table class="w-full text-left text-sm">
        <caption class="px-4 py-2 text-left text-xs font-medium text-slate-500">Pedidos recientes</caption>
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th scope="col" class="px-4 py-2 text-left">ID</th>
            <th scope="col" class="px-4 py-2 text-left">Cliente</th>
            <th scope="col" class="px-4 py-2 text-right">Total</th>
            <th scope="col" class="px-4 py-2 text-left">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/50">
          <tr>
            <td class="px-4 py-2 font-medium">#12345</td>
            <td class="px-4 py-2">María G.</td>
            <td class="px-4 py-2 text-right">$45.00</td>
            <td class="px-4 py-2">Entregado</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-medium">#12340</td>
            <td class="px-4 py-2">Carlos R.</td>
            <td class="px-4 py-2 text-right">$120.00</td>
            <td class="px-4 py-2">Enviado</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-medium">#12338</td>
            <td class="px-4 py-2">Ana P.</td>
            <td class="px-4 py-2 text-right">$34.00</td>
            <td class="px-4 py-2">Entregado</td>
          </tr>
        </tbody>
      </table>
    </div>
  `,
};

export const StickyHeader: Story = {
  render: () => `
    <div class="overflow-y-auto rounded-lg border border-slate-200" style="max-height: 220px;">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 sticky top-0 z-10">
          <tr>
            <th scope="col" class="px-4 py-2">Producto</th>
            <th scope="col" class="px-4 py-2">Stock</th>
            <th scope="col" class="px-4 py-2">Precio</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          ${Array.from({length: 10}).map((_, i) => `
            <tr>
              <td class="px-4 py-2">Producto ${i + 1}</td>
              <td class="px-4 py-2">${10 + i}</td>
              <td class="px-4 py-2">$${(10 + i).toFixed(2)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `,
};

export const NotStriped: Story = {
  render: () => `
    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr><th class="px-4 py-2">A</th><th class="px-4 py-2">B</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <tr><td class="px-4 py-2">1</td><td class="px-4 py-2">2</td></tr>
          <tr><td class="px-4 py-2">3</td><td class="px-4 py-2">4</td></tr>
          <tr><td class="px-4 py-2">5</td><td class="px-4 py-2">6</td></tr>
        </tbody>
      </table>
    </div>
  `,
};

export const WithFooter: Story = {
  render: () => `
    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr><th class="px-4 py-2">Concepto</th><th class="px-4 py-2 text-right">Monto</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <tr><td class="px-4 py-2">Subtotal</td><td class="px-4 py-2 text-right">$145.00</td></tr>
          <tr><td class="px-4 py-2">Envío</td><td class="px-4 py-2 text-right">$5.00</td></tr>
        </tbody>
        <tfoot class="bg-slate-50">
          <tr><td class="px-4 py-2 font-semibold">Total</td><td class="px-4 py-2 text-right font-bold">$150.00</td></tr>
        </tfoot>
      </table>
    </div>
  `,
};