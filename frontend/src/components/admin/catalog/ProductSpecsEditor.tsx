/**
 * ProductSpecsEditor — isla React del tab "Ficha técnica" del admin.
 *
 * Tablas editables de tallas/capacidades (`product_sizes`) y valores de
 * atributo (`product_attribute_values`): alta/edición/eliminación vía
 * API admin, con recarga de la lista tras cada mutación. Los mensajes
 * del API (p. ej. el 409 de slug duplicado) se muestran tal cual.
 *
 * Los builders/modders puros viven en `lib/admin-product-specs`; este
 * componente solo orquesta estado, fetch y render.
 */
import { useCallback, useEffect, useState, type ReactElement } from 'react';
import { apiFetch } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations } from '../../../i18n';
import type { TranslationKey } from '../../../i18n/types';
import { SPECS_EMPTY_CELL, formatAttributeValue } from '../../../lib/product-specs';
import {
  SPECS_SIZE_UNITS,
  attributeValueFromApi,
  attributeValuePayloadFromForm,
  attributeValueToApiBody,
  normalizeAttributeType,
  sizeFromApi,
  sizePayloadFromForm,
  sizeToApiBody,
  type AttributeApiRow,
  type AttributeValueApiRow,
  type SizeApiRow,
  type SpecsErrorCode,
  type SpecsFormError,
} from '../../../lib/admin-product-specs';
import type {
  AttributeValueMock,
  AttributeValueType,
  ProductSizeMock,
} from '../../../data/catalog-mock';

const K = 'admin.catalog.products.detail.specs';

const ERROR_KEYS: Record<SpecsErrorCode, TranslationKey> = {
  slugRequired: `${K}.error.slugRequired`,
  slugInvalid: `${K}.error.slugInvalid`,
  slugTooLong: `${K}.error.slugTooLong`,
  labelRequired: `${K}.error.labelRequired`,
  labelTooLong: `${K}.error.labelTooLong`,
  valueRequired: `${K}.error.valueRequired`,
  valueNotNumeric: `${K}.error.valueNotNumeric`,
  valueNegative: `${K}.error.valueNegative`,
  valueTooLong: `${K}.error.valueTooLong`,
  unitInvalid: `${K}.error.unitInvalid`,
  positionInvalid: `${K}.error.positionInvalid`,
  attributeRequired: `${K}.error.attributeRequired`,
};

/** Atributo enriquecido con su `attributeId` para el PUT/POST. */
type AttrRow = AttributeValueMock & { attributeId: number };

interface SizeFormState {
  id: number | null;
  slug: string;
  label: string;
  value: string;
  unit: string;
  position: string;
}

interface AttrFormState {
  id: number | null;
  attributeId: string;
  type: AttributeValueType;
  rawValue: string;
}

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
      cursor === null
        ? endpoint
        : `${endpoint}${separator}cursor=${encodeURIComponent(cursor)}`;
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

interface Props {
  productId: number | null;
  initialSizes: readonly ProductSizeMock[];
  initialAttributeValues: readonly AttributeValueMock[];
}

export default function ProductSpecsEditor({
  productId,
  initialSizes,
  initialAttributeValues,
}: Props): ReactElement {
  const { t } = useTranslations();
  const [sizes, setSizes] = useState<readonly ProductSizeMock[]>(initialSizes);
  const [attributeValues, setAttributeValues] = useState<readonly AttrRow[]>(() =>
    initialAttributeValues.map((attr) => ({ ...attr, attributeId: 0 }))
  );
  const [attributes, setAttributes] = useState<readonly AttributeApiRow[]>([]);
  const [listError, setListError] = useState(false);
  const [busy, setBusy] = useState(false);

  const [sizeForm, setSizeForm] = useState<SizeFormState | null>(null);
  const [sizeError, setSizeError] = useState<string | null>(null);
  const [sizeIssues, setSizeIssues] = useState<readonly SpecsFormError[]>([]);

  const [attrForm, setAttrForm] = useState<AttrFormState | null>(null);
  const [attrError, setAttrError] = useState<string | null>(null);
  const [attrIssues, setAttrIssues] = useState<readonly SpecsFormError[]>([]);

  const booleanLabels = {
    yes: t(`${K}.yes`),
    no: t(`${K}.no`),
  };

  const loadAll = useCallback(async (): Promise<void> => {
    if (productId === null) return;
    try {
      const [sizeRows, valueRows, dictRows] = await Promise.all([
        fetchAllPages<SizeApiRow>(
          `${API_ENDPOINTS.admin.catalog.productSizes}?product_id=${productId}`
        ),
        fetchAllPages<AttributeValueApiRow>(
          `${API_ENDPOINTS.admin.catalog.productAttributeValues}?product_id=${productId}`
        ),
        fetchAllPages<AttributeApiRow>(`${API_ENDPOINTS.admin.catalog.attributes}?per_page=50`),
      ]);
      setSizes(sizeRows.map(sizeFromApi));
      setAttributeValues(
        valueRows.map((row) => {
          const definition = dictRows.find((attr) => attr.id === row.attribute_id);
          const attr =
            definition ??
            ({
              id: row.attribute_id,
              name: `#${row.attribute_id}`,
              slug: `attr-${row.attribute_id}`,
              type: 'text',
            } satisfies AttributeApiRow);
          return { ...attributeValueFromApi(row, attr), attributeId: row.attribute_id };
        })
      );
      setAttributes(dictRows);
      setListError(false);
    } catch {
      setListError(true);
    }
  }, [productId]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const openCreateSize = (): void => {
    setSizeForm({ id: null, slug: '', label: '', value: '', unit: '', position: '' });
    setSizeError(null);
    setSizeIssues([]);
  };

  const openEditSize = (size: ProductSizeMock): void => {
    setSizeForm({
      id: size.id,
      slug: size.slug,
      label: size.label,
      value: size.value === null ? '' : String(size.value),
      unit: size.unit ?? '',
      position: String(size.position),
    });
    setSizeError(null);
    setSizeIssues([]);
  };

  const submitSize = async (): Promise<void> => {
    if (productId === null || sizeForm === null) return;
    const result = sizePayloadFromForm({
      productId,
      slug: sizeForm.slug,
      label: sizeForm.label,
      value: sizeForm.value,
      unit: sizeForm.unit,
      position: sizeForm.position,
    });
    if (!result.ok) {
      setSizeIssues(result.errors);
      setSizeError(null);
      return;
    }
    setBusy(true);
    setSizeIssues([]);
    setSizeError(null);
    try {
      const body = { ...sizeToApiBody(result.payload) };
      if (sizeForm.id === null) {
        await apiFetch(API_ENDPOINTS.admin.catalog.productSizes, { method: 'POST', body });
      } else {
        await apiFetch(API_ENDPOINTS.admin.catalog.productSize(sizeForm.id), {
          method: 'PUT',
          body,
        });
      }
      setSizeForm(null);
      await loadAll();
    } catch (err) {
      setSizeError(errorMessage(err) || t(`${K}.error.generic`));
    } finally {
      setBusy(false);
    }
  };

  const deleteSize = async (id: number): Promise<void> => {
    if (!window.confirm(t(`${K}.deleteConfirmSize`))) return;
    setBusy(true);
    setSizeError(null);
    try {
      await apiFetch(API_ENDPOINTS.admin.catalog.productSize(id), { method: 'DELETE' });
      await loadAll();
    } catch (err) {
      setSizeError(errorMessage(err) || t(`${K}.error.generic`));
    } finally {
      setBusy(false);
    }
  };

  const openCreateAttr = (): void => {
    setAttrForm({ id: null, attributeId: '', type: 'text', rawValue: '' });
    setAttrError(null);
    setAttrIssues([]);
  };

  const openEditAttr = (row: AttrRow): void => {
    const rawValue =
      row.type === 'boolean'
        ? String(row.valueBoolean ?? false)
        : row.type === 'number'
          ? row.valueNumber === null
            ? ''
            : String(row.valueNumber)
          : (row.value ?? '');
    setAttrForm({
      id: row.id,
      attributeId: row.attributeId === 0 ? '' : String(row.attributeId),
      type: row.type,
      rawValue,
    });
    setAttrError(null);
    setAttrIssues([]);
  };

  const submitAttr = async (): Promise<void> => {
    if (productId === null || attrForm === null) return;
    const result = attributeValuePayloadFromForm({
      productId,
      attributeId: attrForm.attributeId === '' ? '' : Number(attrForm.attributeId),
      type: attrForm.type,
      rawValue: attrForm.rawValue,
    });
    if (!result.ok) {
      setAttrIssues(result.errors);
      setAttrError(null);
      return;
    }
    setBusy(true);
    setAttrIssues([]);
    setAttrError(null);
    try {
      const body = { ...attributeValueToApiBody(result.payload) };
      if (attrForm.id === null) {
        await apiFetch(API_ENDPOINTS.admin.catalog.productAttributeValues, {
          method: 'POST',
          body,
        });
      } else {
        await apiFetch(API_ENDPOINTS.admin.catalog.productAttributeValue(attrForm.id), {
          method: 'PUT',
          body,
        });
      }
      setAttrForm(null);
      await loadAll();
    } catch (err) {
      setAttrError(errorMessage(err) || t(`${K}.error.generic`));
    } finally {
      setBusy(false);
    }
  };

  const deleteAttr = async (id: number): Promise<void> => {
    if (!window.confirm(t(`${K}.deleteConfirmAttribute`))) return;
    setBusy(true);
    setAttrError(null);
    try {
      await apiFetch(API_ENDPOINTS.admin.catalog.productAttributeValue(id), { method: 'DELETE' });
      await loadAll();
    } catch (err) {
      setAttrError(errorMessage(err) || t(`${K}.error.generic`));
    } finally {
      setBusy(false);
    }
  };

  const editable = productId !== null;

  const renderIssues = (issues: readonly SpecsFormError[]): ReactElement | null =>
    issues.length === 0 ? null : (
      <ul className="space-y-1 text-sm text-red-600">
        {issues.map((issue, index) => (
          <li key={`${issue.field}-${issue.code}-${index}`}>{t(ERROR_KEYS[issue.code])}</li>
        ))}
      </ul>
    );

  const renderError = (message: string | null): ReactElement | null =>
    message === null ? null : (
      <p
        role="alert"
        className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {message}
      </p>
    );

  return (
    <div className="space-y-6">
      {listError ? (
        <p
          role="alert"
          className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
        >
          {t(`${K}.loadError`)}
        </p>
      ) : null}

      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-slate-900">{t(`${K}.sizes`)}</h3>
          {editable ? (
            <button
              type="button"
              onClick={openCreateSize}
              className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            >
              {t(`${K}.addSize`)}
            </button>
          ) : null}
        </div>

        {sizeForm !== null ? (
          <form
            className="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            onSubmit={(event) => {
              event.preventDefault();
              void submitSize();
            }}
          >
            <p className="text-sm font-medium text-slate-900">
              {sizeForm.id === null ? t(`${K}.newSize`) : t(`${K}.editSize`)}
            </p>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <label className="space-y-1 text-xs font-medium text-slate-600">
                {t(`${K}.slug`)}
                <input
                  type="text"
                  value={sizeForm.slug}
                  onChange={(event) =>
                    setSizeForm({ ...sizeForm, slug: event.target.value })
                  }
                  className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                />
              </label>
              <label className="space-y-1 text-xs font-medium text-slate-600">
                {t(`${K}.label`)}
                <input
                  type="text"
                  value={sizeForm.label}
                  onChange={(event) =>
                    setSizeForm({ ...sizeForm, label: event.target.value })
                  }
                  className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                />
              </label>
              <label className="space-y-1 text-xs font-medium text-slate-600">
                {t(`${K}.value`)}
                <input
                  type="number"
                  step="any"
                  min="0"
                  value={sizeForm.value}
                  onChange={(event) =>
                    setSizeForm({ ...sizeForm, value: event.target.value })
                  }
                  className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                />
              </label>
              <label className="space-y-1 text-xs font-medium text-slate-600">
                {t(`${K}.unit`)}
                <select
                  value={sizeForm.unit}
                  onChange={(event) =>
                    setSizeForm({ ...sizeForm, unit: event.target.value })
                  }
                  className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                >
                  <option value="">{t(`${K}.unitNone`)}</option>
                  {SPECS_SIZE_UNITS.map((unit) => (
                    <option key={unit} value={unit}>
                      {unit}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1 text-xs font-medium text-slate-600">
                {t(`${K}.position`)}
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={sizeForm.position}
                  onChange={(event) =>
                    setSizeForm({ ...sizeForm, position: event.target.value })
                  }
                  className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                />
              </label>
            </div>
            {renderIssues(sizeIssues)}
            {renderError(sizeError)}
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={busy}
                className="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 disabled:opacity-50"
              >
                {busy ? t(`${K}.saving`) : t(`${K}.save`)}
              </button>
              <button
                type="button"
                onClick={() => setSizeForm(null)}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                {t(`${K}.cancel`)}
              </button>
            </div>
          </form>
        ) : null}

        {sizes.length > 0 ? (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-medium">{t(`${K}.slug`)}</th>
                  <th className="px-4 py-3 font-medium">{t(`${K}.label`)}</th>
                  <th className="px-4 py-3 text-right font-medium">{t(`${K}.value`)}</th>
                  <th className="px-4 py-3 font-medium">{t(`${K}.unit`)}</th>
                  <th className="px-4 py-3 text-right font-medium">{t(`${K}.position`)}</th>
                  {editable ? (
                    <th className="px-4 py-3">
                      <span className="sr-only">{t(`${K}.edit`)}</span>
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {sizes.map((size) => (
                  <tr key={size.id} className="hover:bg-slate-50/50">
                    <td className="px-4 py-3 font-mono text-xs text-slate-600">{size.slug}</td>
                    <td className="px-4 py-3 text-slate-700">{size.label}</td>
                    <td className="px-4 py-3 text-right font-medium text-slate-900">
                      {size.value ?? SPECS_EMPTY_CELL}
                    </td>
                    <td className="px-4 py-3 text-slate-700">{size.unit ?? SPECS_EMPTY_CELL}</td>
                    <td className="px-4 py-3 text-right text-slate-600">{size.position}</td>
                    {editable ? (
                      <td className="px-4 py-3 text-right">
                        <div className="flex justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => openEditSize(size)}
                            className="text-xs font-medium text-slate-600 underline hover:text-slate-900"
                          >
                            {t(`${K}.edit`)}
                          </button>
                          <button
                            type="button"
                            onClick={() => void deleteSize(size.id)}
                            disabled={busy}
                            className="text-xs font-medium text-red-600 underline hover:text-red-800 disabled:opacity-50"
                          >
                            {t(`${K}.delete`)}
                          </button>
                        </div>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
            <p className="text-sm font-medium text-slate-700">{t(`${K}.emptySizes`)}</p>
          </div>
        )}
      </div>

      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-slate-900">{t(`${K}.attributes`)}</h3>
          {editable ? (
            <button
              type="button"
              onClick={openCreateAttr}
              className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            >
              {t(`${K}.addAttribute`)}
            </button>
          ) : null}
        </div>

        {attrForm !== null ? (
          <form
            className="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            onSubmit={(event) => {
              event.preventDefault();
              void submitAttr();
            }}
          >
            <p className="text-sm font-medium text-slate-900">
              {attrForm.id === null ? t(`${K}.newAttribute`) : t(`${K}.editAttribute`)}
            </p>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <label className="space-y-1 text-xs font-medium text-slate-600">
                {t(`${K}.attribute`)}
                <select
                  value={attrForm.attributeId}
                  disabled={attrForm.id !== null}
                  onChange={(event) => {
                    const selected = attributes.find(
                      (attr) => String(attr.id) === event.target.value
                    );
                    setAttrForm({
                      ...attrForm,
                      attributeId: event.target.value,
                      type: selected ? normalizeAttributeType(selected.type) : 'text',
                      rawValue: '',
                    });
                  }}
                  className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900 disabled:bg-slate-100"
                >
                  <option value="">{t(`${K}.selectAttribute`)}</option>
                  {attributes.map((attr) => (
                    <option key={attr.id} value={attr.id}>
                      {attr.name}
                    </option>
                  ))}
                </select>
              </label>
              {attrForm.type === 'boolean' ? (
                <label className="flex items-center gap-2 pt-5 text-xs font-medium text-slate-600">
                  <input
                    type="checkbox"
                    checked={attrForm.rawValue === 'true'}
                    onChange={(event) =>
                      setAttrForm({
                        ...attrForm,
                        rawValue: event.target.checked ? 'true' : 'false',
                      })
                    }
                    className="h-4 w-4 rounded border-slate-300"
                  />
                  {t(`${K}.value`)}
                </label>
              ) : (
                <label className="space-y-1 text-xs font-medium text-slate-600">
                  {t(`${K}.value`)}
                  <input
                    type={attrForm.type === 'number' ? 'number' : 'text'}
                    step="any"
                    value={attrForm.rawValue}
                    onChange={(event) =>
                      setAttrForm({ ...attrForm, rawValue: event.target.value })
                    }
                    className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                  />
                </label>
              )}
            </div>
            {renderIssues(attrIssues)}
            {renderError(attrError)}
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={busy}
                className="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 disabled:opacity-50"
              >
                {busy ? t(`${K}.saving`) : t(`${K}.save`)}
              </button>
              <button
                type="button"
                onClick={() => setAttrForm(null)}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                {t(`${K}.cancel`)}
              </button>
            </div>
          </form>
        ) : null}

        {attributeValues.length > 0 ? (
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-medium">{t(`${K}.attribute`)}</th>
                  <th className="px-4 py-3 font-medium">{t(`${K}.type`)}</th>
                  <th className="px-4 py-3 font-medium">{t(`${K}.value`)}</th>
                  <th className="px-4 py-3 font-medium">{t(`${K}.unit`)}</th>
                  {editable ? (
                    <th className="px-4 py-3">
                      <span className="sr-only">{t(`${K}.edit`)}</span>
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {attributeValues.map((attr) => (
                  <tr key={attr.id} className="hover:bg-slate-50/50">
                    <td className="px-4 py-3 text-slate-700">{attr.label}</td>
                    <td className="px-4 py-3 font-mono text-xs text-slate-600">{attr.type}</td>
                    <td className="px-4 py-3 text-slate-900">
                      {formatAttributeValue(attr, booleanLabels)}
                    </td>
                    <td className="px-4 py-3 text-slate-700">{attr.unit ?? SPECS_EMPTY_CELL}</td>
                    {editable ? (
                      <td className="px-4 py-3 text-right">
                        <div className="flex justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => openEditAttr(attr)}
                            className="text-xs font-medium text-slate-600 underline hover:text-slate-900"
                          >
                            {t(`${K}.edit`)}
                          </button>
                          <button
                            type="button"
                            onClick={() => void deleteAttr(attr.id)}
                            disabled={busy}
                            className="text-xs font-medium text-red-600 underline hover:text-red-800 disabled:opacity-50"
                          >
                            {t(`${K}.delete`)}
                          </button>
                        </div>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
            <p className="text-sm font-medium text-slate-700">{t(`${K}.emptyAttributes`)}</p>
          </div>
        )}
      </div>
    </div>
  );
}
