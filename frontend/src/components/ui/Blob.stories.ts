import type { Meta, StoryObj } from '@storybook/astro';
import Blob from './Blob.astro';

/**
 * Blob — primitive decorativo de fondo orgánico. Se posiciona absoluto
 * detrás del contenido principal (z-index bajo) para sumar la textura
 * pastel sin tapar texto. Border-radius irregular hardcoded según
 * design-references-bonfire §4.
 */
const meta: Meta<typeof Blob> = {
  title: 'UI/Blob',
  component: Blob,
  tags: ['autodocs'],
  argTypes: {
    color: {
      control: { type: 'inline-radio' },
      options: ['peach', 'mint', 'amber'],
    },
    size: {
      control: { type: 'inline-radio' },
      options: ['sm', 'md', 'lg'],
    },
    class: { control: 'text' },
  },
  args: {
    color: 'peach',
    size: 'md',
    class: '',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const MintSmall: Story = {
  args: { color: 'mint', size: 'sm' },
};

export const AmberLarge: Story = {
  args: { color: 'amber', size: 'lg' },
};

export const AbsolutelyPositioned: Story = {
  args: {
    color: 'peach',
    size: 'md',
    class: 'absolute -top-12 -right-12 opacity-60',
  },
};
