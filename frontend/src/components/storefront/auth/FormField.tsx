import type { ReactElement, ReactNode } from 'react';
import { useTranslations } from '../../../i18n';

/**
 * Helpers de UI compartidos por los campos del form de registro:
 * label con marcador de obligatorio, mensaje de error inline y
 * variantes de input (ok / error). Extraído de RegisterForm para
 * no convertirlo en un monolito.
 */

const INPUT_BASE_CLASS =
  'w-full px-4 py-2.5 bg-slate-50 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:outline-none';

const INPUT_OK_CLASS =
  'border border-slate-200 focus:border-[var(--brand-accent,#2D9D8E)] focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]';

const INPUT_ERROR_CLASS =
  'border border-red-500 ring-4 ring-red-100 focus:border-red-500 focus:ring-4 focus:ring-red-200';

/** Clase de input con variante de error (borde rojo + ring suave). */
export function inputClass(hasError: boolean): string {
  return `${INPUT_BASE_CLASS} ${hasError ? INPUT_ERROR_CLASS : INPUT_OK_CLASS}`;
}

/** Asterisco rojo de campo obligatorio + texto accesible asociado. */
export function RequiredMark(): ReactElement {
  const { t } = useTranslations();

  return (
    <>
      {' '}
      <span aria-hidden="true" title={t('forms.requiredMark')} className="text-red-600">
        *
      </span>
      <span className="sr-only">{` ${t('forms.requiredMark')}`}</span>
    </>
  );
}

interface FormFieldProps {
  id: string;
  label: string;
  required?: boolean;
  error?: string | undefined;
  /** Cue de atención (pulse motion-safe) en el mensaje de error. */
  attention?: boolean;
  children: ReactNode;
}

export default function FormField({
  id,
  label,
  required = false,
  error,
  attention = false,
  children,
}: FormFieldProps): ReactElement {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-slate-700 mb-1.5">
        {label}
        {required ? <RequiredMark /> : null}
      </label>
      {children}
      {error !== undefined ? (
        <p
          id={`${id}-error`}
          role="alert"
          className={`mt-1.5 text-sm text-red-600${attention ? ' motion-safe:animate-pulse' : ''}`}
        >
          {error}
        </p>
      ) : null}
    </div>
  );
}
