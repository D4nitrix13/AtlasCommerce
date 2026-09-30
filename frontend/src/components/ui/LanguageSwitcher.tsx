import { useState, type ReactElement } from 'react';
import { ChevronDown } from 'lucide-react';
import {
  LOCALE_FLAGS,
  LOCALE_LABELS,
  SUPPORTED_LOCALES,
  useTranslations,
  type Locale,
} from '../../i18n';

/**
 * Tokens opcionales para que el dropdown no rompa el look del padre.
 * - `dark` (default) → AdminUserMenu en panel admin (fondo slate).
 * - `light` → pantallas de auth (fondo blanco, brand green activo).
 */
export type LanguageSwitcherTheme = {
  /** Fondo del dropdown abierto (y hover del summary/items). */
  surface: string;
  /** Color del texto del summary y de los items inactivos. */
  text: string;
  /** Color del item seleccionado (locale activo). */
  activeText: string;
  /** Fondo del item seleccionado (variant `inline`). Default: `--brand-accent-bg`. */
  activeBg: string;
  /** Borde sutil del dropdown. */
  border: string;
  /** Color del summary en estado cerrado (header del form). */
  summaryColor?: string;
  /** Color del summary en hover. */
  summaryHoverColor?: string;
};

const DARK_THEME: LanguageSwitcherTheme = {
  surface: '#0F172A',
  text: '#FFFFFF',
  activeText: '#DC2626',
  activeBg: 'var(--brand-accent-bg, #E6F7F4)',
  border: 'rgba(255, 255, 255, 0.10)',
};

const LIGHT_THEME: LanguageSwitcherTheme = {
  surface: '#FFFFFF',
  text: '#0F172A',
  activeText: 'var(--brand-accent, #2D9D8E)',
  activeBg: 'var(--brand-accent-bg, #E6F7F4)',
  border: '#E5E7EB',
  summaryColor: '#475569',
  summaryHoverColor: '#0F172A',
};

type LanguageSwitcherProps = {
  theme?: 'dark' | 'light' | LanguageSwitcherTheme;
  /** Layout: "dropdown" (default) | "inline" (botones planos ES/EN). */
  variant?: 'dropdown' | 'inline';
};

const THEME_MAP = {
  dark: DARK_THEME,
  light: LIGHT_THEME,
} as const;

/**
 * Selector de idioma. Al elegir otro locale, navega a la URL
 * equivalente con el nuevo prefijo (ej: `/es/admin/shipping` →
 * `/en/admin/shipping`). El middleware se encarga del resto.
 */
export default function LanguageSwitcher({
  theme = 'dark',
  variant = 'dropdown',
}: LanguageSwitcherProps): ReactElement {
  const { locale, setLocale } = useTranslations();
  const [open, setOpen] = useState(false);

  const resolvedTheme: LanguageSwitcherTheme = typeof theme === 'string' ? THEME_MAP[theme] : theme;

  const handleSelect = (next: Locale): void => {
    setOpen(false);
    setLocale(next);
  };

  if (variant === 'inline') {
    return (
      <div className="flex items-center gap-2 text-xs font-medium">
        {SUPPORTED_LOCALES.map((candidate) => (
          <button
            key={candidate}
            type="button"
            onClick={() => {
              handleSelect(candidate);
            }}
            aria-pressed={candidate === locale}
            className="rounded px-2 py-1 transition focus:outline-none focus:ring-2 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
            style={{
              color: candidate === locale ? resolvedTheme.activeText : resolvedTheme.text,
              backgroundColor: candidate === locale ? resolvedTheme.activeBg : 'transparent',
              fontWeight: candidate === locale ? 600 : 400,
            }}
          >
            {LOCALE_FLAGS[candidate]} {LOCALE_LABELS[candidate]}
          </button>
        ))}
      </div>
    );
  }

  return (
    <details
      className="relative"
      open={open}
      onToggle={(event) => {
        setOpen(event.currentTarget.open);
      }}
    >
      <summary
        className="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition focus:outline-none [&::-webkit-details-marker]:hidden"
        style={{
          color: open
            ? (resolvedTheme.summaryHoverColor ?? resolvedTheme.text)
            : (resolvedTheme.summaryColor ?? resolvedTheme.text),
        }}
      >
        <span aria-hidden="true">{LOCALE_FLAGS[locale]}</span>
        <span>{LOCALE_LABELS[locale]}</span>
        <ChevronDown size={14} strokeWidth={2.5} aria-hidden="true" />
      </summary>

      <ul
        role="menu"
        className="absolute right-0 z-20 mt-1 min-w-[8rem] overflow-hidden rounded-md py-1 text-xs shadow-lg"
        style={{
          backgroundColor: resolvedTheme.surface,
          border: `1px solid ${resolvedTheme.border}`,
          color: resolvedTheme.text,
        }}
      >
        {SUPPORTED_LOCALES.map((candidate) => (
          <li key={candidate}>
            <button
              type="button"
              role="menuitemradio"
              aria-checked={candidate === locale}
              onClick={() => {
                handleSelect(candidate);
              }}
              className="flex w-full items-center gap-2 px-3 py-1.5 text-left transition hover:bg-slate-50"
              style={{
                color: candidate === locale ? resolvedTheme.activeText : resolvedTheme.text,
                fontWeight: candidate === locale ? 600 : 400,
              }}
            >
              <span aria-hidden="true">{LOCALE_FLAGS[candidate]}</span>
              <span>{LOCALE_LABELS[candidate]}</span>
              {candidate === locale && (
                <span aria-hidden="true" className="ml-auto text-[10px]">
                  ✓
                </span>
              )}
            </button>
          </li>
        ))}
      </ul>
    </details>
  );
}
