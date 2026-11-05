/**
 * CheckoutFlow — isla única del flujo multi-step del checkout.
 *
 * Reemplaza la navegación GET entre pasos: estado de paso en React,
 * `history.replaceState` para deep-links `?step=`, borrador en
 * sessionStorage (los valores sobreviven pasos y reloads) y validación
 * por paso con RHF + Zod. El submit final navega a `?step=success`
 * (contrato mock: success SSR con número de pedido).
 */
import { useEffect, useMemo, useState, type FormEvent, type ReactElement } from 'react';
import { useForm, type FieldErrors } from 'react-hook-form';

import CheckoutStepper, { type CheckoutStepperLabels } from './CheckoutStepper';
import ShippingStepForm, { type ShippingStepFormLabels } from './ShippingStepForm';
import PaymentStepForm, { type PaymentStepFormLabels } from './PaymentStepForm';
import ReviewStep, { type ReviewStepLabels } from './ReviewStep';
import CheckoutOrderSummary, {
  type CheckoutOrderSummaryLabels,
} from './CheckoutOrderSummary';
import {
  createCheckoutResolver,
  STEP_FIELD_NAMES,
  type CheckoutFormValues,
  type CheckoutStepId,
  type CheckoutValidationMessages,
} from './checkoutSchemas';
import { clearCheckoutDraft, readCheckoutDraft, writeCheckoutDraft } from './checkoutDraft';
import { useCart, getCartTotals } from '../../../stores/cart';
import { formatCurrency } from '../../../lib/formatters';

export interface CheckoutFlowLabels {
  stepper: CheckoutStepperLabels;
  stepLabels: { shipping: string; payment: string; review: string };
  shipping: ShippingStepFormLabels;
  payment: PaymentStepFormLabels;
  review: ReviewStepLabels;
  summary: CheckoutOrderSummaryLabels;
  continueToPayment: string;
  continueToReview: string;
  placeOrder: string;
  previous: string;
  termsHint: string;
  validation: CheckoutValidationMessages;
  error: { title: string; message: string };
}

interface CheckoutFlowProps {
  initialStep: CheckoutStepId;
  /** Locale-prefixed base (ej. `/${locale}/checkout`). */
  checkoutBaseHref: string;
  labels: CheckoutFlowLabels;
}

const STEP_ORDER: readonly CheckoutStepId[] = ['shipping', 'payment', 'review'];

const PRIMARY_CTA_CLASSES =
  'inline-flex h-12 items-center justify-center rounded-lg bg-[var(--primary,#475569)] px-6 text-sm font-semibold text-white transition-transform hover:bg-[var(--primary-hover,#334155)] active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 motion-reduce:transition-none motion-reduce:active:scale-100';

const STEP_TRANSITION_CSS = `
@keyframes checkout-step-in {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: none; }
}
.checkout-step-in {
  animation: checkout-step-in 220ms cubic-bezier(0.23, 1, 0.32, 1) both;
}
@media (prefers-reduced-motion: reduce) {
  .checkout-step-in { animation: none; }
}
`;

function firstInvalidStep(errors: FieldErrors<CheckoutFormValues>): CheckoutStepId {
  for (const step of STEP_ORDER) {
    const hasError = STEP_FIELD_NAMES[step].some((name) => errors[name] !== undefined);
    if (hasError) return step;
  }
  return 'shipping';
}

export default function CheckoutFlow({
  initialStep,
  checkoutBaseHref,
  labels,
}: CheckoutFlowProps): ReactElement {
  const [initialValues] = useState(() => readCheckoutDraft());
  const resolver = useMemo(() => createCheckoutResolver(labels.validation), [labels.validation]);
  const form = useForm<CheckoutFormValues>({ defaultValues: initialValues, resolver });
  const [step, setStep] = useState<CheckoutStepId>(initialStep);
  const [submitError, setSubmitError] = useState(false);
  const { lines } = useCart();
  const totals = getCartTotals(lines);
  const currencyCode = lines[0]?.currency ?? 'USD';

  useEffect(() => {
    const subscription = form.watch((values) => {
      writeCheckoutDraft(values as CheckoutFormValues);
    });
    return () => subscription.unsubscribe();
  }, [form]);

  function navigateTo(next: CheckoutStepId): void {
    setSubmitError(false);
    setStep(next);
    window.history.replaceState(null, '', `${checkoutBaseHref}?step=${next}`);
  }

  async function handleAdvance(): Promise<void> {
    const isValid = await form.trigger(STEP_FIELD_NAMES[step]);
    if (!isValid) return;
    const nextIndex = STEP_ORDER.indexOf(step) + 1;
    const next = STEP_ORDER[nextIndex];
    if (next !== undefined) navigateTo(next);
  }

  function handleValidSubmit(): void {
    clearCheckoutDraft();
    window.location.assign(`${checkoutBaseHref}?step=success`);
  }

  function handleInvalidSubmit(errors: FieldErrors<CheckoutFormValues>): void {
    setStep(firstInvalidStep(errors));
    setSubmitError(true);
  }

  function handleSubmitEvent(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    if (step === 'review') {
      void form.handleSubmit(handleValidSubmit, handleInvalidSubmit)(event);
      return;
    }
    void handleAdvance();
  }

  const ctaLabel =
    step === 'shipping' ? labels.continueToPayment : step === 'payment' ? labels.continueToReview : labels.placeOrder;
  const stepperSteps = [
    { id: 'shipping' as const, label: labels.stepLabels.shipping },
    { id: 'payment' as const, label: labels.stepLabels.payment },
    { id: 'review' as const, label: labels.stepLabels.review },
  ];

  return (
    <div className="w-full pb-28 lg:pb-12">
      <style>{STEP_TRANSITION_CSS}</style>
      {submitError && (
        <div
          role="alert"
          className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
          <p className="font-semibold">{labels.error.title}</p>
          <p className="mt-1">{labels.error.message}</p>
        </div>
      )}
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-10">
        <form
          id="checkout-flow-form"
          noValidate
          onSubmit={handleSubmitEvent}
          className="flex flex-col gap-6 self-start rounded-2xl border border-[var(--line,#e7e5e4)] bg-white p-6 shadow-[0_0_0_1px_rgba(0,0,0,0.05),0_1px_2px_-1px_rgba(0,0,0,0.06),0_2px_4px_rgba(0,0,0,0.04)] sm:p-8"
        >
          <CheckoutStepper
            steps={stepperSteps}
            currentStep={step}
            onSelectStep={navigateTo}
            labels={labels.stepper}
          />
          <div key={step} className="checkout-step-in">
            {step === 'shipping' && <ShippingStepForm form={form} labels={labels.shipping} />}
            {step === 'payment' && <PaymentStepForm form={form} labels={labels.payment} />}
            {step === 'review' && <ReviewStep labels={labels.review} onEditStep={navigateTo} />}
          </div>
          <footer className="flex items-center justify-between gap-3 border-t border-[var(--line,#e7e5e4)] pt-5">
            {step !== 'shipping' ? (
              <button
                type="button"
                onClick={(): void => navigateTo(STEP_ORDER[STEP_ORDER.indexOf(step) - 1] ?? 'shipping')}
                className="text-sm font-medium text-[var(--ink,#1c1917)]/60 underline-offset-2 hover:text-[var(--ink,#1c1917)] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)]"
              >
                {labels.previous}
              </button>
            ) : (
              <span aria-hidden="true"></span>
            )}
            <button type="submit" className={PRIMARY_CTA_CLASSES}>
              {ctaLabel}
            </button>
          </footer>
          <p className="text-center text-xs text-[var(--ink,#1c1917)]/60">{labels.termsHint}</p>
        </form>
        <div>
          <CheckoutOrderSummary labels={labels.summary} />
        </div>
      </div>
      <div className="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-4 border-t border-[var(--line,#e7e5e4)] bg-[var(--paper,#f3efe8)]/95 px-4 py-3 backdrop-blur lg:hidden">
        <span className="text-sm font-semibold tabular-nums text-[var(--ink,#1c1917)]">
          {labels.summary.total} {formatCurrency(totals.totalCents, currencyCode)}
        </span>
        <button
          type="submit"
          form="checkout-flow-form"
          className="inline-flex h-10 items-center justify-center rounded-lg bg-[var(--primary,#475569)] px-4 text-sm font-semibold text-white transition-transform hover:bg-[var(--primary-hover,#334155)] active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] motion-reduce:transition-none motion-reduce:active:scale-100"
        >
          {ctaLabel}
        </button>
      </div>
    </div>
  );
}
