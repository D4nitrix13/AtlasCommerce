import type { Meta, StoryObj } from '@storybook/astro';
import Input from './Input.astro';

/**
 * Input — primitive UI.
 *
 * Wrapper accesible de `<input>` con label asociado. Soporta tipos
 * text/email/password/number/tel/url/search, estados (error con
 * mensaje) y hint opcional.
 */
const meta: Meta<typeof Input> = {
  title: 'UI/Input',
  component: Input,
  tags: ['autodocs'],
  argTypes: {
    name: { control: 'text' },
    id: { control: 'text' },
    type: {
      control: { type: 'select' },
      options: ['text', 'email', 'password', 'number', 'tel', 'url', 'search'],
    },
    label: { control: 'text' },
    placeholder: { control: 'text' },
    value: { control: 'text' },
    required: { control: 'boolean' },
    disabled: { control: 'boolean' },
    readonly: { control: 'boolean' },
    error: { control: 'text', description: 'Mensaje de error (aria-invalid)' },
    hint: { control: 'text', description: 'Texto de ayuda bajo el input' },
    autocomplete: { control: 'text' },
    min: { control: 'text' },
    max: { control: 'text' },
    pattern: { control: 'text' },
    inputmode: {
      control: { type: 'select' },
      options: ['text', 'numeric', 'decimal', 'tel', 'email', 'url', 'search'],
    },
  },
  args: {
    name: 'email',
    type: 'email',
    label: 'Correo electrónico',
    placeholder: 'tu@correo.com',
    required: true,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const TextType: Story = {
  args: {
    name: 'fullName',
    type: 'text',
    label: 'Nombre completo',
    placeholder: 'María González',
  },
};

export const Password: Story = {
  args: {
    name: 'password',
    type: 'password',
    label: 'Contraseña',
    placeholder: '••••••••',
  },
};

export const Number: Story = {
  args: {
    name: 'quantity',
    type: 'number',
    label: 'Cantidad',
    value: 1,
    min: 1,
    max: 99,
  },
};

export const Tel: Story = {
  args: {
    name: 'phone',
    type: 'tel',
    label: 'Teléfono',
    placeholder: '+505 8888 8888',
    inputmode: 'tel',
  },
};

export const Url: Story = {
  args: {
    name: 'website',
    type: 'url',
    label: 'Sitio web',
    placeholder: 'https://ejemplo.com',
  },
};

export const Search: Story = {
  args: {
    name: 'q',
    type: 'search',
    label: 'Buscar productos',
    placeholder: 'Buscar en el catálogo…',
  },
};

export const WithHint: Story = {
  args: {
    name: 'username',
    type: 'text',
    label: 'Nombre de usuario',
    hint: 'Mínimo 3 caracteres, solo letras y números.',
  },
};

export const WithError: Story = {
  args: {
    name: 'email',
    type: 'email',
    label: 'Correo electrónico',
    value: 'no-es-un-email',
    error: 'Ingresa un correo válido.',
  },
};

export const Required: Story = {
  args: {
    name: 'email',
    type: 'email',
    label: 'Correo electrónico',
    required: true,
    hint: 'Requerido para continuar.',
  },
};

export const Disabled: Story = {
  args: {
    name: 'email',
    type: 'email',
    label: 'Correo (no editable)',
    value: 'usuario@ejemplo.com',
    disabled: true,
  },
};
