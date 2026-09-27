import { adminUrl } from '../lib/path-helpers';
import type { Locale } from '../i18n/config';

/**
 * Configuración del menú lateral admin.
 *
 * Single source of truth para los items del sidebar (categorías + entries).
 * `AdminSidebar.astro` consume `getAdminMenu(locale)` para resolver las URLs
 * finales con prefijo `/{locale}/admin/...`. Editar acá propaga a toda la UI
 * sin tocar el componente.
 *
 * Forma del item:
 *   - `name`   → label visible
 *   - `href`   → bare path admin (ej: "/sales/orders"). El prefijo
 *                "/{locale}/admin" lo agrega `getAdminMenu(locale)`.
 *   - `icon`   → nombre del icono lucide (resuelto por `Icon.astro`)
 *   - `badge`  → opcional. `"orders-pending"` dispara un fetch client-side
 *                al endpoint admin de orders para mostrar el conteo dinámico.
 *                Otros valores futuros: `"returns-pending"`, `"tickets-open"`.
 *
 * El campo `badge` es declarativo (no contiene el número). El sidebar
 * resuelve el número vía API en runtime; si falla, no renderiza badge.
 */

/** Tipos de badge soportados (extension point para futuras métricas). */
export type AdminBadgeKind = 'orders-pending';

export interface AdminMenuItem {
  name: string;
  href: string;
  icon: string;
  badge?: AdminBadgeKind;
}

export interface AdminMenuSection {
  label: string;
  items: AdminMenuItem[];
}

const RAW_MENU: readonly AdminMenuSection[] = [
  {
    label: 'Resumen',
    items: [
      { name: 'Dashboard', href: '/dashboard', icon: 'squares-2x2' },
      { name: 'Reportes', href: '/reports', icon: 'chart-bar' },
    ],
  },
  {
    label: 'Comercio',
    items: [
      { name: 'Órdenes', href: '/sales/orders', icon: 'shopping-cart', badge: 'orders-pending' },
      { name: 'Productos', href: '/catalog/products', icon: 'cube' },
      { name: 'Clientes', href: '/customers', icon: 'users' },
      { name: 'Facturas', href: '/sales/invoices', icon: 'document-text' },
    ],
  },
  {
    label: 'Catálogo',
    items: [
      { name: 'Categorías', href: '/catalog/categories', icon: 'folder' },
      { name: 'Marcas', href: '/catalog/brands', icon: 'tag' },
      { name: 'Variantes', href: '/catalog/variants', icon: 'square-3-stack-3d' },
    ],
  },
  {
    label: 'Inventario',
    items: [
      { name: 'Stock', href: '/inventory/stock', icon: 'archive-box' },
      { name: 'Movimientos', href: '/inventory/movements', icon: 'arrow-path' },
      { name: 'Depósitos', href: '/inventory/warehouses', icon: 'building-office' },
    ],
  },
  {
    label: 'Compras',
    items: [
      { name: 'Proveedores', href: '/purchases/suppliers', icon: 'truck' },
      { name: 'Compras', href: '/purchases/orders', icon: 'clipboard-document-list' },
    ],
  },
  {
    label: 'Marketing',
    items: [
      { name: 'Cupones', href: '/marketing/coupons', icon: 'ticket' },
      { name: 'Promociones', href: '/marketing/promotions', icon: 'gift' },
    ],
  },
  {
    label: 'Soporte',
    items: [
      { name: 'Tickets', href: '/support/tickets', icon: 'chat-bubble-left-right' },
      { name: 'Reseñas', href: '/support/reviews', icon: 'star' },
      { name: 'Preguntas', href: '/support/questions', icon: 'question-mark-circle' },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { name: 'Usuarios', href: '/users', icon: 'user' },
      { name: 'Roles', href: '/roles', icon: 'shield-check' },
      { name: 'Auditoría', href: '/system/audits', icon: 'magnifying-glass' },
      { name: 'Configuración', href: '/settings', icon: 'cog-6-tooth' },
    ],
  },
] as const;

/**
 * Devuelve el menú admin con los `href` ya resueltos al prefijo
 * `/{locale}/admin/...`. El sidebar llama esto en SSR con el locale
 * resuelto por el middleware (`Astro.locals.locale`).
 */
export function getAdminMenu(locale: Locale): AdminMenuSection[] {
  return RAW_MENU.map((section) => ({
    label: section.label,
    items: section.items.map((item) => ({
      ...item,
      href: adminUrl(locale, item.href),
    })),
  }));
}
