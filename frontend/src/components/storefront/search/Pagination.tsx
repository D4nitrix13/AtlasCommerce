import type { ReactElement } from 'react';
interface Props {
  currentPage: number;
  lastPage: number;
  pageLabel: string;
  previousLabel: string;
  nextLabel: string;
  ofLabel: string;
  onPrevious: () => void;
  onNext: () => void;
}

/**
 * Pagination — sólo se renderiza si lastPage > 1.
 *
 * Mantenemos la navegación como botones (no links) porque el estado
 * vive client-side y el click handler hace `setPage`. Si después
 * queremos SSR-friendly, cambiamos a links que apunten a `?q=...&page=N`.
 */
export default function Pagination({
  currentPage,
  lastPage,
  pageLabel,
  previousLabel,
  nextLabel,
  ofLabel,
  onPrevious,
  onNext,
}: Props): ReactElement {
  return (
    <nav
      aria-label={pageLabel}
      className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6"
    >
      <button
        type="button"
        onClick={onPrevious}
        disabled={currentPage <= 1}
        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
      >
        {previousLabel}
      </button>
      <p className="text-sm text-slate-500">
        {pageLabel} {currentPage} {ofLabel} {lastPage}
      </p>
      <button
        type="button"
        onClick={onNext}
        disabled={currentPage >= lastPage}
        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
      >
        {nextLabel}
      </button>
    </nav>
  );
}
