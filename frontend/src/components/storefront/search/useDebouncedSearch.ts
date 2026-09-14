import { useEffect, useState } from 'react';
import { apiFetch } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import type { SearchResponse, SearchStatus } from './SearchPage.types';

const SEARCH_DEBOUNCE_MS = 300;

/**
 * useDebouncedSearch — encapsula el ciclo de fetch debounced.
 *
 * Devuelve:
 *  - status: 'idle' | 'loading' | 'success' | 'error'
 *  - response: la última respuesta exitosa (o null)
 *  - errorMessage: mensaje legible para el usuario (o '')
 *
 * El efecto se re-ejecuta cuando cambia el `query` (debounced) o la
 * `page` (immediate). Si el query queda vacío, se limpia el estado
 * sin pegarle al backend.
 *
 * Custom hook para mantener SearchPage simple y poder testearlo
 * aisladamente.
 */
export function useDebouncedSearch(query: string, page: number): {
  status: SearchStatus;
  response: SearchResponse | null;
  errorMessage: string;
} {
  const [status, setStatus] = useState<SearchStatus>('idle');
  const [response, setResponse] = useState<SearchResponse | null>(null);
  const [errorMessage, setErrorMessage] = useState<string>('');

  useEffect(() => {
    const trimmed = query.trim();
    if (trimmed === '') {
      setResponse(null);
      setStatus('idle');
      setErrorMessage('');
      return;
    }

    const controller = new AbortController();
    const timeout = window.setTimeout(() => {
      void executeFetch(
        trimmed,
        page,
        controller.signal,
        {
          setStatus,
          setResponse,
          setErrorMessage,
        },
      );
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      controller.abort();
      window.clearTimeout(timeout);
    };
  }, [query, page]);

  return { status, response, errorMessage };
}

interface FetchSetters {
  setStatus: (status: SearchStatus) => void;
  setResponse: (response: SearchResponse | null) => void;
  setErrorMessage: (message: string) => void;
}

/**
 * Ejecuta el fetch y actualiza los setters según el resultado.
 * Extraído para mantener `useDebouncedSearch` simple.
 */
async function executeFetch(
  q: string,
  nextPage: number,
  signal: AbortSignal,
  setters: FetchSetters,
): Promise<void> {
  setters.setStatus('loading');
  setters.setErrorMessage('');

  try {
    const params = new URLSearchParams();
    params.set('q', q);
    params.set('page', String(nextPage));

    const data = await apiFetch<SearchResponse>(
      `${API_ENDPOINTS.storefront.productSearch}?${params.toString()}`,
      { method: 'GET', signal },
    );

    setters.setResponse(data);
    setters.setStatus('success');
  } catch (caughtError) {
    if (caughtError instanceof DOMException && caughtError.name === 'AbortError') {
      return;
    }
    const message =
      caughtError instanceof Error && caughtError.message !== '' ? caughtError.message : '';
    setters.setErrorMessage(message);
    setters.setStatus('error');
  }
}