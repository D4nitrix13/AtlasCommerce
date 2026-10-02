import type { ReactElement } from 'react';

interface PasswordStrengthMeterProps {
  /**
   * Score de 0 a 4 (4 = excelente). Cada entero = 1 barra rellena.
   */
  score: number;
  /**
   * Texto opcional que se muestra debajo de las barras cuando
   * `score > 0`. Si no se pasa o `score === 0`, no se renderiza el
   * texto (el caller normalmente hace su propio manejo de "no mostrar
   * strength si no escribiste nada").
   */
  label?: string;
  /**
   * Color de las barras rellenas. Default: `var(--brand-accent, #2D9D8E)`.
   * Acepta cualquier CSS color válido (string suelto o `var(--x)`).
   */
  fillColor?: string;
  /**
   * Color de las barras vacías. Default: `#E5E5E5`.
   */
  emptyColor?: string;
  /**
   * Etiqueta prefijada al `label` (ej. "Fuerza:"). Default: "" (sin prefix).
   * El caller debería pasar esto via i18n.
   */
  labelPrefix?: string;
}

/**
 * Indicador visual de fuerza de contraseña (4 barras).
 *
 * Extraído de `ResetPasswordForm.tsx` (Task 2.12 de sdd-apply PR 2).
 * El cálculo del `score` (0-4) lo hace el caller (ver `scorePassword`
 * en `ResetPasswordForm` o el helper homónimo en `RegisterForm`); este
 * componente solo renderiza las barras y, opcionalmente, el label.
 *
 * El score 0 (sin input) oculta el label para evitar mostrar "Débil"
 * antes de que el usuario haya escrito algo.
 */
export function PasswordStrengthMeter({
  score,
  label,
  fillColor = 'var(--brand-accent, #2D9D8E)',
  emptyColor = '#E5E5E5',
  labelPrefix = '',
}: PasswordStrengthMeterProps): ReactElement {
  const filledCount = Math.max(0, Math.min(4, score));
  const showLabel = score > 0 && label !== undefined && label !== '';

  return (
    <div>
      <div className="mt-2 flex gap-1.5" aria-hidden="true">
        {[1, 2, 3, 4].map((bar) => (
          <div
            key={bar}
            data-strength-bar=""
            data-filled={bar <= filledCount ? 'true' : 'false'}
            className="h-1 flex-1 rounded-full transition-colors"
            style={{
              backgroundColor: bar <= filledCount ? fillColor : emptyColor,
            }}
          />
        ))}
      </div>
      {showLabel && (
        <p className="mt-1.5 text-xs text-slate-400">
          {labelPrefix !== '' ? `${labelPrefix} ` : ''}
          <span className="font-semibold text-[var(--brand-accent,#2D9D8E)]">{label}</span>
        </p>
      )}
    </div>
  );
}
