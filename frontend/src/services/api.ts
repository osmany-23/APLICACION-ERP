export const API_BASE_URL = (
  import.meta.env.VITE_API_BASE_URL || '/api'
).replace(/\/+$/, '');

type ApiErrorPayload = {
  message?: string;
  errors?: Record<string, string[]>;
  retry_after?: number;
};

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;
  retryAfter?: number;

  constructor(status: number, message: string, payload?: ApiErrorPayload) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = payload?.errors;
    this.retryAfter = payload?.retry_after;
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
  token?: string,
): Promise<T> {
  const headers = new Headers(options.headers);

  headers.set('Accept', 'application/json');

  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  if (options.body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  let response: Response;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      headers,
    });
  } catch {
    throw new ApiError(
      0,
      'No se pudo conectar con el servidor. Verifica que Laravel este encendido.',
    );
  }

  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json')
    ? ((await response.json().catch(() => null)) as ApiErrorPayload | null)
    : null;

  if (!response.ok) {
    throw new ApiError(
      response.status,
      payload?.message || 'La solicitud no pudo completarse.',
      payload || undefined,
    );
  }

  return payload as T;
}
