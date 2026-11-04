/**
 * AttributeDictionaryEditor — isla React del diccionario de atributos
 * admin (CRUD contra /v1/admin/attributes).
 *
 * Orquesta fetch (cursor pagination), estados y mutaciones; el form
 * vive en `AttributeDictionaryForm` y la validación del payload en
 * `lib/admin-product-specs`. Los mensajes del API se muestran tal cual.
 */
import { useCallback, useEffect, useState, type ReactElement } from 'react';
import { apiFetch } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations } from '../../../i18n';
import {
  attributeDictionaryPayloadFromForm,
  attributeDictionaryToApiBody,
  type AttributeApiRow,
  type AttributeDictionaryErrorCode,
  type SpecsFormError,
} from '../../../lib/admin-product-specs';
import AttributeDictionaryForm, { type AttributeFormState } from './AttributeDictionaryForm';
import AttributeDictionaryTable from './AttributeDictionaryTable';

const K = 'admin.catalog.attributes';

const EMPTY_FORM: AttributeFormState = {
  id: null,
  name: '',
  slug: '',
  type: 'text',
  unit: '',
  options: [],
  description: '',
  isFilterable: false,
  isRequired: false,
};

interface CursorPage<T> {
  data: T[];
  meta?: { next_cursor?: string | null } | null;
}

async function fetchAllPages<T>(endpoint: string): Promise<T[]> {
  const items: T[] = [];
  let cursor: string | null = null;
  for (let page = 0; page < 10; page += 1) {
    const separator = endpoint.includes('?') ? '&' : '?';
    const url: string =
      cursor === null ? endpoint : `${endpoint}${separator}cursor=${encodeURIComponent(cursor)}`;
    const response: CursorPage<T> = await apiFetch<CursorPage<T>>(url);
    items.push(...response.data);
    cursor = response.meta?.next_cursor ?? null;
    if (cursor === null) break;
  }
  return items;
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

export default function AttributeDictionaryEditor(): ReactElement {
  const { t } = useTranslations();
  const [rows, setRows] = useState<readonly AttributeApiRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState<AttributeFormState | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [formIssues, setFormIssues] = useState<readonly SpecsFormError<AttributeDictionaryErrorCode>[]>([]);

  const loadAll = useCallback(async (): Promise<void> => {
    try {
      const fetched = await fetchAllPages<AttributeApiRow>(
        `${API_ENDPOINTS.admin.catalog.attributes}?per_page=50`
      );
      setRows(fetched);
      setListError(false);
    } catch {
      setListError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const openCreate = (): void => {
    setForm({ ...EMPTY_FORM, options: [] });
    setFormError(null);
    setFormIssues([]);
  };

  const openEdit = (row: AttributeApiRow): void => {
    setForm({
      id: row.id,
      name: row.name,
      slug: row.slug,
      type: row.type,
      unit: row.unit ?? '',
      options: row.options ? [...row.options] : [],
      description: row.description ?? '',
      isFilterable: row.is_filterable ?? false,
      isRequired: row.is_required ?? false,
    });
    setFormError(null);
    setFormIssues([]);
  };

  const submit = async (): Promise<void> => {
    if (form === null) return;
    const result = attributeDictionaryPayloadFromForm({
      name: form.name,
      slug: form.slug,
      type: form.type,
      unit: form.unit,
      options: form.options,
      description: form.description,
      isFilterable: form.isFilterable,
      isRequired: form.isRequired,
    });
    if (!result.ok) {
      setFormIssues(result.errors);
      setFormError(null);
      return;
    }
    setBusy(true);
    setFormIssues([]);
    setFormError(null);
    try {
      // Spread a objeto literal: un `interface` sin index signature no
      // es assignable a `Record<string, unknown>` (body de apiFetch).
      const body = { ...attributeDictionaryToApiBody(result.payload) };
      const endpoint = API_ENDPOINTS.admin.catalog.attributes;
      if (form.id === null) {
        await apiFetch(endpoint, { method: 'POST', body });
      } else {
        await apiFetch(`${endpoint}/${form.id}`, { method: 'PUT', body });
      }
      setForm(null);
      await loadAll();
    } catch (err) {
      setFormError(errorMessage(err) || t(`${K}.error.generic`));
    } finally {
      setBusy(false);
    }
  };

  const remove = async (id: number): Promise<void> => {
    if (!window.confirm(t(`${K}.form.deleteConfirm`))) return;
    setBusy(true);
    setFormError(null);
    try {
      await apiFetch(`${API_ENDPOINTS.admin.catalog.attributes}/${id}`, { method: 'DELETE' });
      await loadAll();
    } catch (err) {
      setFormError(errorMessage(err) || t(`${K}.error.generic`));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      {listError ? (
        <p
          role="alert"
          className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
        >
          {t(`${K}.error.load`)}
        </p>
      ) : null}

      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-slate-900">{t(`${K}.list.title`)}</h2>
        <button
          type="button"
          onClick={openCreate}
          disabled={busy}
          className="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 disabled:opacity-50"
        >
          {t(`${K}.addAttribute`)}
        </button>
      </div>

      {form !== null ? (
        <AttributeDictionaryForm
          form={form}
          onChange={setForm}
          onSubmit={() => void submit()}
          onCancel={() => setForm(null)}
          issues={formIssues}
          error={formError}
          busy={busy}
        />
      ) : null}

      {loading ? (
        <p className="rounded-lg border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-600">
          {t(`${K}.loading`)}
        </p>
      ) : rows.length > 0 ? (
        <AttributeDictionaryTable
          rows={rows}
          busy={busy}
          onEdit={openEdit}
          onDelete={(id) => void remove(id)}
        />
      ) : (
        <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
          <p className="text-sm font-medium text-slate-700">{t(`${K}.empty`)}</p>
          <p className="mt-1 text-xs text-slate-500">{t(`${K}.emptyHint`)}</p>
        </div>
      )}
    </div>
  );
}
