import { type ReactElement, type ReactNode } from 'react';

/**
 * Primitivas de UI compartidas por el `ForgotPasswordWizard`.
 *
 * Este archivo es PURO UI — no tiene estado ni lógica. Los componentes
 * que lo consumen (`EmailStep`, `MethodStep`, `CodeStep`,
 * `PasswordStep`) deciden cuándo y cómo usarlos.
 *
 * La paleta de colores (teal `#2D9D8E`) es la MISMA que usan
 * `LoginForm`, `ForgotPasswordForm` y `ResetPasswordForm` (ver
 * `lib/auth-accent.ts`). Esto mantiene consistencia visual entre
 * páginas de auth — el usuario no debe sentir que cambió de "marca"
 * entre Login y Recover.
 *
 * Convenciones:
 *  - Inputs siguen el patrón `bg-slate-50 border-slate-200 rounded-lg
 *    focus:border-[#2D9D8E]` (consistente con LoginForm).
 *  - Botones primarios usan `bg-[#2D9D8E] hover:bg-[#258777]` (un
 *    shade más oscuro).
 *  - Alerts de error: `border-red-200 bg-red-50 text-red-700`.
 *  - Alerts de éxito: `border-green-200 bg-green-50 text-green-700`.
 */

type WizardStepperProps = {
  /** Lista de IDs de paso en orden. El stepper renderiza un dot por cada uno. */
  steps: readonly string[];
  /** Step actual (debe estar en `steps`). */
  current: string;
  /** Labels opcionales por step. Si se omite, usa el ID como label. */
  labels?: Record<string, string>;
};

/**
 * Indicador de progreso horizontal con dots. Cada dot representa un
 * step del wizard. Los pasos completados se muestran en teal, el
 * actual con un anillo, los pendientes en gris.
 *
 * Usado arriba del form para que el usuario sepa en qué paso está
 * y cuántos faltan.
 */
export function WizardStepper({ steps, current, labels }: WizardStepperProps): ReactElement {
  const currentIndex = steps.indexOf(current);

  return (
    <div className="flex items-start justify-between w-full mb-6" aria-label="Progreso del wizard">
      {steps.map((step, index) => {
        const isComplete = index < currentIndex;
        const isCurrent = index === currentIndex;
        const label = labels?.[step] ?? step;
        const isLast = index === steps.length - 1;

        return (
          <div key={step} className="flex-1 min-w-0 flex items-center">
            <div className="flex flex-col items-center gap-1.5 w-full min-w-0">
              <div
                aria-current={isCurrent ? 'step' : undefined}
                className={
                  isComplete
                    ? 'size-8 rounded-full bg-[#2D9D8E] text-white flex items-center justify-center text-xs font-semibold'
                    : isCurrent
                      ? 'size-8 rounded-full bg-white border-2 border-[#2D9D8E] text-[#2D9D8E] flex items-center justify-center text-xs font-semibold ring-4 ring-[#2D9D8E]/15'
                      : 'size-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xs font-semibold'
                }
              >
                {isComplete ? (
                  <span aria-hidden="true">✓</span>
                ) : (
                  index + 1
                )}
              </div>
              <span
                className={
                  'text-xs text-center max-w-full leading-tight whitespace-normal px-1 ' +
                  (isCurrent
                    ? 'font-medium text-[#2D9D8E]'
                    : isComplete
                      ? 'font-medium text-slate-700'
                      : 'text-slate-400')
                }
              >
                {label}
              </span>
            </div>
            {!isLast && (
              <div
                aria-hidden="true"
                className={
                  'h-0.5 flex-1 mx-1 self-center -mt-3 ' +
                  (isComplete ? 'bg-[#2D9D8E]' : 'bg-slate-200')
                }
              />
            )}
          </div>
        );
      })}
    </div>
  );
}

type WizardFormContainerProps = {
  children: ReactNode;
  /** Class adicional para el wrapper externo. */
  className?: string;
};

/**
 * Wrapper con spacing consistente y borde sutil. Centra el contenido
 * del step actual y provee un borde para separarlo del fondo.
 *
 * Si lo deseás sin borde (ej. cuando el wizard está embebido en otra
 * card), pasá `className="border-0 shadow-none"`.
 */
export function WizardFormContainer({
  children,
  className = '',
}: WizardFormContainerProps): ReactElement {
  return (
    <div
      className={`rounded-xl border border-slate-200 bg-white p-8 shadow-sm w-full max-w-xl ${className}`}
    >
      {children}
    </div>
  );
}

type WizardButtonRowProps = {
  onBack?: () => void;
  onNext?: () => void;
  onSubmit?: () => void;
  /** Label del botón primario ("Siguiente", "Verificar", "Restablecer"). */
  nextLabel: string;
  /** Label mientras se procesa el submit. */
  submittingLabel?: string;
  /** Label del botón back. Default "Atrás". */
  backLabel?: string;
  /** Si el botón primario está disabled (loading o validación fallida). */
  isSubmitting?: boolean;
  /** Si el botón primario debe verse como "success" (verde, no teal). */
  hasSuccess?: boolean;
};

/**
 * Fila de botones al pie del form. Soporta back/next o solo submit.
 *
 * Si `onBack` no se pasa, NO se muestra el botón (ej. en el primer
 * step del wizard donde no hay a dónde volver).
 *
 * Si `onSubmit` se pasa, `onNext` se ignora — son mutuamente
 * excluyentes (un form o tiene next-step o submit-final).
 */
export function WizardButtonRow({
  onBack,
  onNext,
  onSubmit,
  nextLabel,
  submittingLabel,
  backLabel = 'Atrás',
  isSubmitting = false,
  hasSuccess = false,
}: WizardButtonRowProps): ReactElement {
  const handlePrimaryClick = (): void => {
    if (isSubmitting || hasSuccess) {
      return;
    }
    if (onSubmit !== undefined) {
      onSubmit();
    } else if (onNext !== undefined) {
      onNext();
    }
  };

  const primaryLabel = isSubmitting && submittingLabel !== undefined ? submittingLabel : nextLabel;
  const showSpinner = isSubmitting && !hasSuccess;

  return (
    <div className="flex items-center justify-between gap-3 pt-2">
      {onBack !== undefined ? (
        <button
          type="button"
          onClick={onBack}
          disabled={isSubmitting}
          className="text-sm font-semibold text-slate-600 hover:text-slate-900 transition focus:outline-none focus:ring-4 focus:ring-slate-200 rounded px-3 py-2"
        >
          {backLabel}
        </button>
      ) : (
        <span />
      )}
      <button
        type="button"
        onClick={handlePrimaryClick}
        disabled={isSubmitting || hasSuccess}
        className="flex-1 min-w-[180px] max-w-sm bg-[#2D9D8E] hover:bg-[#258777] disabled:bg-[#258777] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25 flex items-center justify-center gap-2"
      >
        {showSpinner ? (
          <>
            <svg className="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
              />
            </svg>
            {primaryLabel}
          </>
        ) : (
          primaryLabel
        )}
      </button>
    </div>
  );
}

type WizardErrorMessageProps = {
  message: string | null;
  /** ID del campo al que apunta (para aria-describedby). */
  fieldId?: string;
};

/**
 * Banner de error con `role="alert"` (screen readers lo anuncian
 * apenas aparece). Si `message` es null, no renderiza nada.
 */
export function WizardErrorMessage({
  message,
  fieldId,
}: WizardErrorMessageProps): ReactElement | null {
  if (message === null) {
    return null;
  }

  return (
    <div
      role="alert"
      aria-describedby={fieldId}
      className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {message}
    </div>
  );
}

type WizardSuccessMessageProps = {
  message: string | null;
};

/**
 * Banner de éxito con `role="status"`. A diferencia del error, usa
 * `status` en vez de `alert` para no interrumpir agresivamente al
 * screen reader.
 */
export function WizardSuccessMessage({
  message,
}: WizardSuccessMessageProps): ReactElement | null {
  if (message === null) {
    return null;
  }

  return (
    <div
      role="status"
      className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
    >
      {message}
    </div>
  );
}