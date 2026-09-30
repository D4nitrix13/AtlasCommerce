import type { Meta, StoryObj } from '@storybook/astro';
import QuickActionsGrid from './QuickActionsGrid.astro';
import type { QuickActionItem } from './QuickActionsGrid.astro';

/**
 * QuickActionsGrid — Composición responsive de QuickActionCards.
 *
 * Grid responsive: mobile 1 col / sm 2 cols / lg 3 cols. Vacío muestra
 * placeholder.
 */
const meta: Meta<typeof QuickActionsGrid> = {
  title: 'Account/Dashboard/QuickActionsGrid',
  component: QuickActionsGrid,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    items: { control: 'object' },
  },
  args: {
    heading: 'Acciones rápidas',
    items: [
      { label: 'Mis pedidos', description: 'Estado de tus pedidos.', href: '/account/orders', iconName: 'package' },
      { label: 'Direcciones', description: 'Gestiona direcciones.', href: '/account/addresses', iconName: 'home' },
      { label: 'Wishlist', description: 'Tu colección guardada.', href: '/account/wishlist', iconName: 'heart' },
      { label: 'Seguridad', description: 'Contraseña y 2FA.', href: '/account/security', iconName: 'settings' },
      { label: 'Tickets', description: 'Atención al cliente.', href: '/account/tickets', iconName: 'bell', badge: 3 },
      { label: 'Notificaciones', description: 'Mensajes sin leer.', href: '/account/notifications', iconName: 'mail', badge: 'Nuevo' },
    ] as QuickActionItem[],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const FourItems: Story = {
  args: {
    heading: 'Acceso rápido',
    items: [
      { label: 'Mis pedidos', description: 'Estado de tus pedidos.', href: '/account/orders', iconName: 'package' },
      { label: 'Direcciones', description: 'Gestiona direcciones.', href: '/account/addresses', iconName: 'home' },
      { label: 'Wishlist', description: 'Tu colección guardada.', href: '/account/wishlist', iconName: 'heart' },
      { label: 'Seguridad', description: 'Contraseña y 2FA.', href: '/account/security', iconName: 'settings' },
    ] as QuickActionItem[],
  },
};

export const SingleItem: Story = {
  args: {
    heading: 'Empezar',
    items: [
      { label: 'Crear mi primer pedido', description: 'Explora el catálogo.', href: '/products', iconName: 'shopping-cart' },
    ] as QuickActionItem[],
  },
};

export const Empty: Story = {
  args: {
    heading: 'Acciones rápidas',
    items: [] as QuickActionItem[],
  },
};

export const WithBadges: Story = {
  args: {
    heading: 'Pendientes',
    items: [
      { label: 'Pedidos', description: 'Pedidos en curso.', href: '/account/orders', iconName: 'package', badge: 2 },
      { label: 'Tickets', description: 'Tickets abiertos.', href: '/account/tickets', iconName: 'bell', badge: 5 },
      { label: 'Notificaciones', description: 'Mensajes nuevos.', href: '/account/notifications', iconName: 'mail', badge: 12 },
    ] as QuickActionItem[],
  },
};