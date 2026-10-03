import type { ReactElement } from 'react';
import type { ChangeEvent, FormEvent } from 'react';

interface Props {
  query: string;
  placeholder: string;
  buttonLabel: string;
  isDisabled: boolean;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/**
 * SearchBar — input + botón de submit.
 *
 * Componente presentacional puro. El manejo de estado vive en
 * `SearchPage`. Decoupled así para poder testearse de forma aislada.
 */
export default function SearchBar({
  query,
  placeholder,
  buttonLabel,
  isDisabled,
  onChange,
  onSubmit,
}: Props): ReactElement {
  return (
    <form
      role="search"
      onSubmit={onSubmit}
      className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center"
    >
      <label htmlFor="search-input" className="sr-only">
        {placeholder}
      </label>
      <input
        id="search-input"
        type="search"
        inputMode="text"
        autoComplete="off"
        spellCheck={false}
        placeholder={placeholder}
        value={query}
        onChange={onChange}
        aria-label={placeholder}
        className="flex-1 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
      />
      <button
        type="submit"
        disabled={isDisabled}
        className="rounded-lg bg-[#2D9D8E] px-6 py-2.5 font-semibold text-white transition hover:bg-[#258777] disabled:opacity-50"
      >
        {buttonLabel}
      </button>
    </form>
  );
}
