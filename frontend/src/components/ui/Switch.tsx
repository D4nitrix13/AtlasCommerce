import { type ChangeEvent, type ReactElement } from 'react';

/**
 * Switch — primitive UI.
 *
 * Toggle accesible construido sobre `<input type="checkbox">` con
 * styling visual tipo iOS. El consumidor controla el valor (`checked`)
 * y el cambio (`onChange`) — este componente es controlado, sin estado
 * interno.
 *
 * Accesibilidad (AGENTS §17):
 *  - Render real `<input>` + `<label>` (los lectores de pantalla lo
 *    anuncian como checkbox).
 *  - `aria-label` cuando no hay label visible (caso típico en tablas).
 *  - El label visible se vincula via `htmlFor` automáticamente.
 *  - Estado disabled: `aria-disabled` + `disabled` + `cursor-not-allowed`.
 *
 * Variante visual:
 *  - `checked` → fondo brand (var(--primary)) + thumb blanco a la derecha.
 *  - `unchecked` → fondo slate-200 + thumb blanco a la izquierda.
 *  - `disabled` → opacity reducida + no se puede cambiar.
 *
 * Uso:
 *   <Switch checked={on} onChange={setOn} ariaLabel="Email de pedidos" />
 *   <Switch checked={on} onChange={setOn} label="Recibir emails" disabled />
 */
export interface SwitchProps {
  /** Estado actual del toggle (controlado). */
  checked: boolean;
  /** Callback con el nuevo valor tras un toggle. */
  onChange: (next: boolean) => void;
  /** Label visible opcional. Si se omite, debe pasarse `ariaLabel`. */
  label?: string;
  /** ARIA label obligatorio cuando no hay `label` visible (tablas densas). */
  ariaLabel?: string;
  /** Si el toggle está bloqueado. */
  disabled?: boolean;
  /** ID único del input — necesario para vincular el label. */
  id?: string;
  /** Name del input (útil para forms nativos). */
  name?: string;
}

/**
 * Componente visual (track + thumb). Acepta las mismas props que
 * el wrapper público, pero se renderiza sin el wrapper `<label>`
 * — útil cuando ya hay un label externo.
 */
export interface SwitchVisualProps {
  checked: boolean;
  onChange: (next: boolean) => void;
  id: string;
  disabled?: boolean;
  ariaLabel: string;
  name?: string;
}

export function Switch({
  checked,
  onChange,
  label,
  ariaLabel,
  disabled = false,
  id,
  name,
}: SwitchProps): ReactElement {
  const resolvedId = id ?? `switch-${Math.random().toString(36).slice(2, 9)}`;
  const inputId = `${resolvedId}-input`;

  if (label === undefined || label === '') {
    return (
      <SwitchVisual
        checked={checked}
        onChange={onChange}
        id={inputId}
        disabled={disabled}
        ariaLabel={ariaLabel ?? ''}
        name={name}
      />
    );
  }

  return (
    <label
      htmlFor={inputId}
      className={`inline-flex cursor-pointer items-center gap-3 text-sm text-slate-700 ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
    >
      <SwitchVisual
        checked={checked}
        onChange={onChange}
        id={inputId}
        disabled={disabled}
        ariaLabel={ariaLabel ?? label}
        name={name}
      />
      <span>{label}</span>
    </label>
  );
}

function SwitchVisual({
  checked,
  onChange,
  id,
  disabled = false,
  ariaLabel,
  name,
}: SwitchVisualProps): ReactElement {
  function handleChange(event: ChangeEvent<HTMLInputElement>): void {
    onChange(event.target.checked);
  }

  return (
    <span
      className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors ${
        checked ? 'bg-[var(--primary,#7c3aed)]' : 'bg-slate-200'
      }`}
    >
      <input
        id={id}
        type="checkbox"
        checked={checked}
        onChange={handleChange}
        disabled={disabled}
        aria-label={ariaLabel}
        name={name}
        className="peer absolute inset-0 cursor-pointer opacity-0 disabled:cursor-not-allowed"
      />
      <span
        aria-hidden="true"
        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform ${
          checked ? 'translate-x-5' : 'translate-x-0.5'
        }`}
      />
    </span>
  );
}

export default Switch;
