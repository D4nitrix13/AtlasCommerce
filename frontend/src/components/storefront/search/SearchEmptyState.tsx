import type { ReactElement } from 'react';
interface Props {
  title: string;
  hint: string;
}

/**
 * Estado vacío — search OK pero 0 resultados. Aria-live para que
 * screen readers anuncien el cambio de estado.
 */
export default function SearchEmptyState({ title, hint }: Props): ReactElement {
  return (
    <div
      aria-live="polite"
      className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-12 text-center"
    >
      <p className="text-base font-semibold text-slate-700">{title}</p>
      <p className="mt-2 text-sm text-slate-500">{hint}</p>
    </div>
  );
}
