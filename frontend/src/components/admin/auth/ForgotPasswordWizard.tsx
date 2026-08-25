import { useState, type ReactElement } from 'react';
import { apiFetch } from '../../../lib/api';
import { useTranslations } from '../../../i18n';
import {
  WizardFormContainer,
  WizardStepper,
  WizardSuccessMessage,
} from './ForgotPasswordWizardUi';
import {
  CodeStep,
  EmailStep,
  MethodStep,
  PasswordStep,
  type CodeStepData,
  type EmailStepData,
  type PasswordStepData,
} from './ForgotPasswordWizardSteps';
import type {
  AvailableMethod,
  ForgotPasswordResponse,
  ForgotPasswordWizardStep,
  LoginResponse,
  RecoveryCodeChallengeResponse,
  ValidationErrors,
} from '../../../types/auth';

/**
 * Wizard de recuperación de contraseña — flow 2-step challenge_token.
 * 4 estados: email → method (opcional) → code → password. El backend
 * decide qué métodos hay disponibles y el wizard solo muestra esos.
 * El `challenge_token` vive solo en estado del componente (no se
 * persiste en URL ni sessionStorage) para reducir ventana de
 * exposición si el usuario abandona la pestaña.
 *
 * Props:
 *  - locale: para el redirect post-success.
 *  - callbackPath: a dónde ir después del reset.
 */

type ForgotPasswordWizardProps = {
  locale?: string;
  callbackPath?: string;
};

const STEP_ORDER: readonly ForgotPasswordWizardStep[] = [
  'email',
  'method',
  'code',
  'password',
] as const;

type WizardState = {
  step: ForgotPasswordWizardStep;
  email: string;
  availableMethods: readonly AvailableMethod[];
  selectedMethod: AvailableMethod | null;
  challengeToken: number | null;
  errorMessage: string | null;
  isSubmitting: boolean;
  isComplete: boolean;
};

const INITIAL_STATE: WizardState = {
  step: 'email',
  email: '',
  availableMethods: [],
  selectedMethod: null,
  challengeToken: null,
  errorMessage: null,
  isSubmitting: false,
  isComplete: false,
};

export default function ForgotPasswordWizard({
  locale = 'es',
  callbackPath = '/admin/dashboard',
}: ForgotPasswordWizardProps): ReactElement {
  const { t } = useTranslations();
  const [state, setState] = useState<WizardState>(INITIAL_STATE);

  // Labels del stepper — derivadas del locale activo vía t().
  const stepLabels: Record<ForgotPasswordWizardStep, string> = {
    email: t('auth.passwordRecovery.stepper.email'),
    method: t('auth.passwordRecovery.stepper.method'),
    code: t('auth.passwordRecovery.stepper.code'),
    password: t('auth.passwordRecovery.stepper.password'),
  };

  const updateState = (patch: Partial<WizardState>): void => {
    setState((prev) => ({ ...prev, ...patch }));
  };

  // Step 1 → 2: descubre métodos via /auth/forgot-password.
  // Si solo hay 1 método, salta al code step directamente.
  const handleEmailSubmit = async (next: EmailStepData): Promise<void> => {
    updateState({ isSubmitting: true, errorMessage: null });
    try {
      const response = await apiFetch<ForgotPasswordResponse>(
        '/auth/forgot-password',
        { method: 'POST', body: { email: next.email } },
      );

      const methods = response.available_methods ?? [];
      const autoSelected: AvailableMethod | null =
        methods.length === 1 ? (methods[0] ?? null) : null;
      const nextStep: ForgotPasswordWizardStep =
        methods.length === 0
          ? 'method'
          : methods.length === 1
            ? 'code'
            : 'method';

      updateState({
        email: next.email,
        availableMethods: methods,
        selectedMethod: autoSelected,
        step: nextStep,
        isSubmitting: false,
      });
    } catch (error_) {
      const apiError = error_ as { message?: string };
      updateState({
        isSubmitting: false,
        errorMessage: apiError.message ?? t('auth.passwordRecovery.errors.emailSubmit'),
      });
    }
  };

  // Step 2 → 3: el usuario eligió método en MethodStep (solo guarda
  // selección; el "Continuar" del WizardButtonRow dispara handleMethodSubmit).
  const handleMethodSelect = (method: AvailableMethod): void => {
    updateState({ selectedMethod: method, errorMessage: null });
  };

  // Step 2 → 3: el usuario hizo click en "Continuar" del MethodStep
  // con un método seleccionado. Avanza al code step.
  const handleMethodSubmit = (method: AvailableMethod): void => {
    updateState({ selectedMethod: method, step: 'code', errorMessage: null });
  };

  const handleBackToEmail = (): void => {
    updateState({ step: 'email', errorMessage: null });
  };

  const handleBackFromCode = (): void => {
    // Si llegamos al code por skip del method step, volver al email.
    const previousStep: ForgotPasswordWizardStep =
      state.availableMethods.length > 1 ? 'method' : 'email';
    updateState({ step: previousStep, errorMessage: null });
  };

  // Step 3 → 4: verifica el código (recovery o TOTP) y emite challenge_token.
  const handleCodeSubmit = async (code: string): Promise<void> => {
    if (state.selectedMethod === null) {
      updateState({ errorMessage: t('auth.passwordRecovery.errors.methodRequired') });
      return;
    }

    const endpoint =
      state.selectedMethod === 'recovery_codes'
        ? '/auth/recover-via-recovery-code'
        : '/auth/recover-via-totp';

    updateState({ isSubmitting: true, errorMessage: null });
    try {
      const response = await apiFetch<RecoveryCodeChallengeResponse>(endpoint, {
        method: 'POST',
        body: { email: state.email, code },
      });
      updateState({
        challengeToken: response.challenge_token,
        step: 'password',
        isSubmitting: false,
      });
    } catch (error_) {
      const apiError = error_ as { message?: string; errors?: ValidationErrors | null };
      const fieldError = apiError.errors?.code?.[0];
      updateState({
        isSubmitting: false,
        errorMessage:
          fieldError ?? apiError.message ?? t('auth.passwordRecovery.errors.codeInvalid'),
      });
    }
  };

  // Step 4 (final): POST /auth/reset-password con challenge_token.
  // En éxito, redirige al login con flash param.
  const handlePasswordSubmit = async (password: string): Promise<void> => {
    if (state.challengeToken === null) {
      updateState({ errorMessage: t('auth.passwordRecovery.errors.challengeExpired') });
      return;
    }

    updateState({ isSubmitting: true, errorMessage: null });
    try {
      const payload = {
        email: state.email,
        challenge_token: state.challengeToken,
        password,
        password_confirmation: password,
        device_name: 'web-admin',
      };
      await apiFetch<LoginResponse>('/auth/reset-password', {
        method: 'POST',
        body: payload,
      });
      updateState({ isSubmitting: false, isComplete: true });
      window.setTimeout(() => {
        const params = new URLSearchParams({
          flash: 'password_reset',
          callback: callbackPath,
        });
        window.location.href = `/${locale}/auth/login?${params.toString()}`;
      }, 800);
    } catch (error_) {
      const apiError = error_ as { message?: string; errors?: ValidationErrors | null };
      const passwordError = apiError.errors?.password?.[0];
      const challengeError = apiError.errors?.challenge_token?.[0];
      updateState({
        isSubmitting: false,
        errorMessage:
          challengeError ?? passwordError ?? apiError.message ??
          t('auth.passwordRecovery.errors.reset'),
      });
    }
  };

  // Render del step activo. Inline (cada branch tiene shape distinto).
  const renderActiveStep = (): ReactElement | null => {
    switch (state.step) {
      case 'email':
        return (
          <EmailStep
            data={{ email: state.email }}
            onNext={handleEmailSubmit}
            errorMessage={state.errorMessage}
            isSubmitting={state.isSubmitting}
          />
        );

      case 'method':
        if (state.availableMethods.length === 0) {
          return (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-slate-900">
                {t('auth.passwordRecovery.empty.heading')}
              </h2>
              <p className="text-sm text-slate-600">{t('auth.passwordRecovery.empty.body')}</p>
              <button
                type="button"
                onClick={handleBackToEmail}
                className="text-sm font-semibold text-[#2D9D8E] hover:text-[#1E6F62]"
              >
                {t('auth.passwordRecovery.empty.backToStart')}
              </button>
            </div>
          );
        }
        return (
          <MethodStep
            email={state.email}
            availableMethods={state.availableMethods}
            onNext={handleMethodSubmit}
            onSelect={handleMethodSelect}
            onBack={handleBackToEmail}
            errorMessage={state.errorMessage}
            selectedMethod={state.selectedMethod}
          />
        );

      case 'code': {
        if (state.selectedMethod === null) {
          return (
            <div className="text-sm text-slate-600">
              {t('auth.passwordRecovery.errors.genericFallback')}
            </div>
          );
        }
        const codeData: CodeStepData = {
          email: state.email,
          selectedMethod: state.selectedMethod,
        };
        return (
          <CodeStep
            {...codeData}
            onNext={handleCodeSubmit}
            onBack={handleBackFromCode}
            errorMessage={state.errorMessage}
            isSubmitting={state.isSubmitting}
          />
        );
      }

      case 'password': {
        if (state.challengeToken === null) {
          return (
            <div className="text-sm text-slate-600">
              {t('auth.passwordRecovery.errors.challengeExpiredLate')}
            </div>
          );
        }
        const passwordData: PasswordStepData = {
          email: state.email,
          challengeToken: state.challengeToken,
        };
        return (
          <PasswordStep
            {...passwordData}
            onNext={handlePasswordSubmit}
            errorMessage={state.errorMessage}
            isSubmitting={state.isSubmitting}
          />
        );
      }

      default:
        return null;
    }
  };

  return (
    <div className="w-full">
      <WizardStepper steps={STEP_ORDER} current={state.step} labels={stepLabels} />
      <div className="flex justify-center">
      <WizardFormContainer>
        {state.isComplete && (
          <WizardSuccessMessage message={t('auth.passwordRecovery.success.redirecting')} />
        )}
        {renderActiveStep()}
      </WizardFormContainer>
      </div>
    </div>
  );
}

export type { ForgotPasswordWizardProps };