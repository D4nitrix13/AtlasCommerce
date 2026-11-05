/**
 * GalleryUploadZone — dropzone de subida de fotos (multipart).
 *
 * Opciones de ranura de la subida: Principal y Hover son excluyentes
 * entre sí (el backend responde 422 si una misma foto ocupa las dos
 * ranuras), por eso marcar una desmarca la otra.
 */
import { type ChangeEvent, type DragEvent, type ReactElement } from 'react';
import { useTranslations } from '../../../i18n';

const K = 'admin.catalog.products.gallery';

interface Props {
  isBusy: boolean;
  uploadAsPrimary: boolean;
  uploadAsHover: boolean;
  onFileSelected: (file: File) => void;
  onUploadAsPrimaryChange: (checked: boolean) => void;
  onUploadAsHoverChange: (checked: boolean) => void;
}

export default function GalleryUploadZone({
  isBusy,
  uploadAsPrimary,
  uploadAsHover,
  onFileSelected,
  onUploadAsPrimaryChange,
  onUploadAsHoverChange,
}: Props): ReactElement {
  const { t } = useTranslations();

  const handleFileInputChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const file = event.target.files?.[0];
    if (file !== undefined) onFileSelected(file);
    event.target.value = '';
  };

  const handleDragOver = (event: DragEvent<HTMLDivElement>): void => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
  };

  const handleDrop = (event: DragEvent<HTMLDivElement>): void => {
    event.preventDefault();
    const file = event.dataTransfer.files[0];
    if (file !== undefined) onFileSelected(file);
  };

  return (
    <div
      onDragOver={handleDragOver}
      onDrop={handleDrop}
      className="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-violet-400 hover:bg-violet-50/30"
    >
      <p className="text-sm font-medium text-slate-700">{t(`${K}.uploadHint`)}</p>
      <label className="inline-flex min-h-10 cursor-pointer items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus-within:ring-2 focus-within:ring-violet-500">
        {isBusy ? t(`${K}.saving`) : t(`${K}.upload`)}
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          disabled={isBusy}
          onChange={handleFileInputChange}
          className="hidden"
        />
      </label>
      <div className="flex flex-wrap items-center justify-center gap-4">
        <label className="flex min-h-10 items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={uploadAsPrimary}
            disabled={isBusy}
            onChange={(event) => onUploadAsPrimaryChange(event.target.checked)}
            className="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
          />
          {t(`${K}.uploadAsPrimary`)}
        </label>
        <label className="flex min-h-10 items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={uploadAsHover}
            disabled={isBusy}
            onChange={(event) => onUploadAsHoverChange(event.target.checked)}
            className="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
          />
          {t(`${K}.uploadAsHover`)}
        </label>
      </div>
    </div>
  );
}
