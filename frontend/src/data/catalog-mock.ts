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
    </svg>`
  );

const PRODUCT_IMAGE = (label: string, bg: string): string =>
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="${label}">
      <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="${bg}"/><stop offset="100%" stop-color="#cbd5e1"/></linearGradient></defs>
      <rect width="400" height="400" fill="url(#g)"/>
      <text x="200" y="210" text-anchor="middle" font-family="system-ui, sans-serif" font-size="48" font-weight="700" fill="white">${label}</text>
    </svg>`
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
  {
    id: 1,
    sku: 'KIT-SAB-001-S',
    productId: 1,
    color: 'Black',
    size: 'S',
    stock: 4,
    priceCents: 14500,
    status: 'active',
  },
  {
    id: 2,
    sku: 'KIT-SAB-001-M',
    productId: 1,
    color: 'Black',
    size: 'M',
    stock: 12,
    priceCents: 14500,
    status: 'active',
  },
  {
    id: 3,
    sku: 'KIT-SAB-001-L',
    productId: 1,
    color: 'Black',
    size: 'L',
    stock: 8,
    priceCents: 14500,
    status: 'active',
  },
  {
    id: 4,
    sku: 'KIT-SAB-001-XL',
    productId: 1,
    color: 'Black',
    size: 'XL',
    stock: 0,
    priceCents: 14500,
    status: 'active',
  },
  // Producto 2: Rem Nendoroid (3 variants)
  {
    id: 5,
    sku: 'KIT-REM-002-STD',
    productId: 2,
    color: 'Blue Maid',
    size: 'Standard',
    stock: 18,
    priceCents: 9800,
    status: 'active',
  },
  {
    id: 6,
    sku: 'KIT-REM-002-EX',
    productId: 2,
    color: 'Blue Maid',
    size: 'Exclusive',
    stock: 2,
    priceCents: 12800,
    status: 'active',
  },
  {
    id: 7,
    sku: 'KIT-REM-002-DRAFT',
    productId: 2,
    color: 'Pink Maid',
    size: 'Standard',
    stock: 0,
    priceCents: 9800,
    status: 'draft',
  },
  // Producto 3: Eva Unit-01 (3 variants)
  {
    id: 8,
    sku: 'KIT-EVA-003-PURP',
    productId: 3,
    color: 'Purple',
    size: 'Standard',
    stock: 6,
    priceCents: 22000,
    status: 'active',
  },
  {
    id: 9,
    sku: 'KIT-EVA-003-GLOW',
    productId: 3,
    color: 'Glow-in-dark',
    size: 'Standard',
    stock: 1,
    priceCents: 24000,
    status: 'active',
  },
  {
    id: 10,
    sku: 'KIT-EVA-003-LIMIT',
    productId: 3,
    color: 'Metallic',
    size: 'Limited',
    stock: 0,
    priceCents: 28000,
    status: 'active',
  },
  // Producto 4: Tanjiro (3 variants)
  {
    id: 11,
    sku: 'KIT-DEM-004-STD',
    productId: 4,
    color: 'Hanafuda',
    size: 'Standard',
    stock: 22,
    priceCents: 11000,
    status: 'active',
  },
  {
    id: 12,
    sku: 'KIT-DEM-004-WEB',
    productId: 4,
    color: 'Water Breathing',
    size: 'Standard',
    stock: 14,
    priceCents: 11000,
    status: 'active',
  },
  {
    id: 13,
    sku: 'KIT-DEM-004-EX',
    productId: 4,
    color: 'Hinokami',
    size: 'Exclusive',
    stock: 3,
    priceCents: 13500,
    status: 'active',
  },
  // Producto 5: Stitch plush (1)
  {
    id: 14,
    sku: 'KIT-STI-005-STD',
    productId: 5,
    color: 'Blue',
    size: 'Medium',
    stock: 35,
    priceCents: 3500,
    status: 'active',
  },
  // Producto 6: Totoro plush (1)
  {
    id: 15,
    sku: 'KIT-TOT-006-LG',
    productId: 6,
    color: 'Gray',
    size: 'Large',
    stock: 9,
    priceCents: 4200,
    status: 'active',
  },
  // Producto 7: One Piece Manga Vol 1 (1)
  {
    id: 16,
    sku: 'KIT-OPM-007-V01',
    productId: 7,
    color: 'Standard',
    size: 'Vol 1',
    stock: 47,
    priceCents: 1500,
    status: 'active',
  },
  // Producto 8: Berserk Vol 1 (1)
  {
    id: 17,
    sku: 'KIT-BSK-008-V01',
    productId: 8,
    color: 'Standard',
    size: 'Vol 1',
    stock: 18,
    priceCents: 2200,
    status: 'active',
  },
  // Producto 9: Chainsaw Man Vol 1 (1)
  {
    id: 18,
    sku: 'KIT-CSM-009-V01',
    productId: 9,
    color: 'Standard',
    size: 'Vol 1',
    stock: 28,
    priceCents: 1500,
    status: 'active',
  },
  // Producto 10: Vinyl Figure Goku SSJ (2)
  {
    id: 20,
    sku: 'KIT-DBS-010-STD',
    productId: 10,
    color: 'Gold',
    size: 'Standard',
    stock: 4,
    priceCents: 8500,
    status: 'active',
  },
  {
    id: 21,
    sku: 'KIT-DBS-010-BLU',
    productId: 10,
    color: 'Blue Hair',
    size: 'Standard',
    stock: 0,
    priceCents: 8500,
    status: 'archived',
  },
  // Producto 11: Anime tee "Senpai" (3)
  {
    id: 22,
    sku: 'KIT-TEE-011-S',
    productId: 11,
    color: 'Black',
    size: 'S',
    stock: 12,
    priceCents: 2800,
    status: 'active',
  },
  {
    id: 23,
    sku: 'KIT-TEE-011-M',
    productId: 11,
    color: 'Black',
    size: 'M',
    stock: 15,
    priceCents: 2800,
    status: 'active',
  },
  {
    id: 24,
    sku: 'KIT-TEE-011-L',
    productId: 11,
    color: 'Black',
    size: 'L',
    stock: 10,
    priceCents: 2800,
    status: 'active',
  },
  // Producto 12: Hoodie Kitsune (2)
  {
    id: 25,
    sku: 'KIT-HOD-012-M',
    productId: 12,
    color: 'Navy',
    size: 'M',
    stock: 7,
    priceCents: 5500,
    status: 'active',
  },
  {
    id: 26,
    sku: 'KIT-HOD-012-L',
    productId: 12,
    color: 'Navy',
    size: 'L',
    stock: 5,
    priceCents: 5500,
    status: 'active',
  },
  // Producto 13: Camiseta Panda estampada B2B (1)
  {
    id: 27,
    sku: 'KIT-PND-013-STD',
    productId: 13,
    color: 'White',
    size: 'M',
    stock: 80,
    priceCents: 1800,
    status: 'active',
  },
  // Producto 14: Tote bag personalizado B2B (1)
  {
    id: 28,
    sku: 'KIT-PND-014-STD',
    productId: 14,
    color: 'Natural',
    size: 'Standard',
    stock: 60,
    priceCents: 1200,
    status: 'active',
  },
  // Producto 15: Spy x Family Anya (1)
  {
    id: 29,
    sku: 'KIT-SXY-015-STD',
    productId: 15,
    color: 'Peanuts',
    size: 'Standard',
    stock: 16,
    priceCents: 6500,
    status: 'active',
  },
  // Producto 16: Jujutsu Kaisen Gojo (1)
  {
    id: 30,
    sku: 'KIT-JJK-016-STD',
    productId: 16,
    color: 'White Hair',
    size: 'Standard',
    stock: 3,
    priceCents: 13500,
    status: 'active',
  },
];

/**
 * 20 productos. Conteos de stock y variantIds en sincronía con
 * VARIANTS_MOCK. Para mantener la coherencia sin un ORM, las
 * funciones helper al final recalculan estos aggregates.
 */
export const PRODUCTS_MOCK_RAW: readonly ProductMock[] = [
  // Instrumentos musicales unicamente. Productos de merchandising removidos
  // (Evangelion, Chainsaw Man, Naruto, Totoro, etc). El catalogo publico
  // debe leer de la API real (assets/products/instrumentos-musicales-la-voz/),
  // no del mock data. Ver .ai/rules/assets-naming-policy.md.
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
