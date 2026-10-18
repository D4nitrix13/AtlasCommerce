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

export function deriveProductDetail(product: Product): ProductDetailData {
  if (product.slug === SABER_ALTER_DETAIL.slug) {
    return SABER_ALTER_DETAIL;
  }

  const variants = (product.variants ?? []).map((v) => {
    const attributes: ProductVariantAttribute[] = [];
    if (v.finishSlug !== undefined && v.finishLabel !== undefined) {
      const colorAttr: ProductVariantAttribute = {
        type: 'color',
        id: v.finishSlug,
        label: v.finishLabel,
      };
      if (v.finishHex !== undefined) {
        colorAttr.hex = v.finishHex;
      }
      attributes.push(colorAttr);
    }
    if (v.sizeSlug !== undefined) {
      attributes.push({ type: 'size', id: v.sizeSlug, label: v.sizeSlug });
    }
    return {
      id: v.id,
      sku: v.sku,
      attributes,
      priceCents: v.priceCents ?? product.priceCents,
      imageUrl: product.images?.[0] ?? product.imageUrl ?? PLACEHOLDER_IMAGE,
      stock: v.stock,
      finishSlug: v.finishSlug,
      finishLabel: v.finishLabel,
      finishHex: v.finishHex,
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
    images: product.images && product.images.length > 0 ? [...product.images] : [product.imageUrl],
    variantAttributes: [],
    variants,
    specs: [],
    reviews: [],
  };
}