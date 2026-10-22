/**
 * Deriva un ProductDetailData para la PDP a partir de un Product base.
 *
 * Si el slug matchea SABER_ALTER_DETAIL (el único mock con PDP
 * enriquecida: variantAttributes, groupedSpecs, reviews, financing,
 * featuredArticle), retorna ese detail directamente.
 *
 * Si no, deriva un detail mínimo desde el Product base SIN heredar
 * colors/sizes/variants de SABER_ALTER_DETAIL. Esto resuelve el bug
 * donde guitarras y otros productos del catálogo La Voz mostraban
 * los selectores de acabado, condición y talla de Saber Alter en
 * lugar de los propios.
 *
 * Los campos opcionales del ProductDetailData (groupedSpecs,
 * highlights, financing, featuredArticle) quedan undefined para
 * productos derivados — el render condicional de la PDP ya los
 * maneja con checks `!== undefined`.
 */

import type { Product } from '../types/catalog';
import {
  SABER_ALTER_DETAIL,
  type ProductDetailData,
  type ProductVariantAttribute,
} from '../data/product-detail-mock';

const PLACEHOLDER_IMAGE = '/images/product-placeholder.png';

/**
 * Aplana `Product.images` (que ahora puede ser `readonly string[]` o
 * `readonly ProductImage[]`) a `string[]`. La PDP espera el shape
 * legacy en `ProductDetailData.images`.
 */
function extractImageUrls(images: Product['images']): string[] {
  if (images === undefined || images.length === 0) return [];
  return images.map((img) => (typeof img === 'string' ? img : img.url));
}

export function deriveProductDetail(product: Product): ProductDetailData {
  if (product.slug === SABER_ALTER_DETAIL.slug) {
    return SABER_ALTER_DETAIL;
  }

  const fallbackImageUrl = extractImageUrls(product.images)[0] ?? product.imageUrl ?? PLACEHOLDER_IMAGE;

  const variants = (product.variants ?? []).map((v) => {
    const attributes: ProductVariantAttribute[] = [];
    if (v.finishSlug !== undefined && v.finishLabel !== undefined) {
      const colorAttr: ProductVariantAttribute = {
        type: 'color',
        id: v.finishSlug,
        label: v.finishLabel,
      };
      // `ProductVariant.finishHex` acepta `null` (backend explícito)
      // o `undefined` (variant sin info de color). `ProductVariantAtt
      // ribute.hex` solo acepta `string | undefined` — coalesce `null`
      // para evitar el type error sin perder el dato.
      if (v.finishHex !== undefined && v.finishHex !== null) {
        colorAttr.hex = v.finishHex;
      }
      attributes.push(colorAttr);
    }
    if (v.sizeSlug !== undefined) {
      attributes.push({ type: 'size', id: v.sizeSlug, label: v.sizeSlug });
    }
    return {
      id: v.id,
      // El listado público no expone `sku`; el PDP lo exige como `string`.
      sku: v.sku ?? '',
      attributes,
      priceCents: v.priceCents ?? product.priceCents,
      imageUrl: fallbackImageUrl,
      stock: typeof v.stock === 'string' ? 0 : v.stock,
      finishSlug: v.finishSlug,
      finishLabel: v.finishLabel,
      finishHex: v.finishHex ?? undefined,
      condition: v.condition,
      sizeSlug: v.sizeSlug,
    };
  });

  const sizes = (product.sizes ?? []).map((s) => ({
    slug: s.slug,
    label: s.label,
    position: s.position,
  }));

  // hasCondition es true solo si hay variants con conditions distintas
  // (un selector de "Nuevo / Usado" no aporta nada si todas las
  // variants son de la misma condición).
  const distinctConditions = new Set(
    variants
      .map((v) => v.condition)
      .filter((c): c is NonNullable<typeof c> => c !== undefined),
  );
  const hasCondition = distinctConditions.size > 1;

  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    description: product.description,
    priceCents: product.priceCents,
    compareAtPriceCents: product.compareAtPriceCents,
    currency: product.currency ?? 'USD',
    category: product.category,
    stock: product.stock,
    badges: product.badges ?? [],
    hasCondition,
    hasSizes: sizes.length > 0,
    isExclusive: product.isExclusive,
    sizes,
    images: (() => {
      const urls = extractImageUrls(product.images);
      return urls.length > 0 ? urls : [product.imageUrl];
    })(),
    variantAttributes: [],
    variants,
    specs: [],
    reviews: [],
    // Phase 8: contenido extendido. Defaults a arrays vacíos para
    // que la PDP pueda iterarlos sin checks `?? []` y para que
    // productos derivados que aún no tienen contenido extendido no
    // rompan el render (el componente ya tiene empty-safe built-in).
    highlights: [],
    groupedSpecs: [],
    faqs: [],
    extendedDescriptions: [],
  };
}