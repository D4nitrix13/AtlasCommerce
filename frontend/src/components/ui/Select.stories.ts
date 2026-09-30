import type { Meta, StoryObj } from '@storybook/astro';
import Select from './Select.astro';

/**
 * Select — primitive UI.
 *
 * Wrapper accesible del `<select>` nativo. Las opciones se pasan por
 * prop `options` (label/value) o por slot default para casos custom.
 */
const meta: Meta<typeof Select> = {
  title: 'UI/Select',
  component: Select,
  tags: ['autodocs'],
  argTypes: {
    name: { control: 'text' },
    label: { control: 'text' },
    placeholder: { control: 'text' },
    required: { control: 'boolean' },
    disabled: { control: 'boolean' },
    error: { control: 'text' },
    hint: { control: 'text' },
    options: { control: 'object' },
  },
  args: {
    name: 'country',
    label: 'País',
    placeholder: 'Selecciona un país',
    options: [
      { value: 'NI', label: 'Nicaragua' },
      { value: 'CR', label: 'Costa Rica' },
      { value: 'PA', label: 'Panamá' },
      { value: 'MX', label: 'México' },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Required: Story = {
  args: {
    name: 'country',
    label: 'País',
    required: true,
    placeholder: 'Selecciona un país (requerido)',
    options: [
      { value: 'NI', label: 'Nicaragua' },
      { value: 'CR', label: 'Costa Rica' },
    ],
  },
};

export const WithSelectedValue: Story = {
  args: {
    name: 'language',
    label: 'Idioma',
    value: 'es',
    options: [
      { value: 'es', label: 'Español' },
      { value: 'en', label: 'English' },
      { value: 'ja', label: '日本語' },
    ],
  },
};

export const WithDisabledOption: Story = {
  args: {
    name: 'shipping',
    label: 'Método de envío',
    options: [
      { value: 'standard', label: 'Estándar (3-5 días)' },
      { value: 'express', label: 'Express (24h)', disabled: true },
      { value: 'pickup', label: 'Recoger en tienda' },
    ],
  },
};

export const WithHint: Story = {
  args: {
    name: 'category',
    label: 'Categoría',
    hint: 'Cambiar la categoría recarga los productos.',
    options: [
      { value: 'figures', label: 'Figures' },
      { value: 'manga', label: 'Manga' },
      { value: 'apparel', label: 'Apparel' },
    ],
  },
};

export const WithError: Story = {
  args: {
    name: 'country',
    label: 'País',
    error: 'Selecciona un país antes de continuar.',
    options: [
      { value: 'NI', label: 'Nicaragua' },
      { value: 'CR', label: 'Costa Rica' },
    ],
  },
};

export const Disabled: Story = {
  args: {
    name: 'locked',
    label: 'País (bloqueado)',
    value: 'NI',
    disabled: true,
    options: [{ value: 'NI', label: 'Nicaragua' }],
  },
};

export const WithOptgroup: Story = {
  args: {
    name: 'shipping-region',
    label: 'Región de envío',
  },
  render: () => `
    <div class="flex flex-col gap-1.5">
      <label for="select-shipping-region" class="text-sm font-medium text-slate-700">Región de envío</label>
      <select id="select-shipping-region" name="shipping-region" class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 transition border-slate-200 focus:border-slate-400 focus:ring-slate-200">
        <optgroup label="América Central">
          <option value="NI">Nicaragua</option>
          <option value="CR">Costa Rica</option>
          <option value="PA">Panamá</option>
        </optgroup>
        <optgroup label="Norteamérica">
          <option value="MX">México</option>
          <option value="US">Estados Unidos</option>
        </optgroup>
        <optgroup label="Asia (próximamente)" disabled>
          <option value="JP">Japón</option>
        </optgroup>
      </select>
      <p class="text-xs text-slate-500">Selecciona la región donde deseas recibir tu pedido.</p>
    </div>
  `,
};