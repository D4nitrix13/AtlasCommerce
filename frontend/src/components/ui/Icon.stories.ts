import type { Meta, StoryObj } from '@storybook/astro';
import Icon from './Icon.astro';

/**
 * Icon — Wrapper sobre lucide-react renderizado del lado Astro.
 *
 * Acepta cualquier nombre de icono del set lucide (https://lucide.dev).
 * Para React islands usar `lucide-react` directamente en lugar de este
 * componente (no agrega valor wrappeando).
 */
const meta: Meta<typeof Icon> = {
  title: 'UI/Icon',
  component: Icon,
  tags: ['autodocs'],
  argTypes: {
    name: {
      control: { type: 'select' },
      description: 'Nombre del icono (lucide-react)',
      options: ['home', 'user', 'settings', 'shopping-cart', 'package', 'box', 'check', 'x', 'menu'],
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
  render: () => (
    <div style={{ display: 'flex', gap: '1rem', alignItems: 'center' }}>
      <Icon name="shopping-cart" size={16} />
      <Icon name="shopping-cart" size={24} />
      <Icon name="shopping-cart" size={32} />
      <Icon name="shopping-cart" size={48} />
    </div>
  ),
};

export const Colored: Story = {
  args: {
    name: 'check',
    size: 32,
    class: 'text-green-500',
  },
};

export const CommonSet: Story = {
  render: () => (
    <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
      <Icon name="home" size={24} />
      <Icon name="user" size={24} />
      <Icon name="settings" size={24} />
      <Icon name="shopping-cart" size={24} />
      <Icon name="package" size={24} />
      <Icon name="box" size={24} />
      <Icon name="check" size={24} class="text-green-500" />
      <Icon name="x" size={24} class="text-red-500" />
      <Icon name="menu" size={24} />
    </div>
  ),
};
