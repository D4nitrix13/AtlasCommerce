/**
 * ProductCardCatalog — tarjeta de catálogo (vista 04 Bonfire).
 *
 * Antes esta card renderizaba `ProductMockup` (un SVG de remera/mug/gorra
 * elegido por `MOCKUP_BY_SLUG`), así que sus stories sólo cambiaban el
 * slug para ver otra silueta. Hoy renderiza la foto real + swatches de
 * acabado, entonces las stories tienen que fijar los estados que un slug
 * no alcanza a cubrir:
 *
 *   - `Default`: catálogo real — galería por variante con `front` + `back`
 *     del variant primario. Es la story que ejercita las reglas
 *     `<style is:global>` per-variant del image-swap.
 *   - `SingleVariant`: 1 sola variante → NO hay swatch row (un círculo
 *     suelto es un control muerto; mismo umbral que
 *     `MIN_FINISHES_FOR_PICKER` del PDP) pero sí muestra la foto.
 *   - `VariantWithNullHex`: variante con `finishHex: null` (lo que
 *     devuelve la API cuando no conoce el color) → cae al dot slate de
 *     `swatchFallbackHex` sin romper el layout.
 *   - `NoImages`: producto sin galería (`images` ausente) → cae al
 *     `product.imageUrl` legacy, una sola imagen, sin swap.
 *   - `LongTitle`: título de 2+ líneas contra uno de 1 — es el par que
 *     reprodujo el bug "hay algunas más altas y otras más cortas" y
 *     documenta que el `min-h` reservado iguala las dos.
 *   - `CustomSwatchGroupLabel`: el `aria-label` del `role="group"` de
 *     los swatches viene por prop (`swatchGroupLabel`). Esta story
 *     documenta que la prop reemplaza el fallback español en vez de
 *     convivir con él — el hueco que deja el i18n cuando se cablee.
 *
 * Datos: todo local y estático (sin fetch, sin rutas de `/public` — el
 * Storybook de este repo no declara `staticDirs`). Las miniaturas de
 * variante son data-URI SVG generadas acá.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductCardCatalog, { type ProductCardCatalogLabels } from './ProductCardCatalog.astro';
import type { Product, ProductImage, ProductVariant } from '../../../types/catalog';

/** Foto de producto (portada). */
const productImage = (label: string, color = '#cbd5e1'): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><rect width="400" height="500" fill="#f1f5f9"/><circle cx="200" cy="190" r="70" fill="${color}"/><text x="200" y="440" text-anchor="middle" font-family="system-ui, sans-serif" font-size="20" fill="#64748b">${label}</text></svg>`,
  )}`;

/** Dorso del instrumento (rol `back`). */
const productBack = (label: string, color = '#a3b3c0'): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><rect width="400" height="500" fill="#e2e8f0"/><circle cx="200" cy="190" r="70" fill="${color}"/><text x="200" y="440" text-anchor="middle" font-family="system-ui, sans-serif" font-size="20" fill="#475569">${label} (dorso)</text></svg>`,
  )}`;

/** Variante de acabado, con el shape que devuelve el listado público. */
function finishVariant(
  id: string,
  finishSlug: string,
  finishLabel: string,
  hex: string | null,
): ProductVariant {
  return {
    id,
    name: finishLabel,
    finishSlug,
    finishLabel,
    finishHex: hex,
    condition: 'nuevo',
    stock: 3,
    handedness: null,
  };
}

interface FinishStub {
  id: string;
  label: string;
  hex: string | null;
}

/**
 * Galería con `front` + `back` del variant primario y `front` del resto.
 * Reproduce el contrato real de `StorefrontProductResource`: el rol viaja
 * en el texto después de `" - vista "` y la imagen atada al variant
 * primario es la única que trae `isPrimary: true`.
 */
function buildGallery(
  finishes: readonly FinishStub[],
  options: { primaryVariantId?: string } = {},
): ProductImage[] {
  const primaryId = options.primaryVariantId ?? finishes[0]?.id ?? null;
  const gallery: ProductImage[] = [];
  let sortOrder = 0;
  for (const finish of finishes) {
    const isPrimary = finish.id === primaryId;
    gallery.push({
      url: productImage(finish.label, finish.hex ?? '#cbd5e1'),
      altText: `${finish.label} - vista front`,
      isPrimary,
      variantId: finish.id,
      sortOrder,
      srcset: '',
    });
    sortOrder += 1;
    if (!isPrimary) continue;
    gallery.push({
      url: productBack(finish.label, finish.hex ?? '#a3b3c0'),
      altText: `${finish.label} - vista back`,
      isPrimary: false,
      variantId: finish.id,
      sortOrder,
      srcset: '',
    });
    sortOrder += 1;
  }
  return gallery;
}

const SUNBURST_FINISHES: readonly FinishStub[] = [
  { id: '1', label: 'Vintage Sunburst', hex: '#7C3A1A' },
  { id: '2', label: 'Iced Tea Burst', hex: '#C89B6A' },
  { id: '3', label: 'Ebony', hex: '#1C1917' },
];

const baseProduct: Product = {
  id: 1,
  slug: 'american-professional-telecaster',
  name: 'American Professional Telecaster',
  description: 'Pastilla V-Mod II, mástil deep C, cejuela de hueso.',
  priceCents: 169900,
  imageUrl: productImage('Telecaster', '#7C3A1A'),
  category: 'instrumentos',
  stock: 5,
  currency: 'USD',
};

const baseLabels: ProductCardCatalogLabels = {
  viewDetail: 'Ver detalle',
  finishCountLabel: (count) => `${count} colores`,
};

const meta: Meta<typeof ProductCardCatalog> = {
  title: 'Storefront/Catalog/ProductCardCatalog',
  component: ProductCardCatalog,
  tags: ['autodocs'],
  argTypes: {
    product: { control: 'object' },
    locale: { control: 'text' },
    labels: { control: 'object' },
  },
  args: {
    product: baseProduct,
    locale: 'es',
    labels: baseLabels,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

/** Catálogo real: 3 acabados, con foto y dorso en el variant primario. */
export const Default: Story = {
  args: {
    product: {
      ...baseProduct,
      images: buildGallery(SUNBURST_FINISHES, { primaryVariantId: '1' }),
      variants: SUNBURST_FINISHES.map((finish) =>
        finishVariant(finish.id, finish.label.toLowerCase().replace(/\s+/g, '-'), finish.label, finish.hex),
      ),
    },
  },
};

/**
 * Una sola variante: `shouldRenderSwatchRow` da false (paridad con el
 * PDP), así que no hay botones — pero la foto real sí se renderiza. Si
 * este caso mostrara un círculo, sería un control que no hace nada.
 */
export const SingleVariant: Story = {
  args: {
    product: {
      ...baseProduct,
      name: '1959 Les Paul Standard Reissue',
      slug: '1959-les-paul-standard-reissue',
      description: 'Pastillas Custombucker, mástil de una pieza.',
      priceCents: 599900,
      imageUrl: productImage('Les Paul', '#3B241C'),
      images: buildGallery([{ id: '1', label: 'Vintage Cream', hex: '#D8C3A5' }]),
      variants: [finishVariant('1', 'vintage-cream', 'Vintage Cream', '#D8C3A5')],
    },
  },
};

/**
 * Variante con `finishHex: null` — la API lo manda explícito cuando no
 * conoce el color del acabado. El swatch cae al slate de
 * `swatchFallbackHex`; si la variante además tiene foto propia, la foto
 * manda sobre el color plano.
 */
export const VariantWithNullHex: Story = {
  args: {
    product: {
      ...baseProduct,
      name: 'SG Standard 61',
      slug: 'sg-standard-61',
      description: 'Acabado sin color declarado en el backend.',
      images: buildGallery(
        [
          { id: '1', label: 'Finish desconocido', hex: null },
          { id: '2', label: 'Vintage Walnut', hex: '#5C3A21' },
        ],
        { primaryVariantId: '1' },
      ),
      variants: [
        finishVariant('1', 'sin-definir', 'Finish desconocido', null),
        finishVariant('2', 'vintage-walnut', 'Vintage Walnut', '#5C3A21'),
      ],
    },
  },
};

/**
 * Producto sin galería (`images` ausente): el helper detecta el array
 * vacío, devuelve `primaryImage: null` y la card cae al `imageUrl` legacy
 * — una sola imagen, sin swap. Cubre el producto que el catálogo publica
 * antes de que le carguen fotos.
 */
export const NoImages: Story = {
  args: {
    product: {
      ...baseProduct,
      name: 'Funda para guitarra eléctrica',
      slug: 'funda-guitarra-electrica',
      description: 'Napa, interior 12 mm.',
      priceCents: 8900,
      imageUrl: productImage('Funda', '#334155'),
      variants: [],
    },
  },
};

/**
 * El par que reprodujo el bug de alturas desiguales: título de una línea
 * contra título de dos. Las dos cards deben ocupar exactamente el mismo
 * alto (el `min-h` del título y de la descripción reservan el espacio).
 */
export const LongTitle: Story = {
  args: {
    product: {
      ...baseProduct,
      name: 'Peluche Totoro mediano edición oficial de estudio con base articulada',
      slug: 'peluche-totoro-mediano',
      description:
        'Peluche de 30 cm con base articulada, incluye certificado de autenticidad y caja original.',
      priceCents: 3500,
      imageUrl: productImage('Totoro', '#94A3B8'),
      images: buildGallery(SUNBURST_FINISHES, { primaryVariantId: '1' }),
      variants: SUNBURST_FINISHES.map((finish) =>
        finishVariant(finish.id, finish.label.toLowerCase().replace(/\s+/g, '-'), finish.label, finish.hex),
      ),
    },
  },
};

/**
 * Mismo catálogo que `Default`, pero con `swatchGroupLabel` inyectado. El
 * `aria-label` del `role="group"` debe pasar a la prop: mientras el i18n
 * no esté cableado, esta story es la prueba visible de que el label NO
 * queda hardcodeado en el markup.
 */
export const CustomSwatchGroupLabel: Story = {
  args: {
    labels: { ...baseLabels, swatchGroupLabel: 'Available finishes' },
    product: {
      ...baseProduct,
      images: buildGallery(SUNBURST_FINISHES, { primaryVariantId: '1' }),
      variants: SUNBURST_FINISHES.map((finish) =>
        finishVariant(finish.id, finish.label.toLowerCase().replace(/\s+/g, '-'), finish.label, finish.hex),
      ),
    },
  },
};