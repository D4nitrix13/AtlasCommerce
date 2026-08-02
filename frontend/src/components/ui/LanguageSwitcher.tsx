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
 * Selector de idioma. Al elegir otro locale, navega a la URL
 * equivalente con el nuevo prefijo (ej: `/es/admin/shipping` →
 * `/en/admin/shipping`). El middleware se encarga del resto.
 */
export default function LanguageSwitcher(): ReactElement {
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
        className="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition hover:bg-white/10 focus:outline-none [&::-webkit-details-marker]:hidden"
        style={{ color: "inherit" }}
      >
        <span aria-hidden="true">{LOCALE_FLAGS[locale]}</span>
        <span>{LOCALE_LABELS[locale]}</span>
        <ChevronDown size={14} strokeWidth={2.5} aria-hidden="true" />
      </summary>

      <ul
        role="menu"
        className="absolute right-0 z-20 mt-1 min-w-[8rem] overflow-hidden rounded-md border border-white/10 bg-slate-900 py-1 text-xs shadow-lg"
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
              className="flex w-full items-center gap-2 px-3 py-1.5 text-left transition hover:bg-white/10"
              style={{
                color: candidate === locale ? "#DC2626" : "#fff",
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