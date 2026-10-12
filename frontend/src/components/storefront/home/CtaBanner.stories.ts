import type { Meta, StoryObj } from '@storybook/astro';
import CtaBanner from './CtaBanner.astro';

/**
 * CtaBanner — banda full-width de cierre con accent del tenant.
 *
 * Una sola propuesta de valor + botón CTA. Tenant-aware vía `--primary`.
 */
const meta: Meta<typeof CtaBanner> = {
  title: 'Storefront/CtaBanner',
  component: CtaBanner,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    ctaLabel: { control: 'text' },
    ctaHref: { control: 'text' },
  },
  args: {
    heading: 'Lleve su diseño al siguiente nivel',
    ctaLabel: 'Empezar ahora',
    ctaHref: '/products',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Panda: Story = {
  args: {
    heading: 'Cotizá tu próximo pedido al por mayor',
    ctaLabel: 'Solicitar cotización',
    ctaHref: '/rfq',
  },
};

export const Kitsune: Story = {
  args: {
    heading: 'Sumate al club Kitsune',
    ctaLabel: 'Suscribirme',
    ctaHref: '/account/signup',
  },
};

export const MmBoutique: Story = {
  args: {
    heading: 'Descubrí la próxima colección',
    ctaLabel: 'Ver lookbook',
    ctaHref: '/products?filter=lookbook',
  },
};

export const MoralesStore: Story = {
  args: {
    heading: 'Aprovechá las ofertas de la semana',
    ctaLabel: 'Ver todas las ofertas',
    ctaHref: '/products?sale=1',
  },
};

export const LongHeading: Story = {
  args: {
    heading:
      'Tu próximo proyecto merece estampados de calidad, sin sorpresas en el precio y entrega a tiempo',
    ctaLabel: 'Cotizar gratis',
    ctaHref: '/rfq',
  },
};