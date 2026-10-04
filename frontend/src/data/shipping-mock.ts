/**
 * Mock data para el módulo de Shipping admin.
 *
 * Dos dominios:
 *  - shippingZones[]     : zonas geográficas con países y costo base (6 items)
 *  - shippingCompanies[] : transportistas con tracking URL + API key (5 items)
 *
 * Mismo patrón que `inventory-mock.ts` / `payments-mock.ts`: tipos
 * exportados + arrays congelados con `as const`. Cuando el backend
 * exponga `GET /api/v1/admin/shipping/*`, los componentes hacen
 * `Astro.fetch()` con fallback a este mock.
 *
 * ShippingZone sigue convención Shopify Admin:
 *   - `countries` es un subset del catálogo ISO-3166-1 alpha-2
 *   - `estimatedDays` es un rango "min..max" (ej: "2-4 días")
 *   - `baseCostCents` es el costo base sin peso adicional
 *
 * ShippingCompany:
 *   - `coverage` resume países/zonas en texto libre para mostrar en la UI
 *   - `apiKeyMasked` muestra solo los últimos 4 caracteres (no se guarda la
 *     clave completa en el frontend — el backend expone el endpoint con
 *     scope restringido)
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading.
 */

export interface ShippingZone {
  readonly id: string;
  readonly name: string;
  readonly countries: readonly string[];
  readonly estimatedDays: string;
  readonly baseCostCents: number;
  readonly isActive: boolean;
}

export interface ShippingCompany {
  readonly id: string;
  readonly name: string;
  readonly trackingUrl: string;
  readonly apiKeyMasked: string;
  readonly coverage: string;
  readonly isActive: boolean;
}

export const shippingZones: readonly ShippingZone[] = [
  {
    id: 'zone-001',
    name: 'Managua (local)',
    countries: ['NI'],
    estimatedDays: '1-2',
    baseCostCents: 250,
    isActive: true,
  },
  {
    id: 'zone-002',
    name: 'Nicaragua (resto)',
    countries: ['NI'],
    estimatedDays: '2-4',
    baseCostCents: 480,
    isActive: true,
  },
  {
    id: 'zone-003',
    name: 'Centroamérica',
    countries: ['CR', 'HN', 'GT', 'SV', 'PA'],
    estimatedDays: '5-8',
    baseCostCents: 1_200,
    isActive: true,
  },
  {
    id: 'zone-004',
    name: 'México',
    countries: ['MX'],
    estimatedDays: '7-12',
    baseCostCents: 1_850,
    isActive: true,
  },
  {
    id: 'zone-005',
    name: 'Estados Unidos',
    countries: ['US'],
    estimatedDays: '6-10',
    baseCostCents: 2_400,
    isActive: true,
  },
  {
    id: 'zone-006',
    name: 'Europa',
    countries: ['ES', 'DE', 'FR', 'IT', 'PT'],
    estimatedDays: '12-20',
    baseCostCents: 3_800,
    isActive: false,
  },
];

export const shippingCompanies: readonly ShippingCompany[] = [
  {
    id: 'co-001',
    name: 'DHL Express',
    trackingUrl: 'https://www.dhl.com/track?id={tracking}',
    apiKeyMasked: '••••••••3f7a',
    coverage: 'Internacional',
    isActive: true,
  },
  {
    id: 'co-002',
    name: 'FedEx',
    trackingUrl: 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
    apiKeyMasked: '••••••••8c2e',
    coverage: 'Internacional',
    isActive: true,
  },
  {
    id: 'co-003',
    name: 'Cargo Expreso (local NI)',
    trackingUrl: 'https://cargoexpreso.com.ni/tracking/{tracking}',
    apiKeyMasked: '••••••••a9d4',
    coverage: 'Nicaragua',
    isActive: true,
  },
  {
    id: 'co-004',
    name: 'UPS',
    trackingUrl: 'https://www.ups.com/track?tracknum={tracking}',
    apiKeyMasked: '••••••••1b6f',
    coverage: 'Internacional',
    isActive: true,
  },
  {
    id: 'co-005',
    name: 'Pickup en tienda',
    trackingUrl: '',
    apiKeyMasked: '—',
    coverage: 'Managua',
    isActive: true,
  },
];
