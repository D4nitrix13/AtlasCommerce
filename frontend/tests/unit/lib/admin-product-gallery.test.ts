/**
 * Tests de la lógica pura de la galería admin de producto.
 *
 * `groupImageRowsToPhotos` colapsa las N filas por foto (resoluciones
 * 400/800/1200/1600 que comparten `variant_id` + `sort_order`, misma
 * identidad que `ProductPhotoGrouper` en backend) en UNA tarjeta por
 * foto, con el thumbnail de la fila de mayor `width`.
 *
 * `buildOrderPayload` arma el body exacto de
 * `PUT .../images/order` (`image_ids` con el juego COMPLETO de ids —
 * ver `ProductImageReorderRequest`).
 */
import { describe, expect, it } from 'vitest';
import {
  buildOrderPayload,
  buildSlotToggleUpdates,
  galleryRowDisplayUrl,
  groupImageRowsToPhotos,
  type GalleryRowApi,
} from '../../../src/lib/admin-product-gallery';

function row(overrides: Partial<GalleryRowApi> = {}): GalleryRowApi {
  return {
    id: 1,
    product_id: 7,
    variant_id: null,
    image_url: 'tenants/t/products/p/hash.webp',
    alt_text: null,
    sort_order: 0,
    is_primary: false,
    is_hover: false,
    width: 1600,
    ...overrides,
  };
}

/** Las 4 resoluciones de UNA foto, en desorden a propósito. */
function photoRows(baseId: number, overrides: Partial<GalleryRowApi> = {}): GalleryRowApi[] {
  return [
    row({ id: baseId + 3, width: 800, ...overrides }),
    row({ id: baseId + 0, width: 1600, ...overrides }),
    row({ id: baseId + 2, width: 400, ...overrides }),
    row({ id: baseId + 1, width: 1200, ...overrides }),
  ];
}

describe('groupImageRowsToPhotos', () => {
  it('agrupa las 4 resoluciones de una foto en una sola tarjeta', () => {
    const photos = groupImageRowsToPhotos(photoRows(10));
    expect(photos).toHaveLength(1);
    expect(photos[0]?.rows).toHaveLength(4);
    expect(photos[0]?.variantId).toBeNull();
    expect(photos[0]?.sortOrder).toBe(0);
    expect(photos[0]?.isPrimary).toBe(false);
    expect(photos[0]?.isHover).toBe(false);
  });

  it('el thumbnail es la fila de mayor width (1600)', () => {
    const rows = photoRows(10).map((r) =>
      r.width === 1600 ? { ...r, image_url: 'tenants/t/products/p/w1600.webp' } : r
    );
    const photos = groupImageRowsToPhotos(rows);
    expect(photos[0]?.thumbnailUrl).toBe(
      '/api/v1/storefront/assets/tenants/t/products/p/w1600.webp'
    );
  });

  it('detecta primary/hover desde las filas de la foto', () => {
    const rows = photoRows(10).map((r) => ({
      ...r,
      is_primary: r.width === 1600,
      is_hover: true,
    }));
    const photos = groupImageRowsToPhotos([...rows, ...photoRows(30, { sort_order: 1 })]);
    expect(photos[0]?.isPrimary).toBe(true);
    expect(photos[0]?.isHover).toBe(true);
    expect(photos[1]?.isPrimary).toBe(false);
    expect(photos[1]?.isHover).toBe(false);
  });

  it('ordena las fotos por sort_order y luego por el id menor', () => {
    const later = photoRows(10, { sort_order: 2 });
    const first = photoRows(30, { sort_order: 0 });
    const secondSameOrder = photoRows(50, { sort_order: 0, variant_id: 3 });
    const photos = groupImageRowsToPhotos([...later, ...secondSameOrder, ...first]);
    expect(photos.map((p) => [p.sortOrder, p.rows[0]?.id ?? 0])).toEqual([
      [0, 30],
      [0, 50],
      [2, 10],
    ]);
  });

  it('filas con igual sort_order pero distinto variant_id son fotos distintas', () => {
    const photos = groupImageRowsToPhotos([
      ...photoRows(10, { sort_order: 0, variant_id: null }),
      ...photoRows(30, { sort_order: 0, variant_id: 5 }),
    ]);
    expect(photos).toHaveLength(2);
    expect(photos[0]?.variantId).toBeNull();
    expect(photos[1]?.variantId).toBe(5);
  });

  it('sin filas con width el thumbnail cae a la primera fila (por id)', () => {
    const photos = groupImageRowsToPhotos([
      row({ id: 2, width: null, image_url: 'tenants/t/products/p/b.webp', sort_order: 0 }),
      row({ id: 1, width: null, image_url: 'tenants/t/products/p/a.webp', sort_order: 0 }),
    ]);
    expect(photos[0]?.thumbnailUrl).toBe('/api/v1/storefront/assets/tenants/t/products/p/a.webp');
  });
});

describe('buildOrderPayload', () => {
  it('arma { image_ids } con el juego completo de ids en orden de foto', () => {
    const rowsA = photoRows(10, { sort_order: 0 });
    const rowsB = photoRows(30, { sort_order: 1 });
    // Llegan desordenadas: el payload respeta el orden de las fotos.
    const photos = groupImageRowsToPhotos([...rowsB, ...rowsA]);
    expect(buildOrderPayload(photos)).toEqual({
      image_ids: [10, 11, 12, 13, 30, 31, 32, 33],
    });
  });

  it('incluye TODAS las filas de cada foto (el backend exige el juego completo)', () => {
    const photos = groupImageRowsToPhotos(photoRows(10));
    expect(buildOrderPayload(photos).image_ids).toHaveLength(4);
  });

  it('una galería vacía devuelve image_ids vacío', () => {
    expect(buildOrderPayload([])).toEqual({ image_ids: [] });
  });
});

describe('buildSlotToggleUpdates', () => {
  it('marcar principal actualiza solo la fila representante', () => {
    const photos = groupImageRowsToPhotos(photoRows(10, { sort_order: 0 }));
    const updates = buildSlotToggleUpdates(photos[0]!, 'is_primary', true);
    expect(updates).toEqual([{ imageId: 10, body: { is_primary: true } }]);
  });

  it('marcar principal limpia una fila primaria vieja no representante', () => {
    const rows = photoRows(10, { sort_order: 0 }).map((r) =>
      r.width === 400 ? { ...r, is_primary: true } : r
    );
    const photos = groupImageRowsToPhotos(rows);
    const updates = buildSlotToggleUpdates(photos[0]!, 'is_primary', true);
    expect(updates).toEqual([
      { imageId: 10, body: { is_primary: true } },
      { imageId: 12, body: { is_primary: false } },
    ]);
  });

  it('marcar hover replica el flag en las 4 filas', () => {
    const photos = groupImageRowsToPhotos(photoRows(10, { sort_order: 0 }));
    const updates = buildSlotToggleUpdates(photos[0]!, 'is_hover', true);
    // Filas ordenadas por width desc: 1600, 1200, 800, 400.
    expect(updates.map((u) => u.imageId)).toEqual([10, 11, 13, 12]);
    expect(updates.every((u) => u.body.is_hover === true)).toBe(true);
  });

  it('desmarcar solo toca las filas que tienen el flag', () => {
    const rows = photoRows(10, { sort_order: 0 }).map((r) =>
      r.width === 800 ? { ...r, is_hover: true } : r
    );
    const photos = groupImageRowsToPhotos(rows);
    const updates = buildSlotToggleUpdates(photos[0]!, 'is_hover', false);
    expect(updates).toEqual([{ imageId: 13, body: { is_hover: false } }]);
  });
});

describe('galleryRowDisplayUrl', () => {
  it('convierte un storage key en la URL del proxy de assets', () => {
    expect(galleryRowDisplayUrl('tenants/t/products/p/h.webp')).toBe(
      '/api/v1/storefront/assets/tenants/t/products/p/h.webp'
    );
  });

  it('una URL absoluta se usa tal cual', () => {
    expect(galleryRowDisplayUrl('https://cdn.example.com/h.webp')).toBe(
      'https://cdn.example.com/h.webp'
    );
  });
});
