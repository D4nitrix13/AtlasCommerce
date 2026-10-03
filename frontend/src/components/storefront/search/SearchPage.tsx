/**
 * SearchPage — UI de búsqueda pública de productos (RF-CAT-028 UI).
 *
 * Esta es la primera integración storefront real del frontend. Toda la
 * lógica de búsqueda (ranking, tenant isolation, paginación) vive
 * en `GET /api/storefront/products/search` del backend; este componente
 * sólo se encarga de:
 *  - Mantener el estado (query, page).
 *  - Renderizar los estados UI: loading / error / empty / success.
 *
 * El fetch con debounce, la sincronización con URL y los handlers están
 * extraídos para mantener este componente simple.
 */
import type { ReactElement, ChangeEvent, FormEvent } from 'react';
import { useCallback, useState } from 'react';
import { useTranslations } from '../../../i18n';
import type { TranslationKey } from '../../../i18n/types';
import { useDebouncedSearch } from './useDebouncedSearch';
import { useUrlStateSync } from './useUrlStateSync';
import SearchBar from './SearchBar';
import Pagination from './Pagination';
import SearchResultsList from './SearchResultsList';
import SearchEmptyState from './SearchEmptyState';
import SearchErrorState from './SearchErrorState';
import SearchLoadingState from './SearchLoadingState';
import type { SearchResponse, SearchStatus } from './SearchPage.types';

interface Props {
  /**
   * Locale actual (segmento URL `/es/...` o `/en/...`).
   * Se pasa desde Astro para que el primer render del React island
   * use el locale correcto (SSR-safe).
   */
  locale: string;
}

interface Handlers {
  onInputChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onPrevious: () => void;
  onNext: () => void;
}

export default function SearchPage({ locale: _locale }: Props): ReactElement {
  const { t } = useTranslations();
  const [query, setQuery] = useState<string>('');
  const [page, setPage] = useState<number>(1);

  const handleMount = useCallback((queryFromUrl: string, pageFromUrl: number): void => {
    if (queryFromUrl !== '') {
      setQuery(queryFromUrl);
    }
    if (pageFromUrl > 1) {
      setPage(pageFromUrl);
    }
  }, []);

  useUrlStateSync(query, page, handleMount);
  const { status, response, errorMessage } = useDebouncedSearch(query, page);
  const handlers = useSearchHandlers(setQuery, setPage);

  return (
    <SearchBody
      query={query}
      status={status}
      response={response}
      errorMessage={errorMessage}
      handlers={handlers}
      t={t}
    />
  );
}

/**
 * Handlers de la UI. Extraído a un custom hook para mantener `SearchPage`
 * por debajo del límite de 40 líneas del lint config.
 */
function useSearchHandlers(
  setQuery: (value: string) => void,
  setPage: (value: number | ((current: number) => number)) => void
): Handlers {
  return {
    onInputChange: (event: ChangeEvent<HTMLInputElement>): void => {
      setQuery(event.target.value);
      setPage(1);
    },
    onSubmit: (event: FormEvent<HTMLFormElement>): void => {
      event.preventDefault();
      setPage(1);
    },
    onPrevious: (): void => {
      setPage((current) => Math.max(1, current - 1));
    },
    onNext: (): void => {
      setPage((current) => current + 1);
    },
  };
}

interface BodyProps {
  query: string;
  status: SearchStatus;
  response: SearchResponse | null;
  errorMessage: string;
  handlers: Handlers;
  t: (key: TranslationKey, vars?: Record<string, string | number>) => string;
}

/**
 * Cuerpo visual — separado para mantener `SearchPage` simple.
 */
function SearchBody({
  query,
  status,
  response,
  errorMessage,
  handlers,
  t,
}: BodyProps): ReactElement {
  const trimmedQuery = query.trim();
  const showResults = status === 'success' && response !== null;
  const hasResults = showResults && response !== null && response.meta.total > 0;

  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
      <header className="mb-6">
        <h1 className="text-3xl font-bold text-slate-900">{t('storefront.search.title')}</h1>
        <p className="mt-2 text-slate-500">{t('storefront.search.subtitle')}</p>
      </header>

      <SearchBar
        query={query}
        placeholder={t('storefront.search.placeholder')}
        buttonLabel={t('storefront.search.button')}
        isDisabled={trimmedQuery === ''}
        onChange={handlers.onInputChange}
        onSubmit={handlers.onSubmit}
      />

      <SearchResultsRegion
        trimmedQuery={trimmedQuery}
        status={status}
        response={response}
        errorMessage={errorMessage}
        showResults={showResults}
        hasResults={hasResults}
        t={t}
        handlers={handlers}
      />
    </section>
  );
}

interface ResultsRegionProps {
  trimmedQuery: string;
  status: SearchStatus;
  response: SearchResponse | null;
  errorMessage: string;
  showResults: boolean;
  hasResults: boolean;
  t: (key: TranslationKey, vars?: Record<string, string | number>) => string;
  handlers: Handlers;
}

/**
 * Sección de resultados — agrupa loading / error / empty / success.
 * Extraída para reducir la complejidad de `SearchBody`.
 */
function SearchResultsRegion({
  trimmedQuery,
  status,
  response,
  errorMessage,
  showResults,
  hasResults,
  t,
  handlers,
}: ResultsRegionProps): ReactElement {
  return (
    <>
      <SearchStatusMessages
        trimmedQuery={trimmedQuery}
        status={status}
        response={response}
        errorMessage={errorMessage}
        showResults={showResults}
        hasResults={hasResults}
        t={t}
      />

      {hasResults && response !== null && (
        <SearchSuccessSection response={response} t={t} handlers={handlers} />
      )}
    </>
  );
}

/**
 * Mensajes de status (loading, error, empty, count) — extraídos para
 * mantener `SearchResultsRegion` simple.
 */
function SearchStatusMessages({
  trimmedQuery,
  status,
  response,
  errorMessage,
  showResults,
  hasResults,
  t,
}: Omit<ResultsRegionProps, 'handlers'>): ReactElement {
  return (
    <>
      {showResults && hasResults && response !== null && (
        <p aria-live="polite" className="mb-4 text-sm text-slate-500">
          {t('storefront.search.resultsCount', { count: response.meta.total })}
        </p>
      )}

      {status === 'loading' && <SearchLoadingState message={t('storefront.search.loading')} />}

      {status === 'error' && (
        <SearchErrorState
          message={errorMessage !== '' ? errorMessage : t('storefront.search.error')}
        />
      )}

      {showResults && !hasResults && (
        <SearchEmptyState
          title={
            trimmedQuery === ''
              ? t('storefront.search.empty')
              : t('storefront.search.noResultsFor', { query: trimmedQuery })
          }
          hint={t('storefront.search.emptyHint')}
        />
      )}
    </>
  );
}

interface SuccessSectionProps {
  response: SearchResponse;
  t: (key: TranslationKey, vars?: Record<string, string | number>) => string;
  handlers: Handlers;
}

/**
 * Sección de éxito — lista de resultados + paginación.
 * Extraído para mantener `SearchResultsRegion` simple.
 */
function SearchSuccessSection({ response, t, handlers }: SuccessSectionProps): ReactElement {
  return (
    <>
      <SearchResultsList products={response.data} ariaLabel={t('storefront.search.title')} />

      {response.meta.last_page > 1 && (
        <Pagination
          currentPage={response.meta.current_page}
          lastPage={response.meta.last_page}
          pageLabel={t('storefront.search.page')}
          previousLabel={t('storefront.search.previous')}
          nextLabel={t('storefront.search.next')}
          ofLabel={t('storefront.search.of')}
          onPrevious={handlers.onPrevious}
          onNext={handlers.onNext}
        />
      )}
    </>
  );
}
