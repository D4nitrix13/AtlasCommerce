import type { Meta, StoryObj } from '@storybook/astro';
import DarkHeroCard from './DarkHeroCard.astro';

/**
 * DarkHeroCard — card oscura con eyebrow + H3 + CTA outline blanco.
 * Equivalente Panda del card hero verde bosque de Bonfire. El fondo
 * usa slate-900 (charcoal neutral) en lugar del teal para no
 * competir con la CTA Final que viene después y para mantener
 * contraste alto.
 */
const meta: Meta<typeof DarkHeroCard> = {
  title: 'Storefront/B2B/DarkHeroCard',
  component: DarkHeroCard,
  tags: ['autodocs'],
  argTypes: {
    eyebrow: { control: 'text' },
    heading: { control: 'text' },
    body: { control: 'text' },
    ctaLabel: { control: 'text' },
    ctaHref: { control: 'text' },
  },
  args: {
    eyebrow: 'Estampado B2B',
    heading: 'Hecho para tu equipo',
    body: 'Sin inventario mínimo, sin disputas de marca. Diseño + estampado + entrega para tu empresa.',
    ctaLabel: 'Cotizar mi pedido',
    ctaHref: '/es/rfq',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const ShortBody: Story = {
  args: { body: 'Diseño + estampado + entrega para tu empresa.' },
};
