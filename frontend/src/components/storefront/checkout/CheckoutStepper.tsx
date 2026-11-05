/**
 * CheckoutStepper — control pill segmentado del flujo de checkout.
 *
 * Pasos hechos: acento relleno + check y clickeables (vuelven al paso
 * con los valores preservados). Activo: anillo/tinte de acento +
 * `aria-current="step"`. Futuros: muted y deshabilitados.
 */
import type { ReactElement } from 'react';
import type { CheckoutStepId } from './checkoutSchemas';

export interface CheckoutStepperLabels {
  stepOf: string;
  stepperLabel: string;
}

export interface CheckoutStepperStep {
  id: CheckoutStepId;
  label: string;
}

interface CheckoutStepperProps {
  steps: readonly CheckoutStepperStep[];
  currentStep: CheckoutStepId;
  onSelectStep: (step: CheckoutStepId) => void;
  labels: CheckoutStepperLabels;
}

function formatStepOf(template: string, current: number, total: number): string {
  return template.replace('{current}', String(current)).replace('{total}', String(total));
}

function CheckIcon(): ReactElement {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className="h-3.5 w-3.5"
    >
      <path d="M20 6 9 17l-5-5" />
    </svg>
  );
}

export default function CheckoutStepper({
  steps,
  currentStep,
  onSelectStep,
  labels,
}: CheckoutStepperProps): ReactElement {
  const currentIndex = steps.findIndex((step) => step.id === currentStep);

  return (
    <nav aria-label={labels.stepperLabel} className="flex flex-col gap-3">
      <ol className="flex list-none items-center gap-2 p-0">
        {steps.map((step, index) => {
          const isDone = index < currentIndex;
          const isActive = step.id === currentStep;
          const isFuture = index > currentIndex;
          const stepOfLabel = formatStepOf(labels.stepOf, index + 1, steps.length);

          return (
            <li key={step.id} className="flex flex-1 items-center gap-2">
              {index > 0 && (
                <span
                  aria-hidden="true"
                  className="hidden h-px flex-1 bg-[var(--line,#e7e5e4)] sm:block"
                />
              )}
              <button
                type="button"
                aria-label={`${stepOfLabel} — ${step.label}`}
                aria-current={isActive ? 'step' : undefined}
                disabled={isFuture}
                onClick={(): void => {
                  if (isDone) onSelectStep(step.id);
                }}
                className={[
                  'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100',
                  isActive
                    ? 'border-[var(--primary,#475569)] bg-[var(--primary-soft,#e6f7f4)] text-[var(--ink,#1c1917)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)]'
                    : isDone
                      ? 'border-[var(--primary,#475569)] bg-[var(--primary,#475569)] text-white hover:bg-[var(--primary-hover,#334155)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)]'
                      : 'border-[var(--line,#e7e5e4)] bg-white text-[var(--ink,#1c1917)]/40',
                ].join(' ')}
              >
                <span
                  aria-hidden="true"
                  className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-current text-[11px] leading-none"
                >
                  {isDone ? <CheckIcon /> : index + 1}
                </span>
                <span className="hidden sm:inline">{step.label}</span>
              </button>
            </li>
          );
        })}
      </ol>
      <p className="text-xs text-[var(--ink,#1c1917)]/60 lg:hidden">
        {formatStepOf(labels.stepOf, currentIndex + 1, steps.length)}
      </p>
    </nav>
  );
}
