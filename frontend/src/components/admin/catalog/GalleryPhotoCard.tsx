/**
 * GalleryPhotoCard — tarjeta de UNA foto en la galería admin.
 *
 * Una foto son N filas (resoluciones); la tarjeta muestra el thumbnail
 * de la fila de mayor `width` y toggles de las dos ranuras exclusivas
 * del producto (Principal / Hover). El drag es HTML5 nativo: el padre
 * orquesta el reordenamiento, acá sólo se emiten los eventos.
 */
import type { DragEvent, ReactElement } from 'react';
import { useTranslations } from '../../../i18n';
import type { GalleryPhoto } from '../../../lib/admin-product-gallery';

const K = 'admin.catalog.products.gallery';

interface Props {
  photo: GalleryPhoto;
  isDragging: boolean;
  isSaving: boolean;
  onTogglePrimary: (index: number) => void;
  onToggleHover: (index: number) => void;
  onDelete: (index: number) => void;
  onDragStart: (index: number) => void;
  onDragOver: (index: number) => void;
  onDrop: (index: number) => void;
  onDragEnd: () => void;
  index: number;
}

export default function GalleryPhotoCard({
  photo,
  isDragging,
  isSaving,
  onTogglePrimary,
  onToggleHover,
  onDelete,
  onDragStart,
  onDragOver,
  onDrop,
  onDragEnd,
  index,
}: Props): ReactElement {
  const { t } = useTranslations();

  const handleDragStart = (event: DragEvent<HTMLLIElement>): void => {
    event.dataTransfer.effectAllowed = 'move';
    // Firefox exige setData para iniciar el drag.
    event.dataTransfer.setData('text/plain', String(index));
    onDragStart(index);
  };

  const handleDragOver = (event: DragEvent<HTMLLIElement>): void => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    onDragOver(index);
  };

  const handleDrop = (event: DragEvent<HTMLLIElement>): void => {
    event.preventDefault();
    onDrop(index);
  };

  return (
    <li
      draggable={!isSaving}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
      onDragEnd={onDragEnd}
      className={[
        'group relative overflow-hidden rounded-lg border bg-white transition',
        isDragging ? 'border-violet-400 opacity-50' : 'border-slate-200',
        isSaving ? 'cursor-not-allowed' : 'cursor-grab active:cursor-grabbing',
      ].join(' ')}
    >
      <div className="relative">
        <img
          src={photo.thumbnailUrl}
          alt={photo.rows[0]?.alt_text ?? ''}
          className="aspect-square w-full object-cover"
          loading="lazy"
          draggable={false}
        />
        <div className="absolute inset-x-0 top-0 flex flex-wrap gap-1 p-2">
          {photo.isPrimary && (
            <span className="rounded bg-violet-600 px-1.5 py-0.5 text-xs font-medium text-white">
              {t(`${K}.primary`)}
            </span>
          )}
          {photo.isHover && (
            <span className="rounded bg-slate-900 px-1.5 py-0.5 text-xs font-medium text-white">
              {t(`${K}.hover`)}
            </span>
          )}
        </div>
      </div>
      <div className="flex items-center justify-between gap-1 border-t border-slate-100 p-1">
        <button
          type="button"
          onClick={() => onTogglePrimary(index)}
          disabled={isSaving}
          aria-pressed={photo.isPrimary}
          className="min-h-10 flex-1 rounded-md px-2 text-xs font-medium text-slate-700 transition hover:bg-violet-50 hover:text-violet-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {photo.isPrimary ? t(`${K}.unsetPrimary`) : t(`${K}.setPrimary`)}
        </button>
        <button
          type="button"
          onClick={() => onToggleHover(index)}
          disabled={isSaving}
          aria-pressed={photo.isHover}
          className="min-h-10 flex-1 rounded-md px-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {photo.isHover ? t(`${K}.unsetHover`) : t(`${K}.setHover`)}
        </button>
        <button
          type="button"
          onClick={() => onDelete(index)}
          disabled={isSaving}
          aria-label={t(`${K}.delete`)}
          className="min-h-10 min-w-10 rounded-md px-2 text-xs font-medium text-slate-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50"
        >
          ✕
        </button>
      </div>
    </li>
  );
}
