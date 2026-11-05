/**
 * useProductGallery — estado y mutaciones de la galería admin.
 *
 * Encapsula fetch (paginación por cursor), toggles de ranura con
 * update optimista, borrado por foto, reordenamiento y upload. La
 * lógica pura (agrupado, payloads) vive en `lib/admin-product-gallery`;
 * este hook sólo orquesta red + estado.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import {
  buildOrderPayload,
  buildSlotToggleUpdates,
  groupImageRowsToPhotos,
  setPhotoSlot,
  type GalleryPhoto,
  type GalleryRowApi,
  type GallerySlot,
} from '../../../lib/admin-product-gallery';

interface CursorPage<T> {
  data: T[];
  meta?: { next_cursor?: string | null } | null;
}

async function fetchAllRows(endpoint: string): Promise<GalleryRowApi[]> {
  const rows: GalleryRowApi[] = [];
  let cursor: string | null = null;
  for (let page = 0; page < 10; page += 1) {
    const separator = endpoint.includes('?') ? '&' : '?';
    // `url` anotado: `cursor` depende de `response` y viceversa, y sin
    // la anotación TS no puede desambiguar el ciclo (ts(7022)).
    const url: string =
      cursor === null
        ? `${endpoint}${separator}per_page=100`
        : `${endpoint}${separator}per_page=100&cursor=${encodeURIComponent(cursor)}`;
    const response: CursorPage<GalleryRowApi> = await apiFetch<CursorPage<GalleryRowApi>>(url);
    rows.push(...response.data);
    cursor = response.meta?.next_cursor ?? null;
    if (cursor === null) break;
  }
  return rows;
}

function errorMessage(err: unknown): string {
  if (
    typeof err === 'object' &&
    err !== null &&
    'message' in err &&
    typeof (err as { message: unknown }).message === 'string'
  ) {
    return (err as { message: string }).message;
  }
  return '';
}

export interface ProductGalleryState {
  photos: readonly GalleryPhoto[];
  loading: boolean;
  loadError: boolean;
  busy: boolean;
  actionError: string | null;
  dragIndex: number | null;
  uploadAsPrimary: boolean;
  uploadAsHover: boolean;
  setDragIndex: (index: number | null) => void;
  handleToggleSlot: (index: number, slot: GallerySlot) => Promise<void>;
  handleDelete: (index: number) => Promise<void>;
  handleDrop: (toIndex: number) => void;
  handleUpload: (file: File) => Promise<void>;
  handleUploadAsPrimaryChange: (checked: boolean) => void;
  handleUploadAsHoverChange: (checked: boolean) => void;
}

export function useProductGallery(
  productId: number | null,
  genericError: string
): ProductGalleryState {
  const [photos, setPhotos] = useState<readonly GalleryPhoto[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  const [uploadAsPrimary, setUploadAsPrimary] = useState(false);
  const [uploadAsHover, setUploadAsHover] = useState(false);

  const loadAll = useCallback(async (): Promise<void> => {
    if (productId === null) {
      setLoading(false);
      return;
    }
    try {
      const rows = await fetchAllRows(API_ENDPOINTS.admin.catalog.productImages(productId));
      setPhotos(groupImageRowsToPhotos(rows));
      setLoadError(false);
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const handleToggleSlot = async (index: number, slot: GallerySlot): Promise<void> => {
    if (productId === null || busy) return;
    const photo = photos[index];
    if (photo === undefined) return;
    const value = slot === 'is_primary' ? !photo.isPrimary : !photo.isHover;
    const previous = photos;
    setBusy(true);
    setActionError(null);
    // Radio optimista: al marcar una foto, las demás bajan de la ranura.
    setPhotos((current) =>
      current.map((item, i) => {
        if (i === index) return setPhotoSlot(item, slot, value);
        return value ? setPhotoSlot(item, slot, false) : item;
      })
    );
    try {
      const updates = buildSlotToggleUpdates(photo, slot, value);
      await Promise.all(
        updates.map((update) =>
          apiFetch(API_ENDPOINTS.admin.catalog.productImage(productId, update.imageId), {
            method: 'PUT',
            body: { ...update.body },
          })
        )
      );
      await loadAll();
    } catch (err) {
      setPhotos(previous);
      setActionError(errorMessage(err) || genericError);
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (index: number): Promise<void> => {
    if (productId === null || busy) return;
    const photo = photos[index];
    if (photo === undefined) return;
    const previous = photos;
    setBusy(true);
    setActionError(null);
    setPhotos((current) => current.filter((_, i) => i !== index));
    try {
      // El endpoint borra UNA fila: la foto entera son sus resoluciones.
      await Promise.all(
        photo.rows.map((row) =>
          apiFetch(API_ENDPOINTS.admin.catalog.productImage(productId, row.id), {
            method: 'DELETE',
          })
        )
      );
      await loadAll();
    } catch (err) {
      setPhotos(previous);
      setActionError(errorMessage(err) || genericError);
    } finally {
      setBusy(false);
    }
  };

  const saveOrder = async (ordered: readonly GalleryPhoto[]): Promise<void> => {
    if (productId === null) return;
    const previous = photos;
    setBusy(true);
    setActionError(null);
    try {
      await apiFetch(API_ENDPOINTS.admin.catalog.productImagesOrder(productId), {
        method: 'PUT',
        body: { ...buildOrderPayload(ordered) },
      });
      await loadAll();
    } catch (err) {
      setPhotos(previous);
      setActionError(errorMessage(err) || genericError);
    } finally {
      setBusy(false);
    }
  };

  const handleDrop = (toIndex: number): void => {
    const fromIndex = dragIndex;
    setDragIndex(null);
    if (fromIndex === null || fromIndex === toIndex || busy || productId === null) return;
    const reordered = [...photos];
    const [moved] = reordered.splice(fromIndex, 1);
    if (moved === undefined) return;
    reordered.splice(toIndex, 0, moved);
    setPhotos(reordered);
    void saveOrder(reordered);
  };

  const handleUpload = async (file: File): Promise<void> => {
    if (productId === null || busy) return;
    setBusy(true);
    setActionError(null);
    try {
      const form = new FormData();
      form.append('image', file);
      if (uploadAsPrimary) form.append('is_primary', '1');
      if (uploadAsHover) form.append('is_hover', '1');
      await apiFetch(API_ENDPOINTS.admin.catalog.productImageUpload(productId), {
        method: 'POST',
        body: form,
      });
      await loadAll();
    } catch (err) {
      setActionError(errorMessage(err) || genericError);
    } finally {
      setBusy(false);
    }
  };

  const handleUploadAsPrimaryChange = (checked: boolean): void => {
    setUploadAsPrimary(checked);
    // Principal y hover son excluyentes (el backend responde 422 si
    // una misma foto ocupa las dos ranuras).
    if (checked) setUploadAsHover(false);
  };

  const handleUploadAsHoverChange = (checked: boolean): void => {
    setUploadAsHover(checked);
    if (checked) setUploadAsPrimary(false);
  };

  return {
    photos,
    loading,
    loadError,
    busy,
    actionError,
    dragIndex,
    uploadAsPrimary,
    uploadAsHover,
    setDragIndex,
    handleToggleSlot,
    handleDelete,
    handleDrop,
    handleUpload,
    handleUploadAsPrimaryChange,
    handleUploadAsHoverChange,
  };
}
