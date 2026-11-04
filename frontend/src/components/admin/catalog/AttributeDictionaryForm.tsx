/**
 * AttributeDictionaryForm — form de alta/edición de un atributo del
 * diccionario (POST/PUT /v1/admin/attributes).
 *
 * Presentacional y controlado: el caller dueña del estado y del
 * submit. El campo `unit` solo aplica a `type=number` y las opciones
 * solo a `type=select`; el resto de reglas viven en
 * `attributeDictionaryPayloadFromForm`.
 */
import { type ReactElement } from 'react';
import { useTranslations } from '../../../i18n';
import type { TranslationKey } from '../../../i18n/types';
import {
  ATTRIBUTE_DICTIONARY_TYPES,
  SPECS_SIZE_UNITS,
  type AttributeDictionaryErrorCode,
  type AttributeDictionaryType,
  type SpecsFormError,
} from '../../../lib/admin-product-specs';
import AttributeOptionsEditor from './AttributeOptionsEditor';

const K = 'admin.catalog.attributes';

const ERROR_KEYS: Record<AttributeDictionaryErrorCode, TranslationKey> = {
  nameRequired: `${K}.error.nameRequired`,
  nameTooShort: `${K}.error.nameTooShort`,
  nameTooLong: `${K}.error.nameTooLong`,
  slugRequired: `${K}.error.slugRequired`,
  slugInvalid: `${K}.error.slugInvalid`,
  slugTooShort: `${K}.error.slugTooShort`,
  slugTooLong: `${K}.error.slugTooLong`,
  typeInvalid: `${K}.error.typeInvalid`,
  unitInvalid: `${K}.error.unitInvalid`,
  optionsTooMany: `${K}.error.optionsTooMany`,
  optionEmpty: `${K}.error.optionEmpty`,
  optionTooLong: `${K}.error.optionTooLong`,
  optionDuplicated: `${K}.error.optionDuplicated`,
  descriptionTooLong: `${K}.error.descriptionTooLong`,
};

const TYPE_LABEL_KEYS: Record<AttributeDictionaryType, TranslationKey> = {
  text: `${K}.type.text`,
  number: `${K}.type.number`,
  boolean: `${K}.type.boolean`,
  select: `${K}.type.select`,
};

const FIELD_LABEL_CLASS = 'space-y-1 text-xs font-medium text-slate-600';
const FIELD_INPUT_CLASS =
  'w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900 disabled:bg-slate-100';

export interface AttributeFormState {
  id: number | null;
  name: string;
  slug: string;
  type: string;
  unit: string;
  options: string[];
  description: string;
  isFilterable: boolean;
  isRequired: boolean;
}

interface Props {
  form: AttributeFormState;
  onChange: (next: AttributeFormState) => void;
  onSubmit: () => void;
  onCancel: () => void;
  issues: readonly SpecsFormError<AttributeDictionaryErrorCode>[];
  error: string | null;
  busy: boolean;
}

export default function AttributeDictionaryForm({
  form,
  onChange,
  onSubmit,
  onCancel,
  issues,
  error,
  busy,
}: Props): ReactElement {
  const { t } = useTranslations();

  return (
    <form
      className="space-y-4 rounded-lg border border-slate-200 bg-white p-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <p className="text-sm font-medium text-slate-900">
        {form.id === null ? t(`${K}.form.newTitle`) : t(`${K}.form.editTitle`)}
      </p>

      <div className="grid gap-3 sm:grid-cols-2">
        <label className={FIELD_LABEL_CLASS}>
          {t(`${K}.form.name`)}
          <input
            type="text"
            value={form.name}
            disabled={busy}
            onChange={(event) => onChange({ ...form, name: event.target.value })}
            className={FIELD_INPUT_CLASS}
          />
        </label>
        <label className={FIELD_LABEL_CLASS}>
          {t(`${K}.form.slug`)}
          <input
            type="text"
            value={form.slug}
            disabled={busy}
            onChange={(event) => onChange({ ...form, slug: event.target.value })}
            className={FIELD_INPUT_CLASS}
          />
          <span className="block text-[11px] font-normal text-slate-500">
            {t(`${K}.form.slugHint`)}
          </span>
        </label>
        <label className={FIELD_LABEL_CLASS}>
          {t(`${K}.form.type`)}
          <select
            value={form.type}
            disabled={busy}
            onChange={(event) => onChange({ ...form, type: event.target.value, options: [] })}
            className={FIELD_INPUT_CLASS}
          >
            {ATTRIBUTE_DICTIONARY_TYPES.map((type) => (
              <option key={type} value={type}>
                {t(TYPE_LABEL_KEYS[type])}
              </option>
            ))}
          </select>
        </label>
        {form.type === 'number' ? (
          <label className={FIELD_LABEL_CLASS}>
            {t(`${K}.form.unit`)}
            <select
              value={form.unit}
              disabled={busy}
              onChange={(event) => onChange({ ...form, unit: event.target.value })}
              className={FIELD_INPUT_CLASS}
            >
              <option value="">{t(`${K}.unit.none`)}</option>
              {SPECS_SIZE_UNITS.map((unit) => (
                <option key={unit} value={unit}>
                  {unit}
                </option>
              ))}
            </select>
          </label>
        ) : null}
      </div>

      {form.type === 'select' ? (
        <AttributeOptionsEditor
          value={form.options}
          disabled={busy}
          onChange={(next) => onChange({ ...form, options: next })}
        />
      ) : null}

      <label className={FIELD_LABEL_CLASS}>
        {t(`${K}.form.description`)}
        <textarea
          value={form.description}
          disabled={busy}
          rows={3}
          onChange={(event) => onChange({ ...form, description: event.target.value })}
          className={FIELD_INPUT_CLASS}
        />
      </label>

      <div className="flex flex-wrap gap-4">
        <label className="flex items-center gap-2 text-xs font-medium text-slate-600">
          <input
            type="checkbox"
            checked={form.isFilterable}
            disabled={busy}
            onChange={(event) => onChange({ ...form, isFilterable: event.target.checked })}
            className="h-4 w-4 rounded border-slate-300"
          />
          {t(`${K}.form.isFilterable`)}
        </label>
        <label className="flex items-center gap-2 text-xs font-medium text-slate-600">
          <input
            type="checkbox"
            checked={form.isRequired}
            disabled={busy}
            onChange={(event) => onChange({ ...form, isRequired: event.target.checked })}
            className="h-4 w-4 rounded border-slate-300"
          />
          {t(`${K}.form.isRequired`)}
        </label>
      </div>

      {issues.length > 0 ? (
        <ul className="space-y-1 text-sm text-red-600">
          {issues.map((issue, index) => (
            <li key={`${issue.field}-${issue.code}-${index}`}>{t(ERROR_KEYS[issue.code])}</li>
          ))}
        </ul>
      ) : null}
      {error !== null ? (
        <p
          role="alert"
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {error}
        </p>
      ) : null}

      <div className="flex gap-2">
        <button
          type="submit"
          disabled={busy}
          className="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 disabled:opacity-50"
        >
          {busy ? t(`${K}.form.saving`) : t(`${K}.form.save`)}
        </button>
        <button
          type="button"
          disabled={busy}
          onClick={onCancel}
          className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >
          {t(`${K}.form.cancel`)}
        </button>
      </div>
    </form>
  );
}
