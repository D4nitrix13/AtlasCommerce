/**
 * Envelope genérico de respuesta del backend Laravel.
 *
 * Laravel Resources envuelven cada respuesta en `{ data: ... }`.
 * Para colecciones paginadas, agrega `meta` y `links`.
 */

export type ApiResponse<T> = {
  data: T;
};

export type PaginatedResponse<T> = {
  data: T[];
  links?: {
    first?: string;
    last?: string;
    prev?: string | null;
    next?: string | null;
  };
  meta?: {
    current_page?: number;
    from?: number | null;
    last_page?: number;
    path?: string;
    per_page?: number;
    to?: number | null;
    total?: number;
  };
};

export type ApiValidationError = {
  status: 422;
  message: string;
  errors: Record<string, string[]>;
};

// ApiError viene de lib/api (runtime), lo importan los componentes desde ahí.