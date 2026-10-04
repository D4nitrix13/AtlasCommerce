/**
 * Mock data para el módulo de Settings admin.
 *
 * Un solo dominio (resumen del tenant + estado de los switches globales).
 * El componente hub (`admin/settings/index.astro`) usa esto para mostrar
 * un resumen del tenant sin reimplementar fetch. Cuando el backend
 * exponga `GET /api/v1/admin/stores/{slug}/summary`, los consumers hacen
 * `Astro.fetch()` con fallback a este mock.
 *
 * Mismo patrón que `admin-dashboard.ts`: tipos exportados + objetos
 * congelados con `as const`.
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que el hub
 * pueda renderizar success; el componente maneja empty/error/loading.
 */

export interface TenantSettingsSummary {
  readonly tenantName: string;
  readonly tenantSlug: string;
  readonly tenantDomain: string;
  readonly contactEmail: string;
  readonly contactPhone: string;
  readonly themeStatus: 'configured' | 'default';
  readonly maintenanceEnabled: boolean;
  readonly taxRatesCount: number;
  readonly currenciesCount: number;
  readonly shippingZonesCount: number;
  readonly paymentMethodsCount: number;
  readonly lastSyncedAt: string;
}

export const tenantSettings: TenantSettingsSummary = {
  tenantName: 'Kitsune Store',
  tenantSlug: 'kitsune',
  tenantDomain: 'kitsune.atlascommerce.test',
  contactEmail: 'soporte@kitsune.test',
  contactPhone: '+505 8888 1234',
  themeStatus: 'configured',
  maintenanceEnabled: false,
  taxRatesCount: 8,
  currenciesCount: 5,
  shippingZonesCount: 6,
  paymentMethodsCount: 5,
  lastSyncedAt: '2026-10-01T08:30:00Z',
};
