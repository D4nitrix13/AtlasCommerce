import type { Meta, StoryObj } from '@storybook/astro';
import MaterialsCard from './MaterialsCard.astro';

/**
 * MaterialsCard — card B2B sobre materiales, tintas y técnicas de
 * estampado para Panda. Tenant-aware vía `--primary`; la lista de
 * features se oculta cuando el array está vacío para permitir
 * degradar a versión sin bullets.
 */
const meta: Meta<typeof MaterialsCard> = {
  title: 'Storefront/B2B/MaterialsCard',
  component: MaterialsCard,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    eyebrow: { control: 'text' },
    body: { control: 'text' },
    features: { control: 'object' },
  },
  args: {
    heading: 'Materiales y tintas',
    eyebrow: 'Calidad que se nota',
    body: 'Trabajamos con textiles certificados y tintas ecológicas. Cada pedido incluye ficha técnica con origen de materiales y certificaciones.',
    features: [
      {
        iconKey: 'shield-check',
        label: 'Algodón orgánico',
        description: 'Certificado GOTS, libre de pesticidas.',
      },
      {
        iconKey: 'eye',
        label: 'Tintas eco-friendly',
        description: 'Base agua, sin ftalatos ni metales pesados.',
      },
      {
        iconKey: 'cog-6-tooth',
        label: 'Técnicas múltiples',
        description: 'Serigrafía, bordado, vinilo textil, sublimación.',
      },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    features: [],
  },
};

export const LongBody: Story = {
  args: {
    body: 'Trabajamos con textiles certificados y tintas ecológicas. Cada pedido incluye ficha técnica con origen de materiales, composición de fibras y certificaciones aplicables (GOTS, OEKO-TEX, CPSIA). Cumplimos con los estándares de la mayoría de licitaciones corporativas y programas de RSE.',
  },
};
