/**
 * Lógica pura de la galería admin de producto (RF-CAT-022).
 *
 * Una foto se persiste como N filas (400/800/1200/1600) que comparten
 * `variant_id` + `sort_order` y se distinguen por `width` — la misma
 * identidad que define `ProductPhotoGrouper` en backend. El admin
 * muestra UNA tarjeta por foto: este módulo colapsa las filas y arma
 * los bodies de los endpoints de imágenes.
 *
 * Sin i18n ni fetch: sólo transformación de datos y payloads.
 */

/** Fila del admin images API (`AdminProductImageResource`), snake_case. */
export interface GalleryRowApi {
  id: number;
  product_id: number;
  variant_id: number | null;
  image_url: string;
  alt_text: string | null;
  sort_order: number;
  is_primary: boolean;
  is_hover: boolean;
  width: number | null;
}

/** Foto = las N resoluciones de una misma imagen. */
export interface GalleryPhoto {
  variantId: number | null;
  sortOrder: number;
  isPrimary: boolean;
  isHover: boolean;
  /** URL ya resuelta para el <img> del thumbnail (fila de mayor width). */
  thumbnailUrl: string;
  /** Filas de la foto, ordenadas por width desc y luego id asc. */
  rows: GalleryRowApi[];
}

/** Body de `PUT .../images/order` (`ProductImageReorderRequest`). */
export interface GalleryOrderBody {
  image_ids: number[];
}

export type GallerySlot = 'is_primary' | 'is_hover';

/** Body de `PUT .../images/{image}` para togglear una ranura. */
export interface GalleryUpdateBody {
  is_primary?: boolean;
  is_hover?: boolean;
}

export interface GalleryRowUpdate {
  imageId: number;
  body: GalleryUpdateBody;
}

const ASSET_PROXY_PREFIX = '/api/v1/storefront/assets/';

/**
 * Convierte el `image_url` de una fila (storage key o URL externa) en
 * la URL servible. Mismo criterio que `toProxyUrl` del backend.
 */
export function galleryRowDisplayUrl(imageUrl: string): string {
  if (/^(?:https?:)?\/\//.test(imageUrl) || imageUrl.startsWith('/')) {
    return imageUrl;
  }
  return `${ASSET_PROXY_PREFIX}${imageUrl.replace(/^\/+/, '')}`;
}

/** Width desc (nulls al final) y luego id asc: el representante queda primero. */
function byWidthDescThenId(a: GalleryRowApi, b: GalleryRowApi): number {
  return (b.width ?? -1) - (a.width ?? -1) || a.id - b.id;
}

function representativeRow(rows: readonly GalleryRowApi[]): GalleryRowApi {
  const withWidth = rows.filter((r) => r.width !== null);
  if (withWidth.length === 0) {
    // Sin anchos conocidos no hay "mayor": la primera fila (por id)
    // evita que el thumbnail dependa del orden de llegada.
    return [...rows].sort((a, b) => a.id - b.id)[0]!;
  }
  return [...withWidth].sort(byWidthDescThenId)[0]!;
}

/**
 * Agrupa filas en fotos. La identidad de foto es la terna
 * `(variant_id, sort_order)` — espejo de `ProductPhotoGrouper::keyOf`.
 * Orden estable: `sort_order` asc, luego el id menor de la foto.
 */
export function groupImageRowsToPhotos(rows: readonly GalleryRowApi[]): GalleryPhoto[] {
  const byKey = new Map<string, GalleryRowApi[]>();
  for (const row of rows) {
    const key = `${row.variant_id === null ? 'unscoped' : String(row.variant_id)}#${row.sort_order}`;
    const bucket = byKey.get(key);
    if (bucket === undefined) {
      byKey.set(key, [row]);
    } else {
      bucket.push(row);
    }
  }

  const photos: GalleryPhoto[] = [];
  for (const groupRows of byKey.values()) {
    const sorted = [...groupRows].sort(byWidthDescThenId);
    const rep = representativeRow(groupRows);
    photos.push({
      variantId: groupRows[0]!.variant_id,
      sortOrder: groupRows[0]!.sort_order,
      // `is_primary` sólo marca la fila de mayor width en el upload y
      // `is_hover` replica en las cuatro: el flag a nivel de foto es la
      // OR de sus filas.
      isPrimary: groupRows.some((r) => r.is_primary),
      isHover: groupRows.some((r) => r.is_hover),
      thumbnailUrl: galleryRowDisplayUrl(rep.image_url),
      rows: sorted,
    });
  }

  return photos.sort((a, b) => {
    const idA = Math.min(...a.rows.map((r) => r.id));
    const idB = Math.min(...b.rows.map((r) => r.id));
    return a.sortOrder - b.sortOrder || idA - idB;
  });
}

/**
 * Body de `PUT .../images/order`: el juego COMPLETO de ids de fila, en
 * orden de foto (ids asc dentro de cada foto). El backend exige que no
 * falte ni sobre ninguna fila (422 si no) y toma la primera aparición
 * de cada foto para ordenar.
 */
export function buildOrderPayload(photos: readonly GalleryPhoto[]): GalleryOrderBody {
  return {
    image_ids: photos.flatMap((photo) => photo.rows.map((row) => row.id).sort((a, b) => a - b)),
  };
}

/**
 * Aplica una ranura a TODAS las filas de la foto (view-model para el
 * update optimista del editor).
 */
export function setPhotoSlot(photo: GalleryPhoto, slot: GallerySlot, value: boolean): GalleryPhoto {
  return {
    ...photo,
    rows: photo.rows.map((row) =>
      slot === 'is_primary' ? { ...row, is_primary: value } : { ...row, is_hover: value }
    ),
    isPrimary: slot === 'is_primary' ? value : photo.isPrimary,
    isHover: slot === 'is_hover' ? value : photo.isHover,
  };
}

/**
 * Updates fila por fila para togglear una ranura de la foto.
 *
 * `is_primary` sigue la convención del repo (sólo la fila de mayor
 * width lo lleva); `is_hover` se replica en todas las filas, igual que
 * hace el upload. Al desmarcar se tocan sólo las filas que tienen el
 * flag, para no escribir de más.
 */
export function buildSlotToggleUpdates(
  photo: GalleryPhoto,
  slot: GallerySlot,
  value: boolean
): GalleryRowUpdate[] {
  const rep = representativeRow(photo.rows);

  if (slot === 'is_hover' && value) {
    return photo.rows.map((row) => ({ imageId: row.id, body: { is_hover: true } }));
  }

  const updates: GalleryRowUpdate[] = [];
  for (const row of photo.rows) {
    const carriesFlag = slot === 'is_primary' ? row.is_primary : row.is_hover;
    const clearBody: GalleryUpdateBody =
      slot === 'is_primary' ? { is_primary: false } : { is_hover: false };
    if (slot === 'is_primary' && value && row.id === rep.id) {
      updates.push({ imageId: row.id, body: { is_primary: true } });
    } else if (carriesFlag) {
      updates.push({ imageId: row.id, body: clearBody });
    }
  }
  return updates;
}
