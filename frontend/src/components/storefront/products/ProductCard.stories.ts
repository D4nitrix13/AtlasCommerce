/**
 * ProductCard — tarjeta de producto para grids del storefront.
 *
 * Fase 9 (Oct 2026): la card creció dos features nuevas — swatch row
 * Fender-style (≥2 variants) y image-swap CSS-only en hover (card →
 * back view; swatch N → primary image del variant N). Las stories
 * cubren los 4 estados visuales que descubrió el bug del usuario
 * "hay cards más altas y más cortas":
 *
 *   - `Default`: el caso base (mock sin variantes, sin gallery rica).
 *     Renderiza 1 imagen sin swap — verifica que las stories legacy
 *     no rompen con el código nuevo.
 *   - `MultipleVariants`: el camino feliz (≥2 variants con imagen
 *     por variant y back view). Es la story que ejercita las reglas
 *     `<style is:global>` per-variant.
 *   - `SingleVariantIsExclusive`: 1 sola variant (no swatch row,
 *     mismo criterio que `MIN_FINISHES_FOR_PICKER`) pero con el
 *     badge "Limitado" — valida que el badge top-right no colisiona
 *     con el slot donde irían los swatches.
 *   - `VariantWithNullHex`: una variant con `finishHex: null`. El
 *     swatch debe caer al dot slate (sin romper el layout).
 *   - `NoImages`: el caso edge del seeder cuando un producto no
 *     tiene gallery rica — la card renderiza sólo la imagen legacy
 *     `product.imageUrl` (no hace swap, 1 sola imagen stackeada).
 *   - `CustomSwatchGroupLabel`: el `aria-label` del `role="group"` de
 *     los swatches viene por prop (`swatchGroupLabel`). Esta story
 *     documenta que la prop reemplaza el fallback español en vez de
 *     convivir con él — el hueco que deja el i18n cuando se cablee.
 *
 * Datos: todo local y estático (sin fetch, sin /public paths — el
 * Storybook no declara `staticDirs` en `.storybook/main.ts`).
 * Thumbnails de variants son data-URI SVG generadas acá.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductCard, { type ProductCardLabels } from './ProductCard.astro';
import type { Product, ProductImage, ProductVariant } from '../../../types/catalog';

const meta: Meta<typeof ProductCard> = {
  title: 'Storefront/ProductCard',
  component: ProductCard,
  tags: ['autodocs'],
  argTypes: {
    product: { control: 'object' },
    locale: { control: 'text' },
    labels: { control: 'object' },
    showAddToCart: { control: 'boolean' },
  },
  args: {
    locale: 'es',
    labels: {
      newBadge: 'Nuevo',
      saleBadge: 'Oferta',
      lowStockBadge: 'Últimas unidades',
      addToCart: 'Añadir',
      outOfStock: 'Agotado',
      viewDetail: 'Ver detalle',
      finishCountLabel: (n: number) => `${n} colores`,
    } as ProductCardLabels,
    showAddToCart: true,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * Miniatura cuadrada para los swatches cuando el backend no expone
 * una foto por variant. SVG data-URI inline — sin fetch, sin
 * `/public` paths. Mismas convenciones que
 * `ProductDetailVariants.stories.ts` (Phase 7).
 *
 * (Los swatches de las stories usan los `hex` directamente via
 * `style="background-color"` — la Phase 9 sólo necesita el thumbnail
 * cuando el backend expone `imageUrl` por variant, y la galería de
 * cada story ya genera esos thumbnails con `productImage()`.)
 */

/** Imagen principal del producto (placeholder SVG). */
const productImage = (label: string, color = '#cbd5e1'): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><rect width="400" height="500" fill="#f1f5f9"/><circle cx="200" cy="200" r="60" fill="${color}"/><text x="200" y="430" text-anchor="middle" font-family="system-ui, sans-serif" font-size="20" fill="#64748b">${label}</text></svg>`,
  )}`;

/** Imagen "back" (rol back) — genérica, sirve para todas las variants. */
const productBack = (label: string, color = '#a3b3c0'): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><rect width="400" height="500" fill="#e2e8f0"/><circle cx="200" cy="200" r="60" fill="${color}"/><text x="200" y="430" text-anchor="middle" font-family="system-ui, sans-serif" font-size="20" fill="#475569">${label} (back)</text></svg>`,
  )}`;

const baseLabels: ProductCardLabels = {
  newBadge: 'Nuevo',
  saleBadge: 'Oferta',
  lowStockBadge: 'Últimas unidades',
  addToCart: 'Añadir',
  outOfStock: 'Agotado',
  viewDetail: 'Ver detalle',
  finishCountLabel: (n) => `${n} colores`,
};

/** Producto legacy sin gallery rica — la card renderiza sólo `imageUrl`. */
const legacyProduct: Product = {
  id: 1,
  slug: 'saber-alter-figma',
  name: 'Saber Alter Figma',
  description: 'Figma articulada 14cm, incluye base y manos intercambiables.',
  priceCents: 14500,
  imageUrl: productImage('Saber Alter'),
  category: 'figures',
  stock: 8,
  currency: 'USD',
};

/** Variante Fender-style con shape nueva (incluye `name` para Phase 9). */
function fenderVariant(
  id: string,
  finishSlug: string,
  finishLabel: string,
  hex: string | null,
): ProductVariant {
  return {
    id,
    name: finishLabel,
    sku: `011-${id}`,
    finishSlug,
    finishLabel,
    finishHex: hex,
    condition: 'nuevo',
    stock: 3,
  };
}

interface VariantStub {
  id: string;
  label: string;
  hex: string | null;
}

/** Helper para construir la gallery rica con `isPrimary` + `back` por variant. */
function buildImageGallery(
  variants: readonly VariantStub[],
  options: { primaryVariantId?: string } = {},
): ProductImage[] {
  const primaryId = options.primaryVariantId ?? variants[0]?.id ?? null;
  const gallery: ProductImage[] = [];
  let sortOrder = 0;
  for (const variant of variants) {
    const isPrimary = variant.id === primaryId;
    if (isPrimary) {
      gallery.push({
        url: productImage(variant.label, variant.hex ?? '#cbd5e1'),
        altText: `${variant.label} - vista front`,
        isPrimary: true,
        isHover: false,
        variantId: variant.id,
        sortOrder: 0,
        srcset: '',
      });
      // La foto de hover vive atada a la portada (mismo variantId) en el
      // backend, y es la única con `isHover: true`.
      gallery.push({
        url: productBack(variant.label, variant.hex ?? '#a3b3c0'),
        altText: `${variant.label} - vista back`,
        isPrimary: false,
        isHover: true,
        variantId: variant.id,
        sortOrder: 1,
        srcset: '',
      });
      sortOrder += 1;
    } else {
      gallery.push({
        url: productImage(variant.label, variant.hex ?? '#cbd5e1'),
        altText: `${variant.label} - vista front`,
        isPrimary: false,
        isHover: false,
        variantId: variant.id,
        sortOrder: ++sortOrder,
        srcset: '',
      });
    }
  }
  return gallery;
}

/* ─────── Stories ───────────────────────────────────────────────── */

export const Default: Story = {
  args: {
    product: { ...legacyProduct },
  },
};

export const OnSale: Story = {
  args: {
    product: { ...legacyProduct, compareAtPriceCents: 16900 },
  },
};

export const New: Story = {
  args: {
    product: { ...legacyProduct, badges: ['new'] },
  },
};

export const LowStock: Story = {
  args: {
    product: { ...legacyProduct, name: 'Nendoroid Zero Two', stock: 2, badges: ['low_stock'] },
  },
};

export const OutOfStock: Story = {
  args: {
    product: { ...legacyProduct, name: 'Evangelion Unit-01', stock: 0, badges: ['sale'] },
  },
};

export const AllBadges: Story = {
  args: {
    product: { ...legacyProduct, badges: ['new', 'sale', 'low_stock'], compareAtPriceCents: 16900, stock: 2 },
  },
};

export const LongTitle: Story = {
  args: {
    product: {
      ...legacyProduct,
      name: 'Evangelion Unit-01 1/6 — Edición limitada con luz LED y piezas articuladas premium',
      description: 'Figura escala 1/6 del Eva-01. 30cm, piezas articuladas, base con luz LED opcional. Incluye manual y certificado de autenticidad.',
    },
  },
};

export const WithoutAddToCart: Story = {
  args: {
    product: { ...legacyProduct },
    showAddToCart: false,
  },
};

/**
 * ≥2 variants con gallery rica. Es el camino feliz de Phase 9: el
 * row de swatches aparece en bottom-left, el image-swap funciona
 * con hover CSS-only (no hay `<script>` acá — Storybook embebe el
 * CSS del componente y renderiza las reglas `<style is:global>`
 * igual que en producción).
 */
export const MultipleVariants: Story = {
  args: {
    labels: baseLabels,
    product: (() => {
      const variantSpecs = [
        { id: '1', slug: 'vintage-sunburst', label: '3-Color Sunburst', hex: '#7C3A1A' },
        { id: '2', slug: 'iced-tea', label: 'Iced Tea Burst', hex: '#C89B6A' },
        { id: '3', slug: 'ebony', label: 'Ebony', hex: '#1C1917' },
      ];
      const variants = variantSpecs.map((v) =>
        fenderVariant(v.id, v.slug, v.label, v.hex),
      );
      return {
        ...legacyProduct,
        name: 'American Professional Telecaster',
        slug: 'american-professional-telecaster',
        description: 'Pastilla V-Mod II, mástil deep C, cejilla de hueso.',
        priceCents: 169900,
        imageUrl: productImage('Telecaster', '#7C3A1A'),
        images: buildImageGallery(variantSpecs, { primaryVariantId: '1' }),
        variants,
      };
    })(),
  },
};

/**
 * Una sola variant + isExclusive. El swatch row NO se renderiza (un
 * solo swatch es un control muerto — `shouldRenderSwatchRow` da
 * false). El badge "Limitado" ocupa el slot top-right.
 */
export const SingleVariantIsExclusive: Story = {
  args: {
    labels: baseLabels,
    product: {
      ...legacyProduct,
      name: '1959 Les Paul Standard Reissue',
      description: 'Edición limitada. Pastillas Custombucker, mástil de una pieza.',
      priceCents: 599900,
      isExclusive: true,
      imageUrl: productImage('Les Paul', '#3B241C'),
      images: [
        {
          url: productImage('Les Paul', '#3B241C'),
          altText: '1959 Les Paul Reissue - vista front',
          isPrimary: true,
          isHover: false,
          variantId: '1',
          sortOrder: 0,
          srcset: '',
        },
      ],
      variants: [fenderVariant('1', '1959-reissue', '1959 Reissue', '#3B241C')],
    },
  },
};

/**
 * Una variant con `finishHex: null` (la API devuelve explícitamente
 * `null` en el JSON, no `undefined`). El swatch debe caer al dot
 * slate-400 que define `swatchFallbackHex` en
 * `lib/product-card-swap.ts`. La card no se rompe (sin null pointer
 * en el inline `style="background-color: ..."`).
 */
export const VariantWithNullHex: Story = {
  args: {
    labels: baseLabels,
    product: {
      ...legacyProduct,
      name: 'SG Standard 61 (acabado sin hex)',
      description: 'Sin información de color en el backend. Slate-400 fallback en el swatch.',
      images: buildImageGallery(
        [
          { id: '1', label: 'Finish desconocido', hex: null },
          { id: '2', label: 'Otro acabado', hex: '#5C3A21' },
        ],
        { primaryVariantId: '1' },
      ),
      variants: [
        { ...fenderVariant('1', 'unknown', 'Finish desconocido', null), finishHex: null },
        fenderVariant('2', 'walnut', 'Walnut', '#5C3A21'),
      ],
    },
  },
};

/**
 * Producto sin gallery rica (`images` ausente). El helper
 * `deriveProductCardSwap` detecta `images === undefined` y devuelve
 * `primaryImage: null`, así que la card cae al render legacy con
 * sólo `product.imageUrl` — 1 imagen, sin swap. Verifica que las
 * stories legacy (`Default`, `OnSale`, etc.) sigan renderizando
 * correctamente con el nuevo helper.
 */
export const NoImages: Story = {
  args: {
    labels: baseLabels,
    product: {
      ...legacyProduct,
      name: 'Peluche Totoro mediano',
      description: '30cm, edición oficial.',
      // Sin `images`: el helper cae al legacy imageUrl.
      variants: [],
    },
  },
};

/**
 * Mismo producto que `MultipleVariants`, pero con `swatchGroupLabel`
 * inyectado. El `aria-label` del `role="group"` debe pasar a la prop:
 * mientras el i18n no esté cableado, esta story es la prueba visible de
 * que el label NO queda hardcodeado en el markup.
 */
export const CustomSwatchGroupLabel: Story = {
  args: {
    labels: { ...baseLabels, swatchGroupLabel: 'Available finishes' },
    product: {
      ...legacyProduct,
      name: 'American Professional Telecaster',
      slug: 'american-professional-telecaster',
      description: 'Pastilla V-Mod II, mástil deep C, cejilla de hueso.',
      priceCents: 169900,
      imageUrl: productImage('Telecaster', '#7C3A1A'),
      images: buildImageGallery(
        [
          { id: '1', label: 'Vintage Sunburst', hex: '#7C3A1A' },
          { id: '2', label: 'Iced Tea Burst', hex: '#C89B6A' },
        ],
        { primaryVariantId: '1' },
      ),
      variants: [
        fenderVariant('1', 'vintage-sunburst', 'Vintage Sunburst', '#7C3A1A'),
        fenderVariant('2', 'iced-tea', 'Iced Tea Burst', '#C89B6A'),
      ],
    },
  },
};
