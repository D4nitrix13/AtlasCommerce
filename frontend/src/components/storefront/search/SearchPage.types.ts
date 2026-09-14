/**
 * Tipos compartidos por SearchPage y sus sub-componentes.
 *
 * Mantener los tipos en un archivo separado evita ciclos de import
 * cuando SearchBar / Pagination / SearchResultsList crecen.
 */

export interface StorefrontProduct {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price_cents: number;
  currency_id: number;
}

export interface SearchResponse {
  data: StorefrontProduct[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    query: string;
  };
}

export type SearchStatus = 'idle' | 'loading' | 'success' | 'error';