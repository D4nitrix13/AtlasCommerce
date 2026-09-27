/**
 * Mock data — catálogo admin (F5.3).
 *
 * Modela 20 productos, 8 categorías (con jerarquía padre/hijo), 5 marcas
 * y 30 variantes. Existe SOLO como placeholder mientras se implementan
 * los endpoints backend `GET /api/v1/admin/catalog/products`,
 * `/categories`, `/brands`, `/variants`. Cuando los endpoints existan,
 * se reemplaza por fetch + hidratación y este archivo se archiva.
 *
 * Convenciones:
 *  - IDs y slugs estables: cruzar la frontera con el backend real
 *    sin necesidad de re-mapping agresivo (los wire types futuros
 *    deben respetar el shape aquí definido).
 *  - `priceCents` / `compareAtPriceCents` / `costCents` en cents
 *    (AGENTS §15). UI formatea con `formatCurrency()`.
 *  - `stock` agregado en producto = suma de stock de variantes.
 *  - `status: 'active' | 'draft' | 'archived'` mapea directo al Badge
 *    semantic del primitive UI (pending/processing/shipped/delivered
 *    para orders; para productos usamos Badge variant=neutral/primary
 *    más un color semántico ad-hoc).
 *  - `status: 'out_of_stock'` se calcula en UI cuando stock === 0,
 *    independientemente del campo status del backend.
 *  - Fechas en ISO 8601. `updatedAt` ordena el listado descendente.
 */

export type ProductStatus = 'active' | 'draft' | 'archived';
export type StockLevel = 'in_stock' | 'low_stock' | 'out_of_stock';

export interface BrandMock {
  id: number;
  slug: string;
  name: string;
  description: string;
  website?: string;
  /** Placeholder SVG con la inicial (mismo pattern que storefront mock). */
  logoUrl: string;
  productsCount: number;
}

export interface CategoryMock {
  id: number;
  slug: string;
  name: string;
  description: string;
  /** FK opcional a la categoría padre (root = null). */
  parentId: number | null;
  productsCount: number;
}

export interface VariantMock {
  id: number;
  sku: string;
  productId: number;
  color: string;
  size: string;
  stock: number;
  priceCents: number;
  status: ProductStatus;
}

export interface ProductMock {
  id: number;
  slug: string;
  name: string;
  description: string;
  sku: string;
  categoryId: number;
  brandId: number;
  priceCents: number;
  compareAtPriceCents?: number;
  costCents: number;
  stock: number;
  status: ProductStatus;
  imageUrl: string;
  weightKg: number;
  updatedAt: string;
  seoTitle: string;
  seoDescription: string;
  variantIds: number[];
}

const BRAND_LOGO = (initial: string, color: string): string =>
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" role="img" aria-label="Brand logo">
      <rect width="80" height="80" rx="12" fill="${color}"/>
      <text x="40" y="50" text-anchor="middle" font-family="system-ui, sans-serif" font-size="36" font-weight="700" fill="white">${initial}</text>
    </svg>`,
  );

const PRODUCT_IMAGE = (label: string, bg: string): string =>
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="${label}">
      <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="${bg}"/><stop offset="100%" stop-color="#cbd5e1"/></linearGradient></defs>
      <rect width="400" height="400" fill="url(#g)"/>
      <text x="200" y="210" text-anchor="middle" font-family="system-ui, sans-serif" font-size="48" font-weight="700" fill="white">${label}</text>
    </svg>`,
  );

/**
 * 5 marcas. Conteos pre-calculados para mostrar el summary en el hub
 * y el grid de cards. Cuando llegue el endpoint, el campo
 * `productsCount` viene del backend y se ignora este cálculo local.
 */
export const BRANDS_MOCK: readonly BrandMock[] = [
  {
    id: 1,
    slug: 'kotobukiya',
    name: 'Kotobukiya',
    description: 'Líder japonés en figuras de colección y merchandise premium.',
    website: 'https://www.kotobukiya.com',
    logoUrl: BRAND_LOGO('K', '#7c3aed'),
    productsCount: 6,
  },
  {
    id: 2,
    slug: 'goodsmile',
    name: 'Good Smile Company',
    description: 'Nendoroids, Figma y figuras de alta calidad.',
    website: 'https://www.goodsmile.info',
    logoUrl: BRAND_LOGO('G', '#0ea5e9'),
    productsCount: 5,
  },
  {
    id: 3,
    slug: 'bandai',
    name: 'Bandai',
    description: 'Tamashii Nations, Gunpla y merchandise de anime clásico.',
    website: 'https://www.bandai.com',
    logoUrl: BRAND_LOGO('B', '#dc2626'),
    productsCount: 4,
  },
  {
    id: 4,
    slug: 'viz-media',
    name: 'VIZ Media',
    description: 'Editor oficial de manga en inglés y merchandising oficial.',
    website: 'https://www.viz.com',
    logoUrl: BRAND_LOGO('V', '#059669'),
    productsCount: 3,
  },
  {
    id: 5,
    slug: 'panda-estampados',
    name: 'Panda Estampados',
    description: 'Marca propia para estampados B2B en textiles y accesorios.',
    logoUrl: BRAND_LOGO('P', '#1e293b'),
    productsCount: 2,
  },
];

/**
 * 8 categorías con jerarquía (3 raíces, 5 hijas). `parentId === null`
 * son raíces. El CategoryTree consume esta shape y la aplana
 * recursivamente para renderizar.
 */
export const CATEGORIES_MOCK: readonly CategoryMock[] = [
  {
    id: 1,
    slug: 'figures',
    name: 'Figures',
    description: 'Figuras de colección de PVC, resin y amiibo.',
    parentId: null,
    productsCount: 8,
  },
  {
    id: 2,
    slug: 'manga',
    name: 'Manga',
    description: 'Tomos de manga en inglés y español.',
    parentId: null,
    productsCount: 4,
  },
  {
    id: 3,
    slug: 'apparel',
    name: 'Apparel',
    description: 'Camisetas, hoodies y merchandise textil.',
    parentId: null,
    productsCount: 5,
  },
  {
    id: 4,
    slug: 'figures-nendoroid',
    name: 'Nendoroid',
    description: 'Línea chibi articulada de Good Smile.',
    parentId: 1,
    productsCount: 3,
  },
  {
    id: 5,
    slug: 'figures-scale',
    name: 'Scale Figures',
    description: 'Figuras a escala 1/7 y 1/8 estáticas.',
    parentId: 1,
    productsCount: 3,
  },
  {
    id: 6,
    slug: 'apparel-tshirts',
    name: 'T-Shirts',
    description: 'Camisetas con estampados oficiales.',
    parentId: 3,
    productsCount: 3,
  },
  {
    id: 7,
    slug: 'manga-shonen',
    name: 'Shonen',
    description: 'Acción y aventura (Shonen Jump).',
    parentId: 2,
    productsCount: 2,
  },
  {
    id: 8,
    slug: 'manga-seinen',
    name: 'Seinen',
    description: 'Narrativa adulta y psychological.',
    parentId: 2,
    productsCount: 2,
  },
];

/**
 * 30 variantes. Distribución: productos #1 y #2 tienen 4 variantes
 * cada uno (tamaños S/M/L/XL); #3 y #4 tienen 3; el resto 1 o 2.
 */
export const VARIANTS_MOCK: readonly VariantMock[] = [
  // Producto 1: Saber Alter (4 variants)
  { id: 1, sku: 'KIT-SAB-001-S', productId: 1, color: 'Black', size: 'S', stock: 4, priceCents: 14500, status: 'active' },
  { id: 2, sku: 'KIT-SAB-001-M', productId: 1, color: 'Black', size: 'M', stock: 12, priceCents: 14500, status: 'active' },
  { id: 3, sku: 'KIT-SAB-001-L', productId: 1, color: 'Black', size: 'L', stock: 8, priceCents: 14500, status: 'active' },
  { id: 4, sku: 'KIT-SAB-001-XL', productId: 1, color: 'Black', size: 'XL', stock: 0, priceCents: 14500, status: 'active' },
  // Producto 2: Rem Nendoroid (3 variants)
  { id: 5, sku: 'KIT-REM-002-STD', productId: 2, color: 'Blue Maid', size: 'Standard', stock: 18, priceCents: 9800, status: 'active' },
  { id: 6, sku: 'KIT-REM-002-EX', productId: 2, color: 'Blue Maid', size: 'Exclusive', stock: 2, priceCents: 12800, status: 'active' },
  { id: 7, sku: 'KIT-REM-002-DRAFT', productId: 2, color: 'Pink Maid', size: 'Standard', stock: 0, priceCents: 9800, status: 'draft' },
  // Producto 3: Eva Unit-01 (3 variants)
  { id: 8, sku: 'KIT-EVA-003-PURP', productId: 3, color: 'Purple', size: 'Standard', stock: 6, priceCents: 22000, status: 'active' },
  { id: 9, sku: 'KIT-EVA-003-GLOW', productId: 3, color: 'Glow-in-dark', size: 'Standard', stock: 1, priceCents: 24000, status: 'active' },
  { id: 10, sku: 'KIT-EVA-003-LIMIT', productId: 3, color: 'Metallic', size: 'Limited', stock: 0, priceCents: 28000, status: 'active' },
  // Producto 4: Tanjiro (3 variants)
  { id: 11, sku: 'KIT-DEM-004-STD', productId: 4, color: 'Hanafuda', size: 'Standard', stock: 22, priceCents: 11000, status: 'active' },
  { id: 12, sku: 'KIT-DEM-004-WEB', productId: 4, color: 'Water Breathing', size: 'Standard', stock: 14, priceCents: 11000, status: 'active' },
  { id: 13, sku: 'KIT-DEM-004-EX', productId: 4, color: 'Hinokami', size: 'Exclusive', stock: 3, priceCents: 13500, status: 'active' },
  // Producto 5: Stitch plush (1)
  { id: 14, sku: 'KIT-STI-005-STD', productId: 5, color: 'Blue', size: 'Medium', stock: 35, priceCents: 3500, status: 'active' },
  // Producto 6: Totoro plush (1)
  { id: 15, sku: 'KIT-TOT-006-LG', productId: 6, color: 'Gray', size: 'Large', stock: 9, priceCents: 4200, status: 'active' },
  // Producto 7: One Piece Manga Vol 1 (1)
  { id: 16, sku: 'KIT-OPM-007-V01', productId: 7, color: 'Standard', size: 'Vol 1', stock: 47, priceCents: 1500, status: 'active' },
  // Producto 8: Berserk Vol 1 (1)
  { id: 17, sku: 'KIT-BSK-008-V01', productId: 8, color: 'Standard', size: 'Vol 1', stock: 18, priceCents: 2200, status: 'active' },
  // Producto 9: Chainsaw Man Vol 1 (1)
  { id: 18, sku: 'KIT-CSM-009-V01', productId: 9, color: 'Standard', size: 'Vol 1', stock: 28, priceCents: 1500, status: 'active' },
  // Producto 10: Vinyl Figure Goku SSJ (2)
  { id: 20, sku: 'KIT-DBS-010-STD', productId: 10, color: 'Gold', size: 'Standard', stock: 4, priceCents: 8500, status: 'active' },
  { id: 21, sku: 'KIT-DBS-010-BLU', productId: 10, color: 'Blue Hair', size: 'Standard', stock: 0, priceCents: 8500, status: 'archived' },
  // Producto 11: Anime tee "Senpai" (3)
  { id: 22, sku: 'KIT-TEE-011-S', productId: 11, color: 'Black', size: 'S', stock: 12, priceCents: 2800, status: 'active' },
  { id: 23, sku: 'KIT-TEE-011-M', productId: 11, color: 'Black', size: 'M', stock: 15, priceCents: 2800, status: 'active' },
  { id: 24, sku: 'KIT-TEE-011-L', productId: 11, color: 'Black', size: 'L', stock: 10, priceCents: 2800, status: 'active' },
  // Producto 12: Hoodie Kitsune (2)
  { id: 25, sku: 'KIT-HOD-012-M', productId: 12, color: 'Navy', size: 'M', stock: 7, priceCents: 5500, status: 'active' },
  { id: 26, sku: 'KIT-HOD-012-L', productId: 12, color: 'Navy', size: 'L', stock: 5, priceCents: 5500, status: 'active' },
  // Producto 13: Camiseta Panda estampada B2B (1)
  { id: 27, sku: 'KIT-PND-013-STD', productId: 13, color: 'White', size: 'M', stock: 80, priceCents: 1800, status: 'active' },
  // Producto 14: Tote bag personalizado B2B (1)
  { id: 28, sku: 'KIT-PND-014-STD', productId: 14, color: 'Natural', size: 'Standard', stock: 60, priceCents: 1200, status: 'active' },
  // Producto 15: Spy x Family Anya (1)
  { id: 29, sku: 'KIT-SXY-015-STD', productId: 15, color: 'Peanuts', size: 'Standard', stock: 16, priceCents: 6500, status: 'active' },
  // Producto 16: Jujutsu Kaisen Gojo (1)
  { id: 30, sku: 'KIT-JJK-016-STD', productId: 16, color: 'White Hair', size: 'Standard', stock: 3, priceCents: 13500, status: 'active' },
];

/**
 * 20 productos. Conteos de stock y variantIds en sincronía con
 * VARIANTS_MOCK. Para mantener la coherencia sin un ORM, las
 * funciones helper al final recalculan estos aggregates.
 */
export const PRODUCTS_MOCK_RAW: readonly ProductMock[] = [
  {
    id: 1,
    slug: 'saber-alter-figure',
    name: 'Saber Alter — Rage of Excalibur',
    description: 'Figura a escala 1/7 de Saber Alter en su icónica pose de Excalibur.',
    sku: 'KIT-SAB-001',
    categoryId: 5,
    brandId: 1,
    priceCents: 14500,
    compareAtPriceCents: 17000,
    costCents: 7500,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('SA', '#7c3aed'),
    weightKg: 0.9,
    updatedAt: '2026-09-25T14:30:00Z',
    seoTitle: 'Saber Alter Figure — Kitsune Store',
    seoDescription: 'Figura premium de Saber Alter en escala 1/7. Envío a todo Nicaragua.',
    variantIds: [1, 2, 3, 4],
  },
  {
    id: 2,
    slug: 'rem-nendoroid',
    name: 'Rem Nendoroid — Re:Zero',
    description: 'Nendoroid de Rem con expresiones intercambiables.',
    sku: 'KIT-REM-002',
    categoryId: 4,
    brandId: 2,
    priceCents: 9800,
    costCents: 5200,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('RE', '#0ea5e9'),
    weightKg: 0.3,
    updatedAt: '2026-09-24T10:15:00Z',
    seoTitle: 'Rem Nendoroid — Kitsune Store',
    seoDescription: 'Nendoroid oficial de Rem con 3 expresiones y accesorios.',
    variantIds: [5, 6, 7],
  },
  {
    id: 3,
    slug: 'eva-unit-01-scale',
    name: 'Evangelion Unit-01 Scale Figure',
    description: 'Figura a escala del Eva-01 en versión purple.',
    sku: 'KIT-EVA-003',
    categoryId: 5,
    brandId: 3,
    priceCents: 22000,
    costCents: 11000,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('EV', '#dc2626'),
    weightKg: 1.4,
    updatedAt: '2026-09-20T08:00:00Z',
    seoTitle: 'Eva-01 Scale Figure — Kitsune Store',
    seoDescription: 'Figura premium de Eva-01 a escala con base iluminada.',
    variantIds: [8, 9, 10],
  },
  {
    id: 4,
    slug: 'tanjiro-nendoroid',
    name: 'Tanjiro Kamado Nendoroid',
    description: 'Nendoroid de Tanjiro con espada Nichirin.',
    sku: 'KIT-DEM-004',
    categoryId: 4,
    brandId: 2,
    priceCents: 11000,
    costCents: 5800,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('TZ', '#7c3aed'),
    weightKg: 0.4,
    updatedAt: '2026-09-23T18:45:00Z',
    seoTitle: 'Tanjiro Nendoroid — Kitsune Store',
    seoDescription: 'Nendoroid oficial de Tanjiro Kamado con efectos de agua.',
    variantIds: [11, 12, 13],
  },
  {
    id: 5,
    slug: 'stitch-plush-medium',
    name: 'Stitch Plush — Medium',
    description: 'Peluche oficial de Stitch tamaño mediano (40cm).',
    sku: 'KIT-STI-005',
    categoryId: 1,
    brandId: 3,
    priceCents: 3500,
    costCents: 1500,
    stock: 35,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('ST', '#0ea5e9'),
    weightKg: 0.4,
    updatedAt: '2026-09-22T12:00:00Z',
    seoTitle: 'Stitch Plush — Kitsune Store',
    seoDescription: 'Peluche oficial de Stitch 40cm.',
    variantIds: [14],
  },
  {
    id: 6,
    slug: 'totoro-plush-large',
    name: 'Totoro Plush — Large',
    description: 'Peluche gigante de Totoro (60cm).',
    sku: 'KIT-TOT-006',
    categoryId: 1,
    brandId: 1,
    priceCents: 4200,
    costCents: 1900,
    stock: 9,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('TT', '#059669'),
    weightKg: 0.7,
    updatedAt: '2026-09-18T09:30:00Z',
    seoTitle: 'Totoro Plush — Kitsune Store',
    seoDescription: 'Peluche de Totoro tamaño grande.',
    variantIds: [15],
  },
  {
    id: 7,
    slug: 'one-piece-vol-1',
    name: 'One Piece Vol. 1 — Romance Dawn',
    description: 'Primer tomo del manga One Piece en inglés (VIZ Media).',
    sku: 'KIT-OPM-007',
    categoryId: 7,
    brandId: 4,
    priceCents: 1500,
    costCents: 600,
    stock: 47,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('OP', '#dc2626'),
    weightKg: 0.2,
    updatedAt: '2026-09-10T15:00:00Z',
    seoTitle: 'One Piece Vol. 1 — Kitsune Store',
    seoDescription: 'Manga oficial One Piece Romance Dawn en inglés.',
    variantIds: [16],
  },
  {
    id: 8,
    slug: 'berserk-vol-1',
    name: 'Berserk Vol. 1 — Black Swordsman',
    description: 'Primer tomo del manga Berserk por Kentaro Miura.',
    sku: 'KIT-BSK-008',
    categoryId: 8,
    brandId: 4,
    priceCents: 2200,
    costCents: 900,
    stock: 18,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('BS', '#1e293b'),
    weightKg: 0.3,
    updatedAt: '2026-09-05T11:20:00Z',
    seoTitle: 'Berserk Vol. 1 — Kitsune Store',
    seoDescription: 'Berserk Vol 1 Black Swordsman manga oficial VIZ.',
    variantIds: [17],
  },
  {
    id: 9,
    slug: 'chainsaw-man-vol-1',
    name: 'Chainsaw Man Vol. 1',
    description: 'Primer tomo del manga Chainsaw Man.',
    sku: 'KIT-CSM-009',
    categoryId: 7,
    brandId: 4,
    priceCents: 1500,
    costCents: 600,
    stock: 28,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('CM', '#dc2626'),
    weightKg: 0.2,
    updatedAt: '2026-09-12T13:00:00Z',
    seoTitle: 'Chainsaw Man Vol. 1 — Kitsune Store',
    seoDescription: 'Manga Chainsaw Man Vol 1 oficial.',
    variantIds: [18],
  },
  {
    id: 10,
    slug: 'goku-ssj-vinyl',
    name: 'Goku SSJ Vinyl Figure',
    description: 'Figura vinilo de Goku en Super Saiyajin.',
    sku: 'KIT-DBS-010',
    categoryId: 5,
    brandId: 3,
    priceCents: 8500,
    costCents: 4200,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('GK', '#f59e0b'),
    weightKg: 0.6,
    updatedAt: '2026-09-19T16:00:00Z',
    seoTitle: 'Goku SSJ Vinyl — Kitsune Store',
    seoDescription: 'Figura vinilo Goku Super Saiyajin.',
    variantIds: [20, 21],
  },
  {
    id: 11,
    slug: 'anime-tee-senpai',
    name: 'Anime Tee "Senpai"',
    description: 'Camiseta negra con estampado "Senpai Noticed Me".',
    sku: 'KIT-TEE-011',
    categoryId: 6,
    brandId: 1,
    priceCents: 2800,
    costCents: 900,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('SN', '#0f172a'),
    weightKg: 0.2,
    updatedAt: '2026-09-21T09:00:00Z',
    seoTitle: 'Anime Tee Senpai — Kitsune Store',
    seoDescription: 'Camiseta estampada Senpai oficial.',
    variantIds: [22, 23, 24],
  },
  {
    id: 12,
    slug: 'kitsune-hoodie-navy',
    name: 'Kitsune Hoodie — Navy',
    description: 'Hoodie color navy con logo Kitsune bordado.',
    sku: 'KIT-HOD-012',
    categoryId: 6,
    brandId: 1,
    priceCents: 5500,
    costCents: 2200,
    stock: 0,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('KH', '#1e3a8a'),
    weightKg: 0.5,
    updatedAt: '2026-09-17T14:00:00Z',
    seoTitle: 'Kitsune Hoodie Navy — Kitsune Store',
    seoDescription: 'Hoodie Kitsune color navy bordado.',
    variantIds: [25, 26],
  },
  {
    id: 13,
    slug: 'panda-tee-b2b',
    name: 'Camiseta estampada B2B — Logo cliente',
    description: 'Camiseta blanca estampada con logo de cliente B2B.',
    sku: 'KIT-PND-013',
    categoryId: 6,
    brandId: 5,
    priceCents: 1800,
    costCents: 700,
    stock: 80,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('PT', '#f1f5f9'),
    weightKg: 0.2,
    updatedAt: '2026-09-15T10:00:00Z',
    seoTitle: 'Camiseta estampada B2B — Kitsune Store',
    seoDescription: 'Camiseta blanca B2B para estampado corporativo.',
    variantIds: [27],
  },
  {
    id: 14,
    slug: 'panda-tote-b2b',
    name: 'Tote bag personalizado B2B',
    description: 'Tote bag de algodón natural para estampado B2B.',
    sku: 'KIT-PND-014',
    categoryId: 3,
    brandId: 5,
    priceCents: 1200,
    costCents: 450,
    stock: 60,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('TB', '#fef3c7'),
    weightKg: 0.15,
    updatedAt: '2026-09-08T08:00:00Z',
    seoTitle: 'Tote bag B2B — Kitsune Store',
    seoDescription: 'Tote bag algodón natural para personalización B2B.',
    variantIds: [28],
  },
  {
    id: 15,
    slug: 'anya-forger-nendoroid',
    name: 'Anya Forger — Spy x Family',
    description: 'Figura de Anya con expresiones "Peanuts".',
    sku: 'KIT-SXY-015',
    categoryId: 4,
    brandId: 2,
    priceCents: 6500,
    costCents: 3200,
    stock: 16,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('AY', '#f472b6'),
    weightKg: 0.3,
    updatedAt: '2026-09-11T11:30:00Z',
    seoTitle: 'Anya Forger — Kitsune Store',
    seoDescription: 'Figura Anya Forger Spy x Family oficial.',
    variantIds: [29],
  },
  {
    id: 16,
    slug: 'gojo-satoru-nendoroid',
    name: 'Gojo Satoru — Jujutsu Kaisen',
    description: 'Nendoroid de Gojo con efecto ilimitado.',
    sku: 'KIT-JJK-016',
    categoryId: 4,
    brandId: 2,
    priceCents: 13500,
    costCents: 7000,
    stock: 3,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('GJ', '#0ea5e9'),
    weightKg: 0.4,
    updatedAt: '2026-09-09T15:45:00Z',
    seoTitle: 'Gojo Satoru Nendoroid — Kitsune Store',
    seoDescription: 'Nendoroid oficial de Gojo Satoru.',
    variantIds: [30],
  },
  {
    id: 17,
    slug: 'pokemon-plush-pikachu',
    name: 'Pikachu Plush — Classic',
    description: 'Peluche oficial Pikachu tamaño clásico.',
    sku: 'KIT-PKM-017',
    categoryId: 1,
    brandId: 3,
    priceCents: 3500,
    costCents: 1500,
    stock: 25,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('PK', '#facc15'),
    weightKg: 0.4,
    updatedAt: '2026-08-30T12:00:00Z',
    seoTitle: 'Pikachu Plush — Kitsune Store',
    seoDescription: 'Peluche Pikachu oficial Bandai.',
    variantIds: [],
  },
  {
    id: 18,
    slug: 'naruto-vol-1',
    name: 'Naruto Vol. 1',
    description: 'Primer tomo del manga Naruto.',
    sku: 'KIT-NAR-018',
    categoryId: 7,
    brandId: 4,
    priceCents: 1500,
    costCents: 600,
    stock: 33,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('NR', '#f97316'),
    weightKg: 0.2,
    updatedAt: '2026-09-02T10:00:00Z',
    seoTitle: 'Naruto Vol. 1 — Kitsune Store',
    seoDescription: 'Manga Naruto Vol 1 oficial VIZ.',
    variantIds: [],
  },
  {
    id: 19,
    slug: 'death-note-vol-1',
    name: 'Death Note Vol. 1',
    description: 'Primer tomo del manga Death Note.',
    sku: 'KIT-DTH-019',
    categoryId: 8,
    brandId: 4,
    priceCents: 1800,
    costCents: 700,
    stock: 12,
    status: 'active',
    imageUrl: PRODUCT_IMAGE('DN', '#1e293b'),
    weightKg: 0.25,
    updatedAt: '2026-09-06T14:00:00Z',
    seoTitle: 'Death Note Vol. 1 — Kitsune Store',
    seoDescription: 'Manga Death Note Vol 1 oficial.',
    variantIds: [],
  },
  {
    id: 20,
    slug: 'ram-nendoroid',
    name: 'Ram Nendoroid — Re:Zero',
    description: 'Nendoroid de Ram con expresiones intercambiables.',
    sku: 'KIT-RAM-020',
    categoryId: 4,
    brandId: 2,
    priceCents: 9800,
    costCents: 5200,
    stock: 0,
    status: 'draft',
    imageUrl: PRODUCT_IMAGE('RM', '#f472b6'),
    weightKg: 0.3,
    updatedAt: '2026-09-26T08:00:00Z',
    seoTitle: 'Ram Nendoroid — Kitsune Store',
    seoDescription: 'Nendoroid oficial de Ram (Re:Zero) — próximo lanzamiento.',
    variantIds: [],
  },
];

/**
 * Recalcula stock agregado + variantIds a partir de VARIANTS_MOCK.
 * Garantiza coherencia si en el futuro se edita una variant sin
 * actualizar el product (ej. devtools). Output inmutable.
 */
function recomputeAggregates(): readonly ProductMock[] {
  const variantsByProduct = new Map<number, VariantMock[]>();
  for (const variant of VARIANTS_MOCK) {
    const list = variantsByProduct.get(variant.productId) ?? [];
    list.push(variant);
    variantsByProduct.set(variant.productId, list);
  }

  return PRODUCTS_MOCK_RAW.map((product) => {
    const variants = variantsByProduct.get(product.id) ?? [];
    const aggregatedStock = variants.reduce((sum, v) => sum + v.stock, 0);
    return {
      ...product,
      stock: aggregatedStock,
      variantIds: variants.map((v) => v.id),
    };
  });
}

export const PRODUCTS_MOCK: readonly ProductMock[] = recomputeAggregates();

/** Umbral por debajo del cual un producto se considera "stock bajo". */
export const LOW_STOCK_THRESHOLD = 10;

/**
 * Devuelve el nivel de stock semántico para un producto.
 * Usado por la tabla para colorear la columna stock y por el hub.
 */
export function getStockLevel(stock: number): StockLevel {
  if (stock === 0) return 'out_of_stock';
  if (stock < LOW_STOCK_THRESHOLD) return 'low_stock';
  return 'in_stock';
}

/** Lookup helpers. */
export function getBrandById(id: number): BrandMock | undefined {
  return BRANDS_MOCK.find((brand) => brand.id === id);
}

export function getCategoryById(id: number): CategoryMock | undefined {
  return CATEGORIES_MOCK.find((category) => category.id === id);
}

export function getProductById(id: number): ProductMock | undefined {
  return PRODUCTS_MOCK.find((product) => product.id === id);
}

export function getVariantsByProductId(productId: number): readonly VariantMock[] {
  return VARIANTS_MOCK.filter((variant) => variant.productId === productId);
}

/**
 * Counts agregados para el hub `/admin/catalog`.
 * Mantener en sync con la tabla hasta que el backend devuelva estos
 * totales como parte del summary endpoint.
 */
export const CATALOG_HUB_SUMMARY = {
  productsCount: PRODUCTS_MOCK.length,
  categoriesCount: CATEGORIES_MOCK.length,
  brandsCount: BRANDS_MOCK.length,
  variantsCount: VARIANTS_MOCK.length,
} as const;