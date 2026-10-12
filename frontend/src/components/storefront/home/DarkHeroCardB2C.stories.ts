import type { Meta, StoryObj } from '@storybook/astro';
import DarkHeroCardB2C from './DarkHeroCardB2C.astro';

/**
 * DarkHeroCardB2C — card oscura con eyebrow + H3 + CTA outline blanco.
 * Equivalente B2C del card hero verde bosque de Bonfire. El fondo
 * usa slate-900 (charcoal neutral) para mantener contraste alto y no
 * competir con la CTA Final que viene después en la composición.
 *
 * Copy B2C: "Tu merch personalizado" para fans, coleccionistas y
 * clientes individuales. Sin MOQ, sin inventario.
 */
const meta: Meta<typeof DarkHeroCardB2C> = {
  title: 'Storefront/Home/DarkHeroCardB2C',
  component: DarkHeroCardB2C,
  tags: ['autodocs'],
  argTypes: {
    eyebrow: { control: 'text' },
    heading: { control: 'text' },
    body: { control: 'text' },
    ctaLabel: { control: 'text' },
    ctaHref: { control: 'text' },
  },
  args: {
    eyebrow: 'Tu merch personalizado',
    heading: 'Diseña y entrega',
    body: 'Sin pedido mínimo, sin inventario. Desde tu idea hasta tu puerta.',
    ctaLabel: 'Empezar a diseñar',
    ctaHref: '/products',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const ShortBody: Story = {
  args: { body: 'Desde tu idea hasta tu puerta.' },
};
