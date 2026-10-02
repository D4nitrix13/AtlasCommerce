/**
 * Mock data — Panda Estampados B2B storefront (F3).
 *
 * Contiene los datos temporales para los slices B2B específicos:
 *  - Categorías top B2B (Estampados / Regalos corporativos / Textil / Sublimación).
 *  - Pricing tiers para bulk order (volumen B2B).
 *  - Tipos de dirección multi-sucursal (sede / almacén / oficina / eventos).
 *
 * Estos datos SON TEMPORALES: cuando los endpoints backend existan
 * (`GET /api/storefront/b2b/categories`, `GET /api/storefront/b2b/pricing-tiers`),
 * este archivo se reemplaza por un fetch real. Las shapes de los tipos
 * (en este archivo o en `types/catalog.ts`) son lo único estable.
 *
 * Decisiones de diseño (design-refs §3.4 + §7):
 *  - Cliente recurrente con pedidos mensuales (no explorador).
 *  - Pricing tiers visibles — RF-CAT-029 wholesale (anti-pattern §8.3
 *    "Pricing oculto contact us").
 *  - Multi-sucursal porque las empresas tienen varias sedes (sede
 *    central, almacenes, oficinas regionales, eventos puntuales).
 *  - Tipos de dirección son EXACTAMENTE 4 (no expandibles) para
 *    mantener la UX predecible: `headquarters | warehouse | office | event`.
 */

/**
 * Categorías top B2B para Panda Estampados.
 *
 * Diferencia con CATEGORIES de F1.4 (Kitsune, B2C otaku):
 *  - Sin figuras/manga/cerámica japonesa.
 *  - Enfocadas en estampado textil, regalos corporativos, sublimación.
 *  - Cada categoría tiene un slug distinto para que no choquen con
 *    las categorías B2C al coexistir en el mismo backend.
 */
export interface B2bCategory {
  slug: string;
  name: string;
  description: string;
  imageUrl: string;
  href: string;
}

const B2B_PLACEHOLDER_IMAGE =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="B2B category placeholder">
      <defs>
        <linearGradient id="b2b-g" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#d1fae5"/>
          <stop offset="100%" stop-color="#a7f3d0"/>
        </linearGradient>
      </defs>
      <rect width="400" height="400" fill="url(#b2b-g)"/>
      <text x="200" y="210" text-anchor="middle" font-family="system-ui, sans-serif" font-size="20" fill="#065f46" font-weight="600">B2B Category</text>
    </svg>`
  );

export const B2B_CATEGORIES: readonly B2bCategory[] = [
  {
    slug: 'estampados-personalizados',
    name: 'Estampados personalizados',
    description: 'Camisetas, hoodies y textiles con tu logo o diseño.',
    imageUrl: B2B_PLACEHOLDER_IMAGE,
    href: '/products?category=estampados-personalizados',
  },
  {
    slug: 'regalos-corporativos',
    name: 'Regalos corporativos',
    description: 'Kits ejecutivos, mugs, agendas y más para clientes y equipo.',
    imageUrl: B2B_PLACEHOLDER_IMAGE,
    href: '/products?category=regalos-corporativos',
  },
  {
    slug: 'textil-empresarial',
    name: 'Textil empresarial',
    description: 'Uniformes, polos y ropa de trabajo para tu equipo.',
    imageUrl: B2B_PLACEHOLDER_IMAGE,
    href: '/products?category=textil-empresarial',
  },
  {
    slug: 'sublimacion',
    name: 'Sublimación',
    description: 'Cerámica, vidrio y aluminio con impresión full-color.',
    imageUrl: B2B_PLACEHOLDER_IMAGE,
    href: '/products?category=sublimacion',
  },
];

/**
 * Pricing tier por volumen B2B (RF-CAT-029 wholesale).
 *
 * Cuatro escalones canónicos del modelo B2B estampados:
 *   - 1-10 unidades  → precio base (sin descuento).
 *   - 11-50          → -10%.
 *   - 51-200         → -18%.
 *   - 200+           → -25%.
 *
 * El descuento se aplica al subtotal del producto seleccionado.
 * El cálculo final (precio + impuestos + envío) SIEMPRE viene del
 * backend — este mock solo sirve para UX de la calculadora.
 */
export interface PricingTier {
  /** Límite inferior inclusivo del tier. */
  minQuantity: number;
  /** Límite superior INCLUSIVO — null = sin tope. */
  maxQuantity: number | null;
  /** Descuento aplicado (0.10 = 10%). */
  discountPercent: number;
  /** Etiqueta legible para el usuario final. */
  label: string;
}

export const PRICING_TIERS: readonly PricingTier[] = [
  {
    minQuantity: 1,
    maxQuantity: 10,
    discountPercent: 0,
    label: '1-10 unidades',
  },
  {
    minQuantity: 11,
    maxQuantity: 50,
    discountPercent: 0.1,
    label: '11-50 unidades',
  },
  {
    minQuantity: 51,
    maxQuantity: 200,
    discountPercent: 0.18,
    label: '51-200 unidades',
  },
  {
    minQuantity: 201,
    maxQuantity: null,
    discountPercent: 0.25,
    label: '200+ unidades',
  },
];

/**
 * Tipos de dirección multi-sucursal (design-refs §3.4 + §7.2).
 *
 * Diferencia con el campo `address_type` actual (`shipping | billing | other`)
 * del modelo cliente: estos tipos son B2B y describen la FUNCIÓN de la
 * dirección en la empresa, no su rol fiscal.
 *
 * Cada tipo tiene flags independientes `isDefaultBilling` y `isDefaultShipping`
 * para que una sede central sea la dirección de facturación por defecto
 * mientras un almacén separado sea la dirección de envío por defecto.
 */
export type AddressTypeB2b = 'headquarters' | 'warehouse' | 'office' | 'event';

export interface B2bAddressType {
  value: AddressTypeB2b;
  /** Locale key sin prefijo (e.g. "account.addresses.b2b.type.headquarters"). */
  i18nKey:
    | 'account.addresses.b2b.type.headquarters'
    | 'account.addresses.b2b.type.warehouse'
    | 'account.addresses.b2b.type.office'
    | 'account.addresses.b2b.type.event';
}

export const B2B_ADDRESS_TYPES: readonly B2bAddressType[] = [
  { value: 'headquarters', i18nKey: 'account.addresses.b2b.type.headquarters' },
  { value: 'warehouse', i18nKey: 'account.addresses.b2b.type.warehouse' },
  { value: 'office', i18nKey: 'account.addresses.b2b.type.office' },
  { value: 'event', i18nKey: 'account.addresses.b2b.type.event' },
];

/**
 * Resuelve el tier aplicable para una cantidad.
 * Devuelve el primer tier cuyo rango contenga la cantidad.
 *
 * Edge case: si la cantidad es 0 (caso borde de UI), retorna el tier
 * mínimo (1-10) para evitar `undefined` en el render.
 */
export function resolvePricingTier(quantity: number): PricingTier {
  const safeQuantity = Math.max(0, Math.floor(quantity));
  const matched = PRICING_TIERS.find((tier) => {
    if (tier.maxQuantity === null) {
      return safeQuantity >= tier.minQuantity;
    }
    return safeQuantity >= tier.minQuantity && safeQuantity <= tier.maxQuantity;
  });
  // El primer tier SIEMPRE contiene `minQuantity: 1` → fallback seguro.
  return matched ?? (PRICING_TIERS[0] as PricingTier);
}

/**
 * Mock: productos candidatos para "Add to bulk order".
 *
 * Diferencia con PRODUCTS de F1.4: estos son productos B2B que aceptan
 * volumen (camisetas, mugs, tote bags). El precio está en cents (USD).
 *
 * Cuando el endpoint `/api/storefront/b2b/products` exista, este mock
 * se reemplaza por fetch + hidratación.
 */
export interface BulkOrderProduct {
  id: number;
  slug: string;
  name: string;
  description: string;
  basePriceCents: number;
  currency: string;
  imageUrl: string;
  minQuantity: number;
}

const BULK_PLACEHOLDER_IMAGE = B2B_PLACEHOLDER_IMAGE;

export const BULK_PRODUCTS: readonly BulkOrderProduct[] = [
  {
    id: 101,
    slug: 'polo-empresarial-bordado',
    name: 'Polo empresarial bordado',
    description: 'Polo piqué 100% algodón con bordado de logo (hasta 10k puntadas).',
    basePriceCents: 2400,
    currency: 'USD',
    imageUrl: BULK_PLACEHOLDER_IMAGE,
    minQuantity: 10,
  },
  {
    id: 102,
    slug: 'taza-ceramica-sublimada',
    name: 'Taza cerámica sublimada',
    description: 'Taza 11oz, impresión full-color wrap. Apta para microondas.',
    basePriceCents: 650,
    currency: 'USD',
    imageUrl: BULK_PLACEHOLDER_IMAGE,
    minQuantity: 25,
  },
  {
    id: 103,
    slug: 'tote-bag-estampada',
    name: 'Tote bag estampada',
    description: 'Tela canvas 12oz, serigrafía a 1 tinta. Asas reforzadas.',
    basePriceCents: 480,
    currency: 'USD',
    imageUrl: BULK_PLACEHOLDER_IMAGE,
    minQuantity: 50,
  },
  {
    id: 104,
    slug: 'hoodie-capucha-bordado',
    name: 'Hoodie con capucha bordado',
    description: 'Sudadera 80% algodón 20% poliéster, bordado frontal + espalda.',
    basePriceCents: 3800,
    currency: 'USD',
    imageUrl: BULK_PLACEHOLDER_IMAGE,
    minQuantity: 12,
  },
];
