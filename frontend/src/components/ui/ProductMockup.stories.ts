import type { Meta, StoryObj } from '@storybook/astro';
import ProductMockup from './ProductMockup.astro';

/**
 * ProductMockup — primitive SVG de mockup de producto estampable. Usado
 * en el home B2B Panda para sustituir fotos de producto (el sprint
 * anterior no podía generarlas). Tenant-aware vía `--primary` para el
 * color del logo estampado.
 */
const meta: Meta<typeof ProductMockup> = {
  title: 'UI/ProductMockup',
  component: ProductMockup,
  tags: ['autodocs'],
  argTypes: {
    variant: {
      control: { type: 'inline-radio' },
      options: ['shirt', 'hoodie', 'mug', 'hat'],
    },
    accentColor: { control: 'color' },
    logoText: { control: 'text' },
    rotation: { control: { type: 'number', min: -15, max: 15 } },
    class: { control: 'text' },
  },
  args: {
    variant: 'shirt',
    accentColor: '#2D9D8E',
    logoText: 'Panda',
    rotation: 0,
    class: 'h-40 w-40',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Shirt: Story = { args: { variant: 'shirt' } };
export const Hoodie: Story = { args: { variant: 'hoodie' } };
export const Mug: Story = { args: { variant: 'mug' } };
export const Hat: Story = { args: { variant: 'hat' } };

export const TiltedLeft: Story = {
  args: { rotation: -8 },
};

export const TiltedRight: Story = {
  args: { rotation: 12 },
};

export const CustomBrand: Story = {
  args: { logoText: 'Acme', accentColor: '#1F5C47' },
};
