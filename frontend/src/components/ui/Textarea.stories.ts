import type { Meta, StoryObj } from '@storybook/astro';
import Textarea from './Textarea.astro';

/**
 * Textarea — primitive UI.
 *
 * Igual que Input pero multilínea. Comparte convenciones de label,
 * error/hint y accesibilidad (mismos aria attributes).
 */
const meta: Meta<typeof Textarea> = {
  title: 'UI/Textarea',
  component: Textarea,
  tags: ['autodocs'],
  argTypes: {
    name: { control: 'text' },
    label: { control: 'text' },
    placeholder: { control: 'text' },
    value: { control: 'text' },
    rows: { control: { type: 'number', min: 1, max: 20 } },
    maxlength: { control: 'number' },
    required: { control: 'boolean' },
    disabled: { control: 'boolean' },
    readonly: { control: 'boolean' },
    error: { control: 'text' },
    hint: { control: 'text' },
  },
  args: {
    name: 'bio',
    label: 'Biografía',
    placeholder: 'Cuéntanos sobre ti…',
    rows: 4,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const ShortRows: Story = {
  args: {
    name: 'subject',
    label: 'Asunto',
    placeholder: 'Resumen breve…',
    rows: 2,
  },
};

export const LongRows: Story = {
  args: {
    name: 'description',
    label: 'Descripción detallada',
    placeholder: 'Describe tu producto o consulta…',
    rows: 8,
  },
};

export const WithMaxLength: Story = {
  args: {
    name: 'shortBio',
    label: 'Biografía corta',
    hint: 'Máximo 200 caracteres.',
    maxlength: 200,
    rows: 3,
  },
};

export const WithHint: Story = {
  args: {
    name: 'feedback',
    label: 'Comentarios',
    hint: 'Tu opinión nos ayuda a mejorar.',
  },
};

export const WithError: Story = {
  args: {
    name: 'message',
    label: 'Mensaje',
    value: 'x',
    error: 'El mensaje es demasiado corto.',
  },
};

export const Disabled: Story = {
  args: {
    name: 'readonly-notes',
    label: 'Notas internas',
    value: 'Este campo no es editable.',
    disabled: true,
    rows: 4,
  },
};
