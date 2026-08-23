import { useState, type ReactElement, type ChangeEvent } from 'react';
import {
  WizardButtonRow,
  WizardErrorMessage,
} from './ForgotPasswordWizardUi';
import type { AvailableMethod } from '../../../types/auth';

/**
 * Sub-componentes del `ForgotPasswordWizard`. Cada step es un form
 * autocontenido: el padre pasa `data` + callbacks. Los steps no se
 * comunican entre sí — todo va por el state del padre, evitando
 * sincronización sutil al navegar back/next.
 */

export type EmailStepData = { email: string };

type EmailStepProps = {
  data: EmailStepData;
  onNext: (next: EmailStepData) => void;
  errorMessage: string | null;
  isSubmitting: boolean;
};

/**
 * Step 1 del wizard: el usuario ingresa su email y se descubre qué
 * métodos de recuperación tiene disponibles. El backend responde 200
 * OK siempre (no revela si el email existe); el array
 * `available_methods` vacío se maneja en el method step.
 */
export function EmailStep({
  data,
  onNext,
  errorMessage,
  isSubmitting,
}: EmailStepProps): ReactElement {
  const [email, setEmail] = useState<string>(data.email);
  const [validationError, setValidationError] = useState<string | null>(null);

  const validate = (value: string): string | null => {
    if (value.trim() === '') {
      return 'Ingresá tu correo electrónico.';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return 'El correo no tiene un formato válido.';
    }
    return null;
  };

  const handleSubmit = (): void => {
    const error = validate(email);
    if (error !== null) {
      setValidationError(error);
      return;
    }
    setValidationError(null);
    onNext({ email: email.trim() });
  };

  const visibleError = validationError ?? errorMessage;

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        handleSubmit();
      }}
      className="space-y-4"
      noValidate
    >
      <div>
        <h2 className="text-lg font-semibold text-slate-900 mb-1">Recuperá tu cuenta</h2>
        <p className="text-sm text-slate-500">
          Empezamos por tu correo. Te mostraremos las opciones de recuperación disponibles.
        </p>
      </div>

      <WizardErrorMessage
        message={visibleError}
        fieldId="wizard-email"
      />

      <div>
        <label
          htmlFor="wizard-email"
          className="block text-sm font-medium text-slate-700 mb-1.5"
        >
          Correo electrónico
        </label>
        <input
          id="wizard-email"
          name="email"
          type="email"
          inputMode="email"
          autoComplete="email"
          required
          placeholder="tu@correo.com"
          value={email}
          onChange={(event: ChangeEvent<HTMLInputElement>) => {
            setEmail(event.target.value);
          }}
          className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        />
      </div>

      <WizardButtonRow
        nextLabel="Continuar"
        submittingLabel="Verificando..."
        onNext={handleSubmit}
        isSubmitting={isSubmitting}
      />
    </form>
  );
}

export type MethodStepData = {
  email: string;
  availableMethods: readonly AvailableMethod[];
};

type MethodStepProps = MethodStepData & {
  onNext: (method: AvailableMethod) => void;
  onBack: () => void;
  errorMessage: string | null;
};

const METHOD_LABELS: Record<AvailableMethod, { title: string; description: string }> = {
  email: {
    title: 'Correo electrónico',
    description: 'Te enviaremos un código de 6 dígitos a tu correo.',
  },
  recovery_codes: {
    title: 'Código de respaldo',
    description: 'Ingresá uno de los códigos de 10 caracteres que guardaste al activar 2FA.',
  },
  totp: {
    title: 'Authenticator app',
    description: 'Ingresá el código de 6 dígitos de tu app de autenticación.',
  },
};

/**
 * Step 2 del wizard: cuando hay >1 método, el usuario elige cuál usar.
 * El `Wizard` se salta este step cuando hay 0 o 1 método disponible.
 */
export function MethodStep({
  email,
  availableMethods,
  onNext,
  onBack,
  errorMessage,
}: MethodStepProps): ReactElement {
  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-semibold text-slate-900 mb-1">Elegí cómo verificarte</h2>
        <p className="text-sm text-slate-500">
          Para {email} tenés {availableMethods.length} opciones de recuperación:
        </p>
      </div>

      <WizardErrorMessage message={errorMessage} />

      <div className="space-y-2">
        {availableMethods.map((method) => {
          const info = METHOD_LABELS[method];
          return (
            <button
              key={method}
              type="button"
              onClick={() => {
                onNext(method);
              }}
              className="w-full text-left p-4 rounded-lg border border-slate-200 hover:border-[#2D9D8E] hover:bg-[#2D9D8E]/5 transition focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
            >
              <div className="font-semibold text-slate-900">{info.title}</div>
              <div className="text-sm text-slate-500 mt-0.5">{info.description}</div>
            </button>
          );
        })}
      </div>

      <WizardButtonRow onBack={onBack} nextLabel="Continuar" />
    </div>
  );
}

export type CodeStepData = {
  email: string;
  selectedMethod: AvailableMethod;
};

type CodeStepProps = CodeStepData & {
  onNext: (code: string) => void;
  onBack: () => void;
  errorMessage: string | null;
  isSubmitting: boolean;
};

/**
 * Step 3 del wizard: el usuario ingresa el código (recovery o TOTP).
 * El shape del input varía según el método seleccionado y filtra
 * caracteres no permitidos a medida que el user tipea.
 */
export function CodeStep({
  email,
  selectedMethod,
  onNext,
  onBack,
  errorMessage,
  isSubmitting,
}: CodeStepProps): ReactElement {
  void email;
  const [code, setCode] = useState<string>('');

  const isRecovery = selectedMethod === 'recovery_codes';
  const expectedLength = isRecovery ? 10 : 6;
  const inputMode = 'text';
  const pattern = isRecovery ? '^[A-HJ-KM-NP-Z2-9]{10}$' : '^[0-9]{6}$';

  const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const raw = event.target.value.toUpperCase();
    if (isRecovery) {
      // Filtrar caracteres no permitidos del alfabeto de recovery codes
      const filtered = raw.replace(/[^A-HJ-KM-NP-Z2-9]/g, '').slice(0, expectedLength);
      setCode(filtered);
    } else {
      // Solo dígitos
      const filtered = raw.replace(/\D/g, '').slice(0, expectedLength);
      setCode(filtered);
    }
  };

  const handleSubmit = (): void => {
    if (code.length !== expectedLength) {
      return;
    }
    onNext(code);
  };

  const title = isRecovery ? 'Ingresá tu código de respaldo' : 'Ingresá tu código de verificación';
  const helperText = isRecovery
    ? 'Es uno de los códigos de 10 caracteres que guardaste al activar la verificación en dos pasos.'
    : 'Es el código de 6 dígitos de tu authenticator app o el que recibiste por correo.';

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        handleSubmit();
      }}
      className="space-y-4"
      noValidate
    >
      <div>
        <h2 className="text-lg font-semibold text-slate-900 mb-1">{title}</h2>
        <p className="text-sm text-slate-500">{helperText}</p>
      </div>

      <WizardErrorMessage message={errorMessage} fieldId="wizard-code" />

      <div>
        <label
          htmlFor="wizard-code"
          className="block text-sm font-medium text-slate-700 mb-1.5"
        >
          Código de verificación
        </label>
        <input
          id="wizard-code"
          name="code"
          type="text"
          inputMode={inputMode}
          autoComplete="one-time-code"
          required
          maxLength={expectedLength}
          pattern={pattern}
          placeholder={isRecovery ? 'ABCDEFGHJK' : '000000'}
          value={code}
          onChange={handleChange}
          className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-center font-mono text-2xl tracking-[0.4em] text-slate-900 placeholder:text-slate-300 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        />
      </div>

      <WizardButtonRow
        onBack={onBack}
        nextLabel="Verificar código"
        submittingLabel="Verificando..."
        onNext={handleSubmit}
        isSubmitting={isSubmitting}
      />
    </form>
  );
}

export type PasswordStepData = {
  email: string;
  challengeToken: number;
};

type PasswordStepProps = PasswordStepData & {
  onNext: (password: string) => void;
  errorMessage: string | null;
  isSubmitting: boolean;
};

const PASSWORD_MIN_LENGTH = 12;

const PASSWORD_INPUT_CLASS =
  'w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25';
const PASSWORD_INPUT_MATCH_CLASS =
  'w-full px-4 py-2.5 bg-slate-50 border border-[#2D9D8E] rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25';

/**
 * Step 4 del wizard: nueva password ×2, enviada al backend con el
 * `challenge_token` del step 3. La validación final la hace el
 * form request del backend; acá mostramos feedback visual antes.
 */
export function PasswordStep({
  onNext,
  errorMessage,
  isSubmitting,
}: PasswordStepProps): ReactElement {
  const [password, setPassword] = useState<string>('');
  const [confirmPassword, setConfirmPassword] = useState<string>('');

  const minLengthOk = password.length >= PASSWORD_MIN_LENGTH;
  const upperLowerOk = /[A-Z]/.test(password) && /[a-z]/.test(password);
  const numberOk = /[0-9]/.test(password);
  const passwordsMatch =
    password !== '' && confirmPassword !== '' && password === confirmPassword;
  const canSubmit = minLengthOk && upperLowerOk && numberOk && passwordsMatch;

  const handleSubmit = (): void => {
    if (canSubmit) {
      onNext(password);
    }
  };

  const checklist = [
    { met: minLengthOk, label: 'Al menos 12 caracteres' },
    { met: upperLowerOk, label: 'Una letra mayúscula y una minúscula' },
    { met: numberOk, label: 'Al menos un número' },
  ];

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        handleSubmit();
      }}
      className="space-y-4"
      noValidate
    >
      <div>
        <h2 className="text-lg font-semibold text-slate-900 mb-1">Creá tu nueva contraseña</h2>
        <p className="text-sm text-slate-500">
          Elegí una contraseña segura. La necesitás para iniciar sesión.
        </p>
      </div>

      <WizardErrorMessage message={errorMessage} />

      <div>
        <label htmlFor="wizard-password" className="block text-sm font-medium text-slate-700 mb-1.5">
          Nueva contraseña
        </label>
        <input
          id="wizard-password"
          name="password"
          type="password"
          autoComplete="new-password"
          required
          minLength={PASSWORD_MIN_LENGTH}
          placeholder="Mínimo 12 caracteres"
          value={password}
          onChange={(event) => {
            setPassword(event.target.value);
          }}
          className={PASSWORD_INPUT_CLASS}
        />
      </div>

      <div>
        <label
          htmlFor="wizard-password-confirm"
          className="block text-sm font-medium text-slate-700 mb-1.5"
        >
          Repetir contraseña
        </label>
        <input
          id="wizard-password-confirm"
          name="password_confirmation"
          type="password"
          autoComplete="new-password"
          required
          minLength={PASSWORD_MIN_LENGTH}
          placeholder="Repetí la contraseña"
          value={confirmPassword}
          onChange={(event) => {
            setConfirmPassword(event.target.value);
          }}
          className={passwordsMatch ? PASSWORD_INPUT_MATCH_CLASS : PASSWORD_INPUT_CLASS}
        />
        {passwordsMatch && (
          <p className="text-xs text-[#2D9D8E] mt-1.5">Las contraseñas coinciden.</p>
        )}
      </div>

      <ul className="text-xs text-slate-600 space-y-1 bg-slate-50 rounded-lg p-3">
        {checklist.map((item) => (
          <li key={item.label} className={item.met ? 'text-[#2D9D8E]' : ''}>
            {item.met ? 'OK' : '-'} {item.label}
          </li>
        ))}
      </ul>

      <WizardButtonRow
        nextLabel="Restablecer contraseña"
        submittingLabel="Restableciendo..."
        onNext={handleSubmit}
        isSubmitting={isSubmitting}
      />
    </form>
  );
}

// Re-export the props to keep types centralized for the Wizard.
export type {
  EmailStepProps,
  MethodStepProps,
  CodeStepProps,
  PasswordStepProps,
};