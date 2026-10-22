/**
 * ProductDetailVariants — swatches de color / acabado del PDP.
 *
 * Shape actual (`VariantOption`): `id`, `label`, `hex?`, `imageUrl?`.
 * La página deriva las opciones en `COLOR_OPTIONS` /
 * `buildFinishOptions()` y agrega `imageUrl` desde la miniatura real de
 * la variante (`apiImages[].url`), no desde un asset del repo.
 *
 * ── Dato informativo vs selector real ────────────────────────────────
 *
 * Con UN solo acabado la página NO renderiza swatches: muestra la
 * etiqueta del acabado más una miniatura fija (`<img>` sin enlace),
 * porque un selector de una opción es un control muerto. La decisión
 * vive en `hasFinishPicker()` (`lib/product-variant-selection.ts`,
 * `MIN_FINISHES_FOR_PICKER = 2`), NO en este componente — el componente
 * renderiza exactamente las opciones que le pasen. Las stories
 * `SingleFinish*` existen para fijar el shape de entrada de ese caso,
 * no para recomendar renderizarlo.
 *
 * ── Datos ────────────────────────────────────────────────────────────
 *
 * Todo local y estático, sin fetch: los `hex` son literales y las
 * miniaturas son data-URI SVG generadas acá. No se usan rutas de
 * `/public` porque el Storybook de este repo no declara `staticDirs`,
 * así que una URL de archivo no resolvería dentro de la story.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductDetailVariants, { type VariantOption } from './ProductDetailVariants.astro';

/** Miniatura cuadrada y estática con el color del acabado. */
const finishThumbnail = (color: string): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 44"><rect width="44" height="44" fill="${color}"/></svg>`
  )}`;

const COLOR_OPTIONS: readonly VariantOption[] = [
  { id: 'vintage-burst', label: 'Vintage Burst', hex: '#7C3A1A' },
  { id: 'iced-tea', label: 'Iced Tea Burst', hex: '#C89B6A' },
  { id: 'ebony', label: 'Ebony', hex: '#1C1917' },
];

const FINISH_OPTIONS_WITH_IMAGE: readonly VariantOption[] = [
  { id: 'vintage-burst', label: 'Vintage Burst', imageUrl: finishThumbnail('#7C3A1A') },
  { id: 'iced-tea', label: 'Iced Tea Burst', imageUrl: finishThumbnail('#C89B6A') },
  { id: 'ebony', label: 'Ebony', imageUrl: finishThumbnail('#1C1917') },
];

const meta: Meta<typeof ProductDetailVariants> = {
  title: 'Storefront/Products/ProductDetailVariants',
  component: ProductDetailVariants,
  tags: ['autodocs'],
  argTypes: {
    legend: { control: 'text' },
    selected: { control: 'text' },
    hideLegend: { control: 'boolean' },
    variantType: { control: 'inline-radio' },
  },
  args: {
    legend: 'Color',
    options: COLOR_OPTIONS,
    selected: 'vintage-burst',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

/** Varios colores con `hex`, sin imágenes: el shape mínimo. */
export const Default: Story = {};

/**
 * Acabados con miniatura real (Phase 7). Cuando hay `imageUrl` el hex
 * deja de pintar el swatch: la foto manda porque un sunburst o un fade
 * no se representa fiel con un color plano.
 */
export const FinishesWithThumbnails: Story = {
  args: {
    legend: 'Acabado',
    variantType: 'finish',
    options: FINISH_OPTIONS_WITH_IMAGE,
  },
};

/**
 * Mezcla real de la API: hay variantes con miniatura y variantes que
 * sólo traen `finishHex`. Cada una cae a su modo sin que la lista se
 * desalinee.
 */
export const MixedThumbnailAndHex: Story = {
  args: {
    legend: 'Acabado',
    variantType: 'finish',
    selected: 'cherry',
    options: [
      { id: 'cherry', label: 'Sixties Cherry', imageUrl: finishThumbnail('#B0121B') },
      { id: 'walnut', label: 'Vintage Walnut', hex: '#5C3A21' },
      { id: 'black', label: 'Ebony', hex: '#1C1917' },
    ],
  },
};

/**
 * Todas con `hex` y sin imagen. El caso `imageUrl: ''` documenta que el
 * componente trata string vacío como "sin imagen" y vuelve al hex: es
 * el valor que puede mandar la API cuando no hay miniatura.
 */
export const HexWithoutImage: Story = {
  args: {
    legend: 'Color',
    options: [
      { id: 'natural', label: 'Natural', hex: '#D8C3A5', imageUrl: '' },
      { id: 'sunburst', label: 'Sunburst', hex: '#7C3A1A' },
    ],
    selected: 'natural',
  },
};

/**
 * Un solo acabado CON imagen. En el PDP real este estado no llega como
 * selector: `hasFinishPicker()` da false y la página muestra la
 * miniatura como dato informativo.
 */
export const SingleFinishWithImage: Story = {
  args: {
    legend: 'Acabado',
    variantType: 'finish',
    options: [
      { id: 'sixties-cherry', label: 'Sixties Cherry', imageUrl: finishThumbnail('#B0121B') },
    ],
    selected: 'sixties-cherry',
  },
};

/**
 * Un solo acabado SIN imagen: sólo `hex`. Mismo criterio que el caso
 * anterior — la página muestra la etiqueta y nada más.
 */
export const SingleFinishWithoutImage: Story = {
  args: {
    legend: 'Acabado',
    variantType: 'finish',
    options: [{ id: 'sixties-cherry', label: 'Sixties Cherry', hex: '#B0121B' }],
    selected: 'sixties-cherry',
  },
};

/** Sin `selected`: ningún swatch queda marcado como activo. */
export const NoSelection: Story = {
  args: {
    selected: undefined,
  },
};

/** `hideLegend`: conserva el `aria-label` del grupo sin mostrar el texto. */
export const HiddenLegend: Story = {
  args: {
    legend: 'Color',
    hideLegend: true,
  },
};
