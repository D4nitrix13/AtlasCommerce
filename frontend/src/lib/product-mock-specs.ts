/**
 * product-mock-specs — specs declarativos del catálogo demo de guitarras.
 *
 * Cada spec es brand-agnostic y se corresponde con una entrada de
 * `backend/database/seeds/products.json`. Se usan con
 * `createProductMock` / `createProductDetailMock` /
 * `createRelatedProduct` para generar mocks consistentes con la
 * data que el seeder backend persiste.
 *
 * Las hex de los swatches se derivan del body color real del
 * producto (consultado en recon Oct 2026).
 */

import type { ProductMockSpec } from './product-mock-factory';

const GUITAR_CATEGORY = {
  category: 'Guitarra eléctrica',
  categorySlug: 'guitarra-electrica',
} as const;

const FENDER_AMPRO_TELE_INDEXED = {
  naming: 'indexed',
  frontSize: '1600x525',
  backSize: '1600x525',
  frontIndex: '01',
  backIndex: '02',
} as const;

const FENDER_AMPRO_STRAT_INDEXED = {
  naming: 'indexed',
  frontSize: '1600x523',
  backSize: '1600x521',
  frontIndex: '01',
  backIndex: '02',
} as const;

const FENDER_AMPRO_JAGUAR_INDEXED = {
  naming: 'indexed',
  frontSize: '1600x545',
  backSize: '1600x543',
  frontIndex: '01',
  backIndex: '02',
} as const;

const FENDER_AMVII_INDEXED = {
  naming: 'indexed',
  frontSize: '1600x525',
  backSize: '1600x525',
  frontIndex: '01',
  backIndex: '02',
} as const;

const GIBSON_KEYWORD = {
  naming: 'keyword',
  frontSize: '1600x1600',
  backSize: '1600x1600',
  frontKeyword: 'front',
  backKeyword: 'back',
} as const;

const GIBSON_LPS_INDEXED = {
  naming: 'indexed',
  frontSize: '1600x1600',
  backSize: '1600x1600',
  frontIndex: '01',
  backIndex: '02',
} as const;

export const FENDER_AMPRO_TELECASTER: ProductMockSpec = {
  slug: 'american-professional-telecaster',
  name: 'Fender American Professional Telecaster',
  brand: 'Fender',
  brandSlug: 'fender',
  ...GUITAR_CATEGORY,
  description:
    'La American Professional Telecaster combina más de setenta años de innovación con pickups V-Mod III single-coil, puente top-load/string-through con ClassicGear™ Compensated Brass Bullet™ Saddles, y perfil de mástil Deep "C" con bordes rolled.',
  priceCents: 169900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: FENDER_AMPRO_TELE_INDEXED,
  variants: [
    { slug: '3-color-sunburst', displayName: '3-Color Sunburst', sku: 'FEND-AMPRO-TEL-0700', fingerboard: 'rosewood', backSku: '0111140700', isDefault: true, hex: '#3B2410', frontSize: '1600x525', backSize: '1600x521', },
    { slug: 'black', displayName: 'Black', sku: 'FEND-AMPRO-TEL-0706', fingerboard: 'rosewood', backSku: '0111140700', hex: '#0A0A0A', frontSize: '1600x523', backSize: '1600x521', },
    { slug: 'natural', displayName: 'Natural', sku: 'FEND-AMPRO-TEL-0721', fingerboard: 'rosewood', backSku: '0111140700', hex: '#C7A66B', frontSize: '1600x525', backSize: '1600x521', },
    { slug: 'oxblood', displayName: 'Oxblood', sku: 'FEND-AMPRO-TEL-0730', fingerboard: 'rosewood', backSku: '0111140700', hex: '#5C1F22', frontSize: '1600x524', backSize: '1600x521', },
    { slug: 'dark-night', displayName: 'Dark Night', sku: 'FEND-AMPRO-TEL-0761', fingerboard: 'rosewood', backSku: '0111140700', hex: '#1B2533', frontSize: '1600x526', backSize: '1600x521', },
    { slug: 'aged-cherry-burst-maple', displayName: 'Aged Cherry Burst', sku: 'FEND-AMPRO-TEL-2731', fingerboard: 'maple', backSku: '0111142700', hex: '#7C2A26', frontSize: '1600x525', backSize: '1600x521', },
    { slug: 'butterscotch-blonde-maple', displayName: 'Butterscotch Blonde', sku: 'FEND-AMPRO-TEL-2750', fingerboard: 'maple', backSku: '0111142700', hex: '#D4A557', frontSize: '1600x522', backSize: '1600x521', },
  ],
};

export const FENDER_AMPRO_STRATOCASTER: ProductMockSpec = {
  slug: 'american-professional-stratocaster',
  name: 'Fender American Professional Stratocaster',
  brand: 'Fender',
  brandSlug: 'fender',
  ...GUITAR_CATEGORY,
  description:
    'La American Professional Stratocaster combina más de setenta años de innovación con pickups V-Mod III single-coil Strat, trémolo 2-point con ClassicGear™ Radial™ bent-steel saddles, y perfil de mástil Deep "C" con bordes rolled.',
  priceCents: 169900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: FENDER_AMPRO_STRAT_INDEXED,
  variants: [
    { slug: '3-color-sunburst', displayName: '3-Color Sunburst', sku: 'FEND-AMPRO-STR-0700', fingerboard: 'rosewood', backSku: '0111110700', isDefault: true, hex: '#3B2410', frontSize: '1600x520', backSize: '1600x519', },
    { slug: 'olympic-white', displayName: 'Olympic White', sku: 'FEND-AMPRO-STR-0705', fingerboard: 'rosewood', backSku: '0111110700', hex: '#F4F0E6', frontSize: '1600x518', backSize: '1600x519', },
    { slug: 'black', displayName: 'Black', sku: 'FEND-AMPRO-STR-0706', fingerboard: 'rosewood', backSku: '0111110700', hex: '#0A0A0A', frontSize: '1600x521', backSize: '1600x519', },
    { slug: 'oxblood', displayName: 'Oxblood', sku: 'FEND-AMPRO-STR-0730', fingerboard: 'rosewood', backSku: '0111110700', hex: '#5C1F22', frontSize: '1600x519', backSize: '1600x519', },
    { slug: 'dark-night', displayName: 'Dark Night', sku: 'FEND-AMPRO-STR-0761', fingerboard: 'rosewood', backSku: '0111110700', hex: '#1B2533', frontSize: '1600x521', backSize: '1600x519', },
    { slug: '3-color-sunburst-maple', displayName: '3-Color Sunburst (Maple)', sku: 'FEND-AMPRO-STR-2700', fingerboard: 'maple', backSku: '0111112700', hex: '#3B2410', frontSize: '1600x523', backSize: '1600x519', },
    { slug: 'olympic-white-maple', displayName: 'Olympic White (Maple)', sku: 'FEND-AMPRO-STR-2705', fingerboard: 'maple', backSku: '0111112700', hex: '#F4F0E6', frontSize: '1600x523', backSize: '1600x519', },
  ],
};

export const FENDER_AMPRO_JAGUAR: ProductMockSpec = {
  slug: 'american-professional-jaguar',
  name: 'Fender American Professional Jaguar',
  brand: 'Fender',
  brandSlug: 'fender',
  ...GUITAR_CATEGORY,
  description:
    'La American Professional Jaguar combina el scale 24" único de la Jaguar con pickups V-Mod III Jaguar single-coil, trémolo Panorama, y perfil de mástil Deep "C" con bordes rolled.',
  priceCents: 189900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: FENDER_AMPRO_JAGUAR_INDEXED,
  variants: [
    { slug: '3-color-sunburst', displayName: '3-Color Sunburst', sku: 'FEND-AMPRO-JAG-0700', backSku: '0111820700', isDefault: true, hex: '#3B2410', frontSize: '1600x545', backSize: '1600x543', },
    { slug: 'olympic-white', displayName: 'Olympic White', sku: 'FEND-AMPRO-JAG-0705', backSku: '0111820700', hex: '#F4F0E6', frontSize: '1600x543', backSize: '1600x543', },
    { slug: 'black', displayName: 'Black', sku: 'FEND-AMPRO-JAG-0706', backSku: '0111820700', hex: '#0A0A0A', frontSize: '1600x542', backSize: '1600x543', },
    { slug: 'oxblood', displayName: 'Oxblood', sku: 'FEND-AMPRO-JAG-0730', backSku: '0111820700', hex: '#5C1F22', frontSize: '1600x544', backSize: '1600x543', },
    { slug: 'dark-night', displayName: 'Dark Night', sku: 'FEND-AMPRO-JAG-0761', backSku: '0111820700', hex: '#1B2533', frontSize: '1600x546', backSize: '1600x543', },
  ],
};

export const FENDER_AMVII_57_STRAT: ProductMockSpec = {
  slug: 'fender-american-vintage-ii-1957-stratocaster',
  name: 'Fender American Vintage II 1957 Stratocaster, Black',
  brand: 'Fender',
  brandSlug: 'fender',
  ...GUITAR_CATEGORY,
  description:
    'Reproducción de la Stratocaster de 1957 con perfil de mástil "V" original, mástil de maple con radio 7.25", trémolo sincronizado Pure Vintage, y pickups Pure Vintage 57 Strat single-coil. Acabado nitrocelulósico gloss sobre cuerpo de alder.',
  priceCents: 239900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: FENDER_AMVII_INDEXED,
  variants: [
    { slug: 'black', displayName: 'Black', sku: 'FEND-AMVII-57STR-2806', isDefault: true, hex: '#0A0A0A', frontSize: '1600x518', },
  ],
};

export const GIBSON_LES_PAUL_SUPREME: ProductMockSpec = {
  slug: 'gibson-les-paul-supreme-blueberry-burst',
  name: 'Gibson Les Paul Supreme, Blueberry Burst, Exclusive',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description:
    'La Les Paul Supreme con acabado exclusivo Blueberry Burst. Cuerpo de mahogany con tapa de arce AAA figurado, mástil de mahogany con perfil SlimTaper, diapasón de ebano con inlays Super Split Block mother-of-pearl. Pastillas Burstbucker Pro y Pro Plus humbuckers con coil-tap, phase y Pure Bypass switching. Incluye estuche hardshell.',
  priceCents: 399900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_LPS_INDEXED,
  variants: [
    { slug: 'blueberry-burst', displayName: 'Blueberry Burst', sku: 'GIBSON-LPSU25-B9GH3', isDefault: true, hex: '#5B6B9E', frontSize: '1600x1600', },
  ],
};

export const GIBSON_CUSTOM_SELECT_1968: ProductMockSpec = {
  slug: 'gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle',
  name: 'Custom 1968 Les Paul Custom Reissue, Gloss, White Sparkle',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description:
    'Reissue Custom Shop de la histórica Les Paul Custom 1968. Construcción mahogany con tapa de arce, binding múltiple, pastillas humbucker.',
  priceCents: 449900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'gloss-white-sparkle', displayName: 'Gloss, White Sparkle', sku: 'GIBSON-LPC68PSL-WHITE-SPARKLE', isDefault: true, hex: '#F8F8F4', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_ES_335: ProductMockSpec = {
  slug: 'gibson-mod-collection-es-335-smooth-sepia',
  name: 'Mod™ Collection ES-335, Smooth Sepia',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'ES-335 semisólida en acabado Smooth Sepia. Mástil de mahogany, pickups MHS, stopbar.',
  priceCents: 299900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'smooth-sepia', displayName: 'Smooth Sepia', sku: 'GIBSON-MOD-ES335-SMOOTH-SEPIA', isDefault: true, hex: '#5C4A3A', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_SG_HOLY_SHIFT: ProductMockSpec = {
  slug: 'gibson-mod-collection-sg-standard-holy-shift',
  name: 'Mod™ Collection SG Standard, Holy Shift',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'SG Standard con acabado Holy Shift exclusivo del Mod Collection. Mástil de mahogany, pastillas 490R/490T.',
  priceCents: 189900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'holy-shift', displayName: 'Holy Shift', sku: 'GIBSON-MOD-SGSTD-HOLY-SHIFT', isDefault: true, hex: '#3A2A2A', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_LP60S_HORNET: ProductMockSpec = {
  slug: 'gibson-mod-collection-les-paul-standard-60s-hornet-burst',
  name: 'Mod™ Collection Les Paul Standard 60s, Hornet Burst',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'Les Paul Standard 60s con tapa de arce figurado en acabado Hornet Burst. Burstbucker 60s, mástil 60s slim taper.',
  priceCents: 269900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'hornet-burst', displayName: 'Hornet Burst', sku: 'GIBSON-MOD-LPSTD60S-HORNET', isDefault: true, hex: '#6B4A1F', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_SG_MODERN_TENACITY: ProductMockSpec = {
  slug: 'gibson-mod-collection-sg-modern-tenacity-blue',
  name: 'Mod™ Collection SG Modern, Tenacity Blue',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'SG Modern con acabado Tenacity Blue. Mástil de maple roastado, pickups Burstbucker Pro, push-pull coil splits.',
  priceCents: 229900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'tenacity-blue', displayName: 'Tenacity Blue', sku: 'GIBSON-MOD-SGMOD-TENACITY', isDefault: true, hex: '#3B6F9E', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_ES_339_GREEN_MEME: ProductMockSpec = {
  slug: 'gibson-mod-collection-es-339-green-meme',
  name: 'Mod™ Collection ES-339, Green Meme',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'ES-339 en exclusivo acabado Green Meme. Construcción centerblock semisólida, Burstbucker Pro.',
  priceCents: 279900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'green-meme', displayName: 'Green Meme', sku: 'GIBSON-MOD-ES339-GREEN-MEME', isDefault: true, hex: '#3E6F4A', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_FLYING_V_BOISTEROUS: ProductMockSpec = {
  slug: 'gibson-mod-collection-flying-v-80s-boisterous-blue',
  name: 'Mod™ Collection Flying V 80s, Boisterous Blue',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'Flying V 80s Limited Run con acabado Boisterous Blue. Mástil de maple, pastillas Burstbucker 3.',
  priceCents: 219900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'boisterous-blue', displayName: 'Boisterous Blue', sku: 'GIBSON-MOD-FV80S-BOISTEROUS', isDefault: true, hex: '#2E5A9E', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_LP_JUNIOR_TROPICALIA: ProductMockSpec = {
  slug: 'gibson-mod-collection-les-paul-junior-tropicalia',
  name: 'Mod™ Collection Les Paul Junior, Tropicalia',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'Les Paul Junior con acabado Tropicalia exclusivo. Cuerpo mahogany single-cut, P-90 dogear.',
  priceCents: 159900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'tropicalia', displayName: 'Tropicalia', sku: 'GIBSON-MOD-LPJUN-TROPICALIA', isDefault: true, hex: '#D9A04A', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_LP_CUSTOM_70S: ProductMockSpec = {
  slug: 'gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite',
  name: 'Mod™ Collection Les Paul Custom 70s, Nighty-Nite Graphite',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'Les Paul Custom 70s en exclusivo acabado Nighty-Nite Graphite. Construcción mahogany, binding 5-ply, T-top pickups.',
  priceCents: 329900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'nighty-nite-graphite', displayName: 'Nighty-Nite Graphite', sku: 'GIBSON-MOD-LPCUST70S-NNITE', isDefault: true, hex: '#2C2C30', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

export const GIBSON_MOD_FLYING_V_FLAME_JOB: ProductMockSpec = {
  slug: 'gibson-mod-collection-flying-v-80s-flame-job-hot-rod',
  name: 'Mod™ Collection Flying V 80s, Flame Job Hot Rod',
  brand: 'Gibson',
  brandSlug: 'gibson',
  ...GUITAR_CATEGORY,
  description: 'Flying V 80s con tapa de arce flameado en acabado Flame Job Hot Rod. Burstbucker 3, push-pull coil splits.',
  priceCents: 249900,
  currency: 'USD',
  badges: ['new'],
  imagePattern: GIBSON_KEYWORD,
  variants: [
    { slug: 'flame-job-hot-rod', displayName: 'Flame Job Hot Rod', sku: 'GIBSON-MOD-FV80S-FLAME', isDefault: true, hex: '#A23A1E', frontSize: '1600x1600', backSize: '1600x1600', },
  ],
};

/** Lista plana de todos los specs de guitarras para iterar. */
export const GUITAR_SPECS: ReadonlyArray<ProductMockSpec> = [
  FENDER_AMPRO_TELECASTER,
  FENDER_AMPRO_STRATOCASTER,
  FENDER_AMPRO_JAGUAR,
  FENDER_AMVII_57_STRAT,
  GIBSON_LES_PAUL_SUPREME,
  GIBSON_CUSTOM_SELECT_1968,
  GIBSON_MOD_ES_335,
  GIBSON_MOD_SG_HOLY_SHIFT,
  GIBSON_MOD_LP60S_HORNET,
  GIBSON_MOD_SG_MODERN_TENACITY,
  GIBSON_MOD_ES_339_GREEN_MEME,
  GIBSON_MOD_FLYING_V_BOISTEROUS,
  GIBSON_MOD_LP_JUNIOR_TROPICALIA,
  GIBSON_MOD_LP_CUSTOM_70S,
  GIBSON_MOD_FLYING_V_FLAME_JOB,
];