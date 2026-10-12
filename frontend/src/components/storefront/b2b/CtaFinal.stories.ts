import type { Meta, StoryObj } from '@storybook/astro';
import CtaFinal from './CtaFinal.astro';

/**
 * CtaFinal — sección full-width charcoal con CTA outline blanco.
 * Replica la "Take your custom merch to the next level" del home
 * de Bonfire, adaptada a Panda con copy local.
 */
const meta: Meta<typeof CtaFinal> = {
  title: 'Storefront/B2B/CtaFinal',
  component: CtaFinal,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    ctaLabel: { control: 'text' },
    ctaHref: { control: 'text' },
  },
  args: {
    heading: 'Lleve su estampado al siguiente nivel',
    ctaLabel: 'Iniciar cotización',
    ctaHref: '/es/rfq',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const EnglishCopy: Story = {
  args: {
    heading: 'Take your custom merch to the next level',
    ctaLabel: 'Start a quote',
    ctaHref: '/en/rfq',
  },
};
