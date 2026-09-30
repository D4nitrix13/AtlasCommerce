import { useState, type ReactElement } from 'react';
import { Eye, EyeOff } from 'lucide-react';

type PasswordInputProps = {
  id: string;
  value: string;
  onChange: (value: string) => void;
  /**
   * ARIA label para el botón toggle (mostrar/ocultar contraseña).
   * Default: "Mostrar contraseña"/"Ocultar contraseña" — los callers
   * deberían pasarlo via i18n si quieren localización.
   */
  showLabel?: string;
  hideLabel?: string;
  /**
   * Auto-complete hint para el navegador. Default: 'new-password'.
   */
  autoComplete?: string;
  required?: boolean;
  minLength?: number;
  placeholder?: string;
  className?: string;
};

/**
 * Input de password con toggle show/hide.
 *
 * Extraído de `ResetPasswordForm.tsx` (Task 2.13 de sdd-apply PR 2)
 * para eliminar la duplicación entre LoginForm, ResetPasswordForm,
 * RegisterForm, etc. Todos comparten la misma estructura visual:
 * input + botón absoluto a la derecha con icono Eye/EyeOff.
 *
 * El caller controla el valor (`value` / `onChange`) — esto es un
 * componente controlado, no maneja estado interno de la contraseña.
 */
export function PasswordInput({
  id,
  value,
  onChange,
  showLabel = 'Mostrar contraseña',
  hideLabel = 'Ocultar contraseña',
  autoComplete = 'new-password',
  required = false,
  minLength,
  placeholder,
  className = '',
}: PasswordInputProps): ReactElement {
  const [showPassword, setShowPassword] = useState(false);

  return (
    <div className="relative">
      <input
        id={id}
        name={id}
        type={showPassword ? 'text' : 'password'}
        autoComplete={autoComplete}
        required={required}
        minLength={minLength}
        placeholder={placeholder}
        value={value}
        onChange={(event) => {
          onChange(event.target.value);
        }}
        className={`w-full px-4 py-2.5 pr-10 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[var(--brand-accent,#2D9D8E)] focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)] ${className}`}
      />
      <button
        type="button"
        onClick={() => {
          setShowPassword(!showPassword);
        }}
        aria-label={showPassword ? hideLabel : showLabel}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
      >
        {showPassword ? <EyeOff size={20} strokeWidth={2} /> : <Eye size={20} strokeWidth={2} />}
      </button>
    </div>
  );
}
