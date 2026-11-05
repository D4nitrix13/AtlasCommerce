/**
 * ProductGalleryEditor — isla React del tab "Media" del admin.
 *
 * Render de la galería (una tarjeta por foto, no por fila) sobre el
 * hook `useProductGallery`: toggles de las ranuras exclusivas
 * Principal/Hover, borrado, reordenamiento con drag & drop nativo y
 * upload multipart. La lógica pura (agrupado, payloads) vive en
 * `lib/admin-product-gallery`.
 */
import type { ReactElement } from 'react';
import { useTranslations } from '../../../i18n';
import GalleryPhotoCard from './GalleryPhotoCard';
import GalleryUploadZone from './GalleryUploadZone';
import { useProductGallery } from './useProductGallery';

const K = 'admin.catalog.products.gallery';

interface Props {
  /** Producto dueño de la galería; null en modo creación. */
  productId: number | null;
}

export default function ProductGalleryEditor({ productId }: Props): ReactElement {
  const { t } = useTranslations();
  const {
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
  } = useProductGallery(productId, t(`${K}.errorGeneric`));

  const handleDeleteConfirmed = (index: number): void => {
    if (!window.confirm(t(`${K}.deleteConfirm`))) return;
    void handleDelete(index);
  };

  if (productId === null) {
    return <p className="text-sm text-slate-500">{t(`${K}.needsProduct`)}</p>;
  }

  return (
    <section aria-labelledby="gallery-title" className="space-y-4">
      <h2 id="gallery-title" className="text-sm font-semibold text-slate-900">
        {t(`${K}.title`)}
      </h2>
      <p className="text-xs text-slate-500">{t(`${K}.reorderHint`)}</p>

      {actionError !== null && (
        <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
          {actionError}
        </p>
      )}
      {loadError && (
        <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
          {t(`${K}.loadError`)}
        </p>
      )}

      {loading && <p className="text-sm text-slate-500">{t(`${K}.loading`)}</p>}

      {!loading && !loadError && photos.length === 0 && (
        <p className="text-sm text-slate-500">{t(`${K}.empty`)}</p>
      )}

      {photos.length > 0 && (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {photos.map((photo, index) => (
            <GalleryPhotoCard
              key={`${photo.variantId}-${photo.sortOrder}-${photo.rows[0]?.id ?? 0}`}
              photo={photo}
              index={index}
              isDragging={dragIndex === index}
              isSaving={busy}
              onTogglePrimary={(i) => void handleToggleSlot(i, 'is_primary')}
              onToggleHover={(i) => void handleToggleSlot(i, 'is_hover')}
              onDelete={handleDeleteConfirmed}
              onDragStart={setDragIndex}
              onDragOver={setDragIndex}
              onDrop={handleDrop}
              onDragEnd={() => setDragIndex(null)}
            />
          ))}
        </ul>
      )}

      <GalleryUploadZone
        isBusy={busy}
        uploadAsPrimary={uploadAsPrimary}
        uploadAsHover={uploadAsHover}
        onFileSelected={(file) => void handleUpload(file)}
        onUploadAsPrimaryChange={handleUploadAsPrimaryChange}
        onUploadAsHoverChange={handleUploadAsHoverChange}
      />
    </section>
  );
}
