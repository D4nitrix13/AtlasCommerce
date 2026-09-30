import type { Meta, StoryObj } from '@storybook/astro';
import QuickActionCard from './QuickActionCard.astro';

/**
 * QuickActionCard — Card de atajo con ícono grande para el dashboard
 * del cliente (Baymard §6.1: icon-based dashboard). Soporta badge
 * opcional (ej: "3" tickets pendientes).
 */
const meta: Meta<typeof QuickActionCard> = {
  title: 'Account/Dashboard/QuickActionCard',
  component: QuickActionCard,
  tags: ['autodocs'],
  argTypes: {
    label: { control: 'text' },
    description: { control: 'text' },
    href: { control: 'text' },
    iconName: {
      control: { type: 'select' },
      options: ['package', 'user', 'settings', 'shopping-cart', 'home', 'mail', 'bell', 'heart'],
    },
    badge: { control: 'text', description: 'Badge opcional (número o texto)' },
  },
  args: {
    label: 'Mis pedidos',
    description: 'Revisa el estado de tus pedidos recientes.',
    href: '/account/orders',
    iconName: 'package',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithBadge: Story = {
  args: {
    label: 'Tickets',
    description: 'Atención al cliente.',
    href: '/account/tickets',
    iconName: 'bell',
    badge: 3,
  },
};

export const WithTextBadge: Story = {
  args: {
    label: 'Notificaciones',
    description: 'Mensajes sin leer.',
    href: '/account/notifications',
    iconName: 'mail',
    badge: 'Nuevo',
  },
};

export const LongLabel: Story = {
  args: {
    label: 'Direcciones de envío',
    description: 'Gestiona tus direcciones guardadas y sucursales B2B.',
    href: '/account/addresses',
    iconName: 'home',
  },
};

export const HoverState: Story = {
  args: {
    label: 'Seguridad',
    description: 'Contraseña, 2FA y dispositivos activos.',
    href: '/account/security',
    iconName: 'settings',
  },
  render: () => `
    <a href="/account/security" aria-label="Seguridad — Contraseña, 2FA y dispositivos activos." class="group relative flex flex-col items-start gap-3 rounded-2xl border border-[var(--primary,#7c3aed)] bg-white p-5 shadow-md transition-all hover:border-[var(--primary,#7c3aed)] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#7c3aed)] focus-visible:ring-offset-2">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--primary,#7c3aed)_18%,white)] text-[var(--primary,#7c3aed)]">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
      </div>
      <div class="flex flex-col gap-1">
        <span class="text-base font-semibold text-slate-900 group-hover:text-[var(--primary,#7c3aed)]">Seguridad</span>
        <span class="text-sm text-slate-500">Contraseña, 2FA y dispositivos activos.</span>
      </div>
    </a>
  `,
};

export const OutlinedGrid: Story = {
  render: () => `
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; max-width: 760px;">
      <a href="/account/orders" aria-label="Mis pedidos — Revisa el estado de tus pedidos recientes." class="group relative flex flex-col items-start gap-3 rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-[var(--primary,#7c3aed)] hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--primary,#7c3aed)_12%,white)] text-[var(--primary,#7c3aed)]">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-base font-semibold text-slate-900">Mis pedidos</span>
          <span class="text-sm text-slate-500">Revisa el estado de tus pedidos.</span>
        </div>
      </a>
      <a href="/account/addresses" aria-label="Direcciones — Gestiona tus direcciones guardadas." class="group relative flex flex-col items-start gap-3 rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-[var(--primary,#7c3aed)] hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--primary,#7c3aed)_12%,white)] text-[var(--primary,#7c3aed)]">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-base font-semibold text-slate-900">Direcciones</span>
          <span class="text-sm text-slate-500">Direcciones guardadas.</span>
        </div>
      </a>
      <a href="/account/security" aria-label="Seguridad — Contraseña y 2FA." class="group relative flex flex-col items-start gap-3 rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-[var(--primary,#7c3aed)] hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--primary,#7c3aed)_12%,white)] text-[var(--primary,#7c3aed)]">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-base font-semibold text-slate-900">Seguridad</span>
          <span class="text-sm text-slate-500">Contraseña y 2FA.</span>
        </div>
      </a>
    </div>
  `,
};