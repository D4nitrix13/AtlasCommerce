/**
 * Mock data para el módulo de Marketing admin.
 *
 * Dos dominios:
 *  - coupons[]     : cupones de descuento (8 items, mezcla activa/expirada/agotada)
 *  - promotions[]  : promociones automáticas (5 items: BOGO/percent/free-shipping)
 *
 * Mismo patrón que `inventory-mock.ts`: tipos exportados + arrays
 * congelados con `as const`. Cuando el backend exponga los endpoints
 * `/api/v1/admin/marketing/*`, los componentes hacen `Astro.fetch()`
 * con fallback a este mock.
 *
 * Coupon status sigue la convención de Shopify Admin:
 *   active    → en vigencia, usable
 *   scheduled → vigente futura, no usable aún
 *   expired   → venció, no usable
 *   exhausted → agotó el `usageLimit`
 *
 * Promotion kind agrupa las 3 mecánicas más comunes del e-commerce:
 *   bogo             → Buy X Get Y
 *   percent_off      → X% de descuento sobre el subtotal
 *   free_shipping    → envío gratis si subtotal >= `minSubtotal`
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading.
 */

export type CouponStatus = 'active' | 'scheduled' | 'expired' | 'exhausted';

export type DiscountType = 'percentage' | 'fixed_amount' | 'free_shipping';

export interface Coupon {
  readonly id: string;
  readonly code: string;
  readonly description: string;
  readonly discountType: DiscountType;
  readonly discountValue: number;
  readonly minSubtotal: number | null;
  readonly usageLimit: number | null;
  readonly usageCount: number;
  readonly validFrom: string;
  readonly validUntil: string;
  readonly status: CouponStatus;
}

export type PromotionKind = 'bogo' | 'percent_off' | 'free_shipping';

export interface Promotion {
  readonly id: string;
  readonly name: string;
  readonly description: string;
  readonly kind: PromotionKind;
  readonly config: {
    readonly buyQuantity?: number;
    readonly getQuantity?: number;
    readonly getDiscountPercent?: number;
    readonly percentOff?: number;
    readonly appliesTo?: string;
    readonly minSubtotal?: number;
  };
  readonly startAt: string;
  readonly endAt: string;
  readonly priority: number;
  readonly usageCount: number;
  readonly status: CouponStatus;
}

export const coupons: readonly Coupon[] = [
  {
    id: 'cpn-001',
    code: 'BIENVENIDO10',
    description: '10% de descuento en la primera compra sobre subtotal mayor a $25.',
    discountType: 'percentage',
    discountValue: 10,
    minSubtotal: 25,
    usageLimit: null,
    usageCount: 412,
    validFrom: '2026-01-01T00:00:00Z',
    validUntil: '2026-12-31T23:59:59Z',
    status: 'active',
  },
  {
    id: 'cpn-002',
    code: 'BLACKFRIDAY25',
    description: '25% de descuento para campaña Black Friday 2026, tope $50.',
    discountType: 'percentage',
    discountValue: 25,
    minSubtotal: 50,
    usageLimit: 1000,
    usageCount: 738,
    validFrom: '2026-11-27T00:00:00Z',
    validUntil: '2026-11-30T23:59:59Z',
    status: 'scheduled',
  },
  {
    id: 'cpn-003',
    code: 'VERANO2026',
    description: 'Envío gratis en pedidos del catálogo de verano sin mínimo.',
    discountType: 'free_shipping',
    discountValue: 0,
    minSubtotal: null,
    usageLimit: 500,
    usageCount: 500,
    validFrom: '2026-06-01T00:00:00Z',
    validUntil: '2026-08-31T23:59:59Z',
    status: 'exhausted',
  },
  {
    id: 'cpn-004',
    code: 'KITSFANS5',
    description: '$5 de descuento fijo en pedidos mayores a $40.',
    discountType: 'fixed_amount',
    discountValue: 5,
    minSubtotal: 40,
    usageLimit: null,
    usageCount: 89,
    validFrom: '2026-09-01T00:00:00Z',
    validUntil: '2026-12-31T23:59:59Z',
    status: 'active',
  },
  {
    id: 'cpn-005',
    code: 'CYBERMONDAY',
    description: '15% en pedidos mayores a $30 con envío gratis incluido.',
    discountType: 'percentage',
    discountValue: 15,
    minSubtotal: 30,
    usageLimit: 800,
    usageCount: 91,
    validFrom: '2026-11-30T00:00:00Z',
    validUntil: '2026-12-02T23:59:59Z',
    status: 'scheduled',
  },
  {
    id: 'cpn-006',
    code: 'PANDAPACK',
    description: '20% off en Pack 3 Playeras Básicas.',
    discountType: 'percentage',
    discountValue: 20,
    minSubtotal: null,
    usageLimit: 200,
    usageCount: 47,
    validFrom: '2026-09-15T00:00:00Z',
    validUntil: '2026-10-15T23:59:59Z',
    status: 'active',
  },
  {
    id: 'cpn-007',
    code: 'VERANO15',
    description: '15% en pedidos mayores a $20 (campaña Q1, ya vencida).',
    discountType: 'percentage',
    discountValue: 15,
    minSubtotal: 20,
    usageLimit: 600,
    usageCount: 311,
    validFrom: '2026-01-15T00:00:00Z',
    validUntil: '2026-03-15T23:59:59Z',
    status: 'expired',
  },
  {
    id: 'cpn-008',
    code: 'REGRESO10',
    description: '10% en regreso a clases (cuadernos, mochilas y loncheras).',
    discountType: 'percentage',
    discountValue: 10,
    minSubtotal: 30,
    usageLimit: 400,
    usageCount: 215,
    validFrom: '2026-02-01T00:00:00Z',
    validUntil: '2026-02-28T23:59:59Z',
    status: 'expired',
  },
] as const;

export const promotions: readonly Promotion[] = [
  {
    id: 'pro-001',
    name: '2x1 Camisetas Kitsune',
    description: 'Llevá 2 camisetas Kitsune y pagá solo 1 (la de menor precio).',
    kind: 'bogo',
    config: {
      buyQuantity: 1,
      getQuantity: 1,
      getDiscountPercent: 100,
      appliesTo: 'Camiseta Kitsune Edición Limitada',
    },
    startAt: '2026-09-20T00:00:00Z',
    endAt: '2026-10-10T23:59:59Z',
    priority: 10,
    usageCount: 87,
    status: 'active',
  },
  {
    id: 'pro-002',
    name: '15% en Accesorios',
    description: '15% off en accesorios (gorras, lanyards, stickers).',
    kind: 'percent_off',
    config: { percentOff: 15, appliesTo: 'Accesorios' },
    startAt: '2026-09-15T00:00:00Z',
    endAt: '2026-10-15T23:59:59Z',
    priority: 20,
    usageCount: 142,
    status: 'active',
  },
  {
    id: 'pro-003',
    name: 'Envío gratis +$50',
    description: 'Envío gratis automático para pedidos con subtotal mayor a $50.',
    kind: 'free_shipping',
    config: { minSubtotal: 50 },
    startAt: '2026-01-01T00:00:00Z',
    endAt: '2026-12-31T23:59:59Z',
    priority: 5,
    usageCount: 612,
    status: 'active',
  },
  {
    id: 'pro-004',
    name: '3x2 Hoodies Premium',
    description: '3 hoodies premium al precio de 2 (50% off en el de menor precio).',
    kind: 'bogo',
    config: {
      buyQuantity: 2,
      getQuantity: 1,
      getDiscountPercent: 100,
      appliesTo: 'Hoodie Bordado Premium',
    },
    startAt: '2026-11-25T00:00:00Z',
    endAt: '2026-11-30T23:59:59Z',
    priority: 8,
    usageCount: 0,
    status: 'scheduled',
  },
  {
    id: 'pro-005',
    name: '20% en Calendarios 2026',
    description: '20% off en calendarios personalizados por lanzamiento del año.',
    kind: 'percent_off',
    config: { percentOff: 20, appliesTo: 'Calendario 2026 Personalizado' },
    startAt: '2026-01-01T00:00:00Z',
    endAt: '2026-01-31T23:59:59Z',
    priority: 15,
    usageCount: 53,
    status: 'expired',
  },
] as const;
