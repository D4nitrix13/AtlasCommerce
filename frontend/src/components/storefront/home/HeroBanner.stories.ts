import type { Meta, StoryObj } from '@storybook/astro';
import HeroBanner from './HeroBanner.astro';

/**
 * HeroBanner — hero del storefront público (home).
 *
 * Tenant-aware: usa `--primary` del theme root. Layout: eyebrow + h1
 * + subtítulo + CTAs primario/sec.
 */
const meta: Meta<typeof HeroBanner> = {
  title: 'Storefront/HeroBanner',
  component: HeroBanner,
  tags: ['autodocs'],
  argTypes: {
    eyebrow: { control: 'text' },
    title: { control: 'text' },
    subtitle: { control: 'text' },
    primaryCta: { control: 'object' },
    secondaryCta: { control: 'object' },
  },
  args: {
    eyebrow: 'Nuevo · Edición limitada',
    title: 'Tu colección otaku, curada con cariño',
    subtitle:
      'Figures, manga y apparel de tus franquicias favoritas. Envío gratis en compras mayores a $50.',
    primaryCta: { label: 'Explorar catálogo', href: '/products' },
    secondaryCta: { label: 'Ver novedades', href: '/products?filter=new' },
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithoutEyebrow: Story = {
  args: {
    eyebrow: undefined,
  },
};

export const SingleCta: Story = {
  args: {
    eyebrow: 'Solo hoy',
    title: '20% off en toda la sección de manga',
    subtitle: 'Aprovecha el descuento por tiempo limitado en tomos seleccionados.',
    primaryCta: { label: 'Comprar manga', href: '/products?category=manga' },
    secondaryCta: undefined,
  },
};

export const PandaVariant: Story = {
  args: {
    eyebrow: 'B2B · Estampados personalizados',
    title: 'Vende más con tu marca, sin mínimos',
    subtitle:
      'Cotiza tu pedido al por mayor en menos de 24h. NET 30 disponible para clientes calificados.',
    primaryCta: { label: 'Solicitar cotización', href: '/bulk-order' },
    secondaryCta: { label: 'Ver catálogo', href: '/products' },
  },
};

export const LongTitle: Story = {
  args: {
    eyebrow: 'Edición coleccionista',
    title: 'Evangelion Unit-01 escala 1/6 con luz LED y base articulada premium',
    subtitle:
      'Una pieza de museo para los coleccionistas más exigentes. Stock limitado a 200 unidades worldwide.',
    primaryCta: { label: 'Reservar ahora', href: '/products/evangelion-unit-01' },
  },
};

export const SimpleKitsune: Story = {
  args: {
    eyebrow: 'Bienvenido',
    title: 'Kitsune Store',
    subtitle: 'Tu tienda de cabecera para todo lo otaku.',
    primaryCta: { label: 'Empezar', href: '/products' },
  },
};
