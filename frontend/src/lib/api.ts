const API_URL = (import.meta.env.PUBLIC_API_URL ?? "").replace(/\/$/, "");

type ApiErrorResponse = {
  message?: string;
  errors?: Record<string, string[]> | null;
};

export type ApiError = {
  status: number;
  message: string;
  errors: Record<string, string[]> | null;
};

type RequestOptions = RequestInit & {
  token?: string | null;
};

function resolveApiUrl(endpoint: string): string {
  if (!API_URL) {
    throw new Error("PUBLIC_API_URL is not configured.");
  }

  const normalizedEndpoint = endpoint.startsWith("/")
    ? endpoint
    : `/${endpoint}`;

  return `${API_URL}${normalizedEndpoint}`;
}

async function parseJsonResponse<T>(response: Response): Promise<T> {
  if (response.status === 204) {
    return null as T;
  }

  const contentType = response.headers.get("content-type");

  if (!contentType?.includes("application/json")) {
    return null as T;
  }

  return response.json() as Promise<T>;
}

async function parseApiError(response: Response): Promise<ApiError> {
  const contentType = response.headers.get("content-type");

  let error: ApiErrorResponse | null = null;

  if (contentType?.includes("application/json")) {
    error = await response.json().catch(() => null);
  }

  return {
    status: response.status,
    message: error?.message ?? "API request failed",
    errors: error?.errors ?? null,
  };
}

export async function apiFetch<T>(
  endpoint: string,
  options: RequestOptions = {},
): Promise<T> {
  const { token, headers, body, ...rest } = options;

  const isFormData = body instanceof FormData;

  const requestHeaders: HeadersInit = {
    Accept: "application/json",
    ...(isFormData ? {} : { "Content-Type": "application/json" }),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...headers,
  };

  const response = await fetch(resolveApiUrl(endpoint), {
    ...rest,
    body,
    headers: requestHeaders,
  });

  if (!response.ok) {
    throw await parseApiError(response);
  }

  return parseJsonResponse<T>(response);
}
