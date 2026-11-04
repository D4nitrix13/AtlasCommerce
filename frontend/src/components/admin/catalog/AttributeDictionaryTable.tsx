/**
 * AttributeDictionaryTable — listado del diccionario de atributos
 * (nombre, tipo, unit, # opciones) con acciones de edición/baja.
 */
import { type ReactElement } from 'react';
import { useTranslations } from '../../../i18n';
import { SPECS_EMPTY_CELL } from '../../../lib/product-specs';
import type { AttributeApiRow } from '../../../lib/admin-product-specs';

const K = 'admin.catalog.attributes';

interface Props {
  rows: readonly AttributeApiRow[];
  busy: boolean;
  onEdit: (row: AttributeApiRow) => void;
  onDelete: (id: number) => void;
}

export default function AttributeDictionaryTable({
  rows,
  busy,
  onEdit,
  onDelete,
}: Props): ReactElement {
  const { t } = useTranslations();

  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th className="px-4 py-3 font-medium">{t(`${K}.list.name`)}</th>
            <th className="px-4 py-3 font-medium">{t(`${K}.list.type`)}</th>
            <th className="px-4 py-3 font-medium">{t(`${K}.list.unit`)}</th>
            <th className="px-4 py-3 font-medium">{t(`${K}.list.optionsCount`)}</th>
            <th className="px-4 py-3">
              <span className="sr-only">{t(`${K}.list.actions`)}</span>
            </th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((row) => (
            <tr key={row.id} className="hover:bg-slate-50/50">
              <td className="px-4 py-3 text-slate-900">{row.name}</td>
              <td className="px-4 py-3 font-mono text-xs text-slate-600">{row.type}</td>
              <td className="px-4 py-3 text-slate-700">{row.unit ?? SPECS_EMPTY_CELL}</td>
              <td className="px-4 py-3 text-slate-700">{row.options?.length ?? 0}</td>
              <td className="px-4 py-3 text-right">
                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => onEdit(row)}
                    disabled={busy}
                    className="text-xs font-medium text-slate-600 underline hover:text-slate-900 disabled:opacity-50"
                  >
                    {t(`${K}.form.edit`)}
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(row.id)}
                    disabled={busy}
                    className="text-xs font-medium text-red-600 underline hover:text-red-800 disabled:opacity-50"
                  >
                    {t(`${K}.form.delete`)}
                  </button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
