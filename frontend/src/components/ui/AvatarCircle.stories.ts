import type { Meta, StoryObj } from '@storybook/astro';
import AvatarCircle from './AvatarCircle.astro';

/**
 * AvatarCircle — primitive de avatar con iniciales y gradiente del
 * tenant. Tenant-aware vía `--primary`. La rotación entre -8 y +13
 * grados replica el lenguaje Bonfire de "fotos torcidas" sin
 * exponer PII del cliente.
 */
const meta: Meta<typeof AvatarCircle> = {
  title: 'UI/AvatarCircle',
  component: AvatarCircle,
  tags: ['autodocs'],
  argTypes: {
    name: { control: 'text' },
    role: { control: 'text' },
    accentColor: { control: 'color' },
    rotation: { control: { type: 'number', min: -15, max: 15 } },
    size: {
      control: { type: 'inline-radio' },
      options: ['sm', 'md', 'lg', 'xl'],
    },
    class: { control: 'text' },
  },
  args: {
    name: 'María González',
    role: undefined,
    accentColor: '#2D9D8E',
    rotation: 0,
    size: 'md',
    class: '',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithRole: Story = {
  args: {
    name: 'María González',
    role: 'Coordinadora de Eventos, Restaurante La Esquina',
  },
};

export const TiltedLeft: Story = {
  args: { name: 'Carlos Mendoza', rotation: -8 },
};

export const TiltedRight: Story = {
  args: { name: 'Andrea Ruiz', rotation: 10 },
};

export const Large: Story = {
  args: { name: 'Andrea Ruiz', size: 'xl', rotation: 5 },
};

export const SingleName: Story = {
  args: { name: 'Cher', size: 'sm' },
};

export const EmptyName: Story = {
  args: { name: '' },
};
