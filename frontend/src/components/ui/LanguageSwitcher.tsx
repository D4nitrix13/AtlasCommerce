import { useState, type ReactElement } from "react";
import { ChevronDown } from "lucide-react";
import {
  LOCALE_FLAGS,
  LOCALE_LABELS,
  SUPPORTED_LOCALES,
  useTranslations,
  type Locale,
} from "../../i18n";

/**
 * Tokens opcionales para que el dropdown no rompa el look del padre.
 * Defaults oscuros para mantener compatibilidad con AdminUserMenu
 * (panel admin, fondo slate). El LoginForm pasa los tokens claros.
 */
export type LanguageSwitcherTheme = {
  /** Fondo del dropdown abierto (y hover del summary/items). */
  surface: string;
  /** Color del texto del summary y de los items inactivos. */
  text: string;
  /** Color del item seleccionado (locale activo). */
  activeText: string;
  /** Borde sutil del dropdown. */
  border: string;
};

const DEFAULT_THEME: LanguageSwitcherTheme = {
  surface: "#0F172A",
  text: "#FFFFFF",
  activeText: "#DC2626",
  border: "rgba(255, 255, 255, 0.10)",
};

type LanguageSwitcherProps = {
  theme?: LanguageSwitcherTheme;
};

/**
 * Selector de idioma. Al elegir otro locale, navega a la URL
 * equivalente con el nuevo prefijo (ej: `/es/admin/shipping` →
 * `/en/admin/shipping`). El middleware se encarga del resto.
 */
export default function LanguageSwitcher({
  theme = DEFAULT_THEME,
}: LanguageSwitcherProps): ReactElement {
  const { locale, setLocale } = useTranslations();
  const [open, setOpen] = useState(false);

  const handleSelect = (next: Locale): void => {
    setOpen(false);
    setLocale(next);
  };

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
        style={{ color: theme.text }}
      >
        <span aria-hidden="true">{LOCALE_FLAGS[locale]}</span>
        <span>{LOCALE_LABELS[locale]}</span>
        <ChevronDown size={14} strokeWidth={2.5} aria-hidden="true" />
      </summary>

      <ul
        role="menu"
        className="absolute right-0 z-20 mt-1 min-w-[8rem] overflow-hidden rounded-md py-1 text-xs shadow-lg"
        style={{
          backgroundColor: theme.surface,
          border: `1px solid ${theme.border}`,
          color: theme.text,
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
              className="flex w-full items-center gap-2 px-3 py-1.5 text-left transition"
              style={{
                color: candidate === locale ? theme.activeText : theme.text,
                fontWeight: candidate === locale ? 600 : 400,
              }}
            >
              <span aria-hidden="true">{LOCALE_FLAGS[candidate]}</span>
              <span>{LOCALE_LABELS[candidate]}</span>
              {candidate === locale && (
                <span aria-hidden="true" className="ml-auto text-[10px]">✓</span>
              )}
            </button>
          </li>
        ))}
      </ul>
    </details>
  );
}