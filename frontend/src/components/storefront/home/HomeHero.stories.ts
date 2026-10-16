import type { Meta, StoryObj } from '@storybook/astro';
import HomeHero from './HomeHero.astro';

/**
 * HomeHero — hero B2C del storefront público (home).
 *
 * Variante Bonfire de HeroBanner: agrega fila opcional de trustBadges
 * debajo de los CTAs. Tenant-aware vía `--primary`.
 */
const meta: Meta<typeof HomeHero> = {
  title: 'Storefront/HomeHero',
  component: HomeHero,
  tags: ['autodocs'],
  argTypes: {
    eyebrow: { control: 'text' },
    title: { control: 'text' },
    subtitle: { control: 'text' },
    primaryCta: { control: 'object' },
    secondaryCta: { control: 'object' },
    trustBadges: { control: 'object' },
  },
  args: {
    eyebrow: 'Edición limitada · Drops semanales',
    title: 'Diseña estampados que la gente quiere ponerse',
    subtitle: 'Calidad premium, sin mínimos y entrega en menos de 7 días. Tu marca, tus diseños.',
    primaryCta: { label: 'Empezar a diseñar', href: '/products' },
    secondaryCta: { label: 'Ver catálogo', href: '/products?filter=new' },
    trustBadges: [
      { iconKey: 'shield-check', label: '100% sin riesgo' },
      { iconKey: 'archive-box', label: 'Sin inventario' },
      { iconKey: 'bolt', label: 'Fácil de usar' },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithoutTrustBadges: Story = {
  args: {
    trustBadges: [],
  },
};

export const WithoutEyebrow: Story = {
  args: {
    eyebrow: undefined,
    trustBadges: [
      { iconKey: 'shield-check', label: 'Pago seguro' },
      { iconKey: 'bolt', label: 'Setup en 5 minutos' },
    ],
  },
};

export const SingleCta: Story = {
  args: {
    eyebrow: 'Black Friday',
    title: '40% off en figuras seleccionadas',
    subtitle: 'Solo este fin de semana. Stock limitado por unidad.',
    primaryCta: { label: 'Comprar ahora', href: '/products?sale=1' },
    secondaryCta: undefined,
    trustBadges: [{ iconKey: 'shield-check', label: 'Garantía oficial' }],
  },
};

export const Panda: Story = {
  args: {
    eyebrow: 'Panda Estampados · B2C estampados',
    title: 'Vende tu marca sin manejar inventario',
    subtitle: 'Estampados personalizados con tu logo. NET 30 disponible para clientes calificados.',
    primaryCta: { label: 'Cotizar mi diseño', href: '/rfq' },
    secondaryCta: { label: 'Ver precios', href: '/products' },
    trustBadges: [
      { iconKey: 'shield-check', label: '100% sin riesgo' },
      { iconKey: 'archive-box', label: 'Sin inventario' },
      { iconKey: 'bolt', label: 'Fácil de usar' },
    ],
  },
};

export const Kitsune: Story = {
  args: {
    eyebrow: 'Kitsune · Cultura japonesa',
    title: 'Tu colección otaku, curada con cariño',
    subtitle:
      'Figures, manga y apparel de tus franquicias favoritas. Envío gratis en compras mayores a $50.',
    primaryCta: { label: 'Explorar catálogo', href: '/products' },
    secondaryCta: { label: 'Ver novedades', href: '/products?filter=new' },
    trustBadges: [
      { iconKey: 'shield-check', label: '100% originales' },
      { iconKey: 'bolt', label: 'Envío 24/48h' },
      { iconKey: 'check', label: 'Garantía de fábrica' },
    ],
  },
};

export const MmBoutique: Story = {
  args: {
    eyebrow: 'MM-Boutique · Moda consciente',
    title: 'Prendas con historia, hechas para durar',
    subtitle:
      'Ediciones limitadas de diseñadores independientes. Cada compra apoya al makers local.',
    primaryCta: { label: 'Descubrir la colección', href: '/products' },
    secondaryCta: { label: 'Sobre nosotros', href: '/about' },
    trustBadges: [
      { iconKey: 'shield-check', label: 'Pago seguro' },
      { iconKey: 'check', label: 'Cambios gratis 30 días' },
      { iconKey: 'bolt', label: 'Envío a todo el país' },
    ],
  },
};

export const MoralesStore: Story = {
  args: {
    eyebrow: 'Morales Store · Tienda general',
    title: 'Todo lo que necesitás, en un solo lugar',
    subtitle:
      'Desde ropa y calzado hasta electrónica del hogar. Precios justos y atención personal.',
    primaryCta: { label: 'Ver catálogo', href: '/products' },
    secondaryCta: { label: 'Ofertas de la semana', href: '/products?sale=1' },
    trustBadges: [
      { iconKey: 'shield-check', label: 'Garantía oficial' },
      { iconKey: 'archive-box', label: 'Retiro en sucursal' },
      { iconKey: 'check', label: 'Devolución sin costo' },
    ],
  },
};

export const LongTitle: Story = {
  args: {
    eyebrow: 'Edición coleccionista',
    title: 'Evangelion Unit-01 escala 1/6 con luz LED y base articulada premium',
    subtitle:
      'Una pieza de museo para los coleccionistas más exigentes. Stock limitado a 200 unidades worldwide.',
    primaryCta: { label: 'Reservar ahora', href: '/products/evangelion-unit-01' },
    secondaryCta: undefined,
    trustBadges: [
      { iconKey: 'shield-check', label: 'Edición numerada' },
      { iconKey: 'check', label: 'Certificado de autenticidad' },
    ],
  },
};
