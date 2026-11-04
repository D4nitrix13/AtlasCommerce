/**
 * AttributeOptionsEditor — editor de la lista de opciones de un
 * atributo `type=select` del diccionario.
 *
 * Componente controlado puro: recibe `value` y notifica `onChange`.
 * Sin llamadas al API ni validación de negocio (eso vive en
 * `lib/admin-product-specs`); solo alta, edición de texto, baja y
 * reordenamiento.
 */
import { type ReactElement } from 'react';
import { useTranslations } from '../../../i18n';

const K = 'admin.catalog.attributes.options';

const ROW_ACTIONS_CLASS =
  'rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50';

interface Props {
  value: readonly string[];
  onChange: (next: string[]) => void;
  disabled?: boolean;
}

export default function AttributeOptionsEditor({
  value,
  onChange,
  disabled = false,
}: Props): ReactElement {
  const { t } = useTranslations();

  const updateAt = (index: number, text: string): void => {
    onChange(value.map((option, i) => (i === index ? text : option)));
  };

  const removeAt = (index: number): void => {
    onChange(value.filter((_, i) => i !== index));
  };

  const move = (index: number, delta: number): void => {
    const target = index + delta;
    if (target < 0 || target >= value.length) return;
    const next = [...value];
    const [item] = next.splice(index, 1);
    if (item !== undefined) next.splice(target, 0, item);
    onChange(next);
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <p className="text-xs font-medium text-slate-600">{t(`${K}.title`)}</p>
        <button
          type="button"
          disabled={disabled}
          onClick={() => onChange([...value, ''])}
          className={ROW_ACTIONS_CLASS}
        >
          {t(`${K}.add`)}
        </button>
      </div>

      {value.length === 0 ? (
        <p className="rounded-md border border-dashed border-slate-300 px-3 py-4 text-center text-xs text-slate-500">
          {t(`${K}.empty`)}
        </p>
      ) : (
        <ul className="space-y-2">
          {value.map((option, index) => (
            <li key={index} className="flex items-center gap-2">
              <input
                type="text"
                value={option}
                disabled={disabled}
                aria-label={t(`${K}.itemLabel`).replace('{index}', String(index + 1))}
                onChange={(event) => updateAt(index, event.target.value)}
                className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900 disabled:bg-slate-100"
              />
              <button
                type="button"
                disabled={disabled || index === 0}
                onClick={() => move(index, -1)}
                aria-label={t(`${K}.moveUp`)}
                className={ROW_ACTIONS_CLASS}
              >
                <span aria-hidden="true">▲</span>
              </button>
              <button
                type="button"
                disabled={disabled || index === value.length - 1}
                onClick={() => move(index, 1)}
                aria-label={t(`${K}.moveDown`)}
                className={ROW_ACTIONS_CLASS}
              >
                <span aria-hidden="true">▼</span>
              </button>
              <button
                type="button"
                disabled={disabled}
                onClick={() => removeAt(index)}
                className="rounded-md border border-red-200 bg-white px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
              >
                {t(`${K}.remove`)}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
