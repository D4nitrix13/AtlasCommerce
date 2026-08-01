import { useState, type ReactElement } from "react";
import {
  LOCALE_FLAGS,
  LOCALE_LABELS,
  SUPPORTED_LOCALES,
  switchLocalePath,
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
        className="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition hover:bg-white/10 focus:outline-none [&::-webkit-details-marker]:hidden"
        style={{ color: "inherit" }}
      >
        <span aria-hidden="true">{LOCALE_FLAGS[locale]}</span>
        <span>{LOCALE_LABELS[locale]}</span>
        <svg
          width="10"
          height="10"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          aria-hidden="true"
        >
          <path d="M6 9l6 6 6-6" />
        </svg>
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
              <span className="ml-auto text-[10px] text-slate-500">
                {switchLocalePath("/admin", candidate)}
              </span>
            </button>
          </li>
        ))}
      </ul>
    </details>
  );
}