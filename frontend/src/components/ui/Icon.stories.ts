import type { Meta, StoryObj } from '@storybook/astro';
import Icon from './Icon.astro';

/**
 * Icon — Wrapper sobre lucide-react renderizado del lado Astro.
 *
 * Acepta cualquier nombre de icono del set lucide (https://lucide.dev).
 */
const meta: Meta<typeof Icon> = {
  title: 'UI/Icon',
  component: Icon,
  tags: ['autodocs'],
  argTypes: {
    name: {
      control: { type: 'select' },
      description: 'Nombre del icono (lucide-react)',
      options: ['home', 'user', 'settings', 'shopping-cart', 'package', 'box', 'check', 'x'],
    },
    size: {
      control: { type: 'number', min: 12, max: 96, step: 4 },
      description: 'Tamaño en px (default 24)',
    },
    class: {
      control: { type: 'text' },
      description: 'Clases Tailwind adicionales (color, margin, etc.)',
    },
  },
  args: {
    name: 'home',
    size: 24,
    class: '',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Sizes: Story = {
  render: () => `
    <div style="display: flex; gap: 1rem; align-items: center;">
      <icon-wrap data-name="shopping-cart" data-size="16"></icon-wrap>
      <icon-wrap data-name="shopping-cart" data-size="24"></icon-wrap>
      <icon-wrap data-name="shopping-cart" data-size="32"></icon-wrap>
      <icon-wrap data-name="shopping-cart" data-size="48"></icon-wrap>
    </div>
  `,
};

export const Colored: Story = {
  args: {
    name: 'check',
    size: 32,
    class: 'text-green-500',
  },
};
