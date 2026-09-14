import { useEffect } from 'react';

/**
 * useUrlStateSync — sincroniza el estado (query + page) con la URL.
 *
 *  - Mount: lee `?q=` y `?page=` para deep links / refresh.
 *  - Cambio de estado: usa history.replaceState para no recargar.
 *
 * Custom hook para mantener SearchPage simple.
 */
export function useUrlStateSync(
  query: string,
  page: number,
  onMount: (query: string, page: number) => void,
): void {
  // Mount: lee URL → estado.
  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }
    const params = new URLSearchParams(window.location.search);
    const queryFromUrl = params.get('q') ?? '';
    const pageFromUrl = Number.parseInt(params.get('page') ?? '1', 10);
    onMount(queryFromUrl, pageFromUrl);
    // onMount es estable (useCallback en el caller).
  }, [onMount]);

  // Estado → URL.
  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }
    const params = new URLSearchParams();
    const trimmed = query.trim();
    if (trimmed !== '') {
      params.set('q', trimmed);
    }
    if (page > 1) {
      params.set('page', String(page));
    }
    const search = params.toString();
    const next = search === ''
      ? window.location.pathname
      : `${window.location.pathname}?${search}`;
    window.history.replaceState(null, '', next);
  }, [query, page]);
}