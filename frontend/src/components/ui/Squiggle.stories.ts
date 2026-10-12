import type { Meta, StoryObj } from '@storybook/astro';
import Squiggle from './Squiggle.astro';

/**
 * Squiggle — primitive decorativo para inyectar el lenguaje hand-drawn
 * del home de Bonfire. Se posiciona absoluto detrás del contenido;
 * `currentColor` no se usa porque Pintamos el stroke con un color
 * literal de la paleta Bonfire (ámbar o khaki, no branded).
 */
const meta: Meta<typeof Squiggle> = {
  title: 'UI/Squiggle',
  component: Squiggle,
  tags: ['autodocs'],
  argTypes: {
    variant: {
      control: { type: 'inline-radio' },
      options: ['arc', 'wave', 'loop'],
    },
    color: { control: 'color' },
    strokeWidth: { control: { type: 'number', min: 1, max: 8 } },
    class: { control: 'text' },
  },
  args: {
    variant: 'arc',
    color: '#F6C36F',
    strokeWidth: 3,
    class: 'h-3 w-16',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Wave: Story = {
  args: { variant: 'wave', class: 'h-4 w-32' },
};

export const Loop: Story = {
  args: { variant: 'loop', class: 'h-8 w-12' },
};

export const Khaki: Story = {
  args: { color: '#D1C59E', class: 'h-3 w-20' },
};

export const Bold: Story = {
  args: { strokeWidth: 5, class: 'h-4 w-24' },
};
