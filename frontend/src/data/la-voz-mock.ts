/**
 * la-voz-mock.ts — Mock data de La Voz Instrumentos (B2C nicho).
 *
 * Hasta que el endpoint backend exista, los datos viven acá. Mismo
 * patron que `data/products.ts` y `data/b2b-mock.ts`. Cuando el
 * backend exponga `/api/storefront/products` con scope por tenant,
 * este archivo se reemplaza por fetch.
 *
 * Decisiones:
 *   - Productos placeholder coherentes con guitargear.com.mx: guitarras
 *     electricas, bajos, amplificadores, audio, accesorios.
 *   - Marcas conocidas: Fender, Gibson, Yamaha, Marshall, Boss, Shure.
 *   - Cada producto tiene campos Nike+guitargear-ready:
 *     `priceCents`, `compareAtPriceCents`, `badges[]` (new|sale|lowStock),
 *     `attributes.brand`, `attributes.specs` (key/value pairs para
 *     ProductSpecsTable), `variants[]` (size, color, etc).
 *   - Currency por defecto NIO (córdoba nicaragüense) — La Voz es tienda
 *     física en Nicaragua. Bilingue es|en con NIO|USD cuando aplique.
 */

export interface LaVozProductSpec {
  label: string;
  value: string;
}

export interface LaVozProductVariant {
  id: string;
  label: string;
  /** Hex color para swatch visual en cards/detalle. */
  hex?: string;
  /** Indica si esta variante esta disponible. */
  inStock: boolean;
}

export interface LaVozProduct {
  id: string;
  slug: string;
  name: string;
  description: string;
  priceCents: number;
  compareAtPriceCents: number | null;
  currency: 'NIO' | 'USD';
  category: 'guitarras-electricas' | 'bajos' | 'amplificadores' | 'audio' | 'accesorios';
  badges: readonly ('new' | 'sale' | 'lowStock')[];
  imageUrl: string;
  imageAlt: string;
  attributes: {
    brand: string;
    specs: readonly LaVozProductSpec[];
  };
  variants: readonly LaVozProductVariant[];
  inStock: boolean;
}

export const LA_VOZ_CATEGORIES = [
  { slug: 'guitarras-electricas', label: 'Guitarras eléctricas', count: 0 },
  { slug: 'bajos',               label: 'Bajos',                count: 0 },
  { slug: 'amplificadores',      label: 'Amplificadores',       count: 0 },
  { slug: 'audio',               label: 'Audio profesional',    count: 0 },
  { slug: 'accesorios',          label: 'Accesorios',           count: 0 },
] as const;

export const LA_VOZ_BRANDS = [
  { slug: 'fender',  label: 'Fender',  count: 0 },
  { slug: 'gibson',  label: 'Gibson',  count: 0 },
  { slug: 'yamaha',  label: 'Yamaha',  count: 0 },
  { slug: 'marshall',label: 'Marshall',count: 0 },
  { slug: 'boss',    label: 'Boss',    count: 0 },
  { slug: 'shure',   label: 'Shure',   count: 0 },
] as const;

export const LA_VOZ_PRODUCTS: readonly LaVozProduct[] = [
  {
    id: 'lv-001',
    slug: 'fender-stratocaster-player-sunburst',
    name: 'Fender Stratocaster Player Series — Sunburst',
    description:
      'La Stratocaster más icónica en su versión Player Series. Tres single-coils, trémolo sincronizado, mástil con perfil moderno C. Lista para estudio y escenario.',
    priceCents: 1350_00,
    compareAtPriceCents: 1500_00,
    currency: 'USD',
    category: 'guitarras-electricas',
    badges: ['sale'],
    imageUrl: '/img/la-voz/stratocaster-sunburst.webp',
    imageAlt: 'Fender Stratocaster Player Series color sunburst',
    attributes: {
      brand: 'fender',
      specs: [
        { label: 'Cuerpo',     value: 'Aliso (Alder)' },
        { label: 'Mástil',     value: 'Arce (Maple), perfil Modern C' },
        { label: 'Diapasón',   value: 'Palo de rosa (Rosewood), 9.5" radius' },
        { label: 'Trastes',    value: '22, Medium Jumbo' },
        { label: 'Pastillas',  value: '3x Single-Coil Player Series Alnico 5' },
        { label: 'Puente',     value: 'Trémolo sincronizado de 2 puntos' },
        { label: 'Acabado',    value: '3-Color Sunburst, poliuretano' },
      ],
    },
    variants: [
      { id: 'sunburst', label: 'Sunburst', hex: '#C8954B', inStock: true },
      { id: 'black',    label: 'Black',    hex: '#1A1A1A', inStock: true },
      { id: 'tidepool', label: 'Tidepool', hex: '#3B7080', inStock: false },
    ],
    inStock: true,
  },
  {
    id: 'lv-002',
    slug: 'gibson-les-paul-standard-50s-goldtop',
    name: 'Gibson Les Paul Standard ’50s — Goldtop',
    description:
      'La Les Paul más auténtica. Mástil mahogany con perfil años 50, pastillas BurstBucker 1 y 2, acabado Goldtop con protector de tortoise. Sonido cálido con sustain infinito.',
    priceCents: 2800_00,
    compareAtPriceCents: null,
    currency: 'USD',
    category: 'guitarras-electricas',
    badges: ['new'],
    imageUrl: '/img/la-voz/les-paul-goldtop.webp',
    imageAlt: 'Gibson Les Paul Standard 50s Goldtop',
    attributes: {
      brand: 'gibson',
      specs: [
        { label: 'Cuerpo',     value: 'Mástil mahogany con tapa de maple' },
        { label: 'Mástil',     value: 'Mahogany, perfil años 50 rounded' },
        { label: 'Diapasón',   value: 'Rosewood, 12" radius' },
        { label: 'Trastes',    value: '22, Medium Jumbo' },
        { label: 'Pastillas',  value: 'BurstBucker 1 (mástil) + BurstBucker 2 (puente)' },
        { label: 'Acabado',    value: 'Goldtop, nitrocelulosa' },
      ],
    },
    variants: [
      { id: 'goldtop',    label: 'Goldtop',    hex: '#C9A14D', inStock: true },
      { id: 'cherry',     label: 'Cherry',     hex: '#7A1F1F', inStock: false },
    ],
    inStock: true,
  },
  {
    id: 'lv-003',
    slug: 'fender-jazz-bass-rosewood-fingerboard-3ts',
    name: 'Fender Jazz Bass — 3-Tone Sunburst',
    description:
      'El bajo eléctrico más versátil. Dos pastillas single-coil JBass, sonido definido para slap, fingerstyle y púa. Mástil delgado para tocar cómodo.',
    priceCents: 1100_00,
    compareAtPriceCents: null,
    currency: 'USD',
    category: 'bajos',
    badges: [],
    imageUrl: '/img/la-voz/jazz-bass-3ts.webp',
    imageAlt: 'Fender Jazz Bass color 3 tone sunburst',
    attributes: {
      brand: 'fender',
      specs: [
        { label: 'Cuerpo',    value: 'Aliso (Alder)' },
        { label: 'Mástil',    value: 'Arce (Maple), perfil Modern C' },
        { label: 'Diapasón',  value: 'Palo de rosa (Rosewood)' },
        { label: 'Trastes',   value: '20, Medium Jumbo' },
        { label: 'Pastillas', value: '2x Single-Coil Jazz Bass' },
      ],
    },
    variants: [
      { id: '3ts', label: '3-Color Sunburst', hex: '#7A4E1F', inStock: true },
      { id: 'blk', label: 'Black',            hex: '#1A1A1A', inStock: true },
    ],
    inStock: true,
  },
  {
    id: 'lv-004',
    slug: 'marshall-jvm410c-combo',
    name: 'Marshall JVM410C — Combo 100W',
    description:
      'Cuatro canales limpios, crunch, OD1, OD2. Cada uno con su propio EQ + reverb. Sonido Marshall de los grandes escenarios en un combo transportable.',
    priceCents: 3200_00,
    compareAtPriceCents: 3500_00,
    currency: 'USD',
    category: 'amplificadores',
    badges: ['sale'],
    imageUrl: '/img/la-voz/jvm410c-combo.webp',
    imageAlt: 'Marshall JVM410C combo amp',
    attributes: {
      brand: 'marshall',
      specs: [
        { label: 'Potencia',  value: '100W (conmutable a 50W)' },
        { label: 'Canales',   value: '4 (Clean, Crunch, OD1, OD2)' },
        { label: 'Parlantes', value: '4x Celestion G12T-100 (12")' },
        { label: 'Efectos',   value: 'Reverb digital + loop de efectos' },
      ],
    },
    variants: [],
    inStock: true,
  },
  {
    id: 'lv-005',
    slug: 'yamaha-stagepas-600bt',
    name: 'Yamaha STAGEPAS 600BT — Sistema PA portátil',
    description:
      'Sistema de PA autoamplificado 680W con dos parlantes de 10", mezcladora integrada con 10 canales y Bluetooth. Perfecto para presentaciones en vivo pequeñas y medianas.',
    priceCents: 980_00,
    compareAtPriceCents: null,
    currency: 'USD',
    category: 'audio',
    badges: ['new'],
    imageUrl: '/img/la-voz/stagepas-600bt.webp',
    imageAlt: 'Yamaha STAGEPAS 600BT PA system',
    attributes: {
      brand: 'yamaha',
      specs: [
        { label: 'Potencia',   value: '680W peak / 340W RMS' },
        { label: 'Parlantes',  value: '2x 10" + driver de compresión 1.4"' },
        { label: 'Canales',    value: '10 (4 mono + 3 estéreo + Bluetooth)' },
        { label: 'Conectividad', value: 'Bluetooth 5.0, XLR, TRS, RCA' },
      ],
    },
    variants: [],
    inStock: true,
  },
  {
    id: 'lv-006',
    slug: 'shure-sm58-lc',
    name: 'Shure SM58 — Micrófono vocal',
    description:
      'El micrófono vocal más vendido del mundo. Cápsula dinámica cardioide, rechazo excepcional a la retroalimentación. Construcción a prueba de todo.',
    priceCents: 120_00,
    compareAtPriceCents: null,
    currency: 'USD',
    category: 'audio',
    badges: [],
    imageUrl: '/img/la-voz/shure-sm58.webp',
    imageAlt: 'Shure SM58 vocal microphone',
    attributes: {
      brand: 'shure',
      specs: [
        { label: 'Tipo',       value: 'Dinámico cardioide' },
        { label: 'Respuesta',  value: '50 Hz – 15 kHz' },
        { label: 'Impedancia', value: '300 Ω' },
        { label: 'Conector',   value: 'XLR-3M' },
      ],
    },
    variants: [],
    inStock: true,
  },
  {
    id: 'lv-007',
    slug: 'boss-katana-100-mkii',
    name: 'Boss Katana-100 MkII — Combo 100W',
    description:
      'Combo de modelado con 5 voces de amplificador + 60 efectos BOSS. Bluetooth para backing tracks. App BOSS Tone Studio para editar todo desde el celular.',
    priceCents: 520_00,
    compareAtPriceCents: null,
    currency: 'USD',
    category: 'amplificadores',
    badges: ['new', 'lowStock'],
    imageUrl: '/img/la-voz/katana-100-mkii.webp',
    imageAlt: 'Boss Katana-100 MkII combo',
    attributes: {
      brand: 'boss',
      specs: [
        { label: 'Potencia',  value: '100W' },
        { label: 'Parlante',  value: '12" custom' },
        { label: 'Efectos',   value: '60+ efectos digitales' },
        { label: 'Conectividad', value: 'Bluetooth, USB-C, MIDI, AUX' },
      ],
    },
    variants: [],
    inStock: true,
  },
  {
    id: 'lv-008',
    slug: 'gibson-sg-standard-cherry',
    name: 'Gibson SG Standard — Cherry',
    description:
      'La SG original. Mástil mahogany perfil SlimTaper, pastillas 490R + 490T, sustain brillante. Liviana y rápida.',
    priceCents: 1750_00,
    compareAtPriceCents: null,
    currency: 'USD',
    category: 'guitarras-electricas',
    badges: [],
    imageUrl: '/img/la-voz/sg-standard-cherry.webp',
    imageAlt: 'Gibson SG Standard cherry',
    attributes: {
      brand: 'gibson',
      specs: [
        { label: 'Cuerpo',     value: 'Mahogany' },
        { label: 'Mástil',     value: 'Mahogany, perfil SlimTaper' },
        { label: 'Diapasón',   value: 'Rosewood, 12" radius' },
        { label: 'Trastes',    value: '22, Cryogenically Treated' },
        { label: 'Pastillas',  value: '490R (mástil) + 490T (puente)' },
      ],
    },
    variants: [
      { id: 'cherry', label: 'Cherry', hex: '#7A1F1F', inStock: true },
      { id: 'ebony', label: 'Ebony',  hex: '#0A0A0A', inStock: false },
    ],
    inStock: true,
  },
];

// Helpers tipo PRODUCTS[] en data/products.ts para no romper la API.
export function findProductBySlug(slug: string): LaVozProduct | null {
  return LA_VOZ_PRODUCTS.find((product) => product.slug === slug) ?? null;
}

export function getFeaturedLaVozProducts(limit: number): readonly LaVozProduct[] {
  return LA_VOZ_PRODUCTS.filter((p) => p.badges.includes('new') || p.badges.includes('sale')).slice(0, limit);
}

export function getLaVozProductsByCategory(category: LaVozProduct['category']): readonly LaVozProduct[] {
  return LA_VOZ_PRODUCTS.filter((product) => product.category === category);
}
